<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Location;
use App\Models\LocationClosure;
use App\Models\Resource;
use App\Models\ResourceBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How much of the day each room and chair was actually working.
 *
 * One question, asked the way an owner asks it: the place was open for so
 * many hours, this room was in use for so many of them, and the rest it sat
 * empty. Everything on the screen is that sentence — the percentage, the
 * bubble's size, the timeline — so there is one arithmetic here rather than
 * one per view that can disagree.
 *
 *     used ÷ available × 100
 *
 * `available` is the hours the resource was OPEN, times how many clients it
 * holds at once. A couple room that seats two and stands open eight hours has
 * sixteen resource-hours to sell; one booking filling half of them is half
 * used, which is the honest reading and not what a per-room clock would say.
 *
 * `used` is the minutes of appointments booked into it. Read from
 * `booking_services`, not from the booking's own `resource_id`: an
 * appointment of a massage then a facial is two rooms at two times, and the
 * booking header holds only the principal one.
 *
 * What does not count as used:
 *
 *  - Cancelled, declined, no-show and draft bookings. A room nobody came to
 *    was empty, whatever the diary once said.
 *  - Blocked time — maintenance, deep cleaning. It is reported beside the
 *    figures rather than inside them, because "closed for cleaning" and "open
 *    and nobody booked it" are different problems with different answers, and
 *    folding them together tells the owner neither.
 */
class ResourceUtilization
{
    /** Bookings that never happened do not fill a room. */
    private const IGNORED = ['cancelled', 'declined', 'no-show', 'draft'];

    /**
     * Every resource over a window, ready for the bubbles and the table.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forRange(Carbon $from, Carbon $until, int|string|null $locationId = null): array
    {
        $resources = Resource::query()
            ->with(['category', 'location', 'hours'])
            ->active()
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->inOrder()
            ->get();

        if ($resources->isEmpty()) {
            return [];
        }

        $days = self::days($from, $until);
        $used = self::usedMinutes($resources->pluck('id'), $from, $until);
        $blocked = self::blockedMinutes($resources->pluck('id'), $from, $until);
        $locations = Location::query()->with('hours')->get()->keyBy('id');

        return $resources
            ->map(function (Resource $resource) use ($days, $used, $blocked, $locations) {
                $open = self::openMinutes($resource, $days, $locations->get($resource->location_id));
                $capacity = max(1, (int) $resource->capacity);
                $available = $open * $capacity;
                $usedMinutes = (int) ($used[$resource->id]['minutes'] ?? 0);

                return [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'code' => $resource->code,
                    'category' => $resource->category?->name,
                    'category_id' => $resource->resource_category_id,
                    'group' => self::groupOf($resource),
                    'location' => $resource->location?->name,
                    'location_id' => $resource->location_id,
                    'capacity' => $capacity,

                    /* The three numbers the labels are written from. Minutes
                       here, hours at the edge: an hour is what the owner
                       thinks in and a minute is what divides cleanly. */
                    'open_minutes' => $open,
                    'available_minutes' => $available,
                    'used_minutes' => $usedMinutes,
                    'idle_minutes' => max(0, $available - $usedMinutes),
                    'blocked_minutes' => (int) ($blocked[$resource->id] ?? 0),

                    'utilization' => self::percentage($usedMinutes, $available),
                    'bookings' => (int) ($used[$resource->id]['bookings'] ?? 0),
                    'revenue_minor' => (int) ($used[$resource->id]['revenue_minor'] ?? 0),
                    'status' => self::status(self::percentage($usedMinutes, $available), $available),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * One resource, in the detail the drill-down shows.
     *
     * @return array<string, mixed>
     */
    public static function detail(Resource $resource, Carbon $from, Carbon $until): array
    {
        $summary = collect(self::forRange($from, $until, null))
            ->firstWhere('id', $resource->id) ?? [];

        return $summary + [
            'daily' => self::daily($resource, $from, $until),
            'by_hour' => self::byHour($resource, $from, $until),
            'services' => self::services($resource, $from, $until),
            'timeline' => self::timeline($resource, $from, $until),
        ];
    }

    // ------------------------------------------------------------- the parts

