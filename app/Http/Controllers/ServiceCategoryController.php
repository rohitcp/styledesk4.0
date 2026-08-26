<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use App\Support\InputCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Service categories for the active tenant.
 *
 * Every action is authorised and every lookup goes through the tenant-scoped
 * model, so a request naming another tenant's category id finds nothing and is
 * refused — the isolation is in the backend, not in what the UI chooses to
 * render.
 */
class ServiceCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCategory::class);

        $categories = ServiceCategory::query()
            ->when($request->boolean('assignable', true), fn ($q) => $q->assignable(), fn ($q) => $q->orderBy('display_order')->orderBy('name'))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->get(['id', 'name', 'status', 'display_order']);

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ServiceCategory::class);

        $data = $this->validated($request);

        $category = ServiceCategory::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? ServiceCategory::STATUS_ACTIVE,
            // Appended rather than inserted, so creating one does not silently
            // reshuffle a list someone has already ordered.
            'display_order' => (int) ServiceCategory::max('display_order') + 1,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $category->only(['id', 'name', 'status', 'display_order'])], 201);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorize('update', $serviceCategory);

        $serviceCategory->update($this->validated($request, $serviceCategory));

        return response()->json(['data' => $serviceCategory->only(['id', 'name', 'status', 'display_order'])]);
    }

    /**
     * Archive rather than delete, and refuse while services still point at it.
     *
     * A category in use is the truthful label on those services; removing it
     * would leave them unlabelled or silently recategorised.
     */
    public function destroy(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorize('delete', $serviceCategory);

        $inUse = $serviceCategory->services()->count();

        if ($inUse > 0 && ! $request->boolean('archive')) {
            return response()->json([
                'message' => 'This category is currently assigned to services. Move the services to another category before deleting it.',
                'services_count' => $inUse,
                'can_archive' => true,
            ], 409);
        }

        $serviceCategory->delete();

        return response()->json(['data' => ['archived' => true]]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('reorder', ServiceCategory::class);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($data['order'] as $position => $id) {
            // Scoped update: an id from another tenant matches no row rather
            // than reordering someone else's list.
            ServiceCategory::whereKey($id)->update(['display_order' => $position]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ServiceCategory $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in([ServiceCategory::STATUS_ACTIVE, ServiceCategory::STATUS_INACTIVE])],
        ]);

        $data['name'] = InputCase::sentence($data['name']);

        /**
         * Uniqueness is checked in PHP rather than with Rule::unique so it runs
         * through the tenant-scoped model. A raw unique rule would compare
         * against every tenant's rows and reject a name another business
         * happens to use.
         *
         * The comparison is case-insensitive to match the database index.
         */
        $clash = ServiceCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->getKey()))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'name' => 'You already have a category with that name.',
            ]);
        }

        return $data;
    }
}
