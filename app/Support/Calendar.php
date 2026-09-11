<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Location;
use App\Models\LocationClosure;
use App\Models\Staff;
use App\Models\StaffShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The diary, as the front desk reads it.
 *
 * The listing answers "what has been booked"; this answers the question a
 * receptionist actually asks over the counter — who is working, who is in the
 * building, what room they are in, and where the gaps are. Same appointments,
 * laid against the clock instead of down a page.
 *
 * Three views, one class. They differ in how far they reach and how much of a
 * booking there is room to say, and in nothing else: a card is built once, the
 * figures are counted once, and the filters mean the same thing in all three.
 * Three classes would be three chances for the day and the week to disagree
 * about what is on a Tuesday.
 *
 * Each view loads only what it draws. A day that fetched a month would be slow
 * on exactly the businesses that live in it, and the desk never looks at two
 * days at once.
 *
 * The availability rules are BookingAvailability's, deliberately: a slot the
 * calendar draws as free and the booking screen then refuses is worse than no
 * calendar at all. Where the two could differ they are both wrong together.
 */
class Calendar
{
    /** The grid's step, in minutes. The desk books to the quarter hour. */
    public const INTERVAL = 15;

    /** How many appointments a month cell names before it starts counting. */
    private const CHIPS_PER_DAY = 3;

    /** The steps the ruler can be drawn in. Anything else is the default. */
    public const INTERVALS = [15, 30];

    /** What to draw when a business keeps no hours worth the name. */
    private const FALLBACK_OPENS = 8 * 60;

    private const FALLBACK_CLOSES = 20 * 60;

    /**
     * The whole day, ready to render.
     *
     * @return array<string, mixed>
     */
    public static function day(
        ?Location $location,
        string $date,
        ?int $staffId = null,
        ?int $resourceId = null,
        ?int $serviceId = null,
        int $interval = self::INTERVAL,
    ): array {
        $day = Carbon::parse($date)->startOfDay();

        $clock = self::clockFor($location);

        $staff = self::staffFor($location, $staffId);
        $shifts = self::shiftsFor($staff->pluck('id')->all(), $date);
        $bookings = self::bookingsFor($location, $date, $staffId, $resourceId, $serviceId);

        [$opens, $closes] = self::bounds($location, $day, $shifts, $bookings);

        return [
            'view' => 'day',
            /* Whether this reader's clock runs to twelve. Sent rather than
               worked out in the browser: the app already decides it once, and
               a ruler that disagreed with the cards beside it would be the
               same appointment printed two ways. */
            'clock12' => TimeFormat::use12Hours(),
            'date' => $day->toDateString(),
            'label' => $day->translatedFormat('l j F Y'),
            'short_label' => $day->translatedFormat('j M Y'),
            'previous' => $day->copy()->subDay()->toDateString(),
            'next' => $day->copy()->addDay()->toDateString(),
            'today' => $clock->toDateString(),
            'is_today' => $day->toDateString() === $clock->toDateString(),
            'interval' => self::interval($interval),
            'opens' => $opens,
            'closes' => $closes,
            /* Where the red line goes, in minutes past midnight. Null on any
               day but today: a "now" marker on next Tuesday is pointing at
               nothing. */
            'now' => $day->toDateString() === $clock->toDateString()
                ? (int) ($clock->hour * 60 + $clock->minute)
                : null,
            'closed' => self::isClosed($location, $day),
            'staff' => $staff->map(fn (Staff $member) => self::staffColumn($member, $shifts, $opens, $closes))->values()->all(),
            'bookings' => $bookings->map(fn (Booking $booking) => self::card($booking))->values()->all(),
            'summary' => self::summary($bookings),
        ];
    }

