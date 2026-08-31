<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Staff;
use App\Models\StaffShift;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shifts — working hours on a named date.
 *
 * The dated half of staff scheduling. A staff schedule is the recurring
 * pattern; a shift is one block on 12 September that supplements or overrides
 * it. Kept as its own screen for that reason: they are read at different
 * times, by different people, for different questions.
 *
 * Permissions come from StaffPolicy rather than a set of their own. A shift is
 * a fact about a member of staff, and whoever may change their record is
 * whoever may say when they work — a second permission list would be a second
 * answer able to disagree with the first.
 */
class ShiftController extends Controller
{
    /** Rows before the listing splits into pages. */
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        return view('shifts.index', [
            'filters' => $this->filters($request),
            'staff' => Staff::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'preferred_name']),
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
            /* Whether this business has any shifts at all, which is a
               different question from whether any matched — and gets a
               different empty state. */
            'hasShifts' => StaffShift::query()->exists(),
        ]);
    }

    /** The rows the listing grid asks for, as JSON. */
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Staff::class);

        $filters = $this->filters($request);

        $query = StaffShift::query()
            ->with(['staff', 'location'])
            ->tap(fn (Builder $q) => $this->applyFilters($q, $filters))
            /* Soonest first. A rota is read forwards: the next thing anyone
               needs to know is who is in tomorrow. */
            ->orderBy('date')
            ->orderBy('starts_at');

        $size = min(200, max(1, (int) $request->query('size', self::PER_PAGE)));
        $page = $query->paginate($size, ['*'], 'page', max(1, (int) $request->query('page', 1)));

        return response()->json([
            'last_page' => max(1, $page->lastPage()),
            'last_row' => $page->total(),
            'total' => $page->total(),
            'data' => collect($page->items())
                ->map(fn (StaffShift $shift) => $this->row($request, $shift))
                ->all(),
        ]);
    }

    /**
     * One row, already worded.
     *
     * @return array<string, mixed>
     */
    private function row(Request $request, StaffShift $shift): array
    {
        return [
            'id' => $shift->id,
            'name' => $shift->staff?->directoryName() ?? '—',
            'initials' => $shift->staff?->initials() ?? '',
            'date' => $shift->date->translatedFormat('D j M Y'),
            'hours' => $shift->hoursLabel(),
            'break' => $shift->break_minutes > 0
                ? __('shifts.minutes', ['count' => $shift->break_minutes])
                : __('shifts.no_break'),
            'location' => $shift->location?->name ?? __('staff.all_locations'),
            'type' => $shift->typeLabel(),
            'type_class' => $shift->typeClass(),
            'status' => $shift->statusLabel(),
            'status_class' => $shift->statusClass(),

            'url' => route('shifts.edit', $shift),
            'menu' => $this->rowMenu($request, $shift),
        ];
    }

    /**
     * One row's actions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowMenu(Request $request, StaffShift $shift): array
    {
        if ($shift->staff === null || ! $request->user()->can('update', $shift->staff)) {
            return [];
        }

        $menu = [
            ['label' => __('common.edit'), 'url' => route('shifts.edit', $shift)],
        ];

        if (! $shift->isCancelled()) {
            $menu[] = ['separator' => true];
            $menu[] = [
                'label' => __('shifts.cancel_shift'),
                'url' => route('shifts.cancel', $shift),
                'method' => 'PATCH',
                'confirm' => __('shifts.cancel_confirm'),
                'confirm_title' => __('shifts.cancel_shift'),
                'confirm_label' => __('shifts.cancel_shift'),
                'tone' => 'danger',
                'danger' => true,
            ];
        }

        $menu[] = [
            'label' => __('common.delete'),
            'url' => route('shifts.destroy', $shift),
            'method' => 'DELETE',
            'danger' => true,
            'confirm' => __('shifts.delete_confirm', [
                'name' => $shift->staff->displayName(),
                'date' => $shift->date->translatedFormat('j M Y'),
            ]),
            'confirm_title' => __('common.delete'),
            'confirm_label' => __('common.delete'),
            'tone' => 'danger',
        ];

        return $menu;
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Staff::class);

        /**
         * Reached from somebody's own schedule with them and the day already
         * chosen — adding a shift from a person's rota should not mean picking
         * them out of a list again. A blank model rather than a saved one, so
         * nothing is written until the form is submitted.
         */
        $prefilled = new StaffShift([
            'staff_id' => $request->query('staff'),
            'date' => $request->query('date'),
        ]);

        return view('shifts.create', $this->formData() + ['shift' => $prefilled]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Staff::class);

        $data = $this->validated($request, new StaffShift);

        $shift = StaffShift::create($data + ['tenant_id' => $request->user()->tenant->getTenantKey()]);

        $message = __('shifts.created', ['name' => $shift->staff->displayName()]);

        /* Straight back to an empty form for the morning somebody sits down
           to enter a whole week, and to the rota otherwise. */
        if ($request->input('after_save') === 'add_another') {
            return redirect()->route('shifts.create')
                ->with('toast', ['type' => 'success', 'message' => $message]);
        }

        return redirect()->route('shifts.index')
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }

    public function edit(Request $request, StaffShift $shift): View
    {
        $this->authorize('viewAny', Staff::class);

        return view('shifts.edit', $this->formData() + ['shift' => $shift]);
    }

    public function update(Request $request, StaffShift $shift): RedirectResponse
    {
        $this->authorize('update', $shift->staff);

        /* Editing a published shift makes it a change waiting to be
           communicated, not a fresh one: published_at is left alone so the
           schedule page can tell "never sent" from "sent, then edited", and
           the manager decides when to publish the change. */
        $shift->forceFill($this->validated($request, $shift) + ['publish_status' => 'draft'])->save();

        return redirect()->route('shifts.index')
            ->with('toast', ['type' => 'success', 'message' => __('shifts.updated')]);
    }

    /**
     * Cancelled, not deleted.
     *
     * A shift that was dropped is a fact about the week. A row that simply
     * disappears leaves whoever is reading the rota wondering whether it was
     * ever there.
     */
    public function cancel(Request $request, StaffShift $shift): RedirectResponse
    {
        $this->authorize('update', $shift->staff);

        $shift->forceFill(['status' => 'cancelled'])->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('shifts.cancelled_toast')]);
    }

    public function destroy(Request $request, StaffShift $shift): RedirectResponse
    {
        $this->authorize('update', $shift->staff);

        $shift->delete();

        return redirect()->route('shifts.index')
            ->with('toast', ['type' => 'success', 'message' => __('shifts.deleted')]);
    }

    /**
     * The option lists both form pages need.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            /* Only people who are actually here. A rota offering somebody who
               has left is a rota that will be wrong the moment it is used. */
            'staffOptions' => Staff::query()->where('is_active', true)->orderBy('first_name')->get(),
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, StaffShift $shift): array
    {
        $tenantId = $request->user()->tenant?->getTenantKey();

        $data = $request->validate([
            'staff_id' => ['required', Rule::exists('staff', 'id')->where('tenant_id', $tenantId)],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            /* after:, not after_or_equal: a shift that ends when it starts is
               not a short shift, it is a mistake. */
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'break_minutes' => ['nullable', 'integer', Rule::in(config('shifts.breaks'))],
            'type' => ['required', Rule::in(array_keys(config('shifts.types')))],
            'status' => ['required', Rule::in(array_keys(config('shifts.statuses')))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'staff_id.required' => __('shifts.validation.staff_required'),
            'date.required' => __('shifts.validation.date_required'),
            'ends_at.after' => __('shifts.validation.ends_after_start'),
        ]);

        $data['break_minutes'] = (int) ($data['break_minutes'] ?? 0);

        /* Checked here rather than as rules, because each needs the whole set
           of answers: how long the shift is, what else that person is already
           down for that day, and when the business is actually open. */
        $this->guardAgainstImpossibleShift($data, $shift);
        $this->guardAgainstHoursOutsideTheBusinessWeek($data);

        return $data;
    }

    /**
     * A shift has to fall inside the hours the business keeps.
     *
     * The business's working hours are the one source of when it is open —
     * App Settings → Business → Working Hours — and a rota that puts somebody
     * on the floor at eight when the doors open at nine is a rota nobody can
     * work. Checked against the branch the shift names, or the primary one
     * when it names none.
     *
     * A business that has recorded no hours at all is left alone: there is
     * nothing to check against, and refusing every shift would be refusing
     * them for a reason the reader cannot act on from this screen.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardAgainstHoursOutsideTheBusinessWeek(array $data): void
    {
        $location = isset($data['location_id'])
            ? Location::query()->find($data['location_id'])
            : Location::query()->orderByDesc('is_primary')->orderBy('name')->first();

        if ($location === null || $location->hours->isEmpty()) {
            return;
        }

        $weekday = Carbon::parse($data['date'])->dayOfWeek;
        $open = $location->hours->where('day_of_week', $weekday)->where('is_open', true);

        if ($open->isEmpty()) {
            throw ValidationException::withMessages([
                'date' => __('shifts.validation.business_closed', [
                    'day' => Carbon::parse($data['date'])->translatedFormat('l'),
                ]),
            ]);
        }

        /**
         * Inside any one open period, not merely inside the day's outer
         * bounds: a business that shuts for lunch is shut for lunch, and a
         * shift spanning the gap would be scheduling somebody into a closed
         * building.
         */
        $fits = $open->contains(fn ($hour) => $hour->timeValue('opens_at') <= $data['starts_at']
            && $hour->timeValue('closes_at') >= $data['ends_at']);

        if (! $fits) {
            throw ValidationException::withMessages([
                'starts_at' => __('shifts.validation.outside_business_hours', [
                    'hours' => $open->map->rangeLabel()->implode(', '),
                ]),
            ]);
        }
    }

    /**
     * The two things a valid field-by-field submission can still get wrong.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardAgainstImpossibleShift(array $data, StaffShift $shift): void
    {
        $candidate = (clone $shift)->forceFill($data);

        if ($candidate->workedMinutes() === 0 && $data['break_minutes'] > 0) {
            throw ValidationException::withMessages([
                'break_minutes' => __('shifts.validation.break_too_long'),
            ]);
        }

        /* A cancelled shift is still a fact about the week but nobody is
           working it, so it cannot clash with anything. */
        if (($data['status'] ?? null) === 'cancelled') {
            return;
        }

        $clash = StaffShift::query()->clashingWith($candidate)->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'starts_at' => __('shifts.validation.clash', [
                    'name' => Staff::query()->find($data['staff_id'])?->displayName() ?? '',
                ]),
            ]);
        }
    }

    /**
     * What the reader asked to see, from the query string.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'staff' => $request->query('staff') ?: null,
            'location' => $request->query('location') ?: null,
            'type' => array_key_exists((string) $request->query('type'), config('shifts.types'))
                ? $request->query('type')
                : null,
            'status' => array_key_exists((string) $request->query('status'), config('shifts.statuses'))
                ? $request->query('status')
                : null,
            'from' => $this->dateOrNull($request->query('from')),
            'until' => $this->dateOrNull($request->query('until')),
        ];
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = (string) $value;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['staff'], fn (Builder $q, $id) => $q->where('staff_id', $id))
            ->when($filters['location'], fn (Builder $q, $id) => $q->where('location_id', $id))
            ->when($filters['type'], fn (Builder $q, $type) => $q->where('type', $type))
            ->when($filters['status'], fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['from'], fn (Builder $q, $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['until'], fn (Builder $q, $until) => $q->whereDate('date', '<=', $until));
    }
}
