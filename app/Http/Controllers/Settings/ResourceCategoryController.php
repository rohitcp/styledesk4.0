<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ResourceCategory;
use App\Support\InputCase;
use App\Support\ResourceCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App Settings → Resources: the catalogue of resource categories.
 *
 * Not the Resources module. This screen decides which kinds of thing exist —
 * styling chairs, massage rooms — where the module manages the actual chairs
 * and rooms. Keeping that line is what stops a settings page from slowly
 * becoming a second resources screen.
 *
 * Two rules carry it. A category StyleDesk supplied may be switched off and
 * reordered but never deleted, because there is no way to get it back. A
 * category anyone created may be deleted, but only while nothing is using it.
 */
class ResourceCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = ResourceCategory::query()
            ->withCount('resources')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->inOrder()
            ->get();

        return view('settings.resources.index', [
            'categories' => $categories,
            /* The numbering scheme sits on this screen because it is a
               property of the catalogue rather than of any one chair — the
               same reason the categories are here and the resources are not. */
            'codePrefix' => ResourceCode::prefix($request->user()->tenant),
            'codePadding' => ResourceCode::padding($request->user()->tenant),
            'codeSample' => ResourceCode::sample($request->user()->tenant),
            'nextCode' => ResourceCode::next($request->user()->tenant),
            /* Grouped for reading, ordered for dragging: the sections are how
               a thirty-entry list is scanned, and position is what the rest
               of the app sorts by. */
            'grouped' => $categories->groupBy(fn (ResourceCategory $category) => $category->groupLabel() ?? __('resources.category_groups.general')),
            'search' => (string) $request->query('search', ''),
        ]);
    }

    /**
     * How this business numbers its resources.
     *
     * Two fields, not a pattern string: a prefix somebody types and a width
     * for the number. Changing either affects the *next* code only — the
     * codes already printed on labels and written into a rota are not
     * rewritten, because a code is a name for a thing rather than a
     * calculation about it.
     */
    public function updateCodeFormat(Request $request): RedirectResponse
    {
        $code = config('resources.code');

        $data = $request->validate([
            /* Letters, digits and the two separators anybody actually uses.
               Anything else ends up in a filename, a spreadsheet or a URL
               eventually, and a code with a slash in it is a support ticket. */
            'resource_code_prefix' => ['nullable', 'string', 'max:'.$code['max_prefix_length'], 'regex:/^[A-Za-z0-9_-]*$/'],
            'resource_code_padding' => ['required', 'integer', 'min:'.$code['min_padding'], 'max:'.$code['max_padding']],
        ], [
            'resource_code_prefix.regex' => __('resources.validation.prefix_shape'),
        ]);

        $request->user()->tenant->forceFill([
            /* Blank means "the product's default", not an empty prefix: the
               same arrangement as every other tenant setting, so a business
               that clears the field keeps following StyleDesk if the default
               ever changes. */
            'resource_code_prefix' => $data['resource_code_prefix'] ?: null,
            'resource_code_padding' => (int) $data['resource_code_padding'],
        ])->save();

        return redirect()->route('settings.resources.index')
            ->with('toast', ['type' => 'success', 'message' => __('resources.categories_ui.code_saved')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        ResourceCategory::create([
            'name' => $data['name'],
            'group' => $data['group'] ?? null,
            'default_capacity' => $data['default_capacity'],
            'is_system' => false,
            'is_active' => true,
            /* Appended rather than inserted, so adding one does not reshuffle
               a list somebody has already put in order. */
            'position' => (int) ResourceCategory::max('position') + 1,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('resources.categories_ui.added')]);
    }

    /**
     * Rename, regroup, recapacity.
     *
     * A system category may be renamed like any other: "Styling chair" is
     * what StyleDesk calls it, not what every business does.
     */
    public function update(Request $request, ResourceCategory $resourceCategory): RedirectResponse
    {
        $resourceCategory->forceFill($this->validated($request, $resourceCategory))->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('resources.categories_ui.updated')]);
    }

    /**
     * Switch one on or off.
     *
     * Off means "not offered for anything new". The resources already in it
     * keep it, and so does every appointment that named them — which is the
     * whole reason this is not a delete.
     */
    public function toggle(ResourceCategory $resourceCategory): RedirectResponse
    {
        $resourceCategory->forceFill(['is_active' => ! $resourceCategory->is_active])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $resourceCategory->is_active
                ? __('resources.categories_ui.activated')
                : __('resources.categories_ui.deactivated'),
        ]);
    }

    /**
     * Remove one, when removing it is a thing that can be done.
     *
     * Refused with a reason rather than hidden: a reader who cannot see why
     * the option is missing concludes the screen is broken. The message names
     * the alternative, because deactivating is what they actually wanted.
     */
    public function destroy(ResourceCategory $resourceCategory): RedirectResponse
    {
        if ($resourceCategory->isSystem()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('resources.categories_ui.system_undeletable'),
            ]);
        }

        $inUse = $resourceCategory->resources()->count();

        if ($inUse > 0) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => trans_choice('resources.categories_ui.in_use', $inUse),
            ]);
        }

        $resourceCategory->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('resources.categories_ui.deleted')]);
    }

    /**
     * The order the reader dragged them into.
     *
     * The whole set is sent, not a pair of swapped ids: the order is the
     * record, and stating it in full is what stops two people dragging at
     * once from producing a list neither of them arranged.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => [Rule::exists('resource_categories', 'id')],
        ]);

        foreach ($data['order'] as $position => $id) {
            ResourceCategory::query()->whereKey($id)->update(['position' => $position]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('resources.categories_ui.reordered')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ResourceCategory $category = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                /* Unique within the business, ignoring itself: two categories
                   with one name is a dropdown nobody can choose from. */
                Rule::unique('resource_categories', 'name')
                    ->where('tenant_id', $request->user()->tenant?->getTenantKey())
                    ->whereNull('deleted_at')
                    ->ignore($category?->id),
            ],
            /* The sections are named in the language file, which is the one
               place they exist — a group nobody can label is a heading that
               renders as its own key. */
            'group' => ['nullable', Rule::in(array_keys(__('resources.category_groups')))],
            'default_capacity' => ['required', 'integer', 'min:1', 'max:'.config('resources.max_capacity')],
        ]);

        $data['name'] = InputCase::sentence($data['name']);

        return $data;
    }
}
