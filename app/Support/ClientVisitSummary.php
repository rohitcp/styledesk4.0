<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Client;

/**
 * The four figures at the top of a client's profile.
 *
 * They used to be two denormalised columns and two honest blanks, because
 * there were no bookings to count. There are now, so all four are counted
 * from the diary and the till rather than from a column something else has
 * to remember to keep in step.
 *
 * Each one answers a different question and each is counted differently, so
 * they are worked out here rather than in four places that would drift:
 * "when were they last in" is not "how many times have they been", and
 * neither is "what have they actually paid us".
 */
class ClientVisitSummary
{
    /**
     * What counts as a visit.
     *
     * Completed, and nothing else. A booking in the diary for next Tuesday
     * is not a visit; a cancelled one never happened; a no-show is somebody
     * who did not come. Counting any of them would make the number a count
     * of rows rather than a count of times this person sat in a chair.
     */
    private const VISITED = ['completed'];

    /** @return array<string, mixed> */
    public static function for(Client $client): array
    {
        return [
            'last_visit' => self::lastVisit($client),
            'next_appointment' => self::nextAppointment($client),
            'total_visits' => self::totalVisits($client),
            'lifetime_spend' => self::lifetimeSpend($client),
        ];
    }

    /**
     * The last time they were actually in, and what for.
     *
     * The service and the stylist are what make the date useful: "3 Sep" is a
     * fact, "3 Sep, balayage with Mei" is the thing a receptionist repeats
     * back down the phone.
     *
     * @return array<string, mixed>|null
     */
    private static function lastVisit(Client $client): ?array
    {
        $booking = Booking::query()
            ->with(['staff', 'services'])
            ->where('client_id', $client->id)
            ->whereIn('status', self::VISITED)
            ->orderByDesc('date')->orderByDesc('starts_at')
            ->first();

        if ($booking === null) {
            return null;
        }

        return [
            'value' => $booking->date->isoFormat('D MMM Y'),
            'detail' => self::detailLine($booking),
            'url' => route('bookings.show', $booking),
        ];
    }

    /**
     * The next thing in the diary, with the time on it.
     *
     * Confirmed only: "arrived" is somebody who is here now, which is the
     * answer to "where are they" rather than to "when are they next in".
     * Today counts whatever the clock says — a two o'clock appointment is
     * still the answer at half past, because they are in the chair.
     *
     * @return array<string, mixed>|null
     */
    private static function nextAppointment(Client $client): ?array
    {
        $booking = Booking::query()
            ->with(['staff', 'services'])
            ->where('client_id', $client->id)
            ->where('status', 'confirmed')
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')->orderBy('starts_at')
            ->first();

        if ($booking === null) {
            return null;
        }

        return [
            'value' => $booking->date->isoFormat('D MMM Y').' · '.TimeFormat::time($booking->startsAt()),
            'detail' => self::detailLine($booking),
            'url' => route('bookings.show', $booking),
        ];
    }

    /** How many times they have actually been in. */
    private static function totalVisits(Client $client): array
    {
        $count = Booking::query()
            ->where('client_id', $client->id)
            ->whereIn('status', self::VISITED)
            ->count();

        return $count === 0 ? [] : ['value' => number_format($count)];
    }

    /**
     * What they have actually paid, which is not what they were billed.
     *
     * Counted from the payments rather than from booking totals: an unpaid
     * booking is not spend, and a bill somebody was quoted and never settled
     * is the difference between a lifetime value and a hopeful one. Refunds
     * are negative rows, so the sum is what the business is still holding
     * rather than everything that ever passed through the till.
     */
    private static function lifetimeSpend(Client $client): array
    {
        $bookings = Booking::query()->where('client_id', $client->id)->select('id');

        $minor = (int) BookingPayment::query()
            ->whereIn('booking_id', $bookings)
            ->whereIn('status', ['paid', 'refunded'])
            ->sum('amount_minor');

        $currency = Booking::query()->where('client_id', $client->id)
            ->whereNotNull('currency_code')->value('currency_code');

        return $minor <= 0 ? [] : ['value' => Money::format($minor / 100, $currency)];
    }

    /** "Balayage · Mei Chen", or as much of it as there is. */
    private static function detailLine(Booking $booking): ?string
    {
        $parts = array_filter([
            $booking->services->pluck('name')->implode(', ') ?: null,
            $booking->staff?->displayName(),
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
