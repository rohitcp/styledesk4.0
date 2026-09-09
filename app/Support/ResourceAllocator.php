<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Which room or chair an appointment goes in.
 *
 * A service does not name a room. It is mapped to every resource that could
 * carry it, in order of preference — chairs before beds for a reflexology,
 * single rooms before couple rooms for a massage — and this is what turns
 * that list into one answer at one time on one day.
 *
 * Two rules do all the work.
 *
 * The first is that a booking with several services needs one place that can
 * carry all of them, so the eligible set is the intersection rather than the
 * union. A cupping added to a deep tissue happens in the room the client is
 * already lying in; it does not consume a second one.
 *
 * The second is preference, and it is the reason this class exists rather
 * than "any free one". A couple room can take a single client, which makes it
 * the most useful room in the building and the one most easily wasted: a
 * twenty-minute scalp massage put in ROOM-C02 because it happened to be free
 * is a couple turned away at four o'clock. So the highest-priority free
 * resource wins, and a couple room is reached only when everything ahead of
 * it is taken.
 */
class ResourceAllocator
{
    /**
     * The resources that could carry all of these services, best first.
     *
     * Ordered by the worst priority any of the services gives it — a resource
     * that is a first choice for one service and a last resort for another is
     * a last resort, because the appointment is both.
     *
     * @param  array<int, int>  $serviceIds
     * @return Collection<int, resource>
     */
    public static function eligibleFor(array $serviceIds, ?int $locationId = null): Collection
    {
        if ($serviceIds === []) {
            return collect();
        }

        $resources = Resource::query()
            ->where('is_active', true)
            ->where('availability_status', 'available')
            /* A room at another branch is not a room this appointment can
               use. Resources with no location belong to the business rather
               than to one address, so they stay in. */
            ->when($locationId !== null, fn ($query) => $query->where(
                fn ($q) => $q->where('location_id', $locationId)->orWhereNull('location_id')
            ))
            ->whereHas('services', fn ($query) => $query->whereIn('services.id', $serviceIds))
            ->with(['services' => fn ($query) => $query->whereIn('services.id', $serviceIds)])
            ->get();

        return $resources
            /* Every service, not any of them. A room that can take the
               massage but not the reflexology cannot take the appointment. */
            ->filter(fn (Resource $resource) => $resource->services->count() === count(array_unique($serviceIds)))
            /* Preference first, then the order the business put its rooms
               in, then the id so the answer is the same twice.

               One callback returning three values rather than Laravel's
               multi-sort array: the array form takes value-extractors, and a
               list of two-argument comparators passed to it is silently
               treated as extractors — which sorts by nothing at all and was
               how a chair service came back with a room. */
            ->sortBy(fn (Resource $resource) => [
                self::rank($resource),
                (int) $resource->position,
                (int) $resource->id,
            ])
            ->values();
    }

    /**
     * How long a room is actually held for one service.
     *
     * Not the same as how long the client is in it. A sixty-minute massage
     * with ten minutes of preparation and ten of cleaning occupies the room
     * from ten to the hour until ten past — and a room offered to somebody
     * else at three o'clock, because the previous appointment "ended" then,
     * is a room being cleaned around a waiting client.
     *
     * The client is still told sixty minutes. This is only what the diary
     * holds.
     *
     * @return array{lead: int, body: int, trail: int}
     */
    public static function occupancyFor(?Service $service): array
    {
        if ($service === null) {
            return ['lead' => 0, 'body' => 0, 'trail' => 0];
        }

        return [
            'lead' => (int) $service->preparation_minutes,
            /* Processing counts as the room being used: a colour developing
               is a client sitting in the chair. */
            'body' => (int) $service->duration_minutes + (int) $service->processing_minutes,
            'trail' => (int) $service->cleanup_minutes + (int) $service->buffer_minutes,
        ];
    }

    /**
     * A room for one service on one booking, best first.
     *
     * Each line is answered on its own, because a booking can be a massage
     * at ten and a facial at eleven — two rooms, at two times, and one answer
     * for both would be wrong about at least one of them.
     */
    public static function assignForService(
        Service $service,
        string $date,
        string $startsAt,
        ?int $locationId = null,
        ?int $ignoreBookingId = null,
    ): ?Resource {
        $eligible = self::eligibleFor([$service->id], $locationId);

        if ($eligible->isEmpty()) {
            return null;
        }

        $taken = self::takenOn($eligible->pluck('id')->all(), $date, $ignoreBookingId);
        $window = self::occupancyFor($service);

        return $eligible->first(fn (Resource $resource) => ! self::isBusy(
            $taken->get($resource->id, collect()),
            self::shift($startsAt, -$window['lead']),
            $window['lead'] + $window['body'] + $window['trail'],
        ));
    }

    /**
     * Every eligible resource with whether it is free, for the selector.
     *
     * Unavailable ones are returned rather than dropped: a receptionist
     * looking for Single Room 03 and not finding it will assume the mapping
     * is wrong, where "Single Room 03 — unavailable" answers the question
     * they actually had.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function optionsForService(
        Service $service,
        string $date,
        string $startsAt,
        ?int $locationId = null,
        ?int $ignoreBookingId = null,
    ): Collection {
        $eligible = self::eligibleFor([$service->id], $locationId);

        if ($eligible->isEmpty()) {
            return collect();
        }

        $taken = self::takenOn($eligible->pluck('id')->all(), $date, $ignoreBookingId);
        $window = self::occupancyFor($service);
        $from = self::shift($startsAt, -$window['lead']);
        $minutes = $window['lead'] + $window['body'] + $window['trail'];

        return $eligible->map(fn (Resource $resource) => [
            'id' => $resource->id,
            'name' => $resource->name,
            'code' => $resource->code,
            'capacity' => (int) $resource->capacity,
            'available' => ! self::isBusy($taken->get($resource->id, collect()), $from, $minutes),
        ])->values();
    }

    /** A time moved by some minutes, as "H:i". */
    private static function shift(string $time, int $minutes): string
    {
        $at = max(0, (self::toMinutes($time) ?? 0) + $minutes);

        return sprintf('%02d:%02d', intdiv($at, 60) % 24, $at % 60);
    }

