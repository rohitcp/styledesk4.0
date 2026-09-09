<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * What a client has actually booked, one row per service.
 *
 * The diary holds a row per appointment; this is the other way of reading it
 * — six balayages rather than six Tuesdays. Grouped rather than listed,
 * because "how often, and when last" is the question a receptionist is
 * actually asking, and a list of every appointment makes them count.
 *
 * Arithmetic, and only arithmetic. Nothing here becomes a favourite: that is
 * a statement somebody made at the desk, and the two are kept apart on
 * purpose — see Client::favoriteServices().
 */
class ClientServiceHistory
{
    /**
     * Which bookings count as having happened.
     *
     * The same three the client's history and insights count. A cancelled
     * appointment is not a service they have had, and a draft is not an
     * appointment at all.
     */
    private const COUNTED = ['confirmed', 'arrived', 'completed'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(Client $client): array
    {
        $lines = BookingService::query()
            ->whereIn('booking_id', Booking::query()
                ->where('client_id', $client->id)
                ->whereIn('status', self::COUNTED)
                ->select('id'))
            /* A service removed from the price list leaves its name on the
               line but no id to group by. Those are left out rather than
               guessed at by name: two salons' worth of "Cut" spelled three
               ways is a count nobody can trust. */
            ->whereNotNull('service_id')
            ->with(['booking.staff'])
            ->get();

        if ($lines->isEmpty()) {
            return [];
        }

        $services = Service::query()
            ->with('category')
            ->whereIn('id', $lines->pluck('service_id')->unique())
            ->get()
            ->keyBy('id');

        $favourites = $client->favoriteServices->pluck('id')->all();

        return $lines
            ->groupBy('service_id')
            ->map(function (Collection $group, int $serviceId) use ($services, $favourites) {
                /* The most recent one it was booked on, which is what "last
                   booked" and "last provider" both come from. */
                $latest = $group
                    ->sortByDesc(fn (BookingService $line) => $line->booking?->date?->toDateString().' '.$line->booking?->starts_at)
                    ->first();

                $service = $services->get($serviceId);
                $currency = $latest->booking?->currency_code;

                return [
                    'id' => $serviceId,
                    'name' => $service?->name ?? $latest->name,
                    'category' => $service?->category?->name,
                    'visits' => $group->count(),
                    'last_booked' => $latest->booking?->date?->isoFormat('D MMM Y'),
                    'last_booked_on' => $latest->booking?->date?->toDateString(),
                    'last_provider' => $latest->booking?->staff?->displayName(),
                    /* What it cost last time, not what it costs now: the
                       point of this line is what this client has been
                       charged. */
                    'last_price' => Money::format(((int) $latest->price_minor) / 100, $currency),
                    'is_favorite' => in_array($serviceId, $favourites, true),
                    /* Only a service still on the price list can be booked
                       again, so only one of those can be favourited. */
                    'bookable' => $service !== null,
                ];
            })
            /* Most booked first, then most recent: "what do they always have"
               before "what did they have once". */
            ->sortBy([
                fn (array $a, array $b) => $b['visits'] <=> $a['visits'],
                fn (array $a, array $b) => ($b['last_booked_on'] ?? '') <=> ($a['last_booked_on'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
