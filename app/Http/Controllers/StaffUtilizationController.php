<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Role;
use App\Models\Staff;
use App\Support\Money;
use App\Support\SalesPeriod;
use App\Support\StaffUtilization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * How much of each person's bookable day is actually booked.
 *
 * A reading of the rota rather than a thing done to it, so it is governed by
 * `staff.view` — and by its SCOPE, which is what makes this screen safe to
 * give a service provider. Somebody who may see only their own record sees
 * only their own figure; a manager sees their branch; an owner sees the team.
 * The narrowing is done in the query rather than in the browser, because the
 * query is the only place it cannot be undone by editing a URL.
 *
 * Money is a second authority again. Seeing that somebody was busy and seeing
 * what they earned are different questions, and a business can perfectly well
 * want its receptionist to have the first and not the second — so revenue is
 * stripped from every payload unless `sales.view_staff_revenue` says
 * otherwise.
 */
class StaffUtilizationController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request);

        $period = $this->period($request);
        $rows = $this->rowsFor($request, $period);

        return view('staff.utilization', [
            'range' => $period->preset,
            'presets' => SalesPeriod::STAFF,
            'from' => $period->from->toDateString(),
            'until' => $period->to->toDateString(),
            'locationId' => $request->integer('location') ?: null,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'staff' => $rows,
            /* Only where the window IS one day. A timeline of a fortnight is
               a picture of nothing. */
            'day' => $this->teamDay($period, $rows),
            'target' => StaffUtilization::target(),
            'canSeeRevenue' => $this->canSeeRevenue($request),
            'scheduleUrl' => route('staff.schedules'),
        ]);
    }

    /**
     * The same figures again, for a filter change.
     *
     * The page re-asks rather than filtering in the browser: a date range is
     * a different question of the rota, not a subset of the answer already on
     * screen. The role and branch chips ARE a subset, and are filtered in the
     * browser — which is what lets the bubbles glide rather than blink.
     */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request);

        $period = $this->period($request);
        $rows = $this->rowsFor($request, $period);

        return response()->json([
            'from' => $period->from->toDateString(),
            'until' => $period->to->toDateString(),
            'staff' => $rows,
            'day' => $this->teamDay($period, $rows),
        ]);
    }

    /**
     * The same figures as rows, for the listing grid.
     *
     * The app's own grid draws this list — the one behind the staff, client
     * and service listings — so it pages, sorts and looks like every other
     * table in StyleDesk. Which means the server does the narrowing, as it
     * does for those: the query is what knows which rows this reader may see.
     */
    public function rows(Request $request): JsonResponse
    {
        $this->allow($request);

        $period = $this->period($request);
        $money = $this->canSeeRevenue($request);
        $page = max(1, $request->integer('page', 1));
        $size = min(200, max(1, $request->integer('size', 100)));

        $rows = collect($this->rowsFor($request, $period))
            ->when($request->filled('status'),
                fn ($rows) => $rows->where('status', $request->string('status')->value()))
            ->when($request->filled('role'),
                fn ($rows) => $rows->where('role_id', $request->integer('role')))
            ->when($request->filled('search'), function ($rows) use ($request) {
                $term = mb_strtolower($request->string('search')->value());

                return $rows->filter(fn (array $row) => str_contains(
                    mb_strtolower(implode(' ', array_filter([$row['name'], $row['role'], $row['location']]))),
                    $term,
                ));
            })
            /* Busiest first: the question this screen is opened with is who
               is working hardest and who has room, and the answer should be
               the first row rather than something to sort for. */
            ->sortByDesc('utilization')
            ->values();

        return response()->json([
            'total' => $rows->count(),
            'last_page' => (int) max(1, ceil($rows->count() / $size)),
            'data' => $rows->forPage($page, $size)->values()->map(fn (array $row) => array_filter([
                'id' => $row['id'],
                'name' => $row['name'],
                'primary_badge' => $row['role'],
                'location' => $row['location'],
                'scheduled' => $this->hours($row['scheduled_minutes']),
                'bookable' => $this->hours($row['available_minutes']),
                'booked' => $this->hours($row['booked_minutes']),
                'idle' => $this->hours($row['idle_minutes']),
                'bookings' => (string) $row['bookings'],
                'utilization' => $row['utilization'].'%',
                'target' => $row['target'].'%',
                'variance' => $this->variance($row),
                'revenue' => $money ? Money::format($row['revenue_minor'] / 100) : null,
                'status' => __('staff.utilization.statuses.'.$row['status']),
                'status_class' => match ($row['status']) {
                    'high' => 'styledesk_badge--attention',
                    'on_target' => 'styledesk_badge--active',
                    'near_target' => 'styledesk_badge--info',
                    'low', 'very_low' => 'styledesk_badge--setup',
                    default => 'styledesk_badge--soon',
                },
            ], fn ($value) => $value !== null))->all(),
        ]);
    }

    /** One person, in the detail the drill-down shows. */
    public function show(Request $request, Staff $staff): JsonResponse
    {
        $this->allow($request);

        /* The scope again, on one record. Without this, somebody who may see
           only themselves could read a colleague's day by changing the id. */
        abort_unless(
            $this->scoped($request)->whereKey($staff->id)->exists(),
            403,
        );

        return response()->json(StaffUtilization::detail(
            $staff,
            $this->period($request)->from,
            $this->period($request)->to,
            $this->canSeeRevenue($request),
        ));
    }

    // ------------------------------------------------------------- the parts

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(Request $request, SalesPeriod $period): array
    {
        $rows = StaffUtilization::forRange($period->from, $period->to, $this->scoped($request));

        if ($this->canSeeRevenue($request)) {
            return $rows;
        }

        return array_map(fn (array $row) => Arr::except($row, ['revenue_minor']), $rows);
    }

    /**
     * The team's day, but only when the window is one.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function teamDay(SalesPeriod $period, array $rows): ?array
    {
        if (! $period->from->isSameDay($period->to) || $rows === []) {
            return null;
        }

        return StaffUtilization::teamDay($period->from, $rows);
    }

    /**
     * The staff this reader may see, as a query rather than as a filter.
     *
     * `own` is the case that matters: a service provider opening this screen
     * gets their own figure and nobody else's, which is what makes it safe to
     * put in front of them at all. Their own staff record is found through
     * the user account rather than through a name — a business with two
     * Sarahs must not show one of them the other's day.
     *
     * @return Builder<Staff>
     */
    private function scoped(Request $request): Builder
    {
        $user = $request->user();
        $query = Staff::query()
            ->when($request->integer('location'), fn ($q, $id) => $q->where('location_id', $id));

        if ($user->hasPermission('staff.view', 'all')) {
            return $query;
        }

        if ($user->hasPermission('staff.view', 'location')) {
            $branch = Staff::query()->where('user_id', $user->id)->value('location_id');

            /* A manager with no branch of their own is not a manager of every
               branch. Nothing rather than everything. */
            return $query->where('location_id', $branch ?? 0);
        }

        return $query->where('user_id', $user->id);
    }

    /**
     * Whether this reader may see what the team earned.
     *
     * Its own permission, and deliberately a sales one: being allowed to see
     * the rota is not being allowed to see the takings.
     */
    private function canSeeRevenue(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('sales.view_staff_revenue', 'location');
    }

    /** "+7%", "−12%", or "on target". */
    private function variance(array $row): string
    {
        if ($row['available_minutes'] <= 0) {
            return '—';
        }

        return match (true) {
            $row['variance'] > 0 => '+'.$row['variance'].'%',
            $row['variance'] < 0 => '−'.abs($row['variance']).'%',
            default => __('staff.utilization.on_target'),
        };
    }

    /** Hours, as a person says them. */
    private function hours(int $minutes): string
    {
        return (string) round($minutes / 60, 1);
    }

    /**
     * The window being asked about.
     *
     * The app's own date-range control, not one invented here. Which presets
     * this screen OFFERS is its own business — a rota is read forwards as
     * well as back, which is why "tomorrow" is on this list and on no other —
     * but working out what a preset means is not.
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
        abort_unless($request->user()?->hasPermission('staff.view', 'own'), 403);
    }
}
