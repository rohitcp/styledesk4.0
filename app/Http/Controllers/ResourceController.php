<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\ResourceCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The resources a business books alongside its people.
 *
 * Chairs, rooms and equipment: the things an appointment needs that are not
 * the stylist. They live here rather than in App Settings because they are
 * worked with daily — a room goes out for repair on a Tuesday morning — where
 * settings are decided once and revisited rarely.
 */
class ResourceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeResources($request, 'resources.view');

        $filters = [
            'search' => trim((string) $request->query('search')),
            'category' => $request->query('category'),
            'location' => $request->query('location'),
            'status' => $request->query('status'),
        ];

        $resources = Resource::query()
            ->with(['category', 'location', 'blocks'])
            ->matching($filters['search'])
            ->when($filters['category'], fn (Builder $q, $id) => $q->where('resource_category_id', $id))
            ->when($filters['location'], fn (Builder $q, $id) => $q->where('location_id', $id))
            ->when($filters['status'] === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->inOrder()
            ->get();

        /**
         * Availability is worked out per row rather than in the query: a
         * block is a period, and "is it blocked now" is a question about the
         * clock, not a column SQL can filter on without reproducing the rule.
         */
        if ($filters['status'] === 'blocked') {
            $resources = $resources->filter(fn (Resource $resource) => $resource->availabilityStatus() === 'blocked');
        }

        if ($filters['status'] === 'available') {
            $resources = $resources->filter(fn (Resource $resource) => $resource->availabilityStatus() === 'available');
        }

        return view('resources.index', [
            'resources' => $resources->values(),
            /* Grouped by category, because that is how a business thinks
               about them: four styling chairs, two treatment rooms. */
            'grouped' => $resources->groupBy(fn (Resource $resource) => $resource->category?->name ?? __('common.none')),
            'categories' => ResourceCategory::query()->inOrder()->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'filters' => $filters,
            'total' => Resource::query()->count(),
            'canEdit' => $request->user()->hasPermission('resources.edit', 'all'),
            'canCreate' => $request->user()->hasPermission('resources.create'),
            'canDelete' => $request->user()->hasPermission('resources.delete'),
            'canBlock' => $request->user()->hasPermission('resources.manage_availability'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeResources($request, 'resources.create');

        $data = $this->validated($request);

        Resource::create($data + ['tenant_id' => $request->user()->tenant->getTenantKey()]);

        return back()->with('toast', ['type' => 'success', 'message' => __('resources.added')]);
    }

    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorizeResources($request, 'resources.edit', $resource);

        $resource->forceFill($this->validated($request))->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('resources.updated')]);
    }

    /**
     * Retire or bring back.
     *
     * Never a delete: a chair that is gone still appears in last year's
     * appointments, and removing the row would rewrite them.
     */
    public function toggle(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorizeResources($request, 'resources.edit', $resource);

        $resource->forceFill(['is_active' => ! $resource->is_active])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $resource->is_active ? __('resources.activated') : __('resources.deactivated'),
        ]);
    }

    /**
     * Take a resource out of service for a while.
     *
     * A row with a start and an end rather than a flag, so the calendar can
     * draw it and so it clears itself when the repair is done.
     */
    public function block(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorizeResources($request, 'resources.manage_availability', $resource);

        $data = $request->validate([
            'reason' => ['required', Rule::in(config('resources.block_reasons'))],
            'note' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            /* After the start, or the block covers nothing. Nullable is
               "until further notice", which is a real answer. */
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $resource->blocks()->create($data + [
            'tenant_id' => $resource->tenant_id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('resources.blocked')]);
    }

    /** End a block early, or remove one added by mistake. */
    public function unblock(Request $request, Resource $resource, ResourceBlock $block): RedirectResponse
    {
        $this->authorizeResources($request, 'resources.manage_availability', $resource);

        abort_unless($block->resource_id === $resource->id, 404);

        $block->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('resources.unblocked')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'resource_category_id' => ['nullable', Rule::exists('resource_categories', 'id')],
            'location_id' => ['nullable', Rule::exists('locations', 'id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:'.config('resources.max_capacity')],
        ]);
    }

    /**
     * Permission, then ownership.
     *
     * The category and location lists are tenant-scoped by their models, but
     * a resource reached by id is not — so the second check is what stops one
     * business editing another's chair by guessing a number.
     */
    private function authorizeResources(Request $request, string $permission, ?Resource $resource = null): void
    {
        abort_unless($request->user()->hasPermission($permission, 'all'), 403);

        if ($resource !== null) {
            abort_unless(
                (string) $resource->tenant_id === (string) $request->user()->tenant?->getTenantKey(),
                404,
            );
        }
    }
}
