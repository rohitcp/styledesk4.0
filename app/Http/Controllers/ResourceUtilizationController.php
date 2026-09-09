<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Resource;
use App\Support\Money;
use App\Support\ResourceUtilization;
use App\Support\SalesPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * How the rooms and chairs are actually being used.
 *
 * A reading of the resources rather than a thing done to them, so it is
 * governed by `resources.view` — somebody who may see the room list may see
 * how busy the rooms are.
 *
 * Its own controller rather than another method on ResourceController: that
 * one maintains the list — what exists, what it is called, when it is free —
 * and this one asks a question about the diary that happens to be grouped by
 * room. They share a table and nothing else.
 */
class ResourceUtilizationController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request);

        $period = $this->period($request);

        return view('resources.utilization', [
            'range' => $period->preset,
            'presets' => SalesPeriod::UTILIZATION,
            'from' => $period->from->toDateString(),
            'until' => $period->to->toDateString(),
            'locationId' => $request->integer('location') ?: null,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'resources' => ResourceUtilization::forRange(
                $period->from, $period->to, $request->integer('location') ?: null,
            ),
        ]);
    }

    /**
     * The same figures again, for a filter change.
     *
     * The page re-asks rather than filtering in the browser: a date range is
     * a different question of the diary, not a subset of the answer already
     * on screen. Category chips ARE a subset, and are filtered in the
     * browser — which is what lets the bubbles glide rather than blink.
     */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request);

        $period = $this->period($request);

        return response()->json([
            'from' => $period->from->toDateString(),
            'until' => $period->to->toDateString(),
            'resources' => ResourceUtilization::forRange(
                $period->from, $period->to, $request->integer('location') ?: null,
            ),
        ]);
    }

    /**
     * The same figures as rows, for the listing grid.
     *
     * The app's own grid draws this list — the one behind the client, service
     * and resource listings — so it pages, sorts and looks like every other
     * table in StyleDesk rather than like a table invented for this screen.
     * Which means the server does the narrowing, as it does for those: the
     * query is what knows which rows this member of staff may see.
     */
    public function rows(Request $request): JsonResponse
    {
        $this->allow($request);

        $period = $this->period($request);
        $page = max(1, $request->integer('page', 1));
        $size = min(200, max(1, $request->integer('size', 100)));

        $rows = collect(ResourceUtilization::forRange(
            $period->from, $period->to, $request->integer('location') ?: null,
        ))
            ->when($request->filled('group') && $request->string('group')->value() !== 'all',
                fn ($rows) => $rows->where('group', $request->string('group')->value()))
            ->when($request->filled('status'),
                fn ($rows) => $rows->where('status', $request->string('status')->value()))
            ->when($request->filled('search'), function ($rows) use ($request) {
                $term = mb_strtolower($request->string('search')->value());

                return $rows->filter(fn (array $row) => str_contains(
                    mb_strtolower(implode(' ', array_filter([
                        $row['name'], $row['code'], $row['category'], $row['location'],
                    ]))),
                    $term,
                ));
            })
            /* Busiest first: the question this screen is opened with is
               which rooms are working hardest, and the answer should be the
               first row rather than something to sort for. */
            ->sortByDesc('utilization')
            ->values();

        return response()->json([
            'total' => $rows->count(),
            'last_page' => (int) max(1, ceil($rows->count() / $size)),
            'data' => $rows->forPage($page, $size)->values()->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'primary_badge' => $row['code'],
                'category' => $row['category'],
                'location' => $row['location'],
                'available' => $this->hours($row['available_minutes']),
                'used' => $this->hours($row['used_minutes']),
                'idle' => $this->hours($row['idle_minutes']),
                'utilization' => $row['utilization'].'%',
                'bookings' => (string) $row['bookings'],
                'revenue' => Money::format($row['revenue_minor'] / 100),
                'per_hour' => Money::format($row['used_minutes'] > 0
                    ? $row['revenue_minor'] / 100 / ($row['used_minutes'] / 60)
                    : 0),
                'status' => __('resources.utilization.statuses.'.$row['status']),
                'status_class' => match ($row['status']) {
                    'busy' => 'styledesk_badge--attention',
                    'steady' => 'styledesk_badge--active',
                    'quiet' => 'styledesk_badge--info',
                    default => 'styledesk_badge--soon',
                },
            ])->all(),
        ]);
    }

    /** Hours, as a person says them. */
    private function hours(int $minutes): string
    {
        return (string) round($minutes / 60, 1);
    }

    /** One room, in the detail the drill-down shows. */
    public function show(Request $request, Resource $resource): JsonResponse
    {
        $this->allow($request);

        $period = $this->period($request);

        return response()->json(ResourceUtilization::detail($resource, $period->from, $period->to));
    }

    /**
     * The window being asked about.
     *
     * The app's own date-range control, not one invented here: the same
     * presets, the same parsing, and the same refusal to query on a range
     * somebody typed backwards. Which presets this screen OFFERS is its own
     * business — an owner asks "is that room busy" about this week, not
     * about this quarter — but working out what a preset means is not.
     *
     * Falls back to today rather than to Sales' month: a utilization board
     * showing a whole month by default answers a question nobody asked on
     * the way in.
     */
    private function period(Request $request): SalesPeriod
    {
        return SalesPeriod::fromRequest(
            $request->string('range')->value(),
            $request->string('from')->value(),
            $request->string('until')->value(),
            'today',
        );
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('resources.view', 'location'), 403);
    }
}
