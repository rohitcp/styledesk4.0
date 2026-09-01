<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What each dashboard panel actually says.
 *
 * Every method here is scoped twice over: to the locations the reader may
 * see, and — for the provider panels — to their own work. That is not a
 * courtesy. A manager assigned to Downtown looking at Uptown's takings is the
 * kind of leak nobody notices until somebody mentions a number they should
 * not have known.
 *
 * Nothing here invents a figure it cannot source. Where StyleDesk does not
 * yet record something the dashboard would like — a member of staff clocking
 * in, a waiver, a refund — the panel says so rather than showing a nought
 * that reads as good news.
 */
class DashboardData
{
    /** @param  array<int, int>|null  $locations */
    public function __construct(
        private readonly ?array $locations = null,
        private readonly ?int $staffId = null,
    ) {}

    /** Bookings this reader may see. */
    private function bookings(): Builder
    {
        return Booking::query()
            ->when($this->locations !== null, fn (Builder $q) => $q->whereIn('location_id', $this->locations))
            /* Drafts are half-written and promised to nobody, so they are
               not appointments and never counted as any. */
            ->where('status', '!=', 'draft');
    }

    private function today(): Builder
    {
        return $this->bookings()->whereDate('date', Carbon::today());
    }

    /* --------------------------------------------------------- performance */

    /**
     * How the business is doing.
     *
     * Takings are read from payments rather than from booking totals: a
     * booking worth two hundred that nobody has paid for is not two hundred
     * pounds of revenue, and a dashboard that says it is will be believed
     * once and distrusted afterwards.
     *
     * @return array<string, mixed>
     */
    public function performance(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $takings = fn (Carbon $from, Carbon $to) => (int) BookingPayment::query()
            ->whereIn('status', ['paid'])
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($this->locations !== null, fn (Builder $q) => $q->whereHas(
                'booking', fn (Builder $b) => $b->whereIn('location_id', $this->locations)
            ))
            ->sum('amount_minor');

        $thisMonth = $takings($monthStart, $today);
        /* The same stretch of the previous month rather than the whole of
           it: on the third, a full month is not a comparison. */
        $lastMonth = $takings(
            $monthStart->copy()->subMonthNoOverflow(),
            $today->copy()->subMonthNoOverflow(),
        );

        $monthBookings = (int) $this->bookings()
            ->whereBetween('date', [$monthStart->toDateString(), $today->copy()->endOfMonth()->toDateString()])
            ->count();

        return [
            'today_minor' => $takings($today, $today),
            'month_minor' => $thisMonth,
            'previous_minor' => $lastMonth,
            /* Null rather than a hundred per cent when there is nothing to
               compare against: a first month is not infinite growth. */
            'change_percent' => $lastMonth > 0
                ? (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100)
                : null,
            'outstanding_minor' => $this->outstandingMinor(),
            'bookings_month' => $monthBookings,
            'average_minor' => $monthBookings > 0
                ? (int) round($this->bookings()
                    ->whereBetween('date', [$monthStart->toDateString(), $today->copy()->endOfMonth()->toDateString()])
                    ->sum('total_minor') / $monthBookings)
                : 0,
        ];
    }

    /** What has been worked and not yet paid for. */
    private function outstandingMinor(): int
    {
        return (int) $this->bookings()
            ->whereIn('status', ['confirmed', 'arrived', 'completed'])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->get(['total_minor', 'paid_minor'])
            ->sum(fn (Booking $booking) => max(0, (int) $booking->total_minor - (int) $booking->paid_minor));
    }

    /* ------------------------------------------------------------ bookings */