    /**
     * A week, one column per day.
     *
     * Not one per staff member: seven days across twelve people is eighty-four
     * columns, and a calendar nobody can read is not a calendar. The week
     * answers a different question from the day — "how full are we" rather
     * than "who is doing what" — so the staff filter narrows it instead of
     * splitting it.
     *
     * @return array<string, mixed>
     */
    public static function week(
        ?Location $location,
        string $date,
        ?int $staffId = null,
        ?int $resourceId = null,
        ?int $serviceId = null,
    ): array {
        $clock = self::clockFor($location);
        /* Monday first. A salon's week runs Monday to Sunday whatever the
           month grid does, and a rota that started on Sunday would put the
           quietest day at the front. */
        $first = Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
        $last = $first->copy()->addDays(6);

        $bookings = self::bookingsFor(
            $location, $first->toDateString(), $staffId, $resourceId, $serviceId, $last->toDateString(),
        );

        $byDay = $bookings->groupBy(fn (Booking $booking) => $booking->date->toDateString());

        [$opens, $closes] = self::bounds($location, $first, collect(), $bookings);

        $days = collect(range(0, 6))->map(function (int $index) use ($first, $byDay, $clock, $location) {
            $on = $first->copy()->addDays($index);
            $onDay = $byDay->get($on->toDateString(), collect());

            return [
                'date' => $on->toDateString(),
                'weekday' => $on->translatedFormat('D'),
                'number' => $on->translatedFormat('j'),
                'is_today' => $on->toDateString() === $clock->toDateString(),
                'closed' => self::isClosed($location, $on),
                'bookings' => $onDay->map(fn (Booking $booking) => self::card($booking))->values()->all(),
            ];
        });

        return [
            'view' => 'week',
            /* Whether this reader's clock runs to twelve. Sent rather than
               worked out in the browser: the app already decides it once, and
               a ruler that disagreed with the cards beside it would be the
               same appointment printed two ways. */
            'clock12' => TimeFormat::use12Hours(),
            'date' => $first->toDateString(),
            'label' => self::spanLabel($first, $last),
            'short_label' => self::spanLabel($first, $last),
            'previous' => $first->copy()->subWeek()->toDateString(),
            'next' => $first->copy()->addWeek()->toDateString(),
            'today' => $clock->toDateString(),
            'is_today' => $clock->betweenIncluded($first, $last),
            'interval' => self::INTERVAL,
            'opens' => $opens,
            'closes' => $closes,
            /* Which column the line belongs in, as well as how far down it
               goes: a "now" line drawn across all seven days would say today
               is every day of the week. */
            'now' => $clock->betweenIncluded($first, $last)
                ? ['day' => (int) $first->diffInDays($clock->copy()->startOfDay()), 'minute' => (int) ($clock->hour * 60 + $clock->minute)]
                : null,
            'days' => $days->all(),
            'summary' => self::summary($bookings),
        ];
    }

