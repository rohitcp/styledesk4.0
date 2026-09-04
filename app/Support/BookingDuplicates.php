<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The same client, booked for the same service, twice on one day.
 *
 * A warning rather than a rule: a client really does come back at three for
 * the blow-dry they had at ten, and a desk told "no" by a screen that cannot
 * be argued with will book it under a made-up name instead. So this answers
 * how alike the two bookings are and leaves the decision to the person
 * holding the phone.
 *
 * Three answers, because they are three different mistakes:
 *
 * - `same_day`   — same service, same day, different times. Usually meant.
 * - `overlap`    — the two appointments run over each other. Usually not.
 * - `exact`      — same branch, same start, same end. Almost never meant, and
 *                  the one where the safe action is to abandon the new one.
 *
 * Nothing here is about whether the chair is free: a room double-booked is
 * refused by availability, which is a rule and stays one.
 */
final class BookingDuplicates
{
    /** Weakest first, so the strongest answer on a booking is the last one found. */
    public const LEVELS = ['same_day', 'overlap', 'exact'];

    /**
     * Bookings this one would repeat, strongest first.
     *
     * Settled bookings are left out: a visit that was cancelled, turned down
     * or already worked is not something the client is about to be booked
     * into twice.
     *
     * @param  array<int, int|string>  $serviceIds
     * @return Collection<int, Booking>
     */
    public static function on(
        int|string|null $clientId,
        array $serviceIds,
        ?string $date,
        ?string $startsAt = null,
        int $minutes = 0,
        int|string|null $locationId = null,
        int|string|null $exceptBookingId = null,
    ): Collection {
        if (blank($clientId) || blank($date) || $serviceIds === []) {
            return collect();
        }

        return Booking::query()
            ->where('client_id', $clientId)
            ->whereDate('date', $date)
            ->whereNotIn('status', ['cancelled', 'declined', 'no-show', 'completed'])
            ->when($exceptBookingId, fn (Builder $query, $id) => $query->whereKeyNot($id))
            ->whereHas('services', fn (Builder $line) => $line->whereIn('service_id', $serviceIds))
            ->with(['services', 'staff', 'location'])
            ->orderBy('starts_at')
            ->get()
            ->sortByDesc(fn (Booking $booking) => array_search(
                self::levelOf($booking, $serviceIds, $startsAt, $minutes, $locationId),
                self::LEVELS,
                true,
            ))
            ->values();
    }

    /**
     * How alike one existing booking is to the one being taken.
     *
     * @param  array<int, int|string>  $serviceIds
     */
    public static function levelOf(
        Booking $booking,
        array $serviceIds,
        ?string $startsAt,
        int $minutes,
        int|string|null $locationId,
    ): string {
        if (blank($startsAt) || $minutes <= 0) {
            return 'same_day';
        }

        $start = self::asMinutes($startsAt);
        $end = $start + $minutes;
        $theirStart = self::asMinutes($booking->startsAt());
        $theirEnd = self::asMinutes($booking->endsAt());

        /* Same branch, same start, same end: two rows saying one thing. The
           branch counts because the same client at two sites at ten o'clock
           is a mistake of a different kind — and a business with one branch
           never notices the difference. */
        if ($start === $theirStart && $end === $theirEnd
            && (string) $locationId === (string) $booking->location_id) {
            return 'exact';
        }

        return $start < $theirEnd && $theirStart < $end ? 'overlap' : 'same_day';
    }

    /**
     * The strongest answer across everything found, or nothing at all.
     *
     * @param  Collection<int, Booking>  $matches
     * @param  array<int, int|string>  $serviceIds
     */
    public static function highest(
        Collection $matches,
        array $serviceIds,
        ?string $startsAt,
        int $minutes,
        int|string|null $locationId,
    ): ?string {
        return $matches
            ->map(fn (Booking $booking) => self::levelOf($booking, $serviceIds, $startsAt, $minutes, $locationId))
            ->sortByDesc(fn (string $level) => array_search($level, self::LEVELS, true))
            ->first();
    }

    /**
     * One match, as the warning shows it.
     *
     * The shared services rather than all of them: the reader is being told
     * what is being repeated, and a booking of four services where one is the
     * repeat should say which one.
     *
     * @param  array<int, int|string>  $serviceIds
     * @return array<string, mixed>
     */
    public static function describe(
        Booking $booking,
        array $serviceIds,
        ?string $startsAt,
        int $minutes,
        int|string|null $locationId,
    ): array {
        $shared = $booking->services
            ->filter(fn ($line) => in_array((string) $line->service_id, array_map('strval', $serviceIds), true))
            ->pluck('name')
            ->values();

        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'level' => self::levelOf($booking, $serviceIds, $startsAt, $minutes, $locationId),
            'date' => $booking->date?->translatedFormat('F j, Y'),
            'time' => $booking->timeLabel(),
            'services' => $shared->all(),
            'staff' => $booking->staff ? trim($booking->staff->first_name.' '.$booking->staff->last_name) : null,
            'location' => $booking->location?->name,
            'status' => $booking->statusLabel(),
            'url' => route('bookings.show', $booking),
        ];
    }

    /** The minutes a booking of these services runs to. */
    public static function minutesOf(array $serviceIds): int
    {
        if ($serviceIds === []) {
            return 0;
        }

        return (int) Service::query()->whereIn('id', $serviceIds)->sum('duration_minutes');
    }

    /** "10:30" as minutes past midnight, which is all the comparison needs. */
    private static function asMinutes(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }
}