    /**
     * Today, by where each appointment has got to.
     *
     * Each count carries the tab it opens, so a number somebody wants to act
     * on is one press from the list behind it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bookingsToday(): array
    {
        $counts = $this->today()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            ['key' => 'total', 'count' => (int) $counts->sum(), 'tab' => 'today'],
            ['key' => 'pending_checkin', 'count' => (int) ($counts['confirmed'] ?? 0), 'tab' => 'check-in'],
            ['key' => 'checked_in', 'count' => (int) ($counts['arrived'] ?? 0), 'tab' => 'today'],
            ['key' => 'completed', 'count' => (int) ($counts['completed'] ?? 0), 'tab' => 'completed'],
            ['key' => 'cancelled', 'count' => (int) ($counts['cancelled'] ?? 0), 'tab' => 'cancelled'],
            ['key' => 'no_show', 'count' => (int) ($counts['no-show'] ?? 0), 'tab' => 'no-shows'],
        ];
    }

    /**
     * Today's confirmed bookings, earliest first — the check-in queue.
     *
     * @return Collection<int, Booking>
     */
    public function checkInQueue(int $limit = 8): Collection
    {
        return $this->today()
            ->where('status', 'confirmed')
            ->when($this->staffId !== null, fn (Builder $q) => $q->where('staff_id', $this->staffId))
            ->with(['client', 'staff', 'services', 'resource'])
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Due within the hour and nobody has arrived yet.
     *
     * @return Collection<int, Booking>
     */
    public function arrivingSoon(): Collection
    {
        $now = Carbon::now();
        $until = $now->copy()->addMinutes((int) config('dashboard.arriving_within_minutes'));

        return $this->checkInQueue(50)
            ->filter(function (Booking $booking) use ($now, $until) {
                $starts = $booking->date->copy()->setTimeFromTimeString($booking->startsAt());

                return $starts->betweenIncluded($now, $until);
            })
            ->values();
    }

    /**
     * Past their time and still not here.
     *
     * Listed rather than acted on: a client twenty minutes late is somebody
     * to telephone, not somebody to mark absent, and the desk decides which.
     *
     * @return Collection<int, Booking>
     */
    public function lateArrivals(): Collection
    {
        $now = Carbon::now();

        return $this->checkInQueue(50)
            ->filter(fn (Booking $booking) => $booking->date->copy()
                ->setTimeFromTimeString($booking->startsAt())->lt($now))
            ->values();
    }

    /**
     * Checked in and still sitting in reception.
     *
     * The waiting time is measured from the check-in entry rather than from
     * the appointment time: somebody who arrived twenty minutes early has
     * been waiting twenty minutes, whatever the diary says.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function waiting(): Collection
    {
        $tooLong = (int) config('dashboard.waiting_too_long_minutes');

        return $this->today()
            ->where('status', 'arrived')
            ->when($this->staffId !== null, fn (Builder $q) => $q->where('staff_id', $this->staffId))
            ->with(['client', 'staff', 'services', 'resource', 'statusChanges'])
            ->orderBy('starts_at')
            ->get()
            ->map(function (Booking $booking) use ($tooLong) {
                $since = $booking->checkIn()?->created_at;
                $minutes = $since === null ? null : (int) round($since->diffInMinutes(Carbon::now()));

                return [
                    'booking' => $booking,
                    'since' => $since,
                    'minutes' => $minutes,
                    'too_long' => $minutes !== null && $minutes >= $tooLong,
                ];
            })
            ->values();
    }

    /* --------------------------------------------------------------- staff */

    /**
     * Who is working today, and how their day is going.
     *
     * "Checked in" is deliberately absent: StyleDesk records a client
     * arriving, not a member of staff clocking on, and a column that guessed
     * would be a column somebody rotas by.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function staffToday(): Collection
    {
        $today = Carbon::today();

        $shifts = StaffShift::query()
            ->whereDate('date', $today)
            ->where('status', '!=', 'cancelled')
            ->when($this->locations !== null, fn (Builder $q) => $q->whereIn('location_id', $this->locations))
            ->with('staff')
            ->orderBy('starts_at')
            ->get();

        $bookings = $this->today()
            ->whereNotIn('status', ['cancelled', 'declined'])
            ->get(['id', 'staff_id', 'starts_at', 'ends_at', 'status']);

        $now = Carbon::now()->format('H:i');

        return $shifts
            ->filter(fn (StaffShift $shift) => $shift->staff !== null)
            ->map(function (StaffShift $shift) use ($bookings, $now) {
                $theirs = $bookings->where('staff_id', $shift->staff_id);

                return [
                    'staff' => $shift->staff,
                    'starts_at' => substr((string) $shift->starts_at, 0, 5),
                    'ends_at' => substr((string) $shift->ends_at, 0, 5),
                    'current' => $theirs->first(fn (Booking $b) => $b->startsAt() <= $now
                        && substr((string) $b->ends_at, 0, 5) > $now),
                    'next' => $theirs->sortBy('starts_at')->first(fn (Booking $b) => $b->startsAt() > $now),
                    'remaining' => $theirs->filter(fn (Booking $b) => $b->startsAt() > $now
                        && ! in_array($b->status, ['completed', 'no-show'], true))->count(),
                ];
            })
            ->values();
    }

    /**
     * What is wrong with today's rota.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scheduleIssues(): array
    {
        $today = Carbon::today();

        $rostered = StaffShift::query()
            ->whereDate('date', $today)
            ->where('status', '!=', 'cancelled')
            ->when($this->locations !== null, fn (Builder $q) => $q->whereIn('location_id', $this->locations))
            ->pluck('staff_id');

        /* Somebody with clients booked and no shift. Either the rota is
           wrong or the booking is, and both are worth a look before nine. */
        $unrostered = $this->today()
            ->whereNotIn('status', ['cancelled', 'declined', 'no-show'])
            ->whereNotNull('staff_id')
            ->whereNotIn('staff_id', $rostered)
            ->distinct()
            ->pluck('staff_id');

        return array_values(array_filter([
            $unrostered->isEmpty() ? null : [
                'key' => 'working_without_a_shift',
                'count' => $unrostered->count(),
                'names' => Staff::query()->whereIn('id', $unrostered)->get()
                    ->map(fn (Staff $s) => $s->displayName())->join(', '),
            ],
            /* An appointment nobody is down to do. */
            ($count = $this->today()
                ->whereNotIn('status', ['cancelled', 'declined'])
                ->whereNull('staff_id')->count()) === 0 ? null : [
                    'key' => 'bookings_without_staff',
                    'count' => $count,
                ],
        ]));
    }

