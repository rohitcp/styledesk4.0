<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\ResourceCategory;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\ResourceCode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        return view('resources.index', $this->formData() + [
            'filters' => $this->filters($request),
            /* Whether the business has any at all, which is a different
               question from whether this search found any: one is an empty
               module and the other is an empty result. */
            'total' => Resource::query()->count(),
            'canCreate' => $request->user()->hasPermission('resources.create'),
            'canBlock' => $request->user()->hasPermission('resources.manage_availability', 'all'),
        ]);
    }

    /**
     * The rows, for the grid.
     *
     * Every value is worded here rather than in the browser: the capacity
     * sentence and the reason a room is out are phrases in the reader's own
     * language, and a grid that assembled them would be a second place for
     * the wording to live.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeResources($request, 'resources.view');

        $filters = $this->filters($request);
        $resources = $this->query($filters);

        $canEdit = $request->user()->hasPermission('resources.edit', 'all');
        $canBlock = $request->user()->hasPermission('resources.manage_availability', 'all');

        $size = min(200, max(1, (int) $request->query('size', 100)));
        $page = max(1, (int) $request->query('page', 1));

        return response()->json([
            'last_page' => max(1, (int) ceil($resources->count() / $size)),
            'last_row' => $resources->count(),
            'total' => $resources->count(),
            'data' => $resources->forPage($page, $size)->map(function (Resource $resource) use ($canEdit, $canBlock) {
                $status = $resource->availabilityStatus();
                $block = $resource->blockAt();

                return [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'color' => $resource->color,
                    'primary_badge' => null,
                    'category' => $resource->category?->name,
                    'location' => $resource->location?->name,
                    'capacity' => $resource->capacity === 1
                        ? __('resources.holds_one')
                        : __('resources.holds_many', ['count' => $resource->capacity]),
                    'description' => $resource->description,

                    /* Why it is out and until when, in the cell that says it
                       is out: "Unavailable" on its own sends the reader to
                       open the record to find out. */
                    'availability' => $block
                        ? $block->reasonLabel().' · '.($block->ends_at
                            ? __('resources.blocked_until', ['date' => $block->ends_at->isoFormat('D MMM Y')])
                            : __('resources.blocked_indefinitely'))
                        : $resource->availabilityLabel(),
                    'availability_class' => match ($status) {
                        'available' => 'styledesk_badge--active',
                        'blocked' => 'styledesk_badge--setup',
                        default => 'styledesk_badge--soon',
                    },

                    'url' => route('resources.show', $resource),
                    'menu' => $this->rowMenu($resource, $block, $canEdit, $canBlock),
                ];
            })->values()->all(),
        ]);
    }

    /**
     * One row's actions.
     *
     * Decided here rather than in the browser: which entries a reader may see
     * is a permission question, and a grid that assembled the menu itself
     * would be a second place for that rule to live.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowMenu(Resource $resource, ?ResourceBlock $block, bool $canEdit, bool $canBlock): array
    {
        $menu = [];

        if ($canEdit) {
            $menu[] = ['label' => __('common.edit'), 'url' => route('resources.edit', $resource)];
        }

        if ($canBlock) {
            $menu[] = $block
                ? [
                    'label' => __('resources.unblock'),
                    'url' => route('resources.unblock', [$resource, $block]),
                    'method' => 'DELETE',
                ]
                : [
                    /* The dialog lives on the listing, because a block is a
                       reason and a period rather than a single decision. The
                       menu announces it and the page opens it — the grid has
                       no business knowing what a resource block is. */
                    'label' => __('resources.block'),
                    'event' => 'resource-block',
                    'payload' => ['id' => $resource->id, 'name' => $resource->name],
                ];
        }

        if ($canEdit) {
            $label = $resource->is_active ? __('resources.retire') : __('resources.restore');

            $menu[] = ['separator' => true];
            $menu[] = [
                'label' => $label,
                'url' => route('resources.toggle', $resource),
                'method' => 'PATCH',
                'danger' => $resource->is_active,
                'confirm' => $resource->is_active
                    ? __('resources.retire_confirm', ['name' => $resource->name])
                    : null,
                'confirm_title' => $label,
                'confirm_label' => $label,
                'tone' => $resource->is_active ? 'danger' : 'brand',
            ];
        }

        return $menu;
    }

    /**
     * What the reader asked to see, from the query string.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search')),
            'category' => $request->query('category'),
            'location' => (array) $request->query('location', []),
            'status' => $request->query('status'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, \App\Models\Resource>
     */
    private function query(array $filters): Collection
    {
        $resources = Resource::query()
            ->with(['category', 'location', 'blocks'])
            ->matching($filters['search'])
            ->when($filters['category'], fn (Builder $q, $id) => $q->where('resource_category_id', $id))
            ->when($filters['location'], fn (Builder $q, array $ids) => $q->whereIn('location_id', $ids))
            ->inOrder()
            ->get();

        /**
         * Availability is worked out per row rather than in the query: a
         * block is a period, and "is it blocked now" is a question about the
         * clock, not a column SQL can filter on without reproducing the rule.
         */
        if (in_array($filters['status'], ['available', 'blocked', 'inactive'], true)) {
            $resources = $resources->filter(
                fn (Resource $resource) => $resource->availabilityStatus() === $filters['status']
            );
        }

        return $resources->values();
    }

    /**
     * The option lists both form pages need.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $categories = ResourceCategory::query()->assignable()->get();

        return [
            'categories' => $categories,
            'categoryOptions' => $this->categoryOptions($categories),
            'locations' => Location::query()->orderBy('name')->get(),
            'services' => Service::query()->active()->inOrder()->get(),
        ];
    }

    /**
     * The category list, each name carrying the section it sits under.
     *
     * The catalogue is thirty entries long and several read alike out of
     * context — "Treatment room" and "Treatment bed" are different kinds of
     * thing. The combo searches the label, so the group is searchable too:
     * typing "room" finds every room.
     *
     * @param  Collection<int, ResourceCategory>  $categories
     * @return array<int|string, string>
     */
    private function categoryOptions(Collection $categories): array
    {
        return $categories
            ->mapWithKeys(fn (ResourceCategory $category) => [
                $category->id => $category->groupLabel()
                    ? $category->groupLabel().' · '.$category->name
                    : $category->name,
            ])
            ->all();
    }

    /**
     * One resource, read-only.
     *
     * Where saving lands, and where the listing's rows open: reading what a
     * resource is costs nothing and risks nothing, where a form is one stray
     * keystroke away from changing a room's capacity.
     */
    public function show(Request $request, Resource $resource): View
    {
        $this->authorizeResources($request, 'resources.view', $resource);

        $resource->load(['category', 'location', 'services', 'hours', 'blocks']);

        return view('resources.show', [
            'resource' => $resource,
            'block' => $resource->blockAt(),
            'canEdit' => $request->user()->hasPermission('resources.edit', 'all'),
        ]);
    }

    /**
     * The add form, on a page of its own.
     *
     * A page rather than the dialog this replaces, for the reason the service
     * form is one: it has to survive a failed validation with everything the
     * user typed still in it, and it is a place that can be linked to.
     */
    public function create(Request $request): View
    {
        $this->authorizeResources($request, 'resources.create');

        /* The form arrives with the next free code already in it. Generated
           rather than typed: "RES-001" after "RES-002" is the kind of mistake
           nobody notices until two labels on two chairs say the same thing. */
        return view('resources.create', $this->formData() + [
            'suggestedCode' => ResourceCode::next($request->user()->tenant),
        ]);
    }

    public function edit(Request $request, Resource $resource): View
    {
        $this->authorizeResources($request, 'resources.edit', $resource);

        $data = $this->formData();

        /*
         * A resource whose category has since been switched off keeps showing
         * it. Dropping it from the list would blank the field, and saving any
         * other change would then quietly uncategorise the chair — which is
         * not what "deactivate this category" asked for.
         */
        if ($resource->resource_category_id && ! isset($data['categoryOptions'][$resource->resource_category_id])) {
            $current = ResourceCategory::query()->find($resource->resource_category_id);

            if ($current) {
                $data['categoryOptions'] = [$current->id => $current->name] + $data['categoryOptions'];
            }
        }

        return view('resources.edit', $data + [
            'resource' => $resource,
            'canDelete' => $request->user()->hasPermission('resources.delete', 'all'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeResources($request, 'resources.create');

        $data = $this->validated($request);
        $tenant = $request->user()->tenant;

        /* Blank means "number it for me". The field is optional, so a
           business that labels its chairs by hand simply clears it. */
        if (($data['code'] ?? null) === null || $data['code'] === '') {
            $data['code'] = ResourceCode::next($tenant) ?: null;
        }

        $resource = $this->createWithCode($this->columns($data), $tenant);

        $this->syncRelations($resource, $data);

        /* To the resource, not back: "back" from a form page is the form
           again, which reads as a save that did not take — and the thing
           just created is what the reader wants to see. */
        return redirect()->route('resources.show', $resource)
            ->with('toast', ['type' => 'success', 'message' => __('resources.added')]);
    }

    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorizeResources($request, 'resources.edit', $resource);

        $data = $this->validated($request, $resource);

        $resource->forceFill($this->columns($data))->save();

        $this->syncRelations($resource, $data);

        return redirect()->route('resources.show', $resource)
            ->with('toast', ['type' => 'success', 'message' => __('resources.updated')]);
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
     * Remove a resource.
     *
     * Soft, always: a chair appears in appointments that already happened,
     * and a hard delete would rewrite them. Gone from every list, still
     * resolvable from the history that refers to it.
     *
     * Distinct from retiring, which is a chair the business still owns and
     * has stopped booking — this is one that should not have existed.
     */
    public function destroy(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorizeResources($request, 'resources.delete', $resource);

        $resource->delete();

        return redirect()->route('resources.index')
            ->with('toast', ['type' => 'success', 'message' => __('resources.deleted')]);
    }

    /**
     * Save, and take the next number if somebody else took this one.
     *
     * Two people adding a resource in the same second are both handed the
     * same next code — the read and the write cannot be one operation — and
     * only the unique index can settle which of them keeps it. The loser is
     * not shown an error: nothing they typed is wrong, so the save is retried
     * with the number that is now next.
     *
     * Only a code the app generated is retried. A code the reader typed is
     * theirs, and silently changing it would be answering a different
     * question from the one they asked; that collision is a validation error
     * on the way in.
     *
     * @param  array<string, mixed>  $columns
     */
    private function createWithCode(array $columns, ?Tenant $tenant): Resource
    {
        $attempts = $columns['code'] === null ? 0 : 3;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                return Resource::create($columns + ['tenant_id' => $tenant->getTenantKey()]);
            } catch (UniqueConstraintViolationException $collision) {
                $columns['code'] = ResourceCode::next($tenant) ?: null;
            }
        }

        return Resource::create($columns + ['tenant_id' => $tenant->getTenantKey()]);
    }

    /**
     * Is this code already on another of this business's resources?
     *
     * Asked while the reader types, so the answer arrives beside the field
     * instead of after a submission they have to redo. The same question the
     * unique rule asks on the way in — one of them without the other is how a
     * form ends up accepting what the server refuses.
     */
    public function codeInUse(Request $request): JsonResponse
    {
        $this->authorizeResources($request, 'resources.create');

        $code = trim((string) $request->query('value'));

        if ($code === '') {
            return response()->json(['ok' => true]);
        }

        $taken = Resource::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $request->user()->tenant?->getTenantKey())
            /* The resource being edited is not a duplicate of itself. */
            ->when($request->query('ignore'), fn ($query, $id) => $query->whereKeyNot($id))
            ->where('code', $code)
            ->exists();

        return response()->json($taken
            ? ['ok' => false, 'message' => __('resources.validation.code_taken')]
            : ['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Resource $resource = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            /* Unique within the business, and only there: two salons both
               numbering their first chair RES-001 is not a conflict, and a
               code is only ever read next to the business it belongs to.
               Archived resources still hold theirs — restoring one whose
               number had been reissued would be a collision nobody could
               resolve — so the rule deliberately does not skip trashed rows. */
            'code' => [
                'nullable', 'string', 'max:40',
                Rule::unique('resources', 'code')
                    ->where('tenant_id', $request->user()->tenant?->getTenantKey())
                    ->ignore($resource?->id),
            ],
            /* The palette is a shortlist, not the whole set: the last card in
               the picker opens a colour picker, so any well-formed hex is a
               colour a business may have chosen. Still validated — an
               unchecked value here reaches a style attribute. */
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            /* Required now, where it used to be optional: a resource nobody
               can categorise cannot be asked for by category, which is the
               whole way a service claims one. */
            'resource_category_id' => ['required', Rule::exists('resource_categories', 'id')],
            'location_id' => ['required', Rule::exists('locations', 'id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:'.config('resources.max_capacity')],

            'is_active' => ['boolean'],
            'availability_status' => ['required', Rule::in(config('resources.availability_statuses'))],
            'availability_type' => ['required', Rule::in(config('resources.availability_types'))],

            /* No interval, preparation, cleanup or buffer: a resource does
               not carry its own timings. The lead and trail around a booking
               come from the service, which is what ResourceAllocator reads —
               these four were saved and never looked at again, and a field
               the app ignores is one the form should not offer. */

            'services' => ['array'],
            'services.*' => [Rule::exists('services', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey())],

            'hours' => ['array'],
            'hours.*.is_available' => ['boolean'],
            'hours.*.starts_at' => ['nullable', 'date_format:H:i'],
            /* After the start, or the day covers nothing. Only checked on the
               days the resource is actually open. */
            'hours.*.ends_at' => ['nullable', 'date_format:H:i', 'after:hours.*.starts_at'],
        ]);
    }

    /**
     * The columns, without the relations that travel beside them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        return collect($data)->except(['services', 'hours'])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Resource $resource, array $data): void
    {
        $resource->services()->sync($data['services'] ?? []);

        /* Replaced in full rather than patched: the week is the record, and
           a day left behind by a partial update is a day the diary still
           believes in. Cleared entirely when the resource keeps its
           location's hours, so switching back and forth cannot leave a
           half-remembered week behind. */
        $resource->hours()->delete();

        if (($data['availability_type'] ?? 'location') !== 'custom') {
            return;
        }

        foreach ($data['hours'] ?? [] as $day => $hours) {
            $available = (bool) ($hours['is_available'] ?? false);

            $resource->hours()->create([
                'day' => (int) $day,
                'is_available' => $available,
                'starts_at' => $available ? ($hours['starts_at'] ?? null) : null,
                'ends_at' => $available ? ($hours['ends_at'] ?? null) : null,
            ]);
        }
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
