<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ReasonCode;
use App\Support\InputCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App Settings → Reasons: why things happened, as a list rather than a box.
 *
 * "Why do we lose bookings" is a question of counting, and forty spellings of
 * "client changed their mind" answer none of it. This screen is where a
 * business shapes the lists every other screen will ask from.
 *
 * Two rules carry it, the same two the service and resource catalogues have.
 * A reason StyleDesk supplied may be renamed, reordered and switched off but
 * never deleted — the March cancellation recorded under it still has to say
 * why. A reason the business invented may be deleted, because nothing else
 * ever supplied it.
 */
class ReasonCodeController extends Controller
{
    /** The nine lists, and how much of each is switched on. */
    public function index(Request $request): View
    {

        $counts = ReasonCode::query()
            ->selectRaw('type, count(*) as total, sum(is_active) as active')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return view('settings.reasons.index', [
            'types' => collect(ReasonCode::types())
                ->map(fn (array $definition, string $type) => [
                    'key' => $type,
                    'label' => ReasonCode::typeLabel($type),
                    'intro' => ReasonCode::typeIntro($type),
                    'total' => (int) ($counts[$type]->total ?? 0),
                    'active' => (int) ($counts[$type]->active ?? 0),
                ])
                ->values(),
        ]);
    }

    /** One list, in the order the business arranged it. */
    public function show(Request $request, string $type): View
    {

        abort_unless(ReasonCode::typeExists($type), 404);

        return view('settings.reasons.show', [
            'type' => $type,
            'label' => ReasonCode::typeLabel($type),
            'intro' => ReasonCode::typeIntro($type),
            /* The second question this type asks, where it asks one. Shown
               so the business can see what else the screen will want, even
               though it is not theirs to configure. */
            'extra' => ReasonCode::extraFor($type),
            'reasons' => ReasonCode::query()->ofType($type)->inOrder()->get(),
        ]);
    }

    /** A reason the business invented. */
    public function store(Request $request, string $type): RedirectResponse
    {

        abort_unless(ReasonCode::typeExists($type), 404);

        $data = $this->validated($request, $type);

        ReasonCode::create([
            'type' => $type,
            /* No key: a key is what marks a reason as StyleDesk's, and this
               one is not. It is also what makes it deletable. */
            'key' => null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_active' => true,
            'requires_details' => (bool) ($data['requires_details'] ?? false),
            /* Appended rather than inserted, so adding one does not reshuffle
               a list somebody has already put in order. */
            'display_order' => (int) ReasonCode::query()->ofType($type)->max('display_order') + 1,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('reasons.added')]);
    }

    /**
     * Rename one, or change what it asks for.
     *
     * A system reason may be renamed — that is the point of the library being
     * a starting position rather than a fixed vocabulary — and its key is
     * left alone, so it is still recognisably ours underneath.
     */
    public function update(Request $request, ReasonCode $reason): RedirectResponse
    {

        $data = $this->validated($request, $reason->type, $reason);

        $reason->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'requires_details' => (bool) ($data['requires_details'] ?? false),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('reasons.updated')]);
    }

    /**
     * On or off.
     *
     * Off is not deletion: everything recorded under it keeps saying what it
     * said, and nothing new can be filed under it.
     */
    public function toggle(Request $request, ReasonCode $reason): RedirectResponse
    {

        $reason->update(['is_active' => ! $reason->is_active]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $reason->is_active ? __('reasons.activated') : __('reasons.deactivated'),
        ]);
    }

    /**
     * Delete one the business made.
     *
     * Refused for a system reason, and not merely hidden from the menu: there
     * is no way to get it back, and the records filed under it would be left
     * pointing at nothing.
     */
    public function destroy(Request $request, ReasonCode $reason): RedirectResponse
    {

        abort_if($reason->isSystem(), 403);

        $reason->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('reasons.deleted')]);
    }

    /**
     * The whole order at once.
     *
     * Stated in full rather than as a pair of swapped ids: two people
     * dragging at the same time would otherwise produce a list neither of
     * them arranged.
     */
    public function reorder(Request $request, string $type): RedirectResponse
    {

        abort_unless(ReasonCode::typeExists($type), 404);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists('reason_codes', 'id')],
        ]);

        foreach (array_values($data['order']) as $position => $id) {
            ReasonCode::query()->ofType($type)->whereKey($id)->update(['display_order' => $position]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('reasons.order_saved')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $type, ?ReasonCode $reason = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                /* One of each name per list. Two reasons a reader cannot tell
                   apart is a count split between them for no reason. */
                Rule::unique('reason_codes', 'name')
                    ->where('tenant_id', $request->user()->tenant->getTenantKey())
                    ->where('type', $type)
                    ->ignore($reason?->id),
            ],
            'description' => ['nullable', 'string', 'max:300'],
            'requires_details' => ['nullable', 'boolean'],
        ]);

        return InputCase::apply($data, ['name']);
    }
}