    /* ------------------------------------------------------------- clients */

    /**
     * @return array<string, int>
     */
    public function clients(): array
    {
        $today = Carbon::today();

        $seenToday = $this->today()
            ->whereNotIn('status', ['cancelled', 'declined'])
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id');

        return [
            'new_today' => Client::query()->whereDate('created_at', $today)->count(),
            'booked_today' => $seenToday->count(),
            /* Somebody who had been in before today. */
            'returning_today' => $seenToday->isEmpty() ? 0 : Booking::query()
                ->whereIn('client_id', $seenToday)
                ->whereDate('date', '<', $today)
                ->whereIn('status', ['completed', 'arrived'])
                ->distinct()
                ->count('client_id'),
            'active' => Client::query()->where('status', 'active')->count(),
        ];
    }

    /* ------------------------------------------------------------ services */

    /**
     * The month's most booked, with what they took.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function services(int $limit = 5): Collection
    {
        $from = Carbon::today()->startOfMonth()->toDateString();
        $to = Carbon::today()->endOfMonth()->toDateString();

        return DB::table('booking_services')
            ->join('bookings', 'bookings.id', '=', 'booking_services.booking_id')
            ->whereBetween('bookings.date', [$from, $to])
            ->whereNotIn('bookings.status', ['draft', 'cancelled', 'declined'])
            ->when($this->locations !== null, fn ($q) => $q->whereIn('bookings.location_id', $this->locations))
            ->groupBy('booking_services.name')
            ->selectRaw('booking_services.name, count(*) as bookings, sum(booking_services.price_minor) as revenue_minor')
            ->orderByDesc('bookings')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'bookings' => (int) $row->bookings,
                'revenue_minor' => (int) $row->revenue_minor,
            ]);
    }

    /* ------------------------------------------------------------ payments */

