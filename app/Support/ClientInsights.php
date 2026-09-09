<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What a client's own bookings say about them.
 *
 * Sentences rather than numbers, because the front desk reads this while
 * somebody is standing there: "usually books every four weeks" is something
 * you can say out loud, and a cadence field of 28 is not.
 *
 * Everything here is worked out from the diary rather than typed by anybody,
 * which is exactly why the booking screen takes a copy of it — see
 * `bookings.client_snapshot`. A client who books every four weeks this year
 * and every eight next year should not rewrite what was true of the
 * appointment they took in between.
 */
class ClientInsights
{
    /** Visits worth reasoning from: kept, not cancelled, and in the past. */
    private const COUNTED = ['confirmed', 'arrived', 'completed'];

    /**
     * The sentences, in the order a receptionist would want them.
     *
     * @return array<int, string>
     */
    public static function for(Client $client): array
    {
        $history = Booking::query()
            ->with(['services', 'staff'])
            ->where('client_id', $client->id)
            ->whereIn('status', self::COUNTED)
            ->whereDate('date', '<=', now()->toDateString())
            ->orderByDesc('date')
            ->limit(40)
            ->get();

        if ($history->isEmpty()) {
            return [];
        }

        return collect([
            self::cadence($history),
            self::favouriteService($history),
            self::usualStaff($history),
            self::averageValue($history),
            self::window($history),
            self::attendance($client, $history),
        ])->filter()->values()->all();
    }

    /**
     * How often they come back, from the gaps between visits.
     *
     * The median rather than the mean: one appointment missed over Christmas
     * should not turn a four-week client into a seven-week one.
     *
     * @param  Collection<int, Booking>  $history
     */
    private static function cadence(Collection $history): ?string
    {
        $dates = $history->pluck('date')->take(8)->values();

        if ($dates->count() < 3) {
            return null;
        }

        $gaps = $dates->sliding(2)
            ->map(fn (Collection $pair) => abs(CarbonImmutable::parse($pair->last())
                ->diffInDays(CarbonImmutable::parse($pair->first()))))
            ->sort()
            ->values();

        $weeks = (int) round(((float) $gaps->get((int) floor($gaps->count() / 2))) / 7);

        return $weeks >= 1 && $weeks <= 26
            ? trans_choice('clients.insights.cadence', $weeks, ['count' => $weeks])
            : null;
    }

    /** @param  Collection<int, Booking>  $history */
    private static function favouriteService(Collection $history): ?string
    {
        $services = $history->flatMap(fn (Booking $booking) => $booking->services->pluck('name'))
            ->countBy()
            ->sortDesc();

        /* Only where there is a favourite. A client with one of each has no
           usual service, and naming the alphabetically-first one invents a
           preference nobody has. */
        return $services->isNotEmpty() && $services->first() >= 2
            ? __('clients.insights.service', ['service' => $services->keys()->first()])
            : null;
    }

    /** @param  Collection<int, Booking>  $history */
    private static function usualStaff(Collection $history): ?string
    {
        $byStaff = $history->filter(fn (Booking $booking) => $booking->staff !== null)
            ->countBy(fn (Booking $booking) => $booking->staff->displayName())
            ->sortDesc();

        if ($byStaff->isEmpty() || $byStaff->first() < 2) {
            return null;
        }

        /* Two thirds or it is a coincidence of the diary rather than a
           preference. */
        return $byStaff->first() / $history->count() >= 0.66
            ? __('clients.insights.staff', ['name' => $byStaff->keys()->first()])
            : null;
    }

    /** @param  Collection<int, Booking>  $history */
    private static function averageValue(Collection $history): ?string
    {
        $spent = $history->sum('total_minor');

        return $spent > 0
            ? __('clients.insights.value', [
                'amount' => Money::format($spent / $history->count() / 100, $history->first()->currency_code),
            ])
            : null;
    }

    /** @param  Collection<int, Booking>  $history */
    private static function window(Collection $history): ?string
    {
        if ($history->count() < 3) {
            return null;
        }

        $windows = $history->countBy(function (Booking $booking) {
            $hour = (int) substr($booking->startsAt(), 0, 2);

            return match (true) {
                $hour < 12 => 'morning',
                $hour < 17 => 'afternoon',
                default => 'evening',
            };
        });

        $top = $windows->sortDesc()->keys()->first();

        return $windows[$top] / $history->count() >= 0.66
            /* Lower-cased: the label is a heading elsewhere ("Afternoon"),
               and this is the middle of a sentence. */
            ? __('clients.insights.window', ['window' => mb_strtolower(__('bookings.when.'.$top))])
            : null;
    }

    /**
     * A run of visits kept.
     *
     * Counted against everything that was booked in the same span, so a
     * client with six kept and two missed does not read as six in a row.
     *
     * @param  Collection<int, Booking>  $history
     */
    private static function attendance(Client $client, Collection $history): ?string
    {
        $missed = Booking::query()
            ->where('client_id', $client->id)
            ->whereIn('status', ['no-show', 'cancelled'])
            ->whereDate('date', '>=', $history->last()->date)
            ->count();

        return $missed === 0 && $history->count() >= 3
            ? __('clients.insights.attendance', ['count' => $history->count()])
            : null;
    }
}
