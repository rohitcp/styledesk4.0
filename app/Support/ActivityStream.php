<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\BookingStatusChange;
use App\Models\ClientActivity;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What has been happening across the business, newest first.
 *
 * A reading of history rather than a new record of it. StyleDesk already
 * writes down what it does — `client_activities` for everything that happens
 * to a client, `audit_logs` for administrative changes, `booking_status_changes`
 * for what became of an appointment and why, `staff_shifts` for when a rota
 * was published — and this turns those four into one list.
 *
 * Deliberately not a fifth table. An activity feed with its own write path
 * means every controller in the application has to remember to write to it,
 * and the day somebody forgets is the day the feed quietly stops being true.
 * Reading what is already recorded cannot fall behind what actually happened.
 *
 * The cost is that a row here is only as good as the record it came from, so
 * this class does no interpreting of its own: the sentence shown is the
 * sentence that was stored, and what this adds is the one-word type, the icon,
 * and the link back to the thing it is about.
 *
 * Permission is applied per SOURCE, not per row, and applied in the query.
 * A service provider is shown their own appointments and nothing else; a
 * receptionist sees the desk's work and not the business's settings. Nobody
 * is shown a line about a record they could not open.
 */
class ActivityStream
{
    /**
     * The one-word types, their icon, and which filter they sit under.
     *
     * One place, because three things read it: the row, the filter chips and
     * the query that decides which sources to ask at all.
     *
     * @var array<string, array{icon: string, group: string}>
     */
    public const KINDS = [
        'booking' => ['icon' => 'calendar-days', 'group' => 'bookings'],
        'reschedule' => ['icon' => 'arrow-right-arrow-left', 'group' => 'bookings'],
        'cancel' => ['icon' => 'calendar-xmark', 'group' => 'bookings'],
        'checkin' => ['icon' => 'calendar-check', 'group' => 'bookings'],
        'checkout' => ['icon' => 'receipt', 'group' => 'bookings'],
        'noshow' => ['icon' => 'clock', 'group' => 'bookings'],

        'client' => ['icon' => 'user', 'group' => 'clients'],
        'note' => ['icon' => 'pen-to-square', 'group' => 'clients'],
        'file' => ['icon' => 'box', 'group' => 'clients'],

        'payment' => ['icon' => 'credit-card', 'group' => 'payments'],
        'deposit' => ['icon' => 'coins', 'group' => 'payments'],
        'refund' => ['icon' => 'hand-holding-dollar', 'group' => 'payments'],

        'staff' => ['icon' => 'users', 'group' => 'staff'],
        'schedule' => ['icon' => 'user-clock', 'group' => 'scheduling'],

        'service' => ['icon' => 'clipboard-list', 'group' => 'system'],
        'resource' => ['icon' => 'chair', 'group' => 'system'],
        'email' => ['icon' => 'envelope', 'group' => 'communication'],
        'sms' => ['icon' => 'comment-sms', 'group' => 'communication'],
        'review' => ['icon' => 'star', 'group' => 'clients'],
        'coupon' => ['icon' => 'percent', 'group' => 'system'],
        'giftcard' => ['icon' => 'gift', 'group' => 'system'],
        'login' => ['icon' => 'lock', 'group' => 'system'],
        'settings' => ['icon' => 'gear', 'group' => 'system'],
    ];

    /** The filter chips, in the order the panel shows them. */
    public const GROUPS = ['all', 'bookings', 'clients', 'payments', 'staff', 'scheduling', 'communication', 'system'];

    /**
     * The feed, newest first.
     *
     * @return array<string, mixed>
     */
    public static function feed(User $user, string $group = 'all', int $limit = 40, ?string $before = null): array
    {
        $cutoff = self::parse($before);
        $seenAt = $user->activity_seen_at;

        $items = self::dedupe(collect()
            /* Status changes FIRST, and that order is the point — see
               dedupe(). */
            ->concat(self::fromStatusChanges($user, $group, $limit, $cutoff))
            ->concat(self::fromClientActivity($user, $group, $limit, $cutoff))
            ->concat(self::fromAuditLog($user, $group, $limit, $cutoff))
            ->concat(self::fromSchedules($user, $group, $limit, $cutoff)))
            ->sortByDesc(fn (array $item) => $item['at'])
            ->values();

        $page = $items->take($limit);

        return [
            'items' => $page
                ->map(fn (array $item) => self::present($item, $seenAt))
                ->values()
                ->all(),
            /* Where the next page starts. Null once a page comes back short,
               which is the only honest way to know there is no more: the
               sources are counted separately and a total would be a guess. */
            'next' => $items->count() > $limit ? $page->last()['at']->toIso8601String() : null,
            'unread' => self::unread($user),
        ];
    }

