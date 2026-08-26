<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Role;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The staff directory, §3.
 *
 * Read-only for now: adding, editing and the per-staff screens are the next
 * stage. What is here is the list, its filters and the state of each person,
 * which is the part every other staff screen is reached from.
 */
class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        $filters = $this->filters($request);

        $staff = Staff::query()
            ->with(['roleRecord', 'location', 'user'])
            ->withCount('services')
            ->tap(fn (Builder $q) => $this->applySearch($q, $filters['search']))
            ->tap(fn (Builder $q) => $this->applyFilters($q, $filters))
            ->tap(fn (Builder $q) => $this->applySort($q, $filters['sort']))
            ->get();

        /**
         * Status is derived, so it cannot be filtered in SQL without
         * duplicating the rule in two places. The directory is a page of
         * staff, not a report over millions of rows, so it is filtered in
         * memory where the single definition lives.
         */
        if ($filters['status'] !== null) {
            $staff = $staff->filter(fn (Staff $member) => $member->status() === $filters['status'])->values();
        }

        return view('settings.staff.index', [
            'staff' => $staff,
            'filters' => $filters,
            'roles' => Role::query()->orderBy('display_order')->get(),
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            'activeCount' => $staff->filter(fn (Staff $m) => $m->status() === 'active')->count(),
            'pendingCount' => $staff->filter(fn (Staff $m) => $m->status() === 'pending-invite')->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search')) ?: null,
            'role' => $request->query('role') ?: null,
            'location' => $request->query('location') ?: null,
            'service' => $request->query('service') ?: null,
            'provider_type' => $request->query('provider_type') ?: null,
            'employment_type' => $request->query('employment_type') ?: null,
            'status' => array_key_exists((string) $request->query('status'), config('staff.statuses'))
                ? $request->query('status')
                : null,
            'sort' => array_key_exists((string) $request->query('sort'), config('staff.sorts'))
                ? $request->query('sort')
                : 'name',
        ];
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        // §3: name, email, phone or job title. Grouped so the search does not
        // swallow the filters applied alongside it — an ungrouped chain of
        // orWhere turns every other condition into a suggestion.
        $query->where(function (Builder $q) use ($search) {
            $like = '%'.$search.'%';

            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('preferred_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('work_email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('job_title', 'like', $like);
        });
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['role'], fn (Builder $q, $role) => $q->whereHas('roleRecord', fn (Builder $r) => $r->where('key', $role)))
            ->when($filters['location'], fn (Builder $q, $location) => $q->where('location_id', $location))
            ->when($filters['service'], fn (Builder $q, $service) => $q->whereHas('services', fn (Builder $s) => $s->where('services.id', $service)))
            ->when($filters['provider_type'], fn (Builder $q, $type) => $q->where('provider_type', $type))
            ->when($filters['employment_type'], fn (Builder $q, $type) => $q->where('employment_type', $type));
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'recent' => $query->orderByDesc('created_at'),
            // Ordering by the joined role would need a join; ordering by the
            // id keeps roles of the same kind together, which is what the
            // sort is for.
            'role' => $query->orderBy('role_id')->orderBy('first_name'),
            'location' => $query->orderBy('location_id')->orderBy('first_name'),
            'status' => $query->orderBy('is_active')->orderBy('first_name'),
            default => $query->orderBy('first_name')->orderBy('last_name'),
        };
    }
}