    /**
     * Minutes of appointments per resource, with what they were worth.
     *
     * @param  Collection<int, int>  $resourceIds
     * @return array<int, array{minutes: int, bookings: int, revenue_minor: int}>
     */
    private static function usedMinutes(Collection $resourceIds, Carbon $from, Carbon $until): array
    {
        return self::lines($resourceIds, $from, $until)
            ->groupBy('resource_id')
            ->map(fn (Collection $lines) => [
                'minutes' => (int) $lines->sum('minutes'),
                /* Appointments, not lines: a booking of three services in one
                   room is one visit, and counting it as three would tell the
                   owner the room is busier than it is. */
                'bookings' => $lines->pluck('booking_id')->unique()->count(),
                'revenue_minor' => (int) $lines->sum('price_minor'),
            ])
            ->all();
    }

    /**
     * The service lines booked into these resources over the window.
     *
     * @param  Collection<int, int>  $resourceIds
     * @return Collection<int, object>
     */
    private static function lines(Collection $resourceIds, Carbon $from, Carbon $until): Collection
    {
        return Booking::query()
            ->whereNotIn('bookings.status', self::IGNORED)
            ->whereBetween('bookings.date', [$from->toDateString(), $until->toDateString()])
            ->join('booking_services', 'booking_services.booking_id', '=', 'bookings.id')
            ->whereIn('booking_services.resource_id', $resourceIds)
            ->get([
                'booking_services.resource_id',
                'booking_services.booking_id',
                'booking_services.name',
                'booking_services.minutes',
                'booking_services.price_minor',
                'booking_services.service_id',
                'bookings.date',
                'bookings.starts_at',
                'bookings.status',
                'bookings.reference',
                'bookings.client_id',
            ]);
    }

    /**
     * Minutes each resource was closed off, per resource.
     *
     * @param  Collection<int, int>  $resourceIds
     * @return array<int, int>
     */
    private static function blockedMinutes(Collection $resourceIds, Carbon $from, Carbon $until): array
    {
        return self::blocks($resourceIds, $from, $until)
            ->groupBy('resource_id')
            ->map(fn (Collection $blocks) => (int) $blocks->sum(
                fn (ResourceBlock $block) => self::overlapMinutes(
                    Carbon::parse($block->starts_at),
                    Carbon::parse($block->ends_at),
                    $from->copy()->startOfDay(),
                    $until->copy()->endOfDay(),
                ),
            ))
            ->all();
    }

    /**
     * @param  Collection<int, int>  $resourceIds
     * @return Collection<int, ResourceBlock>
     */
    private static function blocks(Collection $resourceIds, Carbon $from, Carbon $until): Collection
    {
        return ResourceBlock::query()
            ->whereIn('resource_id', $resourceIds)
            ->where('starts_at', '<', $until->copy()->endOfDay())
            ->where('ends_at', '>', $from->copy()->startOfDay())
            ->get();
    }

    /**
     * How long this resource stands open across the window.
     *
     * Its own hours where it keeps them, the branch's where it does not — a
     * chair on the salon floor is open when the salon is, and a treatment
     * room that only runs mornings says so itself.
     *
     * @param  Collection<int, Carbon>  $days
     */
    private static function openMinutes(Resource $resource, Collection $days, ?Location $location): int
    {
        $own = $resource->hours->where('is_available', true)->groupBy('day');

        return (int) $days->sum(function (Carbon $day) use ($own, $location, $resource) {
            if ($resource->hours->isNotEmpty()) {
                return $own->get((int) $day->dayOfWeek, collect())
                    ->sum(fn ($hour) => self::span($hour->starts_at, $hour->ends_at));
            }

            return $location ? self::locationMinutes($location, $day) : 0;
        });
    }

    /** A branch's open minutes on one day, short days included. */
    private static function locationMinutes(Location $location, Carbon $day): int
    {
        $closedAllDay = LocationClosure::query()
            ->where('location_id', $location->id)
            ->covering($day)
            ->where('is_closed_all_day', true)
            ->exists();

        if ($closedAllDay) {
            return 0;
        }

        return (int) $location->hours
            ->where('day_of_week', (int) $day->dayOfWeek)
            ->where('is_open', true)
            ->sum(fn ($hour) => self::span($hour->opens_at, $hour->closes_at));
    }

