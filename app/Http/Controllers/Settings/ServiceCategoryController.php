<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Support\InputCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App Settings → Services: the catalogue of service categories.
 *
 * Not the Services module. This screen decides how the price list is
 * organised; the services themselves — their durations, prices and staff —
 * belong to the module that reads these categories.
 *
 * Two rules carry it, the same two the resource catalogue has. A category
 * StyleDesk supplied may be switched off and reordered but never deleted,
 * because there is no way to get it back. A category anyone created may be
 * deleted, but only while nothing is using it.
 *
 * Distinct from App\Http\Controllers\ServiceCategoryController, which is the
 * JSON endpoint the service form's category picker reads. Same table, two
 * jobs: one configures the catalogue, the other lists it.
 */
class ServiceCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = ServiceCategory::query()
            ->withCount('services')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            /* A–Z, and only A–Z. This screen is read to find one category
               among thirty — "where is Hair Colour" — and a list in an order
               somebody arranged months ago is one every reader has to scan
               end to end. The hand-set `display_order` still decides where
               categories appear on the screens that offer them for choosing;
               it is simply not what this reference list is sorted by. */
            ->orderBy('name')
            ->get();

        return view('settings.services.index', [
            'categories' => $categories,
            'search' => (string) $request->query('search', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        ServiceCategory::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'status' => ServiceCategory::STATUS_ACTIVE,
            /* Appended rather than inserted, so adding one does not reshuffle
               a list somebody has already put in order. */
            'display_order' => (int) ServiceCategory::max('display_order') + 1,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('services.categories_ui.added')]);
    }

    /**
     * Rename or redescribe.
     *
     * A system category may be renamed like any other: "Hair" is what
     * StyleDesk calls it, not what every business does.
     */
    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $serviceCategory->forceFill($this->validated($request, $serviceCategory))->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('services.categories_ui.updated')]);
    }

    /**
     * Switch one on or off.
     *
     * Off means "not offered for anything new". The services already in it
     * keep it, and so does every appointment that named them — which is the
     * whole reason this is not a delete.
     */
    public function toggle(ServiceCategory $serviceCategory): RedirectResponse
    {
        $active = $serviceCategory->isActive();

        $serviceCategory->forceFill([
            'status' => $active ? ServiceCategory::STATUS_INACTIVE : ServiceCategory::STATUS_ACTIVE,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $active
                ? __('services.categories_ui.deactivated')
                : __('services.categories_ui.activated'),
        ]);
    }

    /**
     * Remove one, when removing it is a thing that can be done.
     *
     * Refused with a reason rather than hidden: a reader who cannot see why
     * the option is missing concludes the screen is broken. The message names
     * the alternative, because deactivating is what they actually wanted.
     */
    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        if ($serviceCategory->isSystem()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('services.categories_ui.system_undeletable'),
            ]);
        }

        $inUse = $serviceCategory->services()->count();

        if ($inUse > 0) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => trans_choice('services.categories_ui.in_use', $inUse),
            ]);
        }

        $serviceCategory->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('services.categories_ui.deleted')]);
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
            'order.*' => [Rule::exists('service_categories', 'id')],
        ]);

        foreach ($data['order'] as $position => $id) {
            ServiceCategory::query()->whereKey($id)->update(['display_order' => $position]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('services.categories_ui.reordered')]);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Is a category already called this?
     *
     * Asked while the reader types, so "you already have one of these" arrives
     * beside the field instead of after a save they have to redo. The same
     * question the unique rule asks on the way in — one of them without the
     * other is how a dialog ends up accepting what the server refuses.
     */
    public function nameInUse(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('value'));

        if ($name === '') {
            return response()->json(['ok' => true]);
        }

        $taken = ServiceCategory::query()
            /* The category being edited is not a duplicate of itself. */
            ->when($request->query('ignore'), fn ($query, $id) => $query->whereKeyNot($id))
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();

        return response()->json($taken
            ? ['ok' => false, 'message' => __('services.categories_ui.name_taken')]
            : ['ok' => true]);
    }

    private function validated(Request $request, ?ServiceCategory $category = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                /* Unique within the business, ignoring itself: two categories
                   with one name is a price list with two identical headings. */
                Rule::unique('service_categories', 'name')
                    ->where('tenant_id', $request->user()->tenant?->getTenantKey())
                    ->whereNull('deleted_at')
                    ->ignore($category?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $data['name'] = InputCase::sentence($data['name']);

        return $data;
    }
}
