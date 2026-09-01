<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Location;
use App\Models\LocationClosure;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Service;
use App\Models\StaffShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which times a booking can actually start at.
 *
 * The screen used to offer 6am to 9pm at the business's interval, every day,
 * whatever was chosen — a list of times rather than a list of appointments
 * that could be made. Everything that made a slot impossible was found out
 * afterwards, by the person the client was on the phone to.
 *
 * A slot survives here only if all five of these hold at once:
 *
 *   the location is open across the whole appointment
 *   the staff member is on shift across the whole appointment
 *   every resource the services need is free
 *   the services fit before closing
 *   nothing else is booked over it
 *
 * The order they are applied in is deliberate: the cheapest question that can
 * empty the list is asked first, so a closed Sunday costs one query rather
 * than five.
 *
 * Times are the location's own wall clock throughout — "H:i" strings, never
 * timestamps. A salon's day is 9 to 6 in the town it is in, and converting
 * that through a zone would be inventing precision the data does not have.
 */
class BookingAvailability
{
    /** What a slot is worth being offered on: a whole interval, at least. */
    private const MIN_INTERVAL = 5;

    /**
     * @param  array<int, int>  $serviceIds
     * @return array{slots: array<int, string>, closed: bool, reason: ?string, minutes: int}
     */
    public static function for(
        ?Location $location,
        string $date,
        ?int $staffId = null,
        array $serviceIds = [],
        ?int $ignoreBookingId = null,
    ): array {
        $minutes = self::durationFor($serviceIds);
        $day = Carbon::parse($date);

        /* No location chosen yet: the business's own hours are not a thing
           this app stores per business, so the honest answer is the whole
           working range rather than a guess dressed as availability. */
        if ($location === null) {
            return self::answer(self::wholeDay($minutes), false, null, $minutes);
        }

        if (self::isClosedByClosure($location, $day)) {
            return self::answer([], true, 'closed_date', $minutes);
        }

        $windows = self::openWindows($location, $day);

        if ($windows->isEmpty()) {
            return self::answer([], true, 'closed_date', $minutes);
        }

        $slots = self::slotsWithin($windows, $minutes);

        if ($slots === []) {
            /* Open, but not for long enough to fit this appointment — which
               is a different fact from being closed, and the screen says so. */
            return self::answer([], false, 'too_long', $minutes);
        }

        $slots = self::keepWhileStaffIsOnShift($slots, $minutes, $staffId, $date);
        $slots = self::dropBookedOver($slots, $minutes, $staffId, $date, $ignoreBookingId);
        $slots = self::dropWhereResourcesAreTaken($slots, $minutes, $serviceIds, $date, $ignoreBookingId);

        return self::answer(array_values($slots), false, $slots === [] ? 'nothing_free' : null, $minutes);
    }

    /**
     * How long the appointment is.
     *
     * The sum of what each service actually occupies — its own time plus the
     * preparation, processing, cleanup and buffer around it — because that is
     * the room the chair is taken for. An appointment quoted as an hour that
     * occupies ninety minutes is how a day silently overbooks.
     *
     * @param  array<int, int>  $serviceIds
     */
    public static function durationFor(array $serviceIds): int
    {
        if ($serviceIds === []) {
            return 0;
        }

        return (int) Service::query()
            ->whereIn('id', $serviceIds)
            ->get()
            ->sum(fn (Service $service) => $service->bookedMinutes());
    }

    /** The step between one offered time and the next. */
    private static function interval(): int
    {
        return max(self::MIN_INTERVAL, (int) (auth()->user()?->tenant?->default_appointment_interval ?: 15));
    }

    /**
     * @param  array<int, string>  $slots
     * @return array{slots: array<int, string>, closed: bool, reason: ?string, minutes: int}
     */
    private static function answer(array $slots, bool $closed, ?string $reason, int $minutes): array
    {
        return ['slots' => $slots, 'closed' => $closed, 'reason' => $reason, 'minutes' => $minutes];
    }

    /**
     * The fallback range, used only while no location has been chosen.
     *
     * @return array<int, string>
     */
    private static function wholeDay(int $minutes): array
    {
        return self::slotsWithin(collect([['opens' => 6 * 60, 'closes' => 21 * 60]]), $minutes);
    }

