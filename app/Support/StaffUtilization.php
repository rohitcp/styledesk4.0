<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How much of each person's bookable day is actually booked.
 *
 * Not "how busy do they look". A utilization figure that counts a member of
 * staff as free while they are at lunch, in a training block or not rostered
 * at all is a figure that says everybody is underused, and an owner who reads
 * it once and finds it wrong never reads it again. So the denominator is what
 * this class is really about:
 *
 *     scheduled − break − non-bookable time = bookable capacity
 *     booked service time ÷ bookable capacity × 100
 *
 * Capacity comes from the rota, not from the branch's opening hours. Somebody
 * who was not scheduled has no capacity and no utilization — they are absent
 * from the reckoning rather than sitting at 0%, because a day off is not a
 * day wasted, and averaging it in drags the whole team's figure down for a
 * reason that is nobody's problem.
 *
 * What does not count as booked:
 *
 *  - Cancelled, declined, no-show and draft bookings. An appointment nobody
 *    came to filled nobody's day.
 *  - Cancelled shifts. A shift that was called off is a fact about the week
 *    and not capacity anybody had.
 *
 * The arithmetic is shared with the resource board where it can be —
 * `ResourceUtilization::percentage()` is the one place a percentage is capped
 * and rounded — so the two screens cannot come to round differently.
 */
class StaffUtilization
{
    /** Bookings that never happened fill nobody's day. */
    private const IGNORED = ['cancelled', 'declined', 'no-show', 'draft'];

    /**
     * Shift types that are working time but not bookable time.
     *
     * A training day is time the business is paying for and time no client
     * can book — counting it as capacity says somebody was idle when they
     * were in a classroom, and leaving it out of the day altogether loses
     * the fact that they were at work. So it is scheduled, and subtracted.
     */
    private const NON_BOOKABLE = ['training', 'on-call'];