    /**
     * A month, as a grid of days.
     *
     * No timeline at all. At this range the question is which days are busy
     * and which are empty, and thirty vertical rulers answer it worse than
     * thirty counts do — so each cell states what it holds and hands the
     * reader to the day view for the rest.
     *
     * @return array<string, mixed>
     */
    public static function month(
        ?Location $location,
        string $date,
        ?int $staffId = null,
        ?int $resourceId = null,
        ?int $serviceId = null,
    ): array {
        $clock = self::clockFor($location);
        $month = Carbon::parse($date)->startOfMonth();

        /* The grid covers whole weeks, so it opens on the Monday before the
           first and closes on the Sunday after the last. The days either side
           are drawn faded rather than left blank: an empty corner reads as a
           cell that failed to load. */
        $first = $month->copy()->startOfWeek(Carbon::MONDAY);
        $last = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $bookings = self::bookingsFor(
            $location, $first->toDateString(), $staffId, $resourceId, $serviceId, $last->toDateString(),
        );

        $byDay = $bookings->groupBy(fn (Booking $booking) => $booking->date->toDateString());

        $cells = collect(range(0, $first->diffInDays($last)))->map(function (int $index) use ($first, $byDay, $clock, $month) {
            $on = $first->copy()->addDays($index);
            $onDay = $byDay->get($on->toDateString(), collect());

            return [
                'date' => $on->toDateString(),
                'number' => $on->translatedFormat('j'),
                'in_month' => $on->month === $month->month,
                'is_today' => $on->toDateString() === $clock->toDateString(),
                'count' => $onDay->count(),
                /* A handful, and the count says how many more. A cell with
                   eleven chips in it is a cell nobody reads. */
                'bookings' => $onDay->take(self::CHIPS_PER_DAY)
                    ->map(fn (Booking $booking) => self::card($booking))
                    ->values()
                    ->all(),
                'more' => max(0, $onDay->count() - self::CHIPS_PER_DAY),
            ];
        });

        return [
            'view' => 'month',
            /* Whether this reader's clock runs to twelve. Sent rather than
               worked out in the browser: the app already decides it once, and
               a ruler that disagreed with the cards beside it would be the
               same appointment printed two ways. */
            'clock12' => TimeFormat::use12Hours(),
            'date' => $month->toDateString(),
            'label' => $month->translatedFormat('F Y'),
            'short_label' => $month->translatedFormat('M Y'),
            'previous' => $month->copy()->subMonthNoOverflow()->toDateString(),
            'next' => $month->copy()->addMonthNoOverflow()->toDateString(),
            'today' => $clock->toDateString(),
            'is_today' => $clock->month === $month->month && $clock->year === $month->year,
            'weekdays' => collect(range(0, 6))
                ->map(fn (int $day) => $first->copy()->addDays($day)->translatedFormat('D'))
                ->all(),
            'weeks' => $cells->chunk(7)->map(fn (Collection $week) => $week->values()->all())->values()->all(),
            'summary' => self::summary($bookings),
        ];
    }

    /** "7 – 13 September 2026", without saying September twice. */
    private static function spanLabel(Carbon $first, Carbon $last): string
    {
        if ($first->month === $last->month) {
            return $first->translatedFormat('j').' – '.$last->translatedFormat('j F Y');
        }

        return $first->translatedFormat('j M').' – '.$last->translatedFormat('j M Y');
    }

