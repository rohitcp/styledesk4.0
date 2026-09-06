<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BookingService;
use App\Models\Location;
use App\Models\LocationClosure;
use App\Models\Resource;
use App\Models\ResourceBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What every room and chair is doing today, minute by minute.
 *
 * The operational half of the resources module. Utilization asks how the
 * week went; this asks what is free right now, when the room somebody is in
 * comes back, and which of them the next booking should go into — three
 * questions a receptionist has with somebody standing in front of them, and
 * which a percentage cannot answer.
 *
 * It computes nothing about availability that the booking engine does not
 * already decide. `ResourceAllocator` owns what "occupied" means — the
 * appointment plus its preparation and its cleaning, blocks counted the same
 * as bookings, cancelled and no-show appointments counted as nothing — and
 * this reads the same rules through `occupancyFor()` rather than restating
 * them. A screen that draws a room as free while the booking screen refuses
 * to book it is worse than no screen.
 *
 * The day is modelled as an array of minutes rather than as a list of
 * intervals to be intersected. A day is at most 1440 of them and a business
 * has tens of resources, so the loop is nothing; interval arithmetic done by
 * hand is where the off-by-one lives, and this way capacity falls out for
 * free — a couple room with one client in it has one lane left, which is a
 * count, not a boolean.
 */
class ResourceAvailability
{
    /** Bookings that never happened hold no room. Same list the allocator keeps. */
    private const IGNORED = ['draft', 'cancelled', 'declined', 'no-show'];

    /** What a minute of a resource's day can be, worst first. */
    public const CLOSED = 'closed';

    public const BLOCKED = 'blocked';

    public const BOOKED = 'booked';

    public const AVAILABLE = 'available';

    /**
     * One day, every resource, ready for the timeline.
     *
     * @return array<string, mixed>
     */
    public static function forDay(Carbon $day, int|string|null $locationId = null): array
    {
        $day = $day->copy()->startOfDay();

        $resources = Resource::query()
            ->with(['category', 'location.hours', 'hours'])
            ->active()
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->inOrder()
            ->get();

        $locations = Location::query()->with('hours')->get()->keyBy('id');

        /* The chart's own window: the earliest anything opens to the latest
           anything closes. Hours nobody is open for are not drawn — an
           eight-hour day stretched across twenty-four is a picture of a
           business that is mostly shut. */
        $window = self::window($resources, $locations, $day);

        $blocks = self::blocksOn($resources->pluck('id')->all(), $day);
        $lines = self::linesOn($resources->pluck('id')->all(), $day);

        $now = $day->isToday()
            ? (int) now()->format('H') * 60 + (int) now()->format('i')
            : null;

        $rows = $resources
            ->map(fn (Resource $resource) => self::row(
                $resource,
                $window,
                $day,
                $locations->get($resource->location_id),
                $blocks->get($resource->id, collect()),
                $lines->get($resource->id, collect()),
                $now,
            ))
            ->values()
            ->all();

        return [
            'date' => $day->toDateString(),
            'label' => $day->isoFormat('dddd, D MMMM YYYY'),
            'is_today' => $day->isToday(),
            'previous' => $day->copy()->subDay()->toDateString(),
            'next' => $day->copy()->addDay()->toDateString(),

            /* Minutes from midnight, both ends, and the hour marks between
               them. The browser scales these to pixels; it does not decide
               which hours exist. */
            'opens_at' => $window['from'],
            'closes_at' => $window['until'],
            'hours' => self::hourMarks($window),
            'closed_all_day' => $window['closed'],

            /* Only on today, and only ever as a number of minutes: a browser
               drawing the line from its own clock would put it in the wrong
               place for every salon in another timezone. */
            'now' => $now !== null && $now >= $window['from'] && $now <= $window['until'] ? $now : null,
            'now_label' => $now === null ? null : TimeFormat::time(self::clock($now)),

            'resources' => $rows,
            'summary' => self::summary($rows),
        ];
    }

    // ------------------------------------------------------------- one row