    /** A whole-day closure, a holiday, or a day the branch has shut. */
    private static function isClosedByClosure(Location $location, Carbon $day): bool
    {
        return LocationClosure::query()
            ->where('location_id', $location->id)
            ->covering($day)
            ->where('is_closed_all_day', true)
            ->exists();
    }

    /**
     * When the branch is open on this date, in minutes past midnight.
     *
     * A day can have several windows — a salon that shuts for lunch is two —
     * and each is treated on its own, so an appointment may not straddle the
     * gap. A closure that shortens the day rather than cancelling it narrows
     * the windows instead of removing them.
     *
     * @return Collection<int, array{opens: int, closes: int}>
     */
    private static function openWindows(Location $location, Carbon $day): Collection
    {
        $windows = $location->hours()
            ->where('day_of_week', (int) $day->dayOfWeek)
            ->where('is_open', true)
            ->get()
            ->map(fn ($hour) => [
                'opens' => self::toMinutes($hour->opens_at),
                'closes' => self::toMinutes($hour->closes_at),
            ])
            ->filter(fn (array $window) => $window['opens'] !== null && $window['closes'] !== null
                && $window['closes'] > $window['opens'])
            ->values();

        $partial = LocationClosure::query()
            ->where('location_id', $location->id)
            ->covering($day)
            ->where('is_closed_all_day', false)
            ->get();

        foreach ($partial as $closure) {
            $opens = self::toMinutes($closure->opens_at);
            $closes = self::toMinutes($closure->closes_at);

            if ($opens === null || $closes === null) {
                continue;
            }

            /* A short day replaces the window rather than adding one: "we are
               open 10 to 2 on Christmas Eve" is the whole of that day. */
            $windows = $windows
                ->map(fn (array $window) => [
                    'opens' => max($window['opens'], $opens),
                    'closes' => min($window['closes'], $closes),
                ])
                ->filter(fn (array $window) => $window['closes'] > $window['opens'])
                ->values();
        }

        return $windows;
    }

    /**
     * Every start time that leaves room for the whole appointment.
     *
     * The last slot of a nine-to-six day with an hour's work in it is five,
     * not half past: half past finishes at half six, and the door is locked.
     *
     * @param  Collection<int, array{opens: int, closes: int}>  $windows
     * @return array<int, string>
     */
    private static function slotsWithin(Collection $windows, int $minutes): array
    {
        $step = self::interval();
        $slots = [];

        foreach ($windows as $window) {
            /* Zero-length is a booking with no services chosen yet. The times
               are still worth showing — the reader is often picking the hour
               before the service — so it is treated as "must start before
               closing" rather than as an appointment of no length. */
            $latest = $window['closes'] - max($minutes, $step);

            for ($start = $window['opens']; $start <= $latest; $start += $step) {
                $slots[] = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            }
        }

        return array_values(array_unique($slots));
    }

    /**
     * Drop the times this person is not working.
     *
     * Only when somebody has been chosen: "anyone" is answered by the
     * location's hours, because the question of which of them is free is the
     * one being asked by picking a time.
     *
     * A shift that has not been published still counts. The rota is what the
     * business intends; publishing is whether the staff member has been told,
     * and a receptionist booking against next week's unpublished rota is
     * booking against the truth.
     *
     * @param  array<int, string>  $slots
     * @return array<int, string>
     */
    private static function keepWhileStaffIsOnShift(array $slots, int $minutes, ?int $staffId, string $date): array
    {
        if ($staffId === null || $slots === []) {
            return $slots;
        }

        $shifts = StaffShift::query()
            ->where('staff_id', $staffId)
            ->onDate($date)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(fn (StaffShift $shift) => [
                'opens' => self::toMinutes($shift->starts_at),
                'closes' => self::toMinutes($shift->ends_at),
            ])
            ->filter(fn (array $window) => $window['opens'] !== null && $window['closes'] !== null);

        /* Nobody rostered is not the same as nobody available: a salon that
           does not keep rotas would otherwise show no times at all for every
           member of staff. Absence of a shift means the location's hours
           stand. */
        if ($shifts->isEmpty()) {
            return $slots;
        }

        return array_values(array_filter(
            $slots,
            fn (string $slot) => $shifts->contains(fn (array $window) => self::fitsWithin($slot, $minutes, $window))
        ));
    }