    /**
     * The columns.
     *
     * Only people who actually perform services: a bookkeeper cannot be
     * booked, and a column nobody can be put in is a column in the way.
     *
     * @return Collection<int, Staff>
     */
    private static function staffFor(?Location $location, ?int $staffId): Collection
    {
        return Staff::query()
            ->where('is_active', true)
            ->where('provides_services', true)
            ->when($staffId, fn (Builder $query, int $id) => $query->whereKey($id))
            /* A staff member with no location works everywhere — the same
               reading Service::isOfferedAt() gives an empty location list. */
            ->when($location, fn (Builder $query, Location $place) => $query
                ->where(fn (Builder $q) => $q
                    ->whereNull('location_id')
                    ->orWhere('location_id', $place->id)))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Who is rostered, keyed by staff id.
     *
     * A shift that has not been published still counts, for the reason
     * BookingAvailability gives: the rota is what the business intends, and
     * publishing is only whether the staff member has been told.
     *
     * @param  list<int>  $staffIds
     * @return Collection<int, Collection<int, StaffShift>>
     */
    private static function shiftsFor(array $staffIds, string $date): Collection
    {
        if ($staffIds === []) {
            return collect();
        }

        return StaffShift::query()
            ->whereIn('staff_id', $staffIds)
            ->onDate($date)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->groupBy('staff_id');
    }

    /**
     * The appointments on the day.
     *
     * Drafts are left out. A draft is a booking still being written — the
     * screen saves one as the receptionist types — and a block on the
     * calendar for a call that was abandoned is a slot nobody can ever have.
     *
     * @return Collection<int, Booking>
     */
    private static function bookingsFor(
        ?Location $location,
        string $date,
        ?int $staffId,
        ?int $resourceId,
        ?int $serviceId = null,
        ?string $until = null,
    ): Collection {
        return Booking::query()
            /* One day, or a span of them. The week and the month ask the same
               question of the same table; only the fence moves. */
            ->when($until === null,
                fn (Builder $query) => $query->whereDate('date', $date),
                fn (Builder $query) => $query->whereBetween('date', [$date, $until]))
            ->where('status', '!=', 'draft')
            ->when($location, fn (Builder $query, Location $place) => $query->where('location_id', $place->id))
            ->when($staffId, fn (Builder $query, int $id) => $query->where('staff_id', $id))
            ->when($resourceId, fn (Builder $query, int $id) => $query
                ->where(fn (Builder $q) => $q
                    ->where('resource_id', $id)
                    ->orWhereHas('services', fn (Builder $s) => $s->where('resource_id', $id))))
            /* Asked against the lines rather than the booking: a booking is
               its services, and "show me today's facials" means the
               appointments that contain one — not only the ones that are
               nothing else. */
            ->when($serviceId, fn (Builder $query, int $id) => $query
                ->whereHas('services', fn (Builder $s) => $s->where('service_id', $id)))
            ->with([
                'client:id,first_name,last_name,mobile,email',
                'services:id,booking_id,service_id,resource_id,name,minutes',
                'services.service:id,name,color',
                'services.resource:id,name',
                'resource:id,name',
                'staff:id,first_name,last_name,preferred_name,avatar_path',
            ])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * One staff member's column.
     *
     * The windows are what they are rostered for. Nobody rostered is not the
     * same as nobody available — a salon that keeps no rota would otherwise
     * show every column greyed out — so an empty rota means the location's
     * hours stand, exactly as the booking screen reads it.
     *
     * @param  Collection<int, Collection<int, StaffShift>>  $shifts
     * @return array<string, mixed>
     */
    private static function staffColumn(Staff $member, Collection $shifts, int $opens, int $closes): array
    {
        $own = $shifts->get($member->id, collect());

        $windows = $own
            ->map(fn (StaffShift $shift) => [
                'opens' => self::minutes($shift->starts_at),
                'closes' => self::minutes($shift->ends_at),
            ])
            ->filter(fn (array $window) => $window['opens'] !== null
                && $window['closes'] !== null
                && $window['closes'] > $window['opens'])
            ->values();

        $rostered = $windows->isNotEmpty();

        return [
            'id' => (int) $member->id,
            'name' => $member->displayName(),
            'initials' => self::initials($member->displayName()),
            'avatar' => $member->avatar_path ? asset('storage/'.$member->avatar_path) : null,
            'job_title' => $member->job_title,
            /* "9:00 AM – 5:00 PM", or the business's own hours where the rota
               says nothing. Stated either way: a column with no hours under
               the name reads as a page that failed to load. */
            'hours' => $rostered
                ? $windows->map(fn (array $window) => self::clock($window['opens']).' – '.self::clock($window['closes']))->implode(', ')
                : self::clock($opens).' – '.self::clock($closes),
            'rostered' => $rostered,
            'windows' => $rostered
                ? $windows->all()
                : [['opens' => $opens, 'closes' => $closes]],
            /* How long they are owed off, not when they take it. The rota
               stores a duration; where in the shift it falls is a decision
               nobody has recorded, so the calendar says how much rather than
               drawing a block at a time it would be guessing. */
            'break_minutes' => (int) $own->sum('break_minutes'),
        ];
    }

    /**
     * One appointment, as a block.
     *
     * @return array<string, mixed>
     */
    private static function card(Booking $booking): array
    {
        $totals = BookingTotals::for($booking);
        $start = self::minutes($booking->starts_at) ?? 0;
        $end = self::minutes($booking->ends_at) ?? ($start + (int) $booking->minutes);

        $resources = $booking->services
            ->map(fn ($line) => $line->resource?->name)
            ->filter()
            ->unique()
            ->values();

        if ($resources->isEmpty() && $booking->resource) {
            $resources = collect([$booking->resource->name]);
        }

        $due = $booking->amountDueMinor();

        return [
            'id' => (int) $booking->id,
            'reference' => $booking->reference,
            'client' => $booking->clientName(),
            'services' => $booking->services->pluck('name')->implode(' + '),
            'staff_id' => $booking->staff_id === null ? null : (int) $booking->staff_id,
            'staff' => $booking->staff?->displayName() ?? __('bookings.any_staff'),
            'start' => $start,
            'end' => max($end, $start + self::INTERVAL),
            'starts_label' => TimeFormat::time($booking->startsAt()),
            'ends_label' => TimeFormat::time($booking->endsAt()),
            'minutes' => (int) $booking->minutes,
            /* The service's own colour, so one glance across the day reads as
               "three massages and a facial" rather than as four statuses. The
               status is a badge on top of it — see the calendar rules. */
            'color' => self::colour($booking),
            'status' => $booking->status,
            'status_label' => $booking->statusLabel(),
            'status_class' => $booking->statusClass(),
            'payment_label' => self::paymentLabel($booking, $totals, $due),
            'payment_class' => $booking->paymentStatusClass(),
            'paid' => $due <= 0,
            /* Named rather than flagged: "1 credit reserved" is the sentence
               the desk repeats to the client, and a tick beside the word
               Membership is not. */
            'membership' => self::membershipLine($booking),
            'resource' => $resources->implode(', '),
            'has_note' => filled($booking->notes),
            'note' => $booking->notes,
            'phone' => $booking->client?->mobile,
            'email' => $booking->client?->email,
            'drawer_url' => route('bookings.drawer', $booking),
            'url' => route('bookings.show', $booking),
        ];
    }

    /**
     * What this booking is paying, in the fewest words that are still true.
     *
     * "Balance due · $45" rather than "Partial": a figure is what the desk
     * has to say out loud, and the word on its own sends them to open the
     * booking to find it.
     */
    private static function paymentLabel(Booking $booking, BookingTotals $totals, int $due): string
    {
        if ($due <= 0) {
            return $booking->paymentStatusLabel();
        }

        return __('calendar.card.balance', ['amount' => $totals->money($due)]);
    }

    /**
     * The membership line, where one paid for any of this.
     *
     * Reserved until the client walks in, which is the rule the redemption
     * engine keeps — see MembershipCredits::consume(). A calendar that called
     * a future appointment "used" would be telling the client they had
     * already had it.
     */
    private static function membershipLine(Booking $booking): ?string
    {
        if ((int) $booking->membership_credit_minor <= 0) {
            return null;
        }

        $held = MembershipCredits::on($booking);

        if ($held->isEmpty()) {
            return __('calendar.card.membership');
        }

        $credits = (int) $held->sum(fn ($redemption) => max(1, (int) $redemption->quantity));
        $consumed = $held->every(fn ($redemption) => $redemption->isConsumed());

        return trans_choice(
            $consumed ? 'calendar.card.credits_used' : 'calendar.card.credits_reserved',
            $credits,
            ['count' => $credits],
        );
    }

    /** The first service's colour, or the palette's first as a floor. */
    private static function colour(Booking $booking): string
    {
        $colour = $booking->services
            ->map(fn ($line) => $line->service?->color)
            ->filter()
            ->first();

        return (string) ($colour ?: config('colors.palette.0', '#3d348b'));
    }

    /**
     * The figures above the timeline.
     *
     * Counted from the day already loaded rather than asked for again: the
     * bookings are in memory, and a second set of queries is a second chance
     * for the two to disagree on one screen.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return array<string, mixed>
     */
    private static function summary(Collection $bookings): array
    {
        $live = $bookings->reject(fn (Booking $booking) => in_array($booking->status, ['cancelled', 'declined'], true));

        return [
            'total' => $bookings->count(),
            'arrived' => $bookings->where('status', 'arrived')->count(),
            'completed' => $bookings->where('status', 'completed')->count(),
            'cancelled' => $bookings->whereIn('status', ['cancelled', 'declined'])->count(),
            'no_show' => $bookings->where('status', 'no-show')->count(),
            /* What is still owed, and by how many. A cancelled appointment
               owes nothing, so it is not counted among them. */
            'owing' => $live->filter(fn (Booking $booking) => $booking->amountDueMinor() > 0)->count(),
            /* The day's takings if everybody turns up. Cancelled and no-show
               are left out: revenue nobody is going to see is not a forecast,
               it is a wrong number on a wall. */
            'revenue_minor' => (int) $live
                ->reject(fn (Booking $booking) => $booking->status === 'no-show')
                ->sum(fn (Booking $booking) => (int) $booking->total_minor + (int) $booking->tip_minor),
        ];
    }

    /**
     * The top and bottom of the timeline.
     *
     * The business's hours, widened to cover anything actually on the day: an
     * appointment that runs past closing is a thing that happened, and a
     * calendar that cropped it would hide the one booking worth looking at.
     *
     * @param  Collection<int, Collection<int, StaffShift>>  $shifts
     * @param  Collection<int, Booking>  $bookings
     * @return array{0: int, 1: int}
     */
    private static function bounds(?Location $location, Carbon $day, Collection $shifts, Collection $bookings): array
    {
        $opens = null;
        $closes = null;

        if ($location) {
            $hours = $location->hours()
                ->where('day_of_week', (int) $day->dayOfWeek)
                ->where('is_open', true)
                ->get();

            foreach ($hours as $hour) {
                $from = self::minutes($hour->opens_at);
                $to = self::minutes($hour->closes_at);

                if ($from === null || $to === null || $to <= $from) {
                    continue;
                }

                $opens = $opens === null ? $from : min($opens, $from);
                $closes = $closes === null ? $to : max($closes, $to);
            }
        }

        $opens ??= self::FALLBACK_OPENS;
        $closes ??= self::FALLBACK_CLOSES;

        foreach ($shifts->flatten() as $shift) {
            $from = self::minutes($shift->starts_at);
            $to = self::minutes($shift->ends_at);

            if ($from !== null) {
                $opens = min($opens, $from);
            }

            if ($to !== null) {
                $closes = max($closes, $to);
            }
        }

        foreach ($bookings as $booking) {
            $from = self::minutes($booking->starts_at);
            $to = self::minutes($booking->ends_at);

            if ($from !== null) {
                $opens = min($opens, $from);
            }

            if ($to !== null) {
                $closes = max($closes, $to);
            }
        }

        /* Rounded out to the hour, so the ruler reads 9, 10, 11 rather than
           9:20, 10:20 — and always wide enough to be worth drawing. */
        $opens = max(0, (int) (floor($opens / 60) * 60));
        $closes = min(24 * 60, (int) (ceil($closes / 60) * 60));

        return [$opens, max($closes, $opens + 60)];
    }

    /** A step the ruler can actually be drawn in. */
    private static function interval(int $asked): int
    {
        return in_array($asked, self::INTERVALS, true) ? $asked : self::INTERVAL;
    }

    /**
     * The salon's clock, not the server's and not the reader's.
     *
     * A desk in Austin looking at a calendar served from UTC needs the red
     * line where the salon's own morning is, and a receptionist checking the
     * branch they do not sit in needs that branch's time rather than their
     * laptop's.
     */
    private static function clockFor(?Location $location): Carbon
    {
        return Carbon::now($location?->timezone ?: config('app.timezone'));
    }

    /** Is the door shut altogether? */
    private static function isClosed(?Location $location, Carbon $day): bool
    {
        if ($location === null) {
            return false;
        }

        $allDay = LocationClosure::query()
            ->where('location_id', $location->id)
            ->covering($day)
            ->where('is_closed_all_day', true)
            ->exists();

        if ($allDay) {
            return true;
        }

        return ! $location->hours()
            ->where('day_of_week', (int) $day->dayOfWeek)
            ->where('is_open', true)
            ->exists();
    }

    /** "09:30" as 570. Null where the column is empty or unreadable. */
    private static function minutes(?string $time): ?int
    {
        if (blank($time)) {
            return null;
        }

        [$hours, $mins] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $mins;
    }

    /** 570 as the reader's own clock, twelve-hour or twenty-four. */
    private static function clock(int $minutes): string
    {
        return (string) TimeFormat::time(sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60));
    }

    /** "Sarah Johnson" as SJ, for a column too narrow for a photograph. */
    private static function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