    /**
     * @param  array{from: int, until: int, closed: bool}  $window
     * @param  Collection<int, ResourceBlock>  $blocks
     * @param  Collection<int, object>  $lines
     * @return array<string, mixed>
     */
    private static function row(
        Resource $resource,
        array $window,
        Carbon $day,
        ?Location $location,
        Collection $blocks,
        Collection $lines,
        ?int $now,
    ): array {
        $capacity = max(1, (int) $resource->capacity);
        $open = self::openWindow($resource, $location, $day);

        /* How many of this resource's places are taken in each minute of the
           chart. Everything below is read off this one array. */
        $span = $window['until'] - $window['from'];
        $taken = array_fill(0, max(0, $span), 0);

        $occupied = self::occupations($lines, $resource);
        $blocked = self::blockBands($blocks, $day);

        foreach ($occupied as $piece) {
            self::paint($taken, $window, $piece['from'], $piece['to'], 1);
        }

        /* A block takes the whole thing, however many places it has. A room
           being repainted is not half available. */
        foreach ($blocked as $band) {
            self::paint($taken, $window, $band['from'], $band['to'], $capacity);
        }

        $lanes = self::lanes($occupied, $capacity);
        $bands = self::bands($taken, $window, $open, $blocked, $capacity);

        $usedMinutes = (int) collect($occupied)
            ->where('kind', self::BOOKED)
            ->sum(fn (array $piece) => max(0, $piece['to'] - $piece['from']));

        $openMinutes = $open === null ? 0 : max(0, $open['until'] - $open['from']);

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'code' => $resource->code,
            'category' => $resource->category?->name,
            'category_id' => $resource->resource_category_id,
            'location' => $resource->location?->name,
            'location_id' => $resource->location_id,
            'capacity' => $capacity,
            'url' => route('resources.show', $resource),

            'opens_at' => $open['from'] ?? null,
            'closes_at' => $open['until'] ?? null,
            'open_minutes' => $openMinutes,
            'used_minutes' => $usedMinutes,
            'utilization' => ResourceUtilization::percentage($usedMinutes, $openMinutes * $capacity),

            /* Row-wide backgrounds: closed, blocked, free. Drawn under the
               appointments rather than between them, so a room with a gap
               reads as free at a glance instead of as nothing at all. */
            'bands' => $bands,
            /* The appointments themselves, each in its lane. */
            'blocks' => $lanes,
            'lanes' => max(1, $capacity),

            'bookings' => collect($occupied)->where('kind', self::BOOKED)->count(),

            /* Null on any day but today — see statusAt(). */
            'status' => self::statusAt($taken, $window, $open, $blocked, $capacity, $now),
            'current' => self::currentAt($lanes, $now),
            'next' => self::nextAfter($lanes, $now ?? $window['from']),
            'available_until' => self::freeUntil($taken, $window, $open, $capacity, $now),
        ];
    }

    /**
     * Every piece of time this resource is spoken for, and by what.
     *
     * The occupancy window comes from `ResourceAllocator::occupancyFor()` —
     * the booking engine's own answer to how long a room is actually held —
     * so preparation and cleaning are part of it here exactly as they are
     * when a slot is offered or refused.
     *
     * @param  Collection<int, object>  $lines
     * @return array<int, array<string, mixed>>
     */
    private static function occupations(Collection $lines, Resource $resource): array
    {
        $pieces = [];

        foreach ($lines as $line) {
            $window = ResourceAllocator::occupancyFor($line->serviceModel);
            $start = self::minutes($line->starts_at);

            if ($start === null) {
                continue;
            }

            /* Preparation before, the appointment, cleaning after. Three
               pieces rather than one long one, because "the room is being
               turned around" and "there is a client in it" look different to
               whoever is trying to use it. */
            $body = [
                'kind' => self::BOOKED,
                'from' => $start,
                'to' => $start + max(1, $window['body'] ?: (int) $line->minutes),
            ];

            $pieces[] = $body + self::describe($line, $resource);

            if ($window['lead'] > 0) {
                $pieces[] = [
                    'kind' => 'prep',
                    'from' => $start - $window['lead'],
                    'to' => $start,
                ] + self::describe($line, $resource);
            }

            if ($window['trail'] > 0) {
                $pieces[] = [
                    'kind' => 'cleanup',
                    'from' => $body['to'],
                    'to' => $body['to'] + $window['trail'],
                ] + self::describe($line, $resource);
            }
        }

        usort($pieces, fn (array $a, array $b) => $a['from'] <=> $b['from']);

        return $pieces;
    }

    /**
     * What the tooltip and the booking panel are written from.
     *
     * @return array<string, mixed>
     */
    private static function describe(object $line, Resource $resource): array
    {
        return [
            'booking_id' => (int) $line->booking_id,
            'reference' => $line->reference,
            'service' => $line->name,
            'client' => $line->client_name,
            'staff' => $line->staff_name,
            'status' => $line->status,
            'resource' => $resource->name,
            'minutes' => (int) $line->minutes,
        ];
    }

    /**
     * Appointments packed into lanes, so a couple room can show two at once.
     *
     * First fit: an appointment goes in the lowest lane free at its start.
     * Anything that will not fit — which means the diary holds more than the
     * room can carry — is put in the last lane rather than dropped. A double
     * booking is something this screen exists to show.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    private static function lanes(array $pieces, int $capacity): array
    {
        $ends = array_fill(0, max(1, $capacity), -1);
        $laneOf = [];

        /* Lanes are decided by the appointments alone. Preparation and
           cleaning then take the lane of the appointment they belong to —
           packed on their own they would drift into a different row from the
           booking they are part of, which draws one appointment as two. */
        foreach ($pieces as $piece) {
            if ($piece['kind'] !== self::BOOKED) {
                continue;
            }

            $lane = null;

            foreach ($ends as $index => $endsAt) {
                if ($piece['from'] >= $endsAt) {
                    $lane = $index;
                    break;
                }
            }

            /* Nowhere to put it means the diary holds more than the room can
               carry. It goes in the last lane rather than being dropped: a
               double booking is something this screen exists to show. */
            $lane ??= count($ends) - 1;
            $ends[$lane] = max($ends[$lane], $piece['to']);
            $laneOf[$piece['booking_id'].':'.$piece['from']] = $lane;
        }

        $placed = [];

        foreach ($pieces as $piece) {
            $key = $piece['kind'] === self::BOOKED
                ? $piece['booking_id'].':'.$piece['from']
                : null;

            $placed[] = $piece + [
                'lane' => $key !== null
                    ? ($laneOf[$key] ?? 0)
                    : self::laneNear($laneOf, $piece['booking_id']),
                'from_label' => TimeFormat::time(self::clock($piece['from'])),
                'to_label' => TimeFormat::time(self::clock($piece['to'])),
                'length' => max(0, $piece['to'] - $piece['from']),
            ];
        }

        return $placed;
    }

    /**
     * The lane an appointment's own preparation or cleaning belongs in.
     *
     * @param  array<string, int>  $laneOf
     */
    private static function laneNear(array $laneOf, int $bookingId): int
    {
        foreach ($laneOf as $key => $lane) {
            if (str_starts_with($key, $bookingId.':')) {
                return $lane;
            }
        }

        return 0;
    }

    /**
     * The row's background, as runs of one state.
     *
     * @param  array<int, int>  $taken
     * @param  array{from: int, until: int, closed: bool}  $window
     * @param  array{from: int, until: int}|null  $open
     * @param  array<int, array{from: int, to: int, label: ?string, reason: ?string}>  $blocked
     * @return array<int, array<string, mixed>>
     */
    private static function bands(array $taken, array $window, ?array $open, array $blocked, int $capacity): array
    {
        $states = [];

        for ($minute = $window['from']; $minute < $window['until']; $minute++) {
            $states[] = self::stateAt($taken, $window, $open, $blocked, $capacity, $minute);
        }

        $bands = [];
        $start = $window['from'];

        foreach ($states as $index => $state) {
            $next = $states[$index + 1] ?? null;

            if ($state === $next) {
                continue;
            }

            $bands[] = [
                'status' => $state,
                'from' => $start,
                'to' => $window['from'] + $index + 1,
                'from_label' => TimeFormat::time(self::clock($start)),
                'to_label' => TimeFormat::time(self::clock($window['from'] + $index + 1)),
                'reason' => $state === self::BLOCKED
                    ? self::blockLabel($blocked, $start)
                    : null,
            ];

            $start = $window['from'] + $index + 1;
        }

        return $bands;
    }

    /**
     * What one minute of this resource's day is.
     *
     * In order of how final it is: shut, then closed off, then full, then
     * free. A room that is closed AND blocked is closed — telling somebody
     * it is under maintenance at nine at night sends them looking for a
     * problem that is only a clock.
     *
     * @param  array<int, int>  $taken
     * @param  array{from: int, until: int, closed: bool}  $window
     * @param  array{from: int, until: int}|null  $open
     * @param  array<int, array{from: int, to: int, label: ?string, reason: ?string}>  $blocked
     */
    private static function stateAt(array $taken, array $window, ?array $open, array $blocked, int $capacity, int $minute): string
    {
        if ($open === null || $minute < $open['from'] || $minute >= $open['until']) {
            return self::CLOSED;
        }

        foreach ($blocked as $band) {
            if ($minute >= $band['from'] && $minute < $band['to']) {
                return self::BLOCKED;
            }
        }

        return ($taken[$minute - $window['from']] ?? 0) >= $capacity
            ? self::BOOKED
            : self::AVAILABLE;
    }

    /** The reason a block gives, for the band that draws it. */
    private static function blockLabel(array $blocked, int $minute): ?string
    {
        foreach ($blocked as $band) {
            if ($minute >= $band['from'] && $minute < $band['to']) {
                return $band['label'];
            }
        }

        return null;
    }

    /**
     * Where the resource stands at this moment, on a day that has one.
     *
     * @param  array<int, int>  $taken
     * @param  array{from: int, until: int, closed: bool}  $window
     * @param  array{from: int, until: int}|null  $open
     * @param  array<int, array{from: int, to: int, label: ?string, reason: ?string}>  $blocked
     */
    private static function statusAt(array $taken, array $window, ?array $open, array $blocked, int $capacity, ?int $now): ?string
    {
        /* Null, not "available". "Available now" said about last Tuesday is
           not a cautious answer, it is a wrong one — and a receptionist who
           reads it once and finds it false stops trusting the column. What
           a past or future day has to say about a room is how much of it was
           booked, and the screen says that instead. */
        if ($now === null) {
            return null;
        }

        $state = self::stateAt($taken, $window, $open, $blocked, $capacity, $now);

        return $state === self::BOOKED ? 'in-use' : $state;
    }

    /**
     * The appointment in the room at this moment, if there is one.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    private static function currentAt(array $blocks, ?int $now): ?array
    {
        if ($now === null) {
            return null;
        }

        foreach ($blocks as $block) {
            if ($block['kind'] === self::BOOKED && $now >= $block['from'] && $now < $block['to']) {
                return $block;
            }
        }

        return null;
    }

    /**
     * The next appointment after a moment.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    private static function nextAfter(array $blocks, int $moment): ?array
    {
        foreach ($blocks as $block) {
            if ($block['kind'] === self::BOOKED && $block['from'] >= $moment) {
                return $block;
            }
        }

        return null;
    }

    /**
     * How long the room stays free from now — the receptionist's question.
     *
     * Null where it is not free at all, and null where it is free until it
     * closes: "available until 6pm" on a room that has nothing in it all
     * afternoon reads as a booking that is not there.
     *
     * @param  array<int, int>  $taken
     * @param  array{from: int, until: int, closed: bool}  $window
     * @param  array{from: int, until: int}|null  $open
     */
    private static function freeUntil(array $taken, array $window, ?array $open, int $capacity, ?int $now): ?int
    {
        if ($now === null || $open === null || $now < $open['from'] || $now >= $open['until']) {
            return null;
        }

        if (($taken[$now - $window['from']] ?? 0) >= $capacity) {
            return null;
        }

        for ($minute = $now; $minute < $open['until']; $minute++) {
            if (($taken[$minute - $window['from']] ?? 0) >= $capacity) {
                return $minute;
            }
        }

        return null;
    }

    // -------------------------------------------------------------- the day

    /**
     * The hours the chart covers.
     *
     * Rounded outwards to whole hours so the grid lines land on the hour: a
     * business opening at half past nine gets a nine o'clock column, not a
     * ragged left edge.
     *
     * @param  Collection<int, resource>  $resources
     * @param  Collection<int, Location>  $locations
     * @return array{from: int, until: int, closed: bool}
     */
    private static function window(Collection $resources, Collection $locations, Carbon $day): array
    {
        $from = null;
        $until = null;

        foreach ($resources as $resource) {
            $open = self::openWindow($resource, $locations->get($resource->location_id), $day);

            if ($open === null) {
                continue;
            }

            $from = $from === null ? $open['from'] : min($from, $open['from']);
            $until = $until === null ? $open['until'] : max($until, $open['until']);
        }

        if ($from === null || $until === null || $until <= $from) {
            /* Nothing open. The chart still has to have a width, and a
               working day is the least misleading thing to draw an empty
               one across. */
            return ['from' => 9 * 60, 'until' => 18 * 60, 'closed' => true];
        }

        return [
            'from' => intdiv($from, 60) * 60,
            'until' => (int) ceil($until / 60) * 60,
            'closed' => false,
        ];
    }

    /**
     * When this resource itself is open on this day.
     *
     * Its own hours where it keeps them, its branch's where it does not — a
     * chair on the salon floor is open when the salon is, and a treatment
     * room that only runs mornings says so itself.
     *
     * @return array{from: int, until: int}|null
     */
    private static function openWindow(Resource $resource, ?Location $location, Carbon $day): ?array
    {
        if ($resource->hours->isNotEmpty()) {
            $hours = $resource->hours
                ->where('is_available', true)
                ->where('day', (int) $day->dayOfWeek);

            $from = self::minutes($hours->min('starts_at'));
            $until = self::minutes($hours->max('ends_at'));

            return $from === null || $until === null || $until <= $from
                ? null
                : ['from' => $from, 'until' => $until];
        }

        if ($location === null) {
            return null;
        }

        $shut = LocationClosure::query()
            ->where('location_id', $location->id)
            ->covering($day)
            ->where('is_closed_all_day', true)
            ->exists();

        if ($shut) {
            return null;
        }

        $hours = $location->hours
            ->where('day_of_week', (int) $day->dayOfWeek)
            ->where('is_open', true);

        $from = self::minutes($hours->min('opens_at'));
        $until = self::minutes($hours->max('closes_at'));

        return $from === null || $until === null || $until <= $from
            ? null
            : ['from' => $from, 'until' => $until];
    }

    /**
     * The service lines booked into these resources on this day.
     *
     * The service model comes with each line because the occupancy window is
     * computed from it. Loaded once for the whole day rather than per line:
     * a busy salon is two hundred lines and this would otherwise be two
     * hundred queries.
     *
     * @param  array<int, int>  $resourceIds
     * @return Collection<int, Collection<int, object>>
     */
    private static function linesOn(array $resourceIds, Carbon $day): Collection
    {
        if ($resourceIds === []) {
            return collect();
        }

        return BookingService::query()
            ->whereIn('resource_id', $resourceIds)
            ->whereHas('booking', fn ($query) => $query
                ->whereDate('date', $day->toDateString())
                ->whereNotIn('status', self::IGNORED))
            ->with([
                'booking:id,reference,date,starts_at,ends_at,status,client_id,staff_id,guest_name',
                'booking.client:id,first_name,last_name',
                'booking.staff:id,first_name,last_name',
                'service:id,preparation_minutes,processing_minutes,cleanup_minutes,buffer_minutes,duration_minutes',
            ])
            ->get()
            ->map(fn (BookingService $line) => (object) [
                'resource_id' => (int) $line->resource_id,
                'booking_id' => (int) $line->booking_id,
                'reference' => $line->booking?->reference,
                'starts_at' => $line->booking?->startsAt(),
                'status' => $line->booking?->status,
                'name' => $line->name,
                'minutes' => (int) $line->minutes,
                'client_name' => $line->booking?->clientName(),
                'staff_name' => $line->booking?->staff?->displayName() ?? __('bookings.any_staff'),
                'serviceModel' => $line->service,
            ])
            ->groupBy('resource_id');
    }

    /**
     * The maintenance and cleaning periods covering this day.
     *
     * @param  array<int, int>  $resourceIds
     * @return Collection<int, Collection<int, ResourceBlock>>
     */
    private static function blocksOn(array $resourceIds, Carbon $day): Collection
    {
        if ($resourceIds === []) {
            return collect();
        }

        return ResourceBlock::query()
            ->whereIn('resource_id', $resourceIds)
            ->where('starts_at', '<', $day->copy()->endOfDay())
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $day->copy()->startOfDay()))
            ->get()
            ->groupBy('resource_id');
    }

    /**
     * Blocks as minutes of this day, clipped to it.
     *
     * A block running from Monday to Friday covers the whole of Wednesday —
     * it is one row in the table and a full-width band on each day it
     * touches.
     *
     * @param  Collection<int, ResourceBlock>  $blocks
     * @return array<int, array{from: int, to: int, label: ?string, reason: ?string}>
     */
    private static function blockBands(Collection $blocks, Carbon $day): array
    {
        return $blocks
            ->map(function (ResourceBlock $block) use ($day) {
                $start = $block->starts_at->copy()->max($day->copy()->startOfDay());
                /* No end is "until further notice", which covers the rest of
                   whatever day is being looked at. */
                $end = ($block->ends_at?->copy() ?? $day->copy()->endOfDay())
                    ->min($day->copy()->endOfDay());

                return [
                    'from' => (int) $start->format('H') * 60 + (int) $start->format('i'),
                    'to' => (int) $end->format('H') * 60 + (int) $end->format('i'),
                    'label' => $block->reasonLabel(),
                    'reason' => $block->reason,
                ];
            })
            ->filter(fn (array $band) => $band['to'] > $band['from'])
            ->values()
            ->all();
    }

    /**
     * The four figures above the chart.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private static function summary(array $rows): array
    {
        $rows = collect($rows);

        return [
            'total' => $rows->count(),
            'available' => $rows->where('status', self::AVAILABLE)->count(),
            'in_use' => $rows->where('status', 'in-use')->count(),
            'blocked' => $rows->where('status', self::BLOCKED)->count(),
            'closed' => $rows->where('status', self::CLOSED)->count(),

            /* What a day that is not today can answer: how many appointments
               it holds, and how many rooms had time closed off in it. */
            'bookings' => (int) $rows->sum(fn (array $row) => collect($row['blocks'])
                ->where('kind', self::BOOKED)->count()),
            'blocked_rows' => $rows->filter(fn (array $row) => collect($row['bands'])
                ->contains('status', self::BLOCKED))->count(),
            'utilization' => ResourceUtilization::percentage(
                (int) $rows->sum('used_minutes'),
                (int) $rows->sum(fn (array $row) => $row['open_minutes'] * $row['capacity']),
            ),
        ];
    }

    // ------------------------------------------------------------ arithmetic

    /**
     * The hour marks the chart draws its grid on.
     *
     * @param  array{from: int, until: int, closed: bool}  $window
     * @return array<int, array{minutes: int, label: string}>
     */
    private static function hourMarks(array $window): array
    {
        $marks = [];

        for ($minute = $window['from']; $minute <= $window['until']; $minute += 60) {
            $marks[] = [
                'minutes' => $minute,
                'label' => TimeFormat::time(self::clock($minute)),
            ];
        }

        return $marks;
    }

    /**
     * Mark a stretch of the day as taken.
     *
     * Clipped to the chart on purpose: an appointment whose preparation
     * starts before opening is real, and the minutes before the chart begins
     * are simply not drawn.
     *
     * @param  array<int, int>  $taken
     * @param  array{from: int, until: int, closed: bool}  $window
     */
    private static function paint(array &$taken, array $window, int $from, int $to, int $weight): void
    {
        $start = max($window['from'], $from);
        $end = min($window['until'], $to);

        for ($minute = $start; $minute < $end; $minute++) {
            $taken[$minute - $window['from']] = ($taken[$minute - $window['from']] ?? 0) + $weight;
        }
    }

    /** "HH:MM" from minutes past midnight. */
    private static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    /** Minutes past midnight from "HH:MM", or nothing where there is no time. */
    private static function minutes(?string $time): ?int
    {
        if (blank($time) || ! str_contains($time, ':')) {
            return null;
        }

        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }
}