    /**
     * The highest-priority resource free for this appointment, or null.
     *
     * Null is a real answer and not a failure: a business that maps no
     * resources to its services has nothing to allocate, and a booking that
     * needs a room when every room is taken should not be written.
     *
     * @param  array<int, int>  $serviceIds
     */
    public static function assign(
        array $serviceIds,
        string $date,
        string $startsAt,
        int $minutes,
        ?int $ignoreBookingId = null,
    ): ?Resource {
        $eligible = self::eligibleFor($serviceIds);

        if ($eligible->isEmpty()) {
            return null;
        }

        $taken = self::takenOn($eligible->pluck('id')->all(), $date, $ignoreBookingId);

        return $eligible->first(fn (Resource $resource) => ! self::isBusy(
            $taken->get($resource->id, collect()), $startsAt, $minutes
        ));
    }

    /**
     * Whether anything at all is free for this appointment.
     *
     * What the availability reader asks, one slot at a time. Kept separate
     * from `assign` because the answer it needs is a yes or no and building a
     * model for each of ninety slots would be a query storm.
     *
     * @param  Collection<int, resource>  $eligible
     * @param  Collection<int, Collection<int, array{starts_at: ?string, ends_at: ?string}>>  $taken
     */
    public static function anythingFree(Collection $eligible, Collection $taken, string $startsAt, int $minutes): bool
    {
        return $eligible->contains(fn (Resource $resource) => ! self::isBusy(
            $taken->get($resource->id, collect()), $startsAt, $minutes
        ));
    }

    /**
     * What each resource is already holding that day.
     *
     * Bookings and maintenance blocks together, because a room being cleaned
     * and a room with somebody in it are the same fact to whoever is looking
     * for a free one.
     *
     * Settled bookings are left out: an appointment that was cancelled or
     * never arrived for is not still holding a room, and counting it would
     * make the day look fuller than it is.
     *
     * @param  array<int, int>  $resourceIds
     * @return Collection<int, Collection<int, array{starts_at: ?string, ends_at: ?string}>>
     */
    public static function takenOn(array $resourceIds, string $date, ?int $ignoreBookingId = null): Collection
    {
        if ($resourceIds === []) {
            return collect();
        }

        $live = fn ($query) => $query
            ->whereDate('date', $date)
            ->whereNotIn('status', ['draft', 'cancelled', 'declined', 'no-show'])
            ->when($ignoreBookingId !== null, fn ($q) => $q->whereKeyNot($ignoreBookingId));

        /* The booking's own room, for appointments taken before rooms were
           recorded per service and for the single-service case. */
        $bookings = Booking::query()
            ->whereIn('resource_id', $resourceIds)
            ->tap($live)
            ->get(['id', 'resource_id', 'starts_at', 'ends_at'])
            ->map(fn (Booking $booking) => [
                'resource_id' => (int) $booking->resource_id,
                'starts_at' => $booking->startsAt(),
                'ends_at' => substr((string) $booking->ends_at, 0, 5),
            ]);

        /*
         * And each service line's own room, held for the whole time it is
         * actually occupied.
         *
         * The window is wider than the appointment: a sixty-minute massage
         * with ten minutes either side holds the room for eighty. A room
         * offered to somebody else the moment the previous client stood up
         * is a room being cleaned around them.
         */
        $lines = BookingService::query()
            ->whereIn('resource_id', $resourceIds)
            ->whereHas('booking', fn ($query) => $live($query))
            ->with(['booking:id,starts_at,ends_at', 'service:id,preparation_minutes,processing_minutes,cleanup_minutes,buffer_minutes,duration_minutes'])
            ->get()
            ->map(function (BookingService $line) {
                $window = self::occupancyFor($line->service);
                $starts = $line->booking?->startsAt() ?? '00:00';

                return [
                    'resource_id' => (int) $line->resource_id,
                    'starts_at' => self::shift($starts, -$window['lead']),
                    'ends_at' => self::shift($starts, $window['body'] + $window['trail']),
                ];
            });

        $bookings = $bookings->concat($lines);

        $blocks = ResourceBlock::query()
            ->whereIn('resource_id', $resourceIds)
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date)
            ->get()
            ->map(fn (ResourceBlock $block) => [
                'resource_id' => (int) $block->resource_id,
                'starts_at' => $block->starts_at?->format('H:i'),
                'ends_at' => $block->ends_at?->format('H:i'),
            ]);

        return $bookings->concat($blocks)->groupBy('resource_id');
    }

    /** The worst priority any of the chosen services gives this resource. */
    private static function rank(Resource $resource): int
    {
        return (int) $resource->services->max(fn ($service) => (int) $service->pivot->priority);
    }

    /**
     * @param  Collection<int, array{starts_at: ?string, ends_at: ?string}>  $windows
     */
    private static function isBusy(Collection $windows, string $startsAt, int $minutes): bool
    {
        return $windows->contains(fn (array $window) => self::overlaps(
            $startsAt, $minutes, $window['starts_at'], $window['ends_at']
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

        /* Back-to-back is not a clash: a booking that ends exactly when
           another starts is how a busy room is run. */
        return $start < $otherEnd && $otherStart < $start + $minutes;
    }

    private static function toMinutes(?string $time): ?int
    {
        if ($time === null || ! str_contains($time, ':')) {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