    /**
     * How many have happened since this reader last looked.
     *
     * Capped, because the badge is a nudge and not a count: "99+" and "312"
     * prompt exactly the same action, and one of them makes the app look
     * broken.
     */
    public static function unread(User $user, int $cap = 99): int
    {
        $since = $user->activity_seen_at;

        if ($since === null) {
            /* Never looked. Everything from the last day counts — a badge of
               four hundred on somebody's first morning is noise, not news. */
            $since = now()->subDay();
        }

        $count = self::countSince($user, $since);

        return min($cap + 1, $count);
    }

    /**
     * One event, told once.
     *
     * Checking a client in writes to two places: the client's own timeline,
     * and the booking's status history. Both are correct and the feed showed
     * both — the same check-in twice, a minute apart on the screen, which
     * reads as a bug in the product rather than as two records of one fact.
     *
     * Matched on the booking, the kind and the minute, and the status change
     * wins because it is the better sentence: it names the booking and it
     * carries the reason somebody gave. Which is why the sources are
     * concatenated with that one first — first past the post keeps its place.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private static function dedupe(Collection $items): Collection
    {
        return $items->unique(function (array $item) {
            /* Anything without a booking is not a duplicate of anything: two
               notes on one client in the same minute are two notes. */
            if (blank($item['reference'] ?? null)) {
                return $item['id'];
            }

