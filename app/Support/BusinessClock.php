<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Location;
use Illuminate\Support\Carbon;

/**
 * What time it is where the appointment happens.
 *
 * Not the server's time, not the browser's, and not the receptionist's — a
 * desk in Austin booking the London branch is asking about London's morning,
 * and a laptop that has been carried to another country is not evidence of
 * anything. Every question about "now" that a booking depends on is answered
 * here so the five places that ask cannot each pick a different clock.
 *
 * The order is most specific first: the branch knows its own town, the
 * business knows the country it trades in, and `app.timezone` is the last
 * resort for a business that has told us neither.
 *
 * `app.timezone` is UTC in this application. That makes the fallback wrong
 * for almost everybody, which is deliberate: it is wrong in an obvious,
 * uniform way rather than quietly right for whichever server the code
 * happens to be running on.
 */
class BusinessClock
{
    /** The zone this appointment's day is measured in. */
    public static function timezone(?Location $location = null): string
    {
        return $location?->timezone
            ?: (auth()->user()?->tenant?->timezone
                ?: (string) config('app.timezone', 'UTC'));
    }

    /** Now, on the business's own wall clock. */
    public static function now(?Location $location = null): Carbon
    {
        return Carbon::now(self::timezone($location));
    }

    /** Today's date there, as Y-m-d. */
    public static function today(?Location $location = null): string
    {
        return self::now($location)->toDateString();
    }

    /**
     * Whether this date is the business's today.
     *
     * Asked rather than compared against `now()->toDateString()` at each call
     * site, because a salon five hours behind the server is on yesterday's
     * date for most of the server's morning — and every one of those call
     * sites would have to remember it.
     */
    public static function isToday(?Location $location, string $date): bool
    {
        return $date === self::today($location);
    }

    /**
     * Whether an appointment starting then has already started.
     *
     * Only ever true for today. A date wholly in the past is a different
     * question and not this one's to answer: a salon recording yesterday's
     * walk-in is doing something legitimate, and refusing it here would make
     * the books unfixable.
     *
     * The comparison is on the start, not the end: an appointment that began
     * a minute ago cannot be booked, and one starting in a minute can.
     *
     * @param  string  $time  "H:i", the location's own wall clock
     */
    public static function hasPassed(?Location $location, string $date, ?string $time): bool
    {
        if ($time === null || trim($time) === '' || ! self::isToday($location, $date)) {
            return false;
        }

        $now = self::now($location);

        return self::minutesOf($time) <= ($now->hour * 60 + $now->minute);
    }

    /**
     * The first minute of today still worth offering, as minutes past
     * midnight, or null on any day but today.
     *
     * Rounded up to the next whole minute rather than down: at 9:07:30 the
     * nine-o'clock slot is gone and so is any slot at 9:07, because an
     * appointment cannot start in a second that has already begun.
     */
    public static function earliestMinuteToday(?Location $location, string $date): ?int
    {
        if (! self::isToday($location, $date)) {
            return null;
        }

        $now = self::now($location);

        return $now->hour * 60 + $now->minute + 1;
    }

    /** "09:30" as 570. */
    private static function minutesOf(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', trim($time)), 2, '0');

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