    /**
     * @return array<string, mixed>
     */
    public function payments(): array
    {
        $today = Carbon::today();

        $collected = (int) BookingPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->when($this->locations !== null, fn (Builder $q) => $q->whereHas(
                'booking', fn (Builder $b) => $b->whereIn('location_id', $this->locations)
            ))
            ->sum('amount_minor');

        return [
            'collected_minor' => $collected,
            'outstanding_minor' => $this->outstandingMinor(),
            'partial' => $this->bookings()->where('payment_status', 'partial')->count(),
            /* Today's work that has not been paid for — the list the desk
               chases before locking up. */
            'due_today' => $this->today()
                ->whereIn('status', ['arrived', 'completed'])
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->count(),
        ];
    }

    /* ---------------------------------------------------------- my own day */

    /** The next client this provider is due to see. */
    public function myNextClient(): ?Booking
    {
        if ($this->staffId === null) {
            return null;
        }

        $now = Carbon::now()->format('H:i');

        return $this->today()
            ->where('staff_id', $this->staffId)
            ->whereIn('status', ['confirmed', 'arrived'])
            ->where('starts_at', '>=', $now)
            /* Not the behavioural tags: they are worked out from the diary
               by a method rather than stored as a relation, so the view asks
               the client for them directly. */
            ->with(['client', 'services', 'resource'])
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function myDay(): Collection
    {
        /* Never everybody's day by accident: without a staff row there is no
           "my", and an unscoped query here would put the whole branch's
           clients on one therapist's screen. */
        if ($this->staffId === null) {
            return collect();
        }

        return $this->today()
            ->where('staff_id', $this->staffId)
            ->with(['client', 'services', 'resource'])
            ->orderBy('starts_at')
            ->get();
    }

    /** Today's shift, where there is one on the rota. */
    public function myShift(): ?StaffShift
    {
        if ($this->staffId === null) {
            return null;
        }

        return StaffShift::query()
            ->where('staff_id', $this->staffId)
            ->whereDate('date', Carbon::today())
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * This provider's own day in numbers, and nobody else's.
     *
     * @return array<string, mixed>
     */
    public function myPerformance(): array
    {
        if ($this->staffId === null) {
            return ['clients' => 0, 'completed' => 0, 'minutes' => 0, 'average_minutes' => 0, 'tips_minor' => 0];
        }

        $day = $this->today()->where('staff_id', $this->staffId);

        $completed = (clone $day)->where('status', 'completed')->get(['id', 'minutes']);

        return [
            'clients' => (clone $day)->whereNotIn('status', ['cancelled', 'declined'])->distinct()->count('client_id'),
            'completed' => $completed->count(),
            'minutes' => (int) $completed->sum('minutes'),
            'average_minutes' => $completed->isEmpty() ? 0 : (int) round($completed->avg('minutes')),
            /* Tips against their own bookings. Theirs alone: another
               therapist's tips are not this person's business. */
            'tips_minor' => (int) BookingPayment::query()
                ->where('status', 'paid')
                ->whereHas('booking', fn (Builder $b) => $b
                    ->whereDate('date', Carbon::today())
                    ->where('staff_id', $this->staffId))
                ->sum('tip_minor'),
        ];
    }

    /** A reader's own scope, built from what they may see. */
    public static function forUser(User $user, ?array $locations = null): self
    {
        $staff = DashboardLayout::staffFor($user);

        /* A provider is scoped to their own work as well as their branch:
           "my day" means theirs, and the permission that grants it is
           deliberately unscoped because own is all it ever means. */
        /* Their own work only, where they have no wider reading of the
           diary. Somebody who can see the branch's bookings sees the branch;
           somebody who can only see their own sees their own. */
        $ownOnly = ! $user->hasPermission('dashboard.view_bookings', 'location');

        return new self($locations, $ownOnly ? $staff?->id : null);
    }
}