            return $item['kind'].'|'.$item['reference'].'|'.$item['at']->format('Y-m-d H:i');
        });
    }

    // ------------------------------------------------------------ the sources

    /**
     * Everything that happened to a client.
     *
     * The bulk of the feed, and the only source that already holds a written
     * sentence — so it is used as written. What is added here is the one-word
     * type and the link.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function fromClientActivity(User $user, string $group, int $limit, ?Carbon $cutoff): Collection
    {
        $allowed = collect(self::KINDS)
            ->filter(fn (array $kind) => in_array($kind['group'], ['bookings', 'clients', 'payments', 'communication'], true))
            ->keys();

        if (! self::wants($group, ['bookings', 'clients', 'payments', 'communication'])) {
            return collect();
        }

        if (! $user->hasPermission('clients.view', 'own') && ! $user->hasPermission('calendar.view', 'own')) {
            return collect();
        }

        return ClientActivity::query()
            ->with(['client:id,first_name,last_name', 'booking:id,reference,staff_id', 'user:id,first_name,last_name'])
            ->when($cutoff, fn ($query) => $query->where('created_at', '<', $cutoff))
            /* A private note is private. It is on the client's own timeline
               for the people who may read it and it is not business news. */
            ->where('is_private', false)
            ->when(self::ownOnly($user), fn ($query, $staffId) => $query
                ->whereHas('booking', fn ($booking) => $booking->where('staff_id', $staffId)))
            ->latest('created_at')
            ->limit($limit + 1)
            ->get()
            ->map(function (ClientActivity $row) {
                $kind = self::kindOfActivity((string) $row->type);

                return [
                    'id' => 'ca:'.$row->id,
                    'kind' => $kind,
                    'at' => $row->created_at,
                    'description' => $row->description,
                    'actor' => $row->user?->name,
                    'reference' => $row->booking?->reference,
                    'url' => $row->booking_id
                        ? route('bookings.show', $row->booking_id)
                        : ($row->client_id ? route('clients.show', $row->client_id) : null),
                    'link' => $row->booking_id ? 'booking' : 'client',
                ];
            })
            ->filter(fn (array $item) => in_array($item['kind'], $allowed->all(), true))
            ->filter(fn (array $item) => self::inGroup($item['kind'], $group));
    }

    /**
     * What became of an appointment, and why.
     *
     * Its own source rather than part of the client timeline because it
     * carries the reason somebody gave — "client requested cancellation" is
     * the half of a cancellation worth reading.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function fromStatusChanges(User $user, string $group, int $limit, ?Carbon $cutoff): Collection
    {
        if (! self::wants($group, ['bookings']) || ! $user->hasPermission('calendar.view', 'own')) {
            return collect();
        }

        return BookingStatusChange::query()
            ->with(['booking:id,reference,staff_id', 'changedBy:id,first_name,last_name'])
            ->when($cutoff, fn ($query) => $query->where('created_at', '<', $cutoff))
            ->when(self::ownOnly($user), fn ($query, $staffId) => $query
                ->whereHas('booking', fn ($booking) => $booking->where('staff_id', $staffId)))
            ->latest('created_at')
            ->limit($limit + 1)
            ->get()
            ->map(fn (BookingStatusChange $row) => [
                'id' => 'bs:'.$row->id,
                'kind' => match ($row->to_status) {
                    'cancelled', 'declined' => 'cancel',
                    'no-show' => 'noshow',
                    'completed' => 'checkout',
                    'arrived', 'checked-in' => 'checkin',
                    default => 'booking',
                },
                'at' => $row->created_at,
                'description' => self::statusSentence($row),
                'actor' => $row->actor(),
                'reference' => $row->booking?->reference,
                'url' => $row->booking_id ? route('bookings.show', $row->booking_id) : null,
                'link' => 'booking',
            ]);
    }

    /**
     * Administrative changes — who was added, whose role changed, who signed in.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function fromAuditLog(User $user, string $group, int $limit, ?Carbon $cutoff): Collection
    {
        if (! self::wants($group, ['staff', 'system'])) {
            return collect();
        }

        /* The business's own administration, so it needs the authority to see
           the business's own administration. A service provider is not shown
           who changed whose permissions. */
        if (! $user->hasPermission('staff.view', 'location')) {
            return collect();
        }

        return AuditLog::query()
            ->when($cutoff, fn ($query) => $query->where('created_at', '<', $cutoff))
            ->latest('created_at')
            ->limit($limit + 1)
            ->get()
            ->map(function (AuditLog $row) {
                $kind = match (true) {
                    str_starts_with((string) $row->action, 'staff.') => 'staff',
                    str_starts_with((string) $row->action, 'auth.') => 'login',
                    default => 'settings',
                };

                return [
                    'id' => 'al:'.$row->id,
                    'kind' => $kind,
                    'at' => $row->created_at,
                    'description' => self::auditSentence($row),
                    'actor' => $row->actor_name,
                    'reference' => null,
                    'url' => $kind === 'staff' && $row->subject_id
                        ? route('staff.show', $row->subject_id)
                        : null,
                    'link' => 'staff',
                ];
            })
            ->filter(fn (array $item) => self::inGroup($item['kind'], $group));
    }

    /**
     * Rotas that were published.
     *
     * One line per person per publication rather than one per shift: a
     * fortnight published for six people is six pieces of news, not eighty.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function fromSchedules(User $user, string $group, int $limit, ?Carbon $cutoff): Collection
    {
        if (! self::wants($group, ['scheduling']) || ! $user->hasPermission('staff.view', 'own')) {
            return collect();
        }

        return StaffShift::query()
            ->with('staff:id,first_name,last_name')
            ->whereNotNull('published_at')
            ->when($cutoff, fn ($query) => $query->where('published_at', '<', $cutoff))
            ->when(self::ownOnly($user), fn ($query, $staffId) => $query->where('staff_id', $staffId))
            ->orderByDesc('published_at')
            ->limit(($limit + 1) * 20)
            ->get(['id', 'staff_id', 'date', 'published_at'])
            /* Grouped to the minute: one Publish writes every shift in the
               range with the same stamp. */
            ->groupBy(fn (StaffShift $shift) => $shift->staff_id.'@'.$shift->published_at->format('Y-m-d H:i'))
            ->map(function (Collection $shifts) {
                $first = $shifts->first();

                return [
                    'id' => 'sh:'.$first->id,
                    'kind' => 'schedule',
                    'at' => $first->published_at,
                    'description' => __('activity.sentences.schedule', [
                        'name' => $first->staff?->displayName() ?? '—',
                        'count' => $shifts->count(),
                    ]),
                    'actor' => null,
                    'reference' => null,
                    'url' => $first->staff_id ? route('staff.schedule', $first->staff_id) : null,
                    'link' => 'schedule',
                ];
            })
            ->values()
            ->take($limit + 1);
    }

    // ------------------------------------------------------------- the wording

    /** The one-word type a client activity belongs to. */
    private static function kindOfActivity(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'booking.rescheduled') => 'reschedule',
            str_starts_with($type, 'booking.cancelled'), str_starts_with($type, 'booking.declined') => 'cancel',
            str_starts_with($type, 'booking.checked_in') => 'checkin',
            str_starts_with($type, 'booking.completed'), str_starts_with($type, 'booking.checked_out') => 'checkout',
            str_starts_with($type, 'booking.no_show') => 'noshow',
            str_starts_with($type, 'booking.') => 'booking',
            str_starts_with($type, 'payment.refund') => 'refund',
            str_starts_with($type, 'payment.deposit') => 'deposit',
            str_starts_with($type, 'payment.') => 'payment',
            str_starts_with($type, 'note.') => 'note',
            str_starts_with($type, 'file') => 'file',
            str_starts_with($type, 'email.') => 'email',
            str_starts_with($type, 'sms.') => 'sms',
            str_starts_with($type, 'review.') => 'review',
            default => 'client',
        };
    }

    private static function statusSentence(BookingStatusChange $row): string
    {
        $sentence = __('activity.sentences.status', [
            'reference' => $row->booking?->reference ?? '—',
            'status' => __('bookings.statuses.'.$row->to_status.'.label'),
        ]);

        /* The reason as it was written on the day — never joined back to the
           reason list, which a business may since have renamed. */
        return filled($row->reason_label)
            ? $sentence.' '.__('activity.sentences.reason', ['reason' => $row->reason_label])
            : $sentence;
    }

    private static function auditSentence(AuditLog $row): string
    {
        $key = 'activity.audit.'.$row->action;

        return trans()->has($key)
            ? __($key, ['name' => $row->subject_label ?? '—', 'actor' => $row->actor_name ?? '—'])
            : __('activity.audit.fallback', [
                'action' => str_replace(['.', '_'], [' ', ' '], (string) $row->action),
                'name' => $row->subject_label ?? '—',
            ]);
    }

    /**
     * One row, dressed for the panel.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function present(array $item, ?Carbon $seenAt): array
    {
        $at = $item['at'];
        $kind = self::KINDS[$item['kind']] ?? self::KINDS['settings'];

        return [
            'id' => $item['id'],
            'kind' => $item['kind'],
            'kind_label' => __('activity.kinds.'.$item['kind']),
            'icon' => $kind['icon'],
            'group' => $kind['group'],
            'description' => $item['description'],
            'actor' => $item['actor'],
            'reference' => $item['reference'],
            'url' => $item['url'],
            'link_label' => $item['url'] ? __('activity.links.'.$item['link']) : null,
            'time_label' => $at->translatedFormat('g:i A'),
            'day' => self::dayKey($at),
            'day_label' => self::dayLabel($at),
            /* New since this reader last opened the panel. Never "unread" per
               row in a table: the panel is a glance at what happened, not an
               inbox to be worked through. */
            'unread' => $seenAt === null || $at->greaterThan($seenAt),
        ];
    }

    private static function dayKey(Carbon $at): string
    {
        return match (true) {
            $at->isToday() => 'today',
            $at->isYesterday() => 'yesterday',
            default => 'earlier',
        };
    }

    private static function dayLabel(Carbon $at): string
    {
        return match (true) {
            $at->isToday() => __('activity.today'),
            $at->isYesterday() => __('activity.yesterday'),
            default => $at->translatedFormat('j M Y'),
        };
    }

    // ----------------------------------------------------------------- helpers

    /**
     * The staff id this reader is limited to, or null for everybody.
     *
     * A service provider sees their own appointments and their own rota. It
     * is applied as a query condition on every source rather than as a filter
     * afterwards, because a filter afterwards is one somebody can page past.
     */
    private static function ownOnly(User $user): ?int
    {
        if ($user->hasPermission('calendar.view_all_staff', 'own')
            || $user->hasPermission('clients.view', 'location')) {
            return null;
        }

        return Staff::query()->where('user_id', $user->id)->value('id') ?? 0;
    }

    /** Whether the chosen filter wants anything this source can offer. */
    private static function wants(string $group, array $offers): bool
    {
        return $group === 'all' || in_array($group, $offers, true);
    }

    private static function inGroup(string $kind, string $group): bool
    {
        return $group === 'all' || (self::KINDS[$kind]['group'] ?? null) === $group;
    }

    private static function countSince(User $user, Carbon $since): int
    {
        $feed = self::rawSince($user, $since);

        return $feed;
    }

    /** How many rows across every source this reader may see, since a moment. */
    private static function rawSince(User $user, Carbon $since): int
    {
        $count = 0;

        if ($user->hasPermission('clients.view', 'own') || $user->hasPermission('calendar.view', 'own')) {
            $count += ClientActivity::query()
                ->where('is_private', false)
                ->where('created_at', '>', $since)
                ->when(self::ownOnly($user), fn ($query, $staffId) => $query
                    ->whereHas('booking', fn ($booking) => $booking->where('staff_id', $staffId)))
                ->count();
        }

        if ($user->hasPermission('staff.view', 'location')) {
            $count += AuditLog::query()->where('created_at', '>', $since)->count();
        }

        return $count;
    }

    private static function parse(?string $before): ?Carbon
    {
        if (blank($before)) {
            return null;
        }

        try {
            return Carbon::parse($before);
        } catch (\Throwable) {
            return null;
        }
    }
}