    /**
     * Every member of staff over a window, ready for the bubbles and the table.
     *
     * @param  Builder<Staff>|null  $scope  the staff this reader may see
     * @return array<int, array<string, mixed>>
     */
    public static function forRange(Carbon $from, Carbon $until, ?Builder $scope = null): array
    {
        $team = ($scope ?? Staff::query())
            ->with(['location', 'roleRecord'])
            ->where('is_active', true)
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        if ($team->isEmpty()) {
            return [];
        }

        $shifts = self::shifts($team->pluck('id'), $from, $until);
        $booked = self::bookedMinutes($team->pluck('id'), $from, $until);
        $target = self::target();

        return $team
            ->map(function (Staff $member) use ($shifts, $booked, $target) {
                $capacity = self::capacityOf($shifts->get($member->id, collect()));
                $work = $booked[$member->id] ?? ['minutes' => 0, 'bookings' => 0, 'revenue_minor' => 0];
                $utilization = ResourceUtilization::percentage($work['minutes'], $capacity['bookable']);

                return [
                    'id' => $member->id,
                    'name' => $member->displayName(),
                    'initials' => $member->initials(),
                    'role' => $member->roleName(),
                    'role_id' => $member->role_id,
                    /* The bubble's chip, so the shared board can label it the
                       same way the resource board labels a category. */
                    'category' => $member->roleName(),
                    'location' => $member->location?->name,
                    'location_id' => $member->location_id,

                    /* The three numbers every label on the screen is written
                       from. Minutes here, hours at the edge: an hour is what
                       an owner thinks in and a minute is what divides. */
                    'scheduled_minutes' => $capacity['scheduled'],
                    'break_minutes' => $capacity['break'],
                    'blocked_minutes' => $capacity['blocked'],
                    'available_minutes' => $capacity['bookable'],
                    'booked_minutes' => $work['minutes'],
                    'idle_minutes' => max(0, $capacity['bookable'] - $work['minutes']),

                    'utilization' => $utilization,
                    'target' => $target,
                    'variance' => $capacity['bookable'] > 0 ? $utilization - $target : 0,
                    'bookings' => $work['bookings'],
                    'revenue_minor' => $work['revenue_minor'],
                    'shifts' => $capacity['shifts'],
                    'scheduled' => $capacity['scheduled'] > 0,
                    'status' => self::status($utilization, $capacity['bookable']),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * One person, in the detail the drill-down shows.
     *
     * @return array<string, mixed>
     */
    public static function detail(Staff $member, Carbon $from, Carbon $until, bool $withRevenue = true): array
    {
        $summary = collect(self::forRange($from, $until, Staff::query()->whereKey($member->id)))
            ->first() ?? [];

        $detail = $summary + [
            'daily' => self::daily($member, $from, $until),
            'services' => self::services($member, $from, $until),
            'timeline' => self::timeline($member, $until),
            'gaps' => self::gaps($member, $until),
        ];

        /* Revenue is a separate authority from seeing the rota — see the
           controller. Stripped here as well as there, so a payload built by
           anything else cannot leak it either. */
        if (! $withRevenue) {
            unset($detail['revenue_minor']);

            $detail['services'] = array_map(
                fn (array $line) => Arr::except($line, ['revenue_minor']),
                $detail['services'],
            );
        }

        return $detail;
    }

    /**
     * The whole team's day, drawn along a clock.
     *
     * Only ever one day: a timeline of a fortnight is a picture of nothing.
     * The board asks for it when the range is a single day and hides it
     * otherwise.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public static function teamDay(Carbon $day, array $rows): array
    {
        $team = Staff::query()
            ->whereIn('id', collect($rows)->pluck('id'))
            ->get()
            ->keyBy('id');

        $lines = collect($rows)
            ->map(fn (array $row) => $row + [
                'timeline' => self::timeline($team->get($row['id']), $day),
            ])
            ->filter(fn (array $row) => $row['timeline']['bands'] !== [])
            ->values();

        $opens = $lines->min(fn (array $row) => $row['timeline']['opens_at']);
        $closes = $lines->max(fn (array $row) => $row['timeline']['closes_at']);

        return [
            'date' => $day->toDateString(),
            'label' => $day->isoFormat('dddd, D MMMM YYYY'),
            'opens_at' => $opens ?? 9 * 60,
            'closes_at' => $closes ?? 18 * 60,
            'hours' => self::hourMarks($opens ?? 9 * 60, $closes ?? 18 * 60),
            'rows' => $lines->all(),
        ];
    }

    // ------------------------------------------------------------- capacity

    /**
     * What one person's rota gives them, over the whole window.
     *
     * @param  Collection<int, StaffShift>  $shifts
     * @return array{scheduled: int, break: int, blocked: int, bookable: int, shifts: int}
     */
    private static function capacityOf(Collection $shifts): array
    {
        $scheduled = 0;
        $break = 0;
        $blocked = 0;

        foreach ($shifts as $shift) {
            $length = max(0, (int) $shift->startsAt()->diffInMinutes($shift->endsAt()));

            $scheduled += $length;
            $break += (int) $shift->break_minutes;

            /* Training and on-call are at work and unbookable, so the whole
               shift comes back out of capacity — its break included, which is
               already inside the length. */
            if (in_array($shift->type, self::NON_BOOKABLE, true)) {
                $blocked += max(0, $length - (int) $shift->break_minutes);
            }
        }

        return [
            'scheduled' => $scheduled,
            'break' => $break,
            'blocked' => $blocked,
            'bookable' => max(0, $scheduled - $break - $blocked),
            'shifts' => $shifts->count(),
        ];
    }

    /**
     * The rota over the window, per person.
     *
     * @param  Collection<int, int>  $staffIds
     * @return Collection<int, Collection<int, StaffShift>>
     */
    private static function shifts(Collection $staffIds, Carbon $from, Carbon $until): Collection
    {
        return StaffShift::query()
            ->whereIn('staff_id', $staffIds)
            ->inRange($from->toDateString(), $until->toDateString())
            /* A shift that was called off is a fact about the week and not
               capacity anybody had. */
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')->orderBy('starts_at')
            ->get()
            ->groupBy('staff_id');
    }

    /**
     * Minutes of appointments per person, with what they were worth.
     *
     * Read from the booking rather than from its service lines: a booking has
     * one member of staff, and its `minutes` is the time that person was with
     * the client. Revenue is the bill as it was written on the day —
     * recomputing from today's price list would rewrite what somebody earned
     * last March.
     *
     * @param  Collection<int, int>  $staffIds
     * @return array<int, array{minutes: int, bookings: int, revenue_minor: int}>
     */
    private static function bookedMinutes(Collection $staffIds, Carbon $from, Carbon $until): array
    {
        return self::bookings($staffIds, $from, $until)
            ->groupBy('staff_id')
            ->map(fn (Collection $bookings) => [
                'minutes' => (int) $bookings->sum('minutes'),
                'bookings' => $bookings->count(),
                'revenue_minor' => (int) $bookings->sum('total_minor'),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, int>  $staffIds
     * @return Collection<int, Booking>
     */
    private static function bookings(Collection $staffIds, Carbon $from, Carbon $until): Collection
    {
        return Booking::query()
            ->whereIn('staff_id', $staffIds)
            ->whereNotIn('status', self::IGNORED)
            ->whereBetween('date', [$from->toDateString(), $until->toDateString()])
            /* What the timeline blocks are written from, loaded once for the
               whole window rather than per booking. */
            ->with(['services:id,booking_id,name', 'location:id,name', 'client:id,first_name,last_name'])
            ->orderBy('date')->orderBy('starts_at')
            ->get();
    }

    // -------------------------------------------------------- the drill-down

    /**
     * Utilization day by day, for the trend line.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function daily(Staff $member, Carbon $from, Carbon $until): array
    {
        $shifts = self::shifts(collect([$member->id]), $from, $until)
            ->get($member->id, collect())
            ->groupBy(fn (StaffShift $shift) => $shift->date->toDateString());

        $bookings = self::bookings(collect([$member->id]), $from, $until)
            ->groupBy(fn (Booking $booking) => $booking->date->toDateString());

        $days = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            $key = $day->toDateString();
            $capacity = self::capacityOf($shifts->get($key, collect()));
            $minutes = (int) ($bookings->get($key)?->sum('minutes') ?? 0);

            $days[] = [
                'date' => $key,
                'label' => $day->isoFormat('D MMM'),
                'weekday' => $day->isoFormat('ddd'),
                'available_minutes' => $capacity['bookable'],
                'booked_minutes' => $minutes,
                'utilization' => ResourceUtilization::percentage($minutes, $capacity['bookable']),
            ];
        }

        return $days;
    }

    /**
     * What they actually did, most minutes first.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function services(Staff $member, Carbon $from, Carbon $until): array
    {
        $bookingIds = self::bookings(collect([$member->id]), $from, $until)->pluck('id');

        if ($bookingIds->isEmpty()) {
            return [];
        }

        return BookingService::query()
            ->whereIn('booking_id', $bookingIds)
            ->get(['name', 'minutes', 'price_minor'])
            ->groupBy('name')
            ->map(fn (Collection $lines, string $name) => [
                'name' => $name,
                'bookings' => $lines->count(),
                'minutes' => (int) $lines->sum('minutes'),
                'revenue_minor' => (int) $lines->sum('price_minor'),
            ])
            ->sortByDesc('minutes')
            ->values()
            ->all();
    }

    /**
     * One day of somebody's rota, as bands along a clock.
     *
     * The day the drill-down is asking about: the closing day of the window,
     * which is "today" on a today filter and the last day of a range
     * otherwise.
     *
     * @return array<string, mixed>
     */
    private static function timeline(?Staff $member, Carbon $day): array
    {
        if ($member === null) {
            return ['opens_at' => null, 'closes_at' => null, 'bands' => [], 'blocks' => []];
        }

        $day = $day->copy()->startOfDay();

        $shifts = StaffShift::query()
            ->where('staff_id', $member->id)
            ->onDate($day->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')
            ->get();

        if ($shifts->isEmpty()) {
            return ['opens_at' => null, 'closes_at' => null, 'bands' => [], 'blocks' => []];
        }

        $opens = (int) $shifts->min(fn (StaffShift $shift) => self::minutes($shift->timeValue('starts_at')));
        $closes = (int) $shifts->max(fn (StaffShift $shift) => self::minutes($shift->timeValue('ends_at')));

        /* The day as an array of minutes. A day is at most 1440 of them, and
           interval arithmetic done by hand is where the off-by-one lives. */
        $state = array_fill(0, max(0, $closes - $opens), 'off');

        foreach ($shifts as $shift) {
            $from = self::minutes($shift->timeValue('starts_at'));
            $to = self::minutes($shift->timeValue('ends_at'));

            $working = in_array($shift->type, self::NON_BOOKABLE, true) ? 'blocked' : 'available';

            self::paint($state, $opens, $closes, $from, $to, $working);

            /* The break, in the middle of the shift. Stored as a number of
               minutes rather than a pair of times, so it is drawn where a
               break is actually taken — the middle — rather than pretended
               to be at either end. */
            if ($shift->break_minutes > 0 && $working === 'available') {
                $middle = (int) (($from + $to) / 2);
                $half = (int) ($shift->break_minutes / 2);

                self::paint($state, $opens, $closes, $middle - $half, $middle - $half + $shift->break_minutes, 'break');
            }
        }

        $blocks = [];

        foreach (self::bookings(collect([$member->id]), $day, $day) as $booking) {
            $from = self::minutes($booking->startsAt());
            $to = $from + max(1, (int) $booking->minutes);

            self::paint($state, $opens, $closes, $from, $to, 'booked');

            $blocks[] = [
                'from' => $from,
                'to' => $to,
                'from_label' => TimeFormat::time(self::clock($from)),
                'to_label' => TimeFormat::time(self::clock($to)),
                'minutes' => (int) $booking->minutes,
                'booking_id' => $booking->id,
                'reference' => $booking->reference,
                'client' => $booking->clientName(),
                'service' => $booking->services->pluck('name')->implode(', '),
                'status' => $booking->status,
                'revenue_minor' => (int) $booking->total_minor,
                'location' => $booking->location?->name,
            ];
        }

        return [
            'opens_at' => $opens,
            'closes_at' => $closes,
            'hours' => self::hourMarks($opens, $closes),
            'bands' => self::runs($state, $opens),
            'blocks' => $blocks,
        ];
    }

    /**
     * The gaps worth selling.
     *
     * Not every empty minute: a nine-minute hole between two appointments is
     * arithmetic, not an opportunity, and listing it teaches a receptionist to
     * ignore the list. Only runs long enough to actually put something in.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function gaps(Staff $member, Carbon $day): array
    {
        $timeline = self::timeline($member, $day);

        if ($timeline['bands'] === []) {
            return [];
        }

        $shortest = (int) config('staff.utilization_gap_minutes', 30);

        /* What could actually be sold into the hole: this person's own
           services, shortest first, so the suggestion is one they can do
           rather than one the salon happens to offer. */
        $services = $member->services()
            ->where('services.is_active', true)
            ->orderBy('duration_minutes')
            ->get(['services.id', 'services.name', 'services.duration_minutes']);

        return collect($timeline['bands'])
            ->filter(fn (array $band) => $band['status'] === 'available'
                && $shortest <= $band['to'] - $band['from'])
            ->map(function (array $band) use ($services) {
                $minutes = $band['to'] - $band['from'];

                return [
                    'from' => $band['from'],
                    'to' => $band['to'],
                    'from_label' => TimeFormat::time(self::clock($band['from'])),
                    'to_label' => TimeFormat::time(self::clock($band['to'])),
                    'minutes' => $minutes,
                    'services' => $services
                        ->filter(fn (Service $service) => (int) $service->duration_minutes <= $minutes)
                        ->sortByDesc('duration_minutes')
                        ->take(3)
                        ->map(fn (Service $service) => [
                            'name' => $service->name,
                            'minutes' => (int) $service->duration_minutes,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    // ------------------------------------------------------------ arithmetic

    /**
     * The figure a business is aiming at.
     *
     * Config rather than a number in the code: a spa running four ninety-
     * minute treatments a day and a barber running twenty cuts do not have
     * the same healthy figure. Deliberately not 100 — a day with no gap in it
     * is a day where one client running late makes everybody after them late.
     */
    public static function target(): int
    {
        return (int) config('staff.utilization_target', 75);
    }

    /**
     * How the figure reads at a glance.
     *
     * Bands rather than a gradient: a manager is asking "who needs doing
     * something about", and named answers are actionable where a continuous
     * scale is only decorative. High is its own band and not a better version
     * of on-target, because somebody at 95% has no room left for an
     * appointment that overruns.
     */
    private static function status(int $percentage, int $bookable): string
    {
        return match (true) {
            $bookable <= 0 => 'unscheduled',
            $percentage >= 90 => 'high',
            $percentage >= self::target() => 'on_target',
            $percentage >= 60 => 'near_target',
            $percentage >= 40 => 'low',
            default => 'very_low',
        };
    }

    /**
     * Runs of one state, from an array of minutes.
     *
     * @param  array<int, string>  $state
     * @return array<int, array<string, mixed>>
     */
    private static function runs(array $state, int $offset): array
    {
        $bands = [];
        $start = $offset;

        foreach ($state as $index => $value) {
            if (($state[$index + 1] ?? null) === $value) {
                continue;
            }

            $bands[] = [
                'status' => $value,
                'from' => $start,
                'to' => $offset + $index + 1,
                'from_label' => TimeFormat::time(self::clock($start)),
                'to_label' => TimeFormat::time(self::clock($offset + $index + 1)),
            ];

            $start = $offset + $index + 1;
        }

        return $bands;
    }

    /**
     * @param  array<int, string>  $state
     */
    private static function paint(array &$state, int $opens, int $closes, int $from, int $to, string $value): void
    {
        for ($minute = max($opens, $from); $minute < min($closes, $to); $minute++) {
            $state[$minute - $opens] = $value;
        }
    }

    /**
     * @return array<int, array{minutes: int, label: string}>
     */
    private static function hourMarks(int $from, int $until): array
    {
        $marks = [];

        for ($minute = intdiv($from, 60) * 60; $minute <= $until; $minute += 60) {
            $marks[] = ['minutes' => $minute, 'label' => TimeFormat::time(self::clock($minute))];
        }

        return $marks;
    }

    private static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    private static function minutes(?string $time): int
    {
        if (blank($time) || ! str_contains($time, ':')) {
            return 0;
        }

        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }
}