    /**
     * Drop the times this person is already booked over.
     *
     * @param  array<int, string>  $slots
     * @return array<int, string>
     */
    private static function dropBookedOver(array $slots, int $minutes, ?int $staffId, string $date, ?int $ignore): array
    {
        if ($staffId === null || $slots === []) {
            return $slots;
        }

        $taken = Booking::query()
            ->where('staff_id', $staffId)
            ->whereDate('date', $date)
            /* A draft is a booking still being written and holds nothing:
               the screen saves one as the receptionist types, and a slot
               reserved by a call that was abandoned is a slot nobody can
               ever have. It becomes real when it is confirmed. */
            ->whereNotIn('status', ['draft', 'cancelled', 'no-show'])
            ->when($ignore, fn ($query, $id) => $query->whereKeyNot($id))
            ->get(['starts_at', 'ends_at']);

        return self::withoutOverlaps($slots, $minutes, $taken);
    }

    /**
     * Drop the times a required chair or room is taken.
     *
     * A service that needs a room cannot be given one that is blocked for
     * maintenance or already holding somebody else, however free the stylist
     * is. Services that need nothing are unaffected.
     *
     * @param  array<int, string>  $slots
     * @param  array<int, int>  $serviceIds
     * @return array<int, string>
     */
    private static function dropWhereResourcesAreTaken(array $slots, int $minutes, array $serviceIds, string $date, ?int $ignore): array
    {
        if ($slots === [] || $serviceIds === []) {
            return $slots;
        }

        $resourceIds = Resource::query()
            ->whereHas('services', fn ($query) => $query->whereIn('services.id', $serviceIds))
            ->pluck('resources.id');

        if ($resourceIds->isEmpty()) {
            return $slots;
        }

        /* Every one of them being busy is what makes a slot impossible. One
           free chair of three is a bookable appointment, so the count is what
           matters rather than any individual row. */
        $capacity = $resourceIds->count();

        $blocked = ResourceBlock::query()
            ->whereIn('resource_id', $resourceIds)
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date)
            ->get()
            ->map(fn (ResourceBlock $block) => [
                'starts_at' => $block->starts_at?->format('H:i'),
                'ends_at' => $block->ends_at?->format('H:i'),
            ]);

        return array_values(array_filter($slots, function (string $slot) use ($minutes, $blocked, $capacity) {
            $clashes = $blocked->filter(fn (array $window) => self::overlaps($slot, $minutes, $window['starts_at'], $window['ends_at']))->count();

            return $clashes < $capacity;
        }));
    }

    /**
     * @param  array<int, string>  $slots
     * @param  Collection<int, mixed>  $taken
     * @return array<int, string>
     */
    private static function withoutOverlaps(array $slots, int $minutes, Collection $taken): array
    {
        if ($taken->isEmpty()) {
            return $slots;
        }

        return array_values(array_filter(
            $slots,
            fn (string $slot) => ! $taken->contains(
                fn ($booking) => self::overlaps($slot, $minutes, $booking->starts_at, $booking->ends_at)
            )
        ));
    }

    /** Whether an appointment starting here runs into that period. */
    private static function overlaps(string $slot, int $minutes, ?string $from, ?string $until): bool
    {
        $start = self::toMinutes($slot);
        $otherStart = self::toMinutes($from);
        $otherEnd = self::toMinutes($until);

        if ($start === null || $otherStart === null || $otherEnd === null) {
            return false;
        }

        /* A booking that ends exactly when another starts does not overlap:
           back-to-back is how a busy chair is run. */
        return $start < $otherEnd && $otherStart < $start + max($minutes, 1);
    }

    /** @param  array{opens: int, closes: int}  $window */
    private static function fitsWithin(string $slot, int $minutes, array $window): bool
    {
        $start = self::toMinutes($slot);

        return $start !== null
            && $start >= $window['opens']
            && $start + $minutes <= $window['closes'];
    }

    /** "09:30" or "09:30:00" as minutes past midnight. */
    private static function toMinutes(?string $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }
}