    /** @return Collection<int, Carbon> */
    private static function days(Carbon $from, Carbon $until): Collection
    {
        $days = collect();

        for ($day = $from->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            $days->push($day->copy());
        }

        return $days;
    }

    // --------------------------------------------------------- the drill-down

    /**
     * Utilization day by day, for the trend line.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function daily(Resource $resource, Carbon $from, Carbon $until): array
    {
        $lines = self::lines(collect([$resource->id]), $from, $until)->groupBy(
            fn ($line) => Carbon::parse($line->date)->toDateString(),
        );

        $location = $resource->location()->with('hours')->first();
        $capacity = max(1, (int) $resource->capacity);

        return self::days($from, $until)
            ->map(function (Carbon $day) use ($lines, $resource, $location, $capacity) {
                $open = self::openMinutes($resource, collect([$day]), $location);
                $used = (int) ($lines->get($day->toDateString())?->sum('minutes') ?? 0);

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->isoFormat('D MMM'),
                    'weekday' => $day->isoFormat('ddd'),
                    'open_minutes' => $open,
                    'used_minutes' => $used,
                    'utilization' => self::percentage($used, $open * $capacity),
                ];
            })
            ->all();
    }

    /**
     * When in the day it is busy, hour by hour.
     *
     * The question behind it is not "how full is this room" but "should the
     * six o'clock slot exist at all" — and a total cannot answer that.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function byHour(Resource $resource, Carbon $from, Carbon $until): array
    {
        $minutes = array_fill(0, 24, 0);

        foreach (self::lines(collect([$resource->id]), $from, $until) as $line) {
            $start = self::startOf($line);
            $end = $start->copy()->addMinutes((int) $line->minutes);

            for ($hour = 0; $hour < 24; $hour++) {
                $minutes[$hour] += self::overlapMinutes(
                    $start,
                    $end,
                    $start->copy()->startOfDay()->addHours($hour),
                    $start->copy()->startOfDay()->addHours($hour + 1),
                );
            }
        }

        return collect($minutes)
            ->map(fn (int $value, int $hour) => [
                'hour' => $hour,
                'label' => TimeFormat::time(str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00'),
                'minutes' => $value,
            ])
            ->values()
            ->all();
    }

    /**
     * What was actually done in here, most minutes first.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function services(Resource $resource, Carbon $from, Carbon $until): array
    {
        return self::lines(collect([$resource->id]), $from, $until)
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
     * One day's worth of blocks, so the owner can see exactly when the room
     * was working and when it stood empty.
     *
     * The last day of the window, which is the one somebody drilling in is
     * asking about: "today" on a today filter, and the closing day of a range
     * otherwise.
     *
     * @return array<string, mixed>
     */
    private static function timeline(Resource $resource, Carbon $from, Carbon $until): array
    {
        $day = $until->copy()->startOfDay();
        $location = $resource->location()->with('hours')->first();

        $appointments = self::lines(collect([$resource->id]), $day, $day)
            ->map(function ($line) {
                $start = self::startOf($line);

                return [
                    'kind' => 'booking',
                    'from' => $start->format('H:i'),
                    'to' => $start->copy()->addMinutes((int) $line->minutes)->format('H:i'),
                    'from_minutes' => ((int) $start->format('H')) * 60 + (int) $start->format('i'),
                    'minutes' => (int) $line->minutes,
                    'label' => $line->name,
                    'reference' => $line->reference,
                    'revenue_minor' => (int) $line->price_minor,
                ];
            });

        $closures = self::blocks(collect([$resource->id]), $day, $day)
            ->map(function (ResourceBlock $block) use ($day) {
                $start = Carbon::parse($block->starts_at)->max($day->copy()->startOfDay());
                $end = Carbon::parse($block->ends_at)->min($day->copy()->endOfDay());

                return [
                    'kind' => 'blocked',
                    'from' => $start->format('H:i'),
                    'to' => $end->format('H:i'),
                    'from_minutes' => ((int) $start->format('H')) * 60 + (int) $start->format('i'),
                    'minutes' => (int) $start->diffInMinutes($end),
                    'label' => $block->reason ?: $block->note,
                    'reference' => null,
                    'revenue_minor' => 0,
                ];
            });

        return [
            'date' => $day->toDateString(),
            'label' => $day->isoFormat('dddd, D MMMM'),
            'open_minutes' => $location ? self::locationMinutes($location, $day) : 0,
            'opens_at' => self::firstOpening($resource, $location, $day),
            'closes_at' => self::lastClosing($resource, $location, $day),
            'blocks' => $appointments->concat($closures)->sortBy('from_minutes')->values()->all(),
        ];
    }

    // ------------------------------------------------------------- arithmetic

    /**
     * The percentage, rounded and never past a hundred.
     *
     * Capped because the alternative is a room that reads 140% and an owner
     * who stops believing the number. Over-full is a capacity question, and
     * the detail view answers it with the figures.
     */
    public static function percentage(int $used, int $available): int
    {
        if ($available <= 0) {
            return 0;
        }

        return (int) min(100, round($used / $available * 100));
    }

    /**
     * How the bubble reads at a glance.
     *
     * Bands rather than a gradient: an owner is asking "which of these needs
     * doing something about", and three answers are actionable where a
     * continuous scale is only decorative.
     */
    private static function status(int $percentage, int $available): string
    {
        return match (true) {
            $available <= 0 => 'closed',
            $percentage >= 85 => 'busy',
            $percentage >= 40 => 'steady',
            default => 'quiet',
        };
    }

    /** Which chip this resource sits under. */
    private static function groupOf(Resource $resource): string
    {
        $name = strtolower((string) ($resource->category?->name ?? ''));

        return match (true) {
            str_contains($name, 'room') => 'rooms',
            str_contains($name, 'chair'), str_contains($name, 'station') => 'stations',
            str_contains($name, 'bed'), str_contains($name, 'table') => 'beds',
            str_contains($name, 'wellness'), str_contains($name, 'sauna'), str_contains($name, 'spa') => 'wellness',
            str_contains($name, 'equipment'), str_contains($name, 'machine') => 'equipment',
            default => 'other',
        };
    }

    /**
     * When one booked service actually starts.
     *
     * `bookings.date` is cast to a datetime, so it comes back as
     * "2026-09-01 00:00:00" — concatenating the time onto that gives
     * "2026-09-01 00:00:00 09:00:00", which is two times in one string and
     * which Carbon refuses. The date part is taken and the time set onto it.
     */
    private static function startOf(object $line): Carbon
    {
        return Carbon::parse($line->date)->startOfDay()
            ->setTimeFromTimeString((string) $line->starts_at);
    }

    /** Minutes between two "HH:MM" times, or nothing where either is missing. */
    private static function span(?string $from, ?string $to): int
    {
        if (blank($from) || blank($to)) {
            return 0;
        }

        return max(0, self::toMinutes($to) - self::toMinutes($from));
    }

    private static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }

    /** How much of one period falls inside another. */
    private static function overlapMinutes(Carbon $from, Carbon $to, Carbon $windowFrom, Carbon $windowTo): int
    {
        $start = $from->max($windowFrom);
        $end = $to->min($windowTo);

        return $end->gt($start) ? (int) $start->diffInMinutes($end) : 0;
    }

    private static function firstOpening(Resource $resource, ?Location $location, Carbon $day): ?string
    {
        if ($resource->hours->isNotEmpty()) {
            return $resource->hours->where('is_available', true)
                ->where('day', (int) $day->dayOfWeek)->min('starts_at');
        }

        return $location?->hours->where('day_of_week', (int) $day->dayOfWeek)
            ->where('is_open', true)->min('opens_at');
    }

    private static function lastClosing(Resource $resource, ?Location $location, Carbon $day): ?string
    {
        if ($resource->hours->isNotEmpty()) {
            return $resource->hours->where('is_available', true)
                ->where('day', (int) $day->dayOfWeek)->max('ends_at');
        }

        return $location?->hours->where('day_of_week', (int) $day->dayOfWeek)
            ->where('is_open', true)->max('closes_at');
    }
}
