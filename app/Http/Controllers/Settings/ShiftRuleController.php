<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationHour;
use App\Models\ShiftRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * App Settings → Staff → Shift Rules.
 *
 * A shift rule is a reusable working pattern — "Standard Full-Time", "Weekend
 * Shift". It is configuration rather than operations, which is why it lives
 * here: the pattern is decided once and revisited rarely, where the rota it
 * generates is touched every week.
 *
 * Owner and Administrator only, enforced by the can-manage-settings middleware
 * this whole route group carries rather than by a check per action. A manager
 * meets shift rules on the Staff Schedule screen, where they pick one — never
 * here, where they are written.
 */
class ShiftRuleController extends Controller
{
    /**
     * The whole feature on one page.
     *
     * The list, the switch that turns the feature off, and the form for
     * adding or editing a rule, all at one address. `add` and `edit` are
     * states of this screen rather than screens of their own — which is what
     * lets the reader keep their place, and what makes "return to the list
     * after saving" a redirect back here rather than a journey.
     */
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant;

        $editing = $request->query('edit')
            ? ShiftRule::query()->with(['locations', 'shiftPeriods', 'staff'])->find($request->query('edit'))
            : null;

        /* Adding and editing are the same form in two states; a request for
           both is a request for the one the reader most recently asked for,
           and edit is the more specific. */
        $mode = match (true) {
            $editing !== null => 'edit',
            $request->boolean('add') => 'add',
            default => 'list',
        };

        return view('settings.shift-rules.index', [
            'enabled' => (bool) $tenant->shift_rules_enabled,
            'mode' => $mode,
            'rule' => $editing,
            /* Every rule, active and inactive alike: a disabled rule stays on
               the page saying so, which is the difference between switching
               one off and deleting it. */
            'rules' => ShiftRule::query()->with(['locations', 'shiftPeriods'])
                ->withCount('staff')->orderBy('name')->get(),
        ] + $this->formData($editing));
    }

    /** Whether this business uses shift rules at all. */
    public function setFeature(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $enabling = $request->boolean('enabled');

        /* Every rule is kept either way. Turning the feature off hides it;
           the rules are still there when it comes back on, which is the whole
           difference between this and deleting them. */
        $tenant->forceFill(['shift_rules_enabled' => $enabling])->save();

        return redirect()->route('settings.shift-rules.index')->with('toast', [
            'type' => 'success',
            'message' => $enabling ? __('shift_rules.feature_on') : __('shift_rules.feature_off'),
        ]);
    }

    /**
     * Kept as an address, rendered as a state.
     *
     * The form lives on the listing page now, so this redirects there with
     * the page in its "adding" state rather than drawing a second screen —
     * old links and bookmarks still land somewhere sensible.
     */
    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('settings.shift-rules.index', ['add' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $rule = DB::transaction(function () use ($request, $data) {
            $rule = ShiftRule::create(
                $this->columns($data) + ['tenant_id' => $request->user()->tenant->getTenantKey()]
            );

            $this->syncLocations($rule, $data);
            $this->syncPeriods($rule, $data);

            return $rule;
        });

        $message = __('shift_rules.created', ['name' => $rule->name]);

        if ($request->input('after_save') === 'add_another') {
            return redirect()->route('settings.shift-rules.index', ['add' => 1])
                ->with('toast', ['type' => 'success', 'message' => $message]);
        }

        return redirect()->route('settings.shift-rules.index')
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }

    /** As create(): an address that resolves to a state of the one page. */
    public function edit(Request $request, ShiftRule $shiftRule): RedirectResponse
    {
        return redirect()->route('settings.shift-rules.index', ['edit' => $shiftRule->id]);
    }

    public function update(Request $request, ShiftRule $shiftRule): RedirectResponse
    {
        $data = $this->validated($request, $shiftRule);

        DB::transaction(function () use ($shiftRule, $data) {
            $shiftRule->forceFill($this->columns($data))->save();

            $this->syncLocations($shiftRule, $data);
            $this->syncPeriods($shiftRule, $data);
        });

        return redirect()->route('settings.shift-rules.index')
            ->with('toast', [
                'type' => 'success',
                'message' => __('shift_rules.updated', ['name' => $shiftRule->name]),
            ]);
    }

    /**
     * Copy a rule, and open the copy for editing.
     *
     * Not saved-and-listed: the copy exists to be changed — "Standard
     * Full-Time" into "Standard Full-Time – Saturday" — and dropping the
     * reader back on the listing would leave two identically-shaped rules with
     * near-identical names and nothing said about which is which.
     */
    public function duplicate(Request $request, ShiftRule $shiftRule): RedirectResponse
    {
        $shiftRule->loadMissing(['locations', 'shiftPeriods']);

        $copy = DB::transaction(function () use ($shiftRule) {
            $copy = $shiftRule->replicate(['created_at', 'updated_at']);
            $copy->name = __('shift_rules.copy_of', ['name' => $shiftRule->name]);
            /* The copy starts switched off. A duplicate is a draft until
               somebody has renamed it, and an active one is immediately
               offered for new schedules under a name meaning "copy". */
            $copy->status = ShiftRule::STATUS_INACTIVE;
            $copy->save();

            $copy->locations()->sync($shiftRule->locations->pluck('id'));

            foreach ($shiftRule->shiftPeriods as $period) {
                $copy->shiftPeriods()->create(
                    $period->only(['name', 'starts_at', 'ends_at', 'break_minutes', 'is_active', 'sort_order'])
                );
            }

            return $copy;
        });

        /* The copy opens in the form, on the same page — it exists to be
           renamed, and dropping the reader back on the list would leave two
           identically-shaped rules and nothing said about which is which. */
        return redirect()->route('settings.shift-rules.index', ['edit' => $copy->id])
            ->with('toast', [
                'type' => 'success',
                'message' => __('shift_rules.duplicated', ['name' => $shiftRule->name]),
            ]);
    }

    /** Offered for new schedules, or not. */
    public function setStatus(Request $request, ShiftRule $shiftRule): RedirectResponse
    {
        $activating = ! $shiftRule->isActive();

        $shiftRule->forceFill([
            'status' => $activating ? ShiftRule::STATUS_ACTIVE : ShiftRule::STATUS_INACTIVE,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $activating
                ? __('shift_rules.made_active', ['name' => $shiftRule->name])
                : __('shift_rules.made_inactive', ['name' => $shiftRule->name]),
        ]);
    }

    public function destroy(Request $request, ShiftRule $shiftRule): RedirectResponse
    {
        /**
         * Checked here as well as hidden from the menu: the menu only decides
         * what is drawn, and this is a request anybody can make.
         */
        if ($shiftRule->isInUse()) {
            return back()->with('toast', [
                'type' => 'danger',
                'message' => __('shift_rules.delete_blocked', [
                    'name' => $shiftRule->name,
                    'count' => $shiftRule->assignedStaffCount(),
                ]),
            ]);
        }

        $shiftRule->delete();

        return redirect()->route('settings.shift-rules.index')
            ->with('toast', ['type' => 'success', 'message' => __('shift_rules.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?ShiftRule $rule = null): array
    {
        /**
         * The branch whose working hours the form shows.
         *
         * The one the rule names when it names exactly one, and the primary
         * branch otherwise — a rule that applies everywhere is still shown a
         * real week, and the primary branch is the one a business answers
         * "what are your hours" with.
         */
        $hoursLocation = $rule?->hoursLocation()
            ?? Location::query()->orderByDesc('is_primary')->orderBy('name')->first();

        return [
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
            'hoursLocation' => $hoursLocation,
            /* Grouped by weekday so the table can render seven rows without
               asking the database once per day. */
            'businessHours' => $hoursLocation?->hours->where('is_open', true)->groupBy('day_of_week')
                ?? collect(),
        ];
    }

    /**
     * The columns of the rule itself, without the children.
     *
     * A whitelist rather than an exclusion list: the validated set carries
     * `locations`, which are rows of their own, and excluding the ones that
     * happen to be known today makes the next field added to the form a fatal
     * "unknown column" the first time somebody saves.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        $columns = collect($data)->only([
            'name', 'description', 'location_scope', 'status',
            'break_type', 'break_minutes', 'break_starts_at', 'break_ends_at',
            'max_hours_per_day', 'max_hours_per_week', 'min_hours_per_shift', 'max_hours_per_shift',
            'min_rest_hours', 'max_consecutive_days',
            'overtime_after_hours', 'max_overtime_hours',
            'max_periods_per_employee_per_day', 'min_gap_minutes',
            'effective_from', 'effective_until',
        ])->all();

        /* The switches. Read from the request rather than from the validated
           set, because an unticked checkbox posts nothing at all and
           `boolean` on a missing key leaves the old value standing. */
        $columns['allow_overtime'] = (bool) ($data['allow_overtime'] ?? false);
        $columns['allow_adjustment'] = (bool) ($data['allow_adjustment'] ?? false);
        $columns['allow_split_shift'] = (bool) ($data['allow_split_shift'] ?? false);

        /**
         * Split shifts and overtime stay separate settings.
         *
         * Dividing the day into periods says nothing about whether anybody may
         * exceed their hours, and switching one on must never quietly switch
         * the other on with it.
         */
        $columns['allow_same_employee_multiple_periods'] = $columns['allow_split_shift']
            && (bool) ($data['allow_same_employee_multiple_periods'] ?? false);

        /* A ceiling and a gap only mean something once one person may work
           more than one period; keeping the last values would leave a rule
           quietly holding limits it does not apply. */
        if (! $columns['allow_same_employee_multiple_periods']) {
            $columns['max_periods_per_employee_per_day'] = 2;
            $columns['min_gap_minutes'] = null;
        }

        /* A break of "none" carries no length and no times; keeping the last
           values would leave a rule quietly holding a break it does not
           have. Same for overtime figures once overtime is switched off. */
        if (($columns['break_type'] ?? 'none') === 'none') {
            $columns['break_minutes'] = null;
            $columns['break_starts_at'] = null;
            $columns['break_ends_at'] = null;
        } elseif ($columns['break_type'] === 'duration') {
            $columns['break_starts_at'] = null;
            $columns['break_ends_at'] = null;
        }

        if (! $columns['allow_overtime']) {
            $columns['overtime_after_hours'] = null;
            $columns['max_overtime_hours'] = null;
        }

        return $columns;
    }

    /** @param array<string, mixed> $data */
    private function syncLocations(ShiftRule $rule, array $data): void
    {
        /* Emptied when the rule applies everywhere: a dormant list of
           branches is a second answer waiting to contradict the first. */
        $rule->locations()->sync(
            ($data['location_scope'] ?? 'all') === 'specific' ? ($data['locations'] ?? []) : []
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ShiftRule $rule): array
    {
        $tenantId = $request->user()->tenant?->getTenantKey();
        $limits = config('shift_rules.limits');

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                /* Unique within the business: two rules called "Weekend
                   Shift" is a schedule nobody can be sure they picked right. */
                Rule::unique('shift_rules', 'name')->where('tenant_id', $tenantId)->ignore($rule?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'location_scope' => ['required', Rule::in(array_keys(config('shift_rules.location_scopes')))],
            'locations' => ['array'],
            'locations.*' => [Rule::exists('locations', 'id')->where('tenant_id', $tenantId)],
            'status' => ['required', Rule::in(array_keys(config('shift_rules.statuses')))],

            'break_type' => ['required', Rule::in(array_keys(config('shift_rules.break_types')))],
            'break_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'break_starts_at' => ['nullable', 'date_format:H:i'],
            'break_ends_at' => ['nullable', 'date_format:H:i', 'after:break_starts_at'],

            'max_hours_per_day' => ['nullable', 'integer', 'min:1', 'max:'.$limits['max_hours_per_day']],
            'max_hours_per_week' => ['nullable', 'integer', 'min:1', 'max:'.$limits['max_hours_per_week']],
            'min_hours_per_shift' => ['nullable', 'integer', 'min:1', 'max:'.$limits['max_hours_per_day']],
            'max_hours_per_shift' => ['nullable', 'integer', 'min:1', 'max:'.$limits['max_hours_per_day']],
            'min_rest_hours' => ['nullable', 'integer', 'min:1', 'max:'.$limits['max_rest_hours']],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:'.$limits['max_consecutive_days']],

            'allow_overtime' => ['nullable', 'boolean'],
            'overtime_after_hours' => ['nullable', 'integer', 'min:1', 'max:'.$limits['max_hours_per_week']],
            'max_overtime_hours' => ['nullable', 'integer', 'min:1', 'max:'.$limits['max_hours_per_week']],
            'allow_adjustment' => ['nullable', 'boolean'],
            'allow_split_shift' => ['nullable', 'boolean'],
            'allow_same_employee_multiple_periods' => ['nullable', 'boolean'],
            'max_periods_per_employee_per_day' => ['nullable', 'integer', 'min:1', 'max:6'],
            'min_gap_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],

            /* The named divisions of the business day. Every field is
               checked here; whether a period fits inside the business's own
               hours needs those hours, so it is answered below. */
            'periods' => ['array', 'max:12'],
            'periods.*.name' => ['nullable', 'string', 'max:80'],
            'periods.*.starts_at' => ['nullable', 'date_format:H:i'],
            'periods.*.ends_at' => ['nullable', 'date_format:H:i'],
            'periods.*.break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'periods.*.is_active' => ['nullable', 'boolean'],

            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ], [
            'name.required' => __('shift_rules.validation.name_required'),
            'name.unique' => __('shift_rules.validation.name_taken'),
            'break_ends_at.after' => __('shift_rules.validation.ends_after_starts'),
            'effective_until.after_or_equal' => __('shift_rules.validation.until_before_from'),
            'max_hours_per_week.min' => __('shift_rules.validation.weekly_hours_positive'),
        ]);

        $data['allow_overtime'] = $request->boolean('allow_overtime');
        $data['allow_adjustment'] = $request->boolean('allow_adjustment');
        $data['allow_split_shift'] = $request->boolean('allow_split_shift');
        $data['allow_same_employee_multiple_periods'] = $request->boolean('allow_same_employee_multiple_periods');

        $this->guardAgainstImpossibleRule($data);

        return $data;
    }

    /**
     * The things a valid field-by-field submission can still get wrong.
     *
     * Each needs more than one answer to decide — whether the break fits
     * needs the business's shortest open day, whether the shift bounds agree
     * needs both of them — which is exactly what a per-field rule cannot see.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardAgainstImpossibleRule(array $data): void
    {
        $breakType = $data['break_type'] ?? 'none';

        if ($breakType === 'duration' && empty($data['break_minutes'])) {
            throw ValidationException::withMessages([
                'break_minutes' => __('shift_rules.validation.break_minutes_required'),
            ]);
        }

        if ($breakType === 'fixed' && (empty($data['break_starts_at']) || empty($data['break_ends_at']))) {
            throw ValidationException::withMessages([
                'break_starts_at' => __('shift_rules.validation.break_times_required'),
            ]);
        }

        /**
         * A break has to fit inside the shortest day the business is open.
         *
         * Measured against the business's own week rather than a copy on the
         * rule: the hours are one fact, and checking against a second would
         * eventually pass a break the real week cannot hold.
         */
        $shortestDay = $this->shortestOpenDayMinutes($data);

        $breakMinutes = $breakType === 'fixed'
            ? ShiftRule::minutesBetween($data['break_starts_at'] ?? null, $data['break_ends_at'] ?? null)
            : (int) ($data['break_minutes'] ?? 0);

        if ($breakType !== 'none' && $shortestDay !== null && $breakMinutes >= $shortestDay) {
            throw ValidationException::withMessages([
                'break_minutes' => __('shift_rules.validation.break_too_long'),
            ]);
        }

        $min = $data['min_hours_per_shift'] ?? null;
        $max = $data['max_hours_per_shift'] ?? null;

        if ($min !== null && $max !== null && (int) $min > (int) $max) {
            throw ValidationException::withMessages([
                'min_hours_per_shift' => __('shift_rules.validation.min_shift_over_max'),
            ]);
        }

        if (($data['location_scope'] ?? 'all') === 'specific' && empty($data['locations'])) {
            throw ValidationException::withMessages([
                'locations' => __('shift_rules.validation.locations_required'),
            ]);
        }

        $this->guardShiftPeriods($data);
    }

    /**
     * The named divisions of the business day.
     *
     * Only checked while split shifts are on: the rows keep posting when the
     * switch is off so that turning it back on does not cost the reader what
     * they wrote, and refusing a submission over a section nobody can see
     * would be refusing it for a reason they cannot act on.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardShiftPeriods(array $data): void
    {
        if (! ($data['allow_split_shift'] ?? false)) {
            return;
        }

        $rows = collect($data['periods'] ?? [])
            ->filter(fn ($row) => filled($row['name'] ?? null)
                || filled($row['starts_at'] ?? null)
                || filled($row['ends_at'] ?? null));

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'periods' => __('shift_rules.validation.periods_required'),
            ]);
        }

        $openHours = $this->openHoursFor($data);
        $messages = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $starts = $row['starts_at'] ?? null;
            $ends = $row['ends_at'] ?? null;

            if ($name === '') {
                $messages['periods.'.$index.'.name'] = __('shift_rules.validation.period_name_required');
            }

            if (! $starts || ! $ends) {
                $messages['periods.'.$index.'.window'] = __('shift_rules.validation.period_times_required');

                continue;
            }

            if (ShiftRule::minutesBetween($starts, $ends) <= 0) {
                $messages['periods.'.$index.'.window'] = __('shift_rules.validation.period_ends_after_starts');

                continue;
            }

            if ((int) ($row['break_minutes'] ?? 0) >= ShiftRule::minutesBetween($starts, $ends)) {
                $messages['periods.'.$index.'.window'] = __('shift_rules.validation.period_break_too_long');

                continue;
            }

            /**
             * Inside the business's own hours on at least one open day.
             *
             * At least one rather than every one: a business open Monday to
             * Friday nine to five and Saturday ten to four would otherwise
             * refuse a nine-o'clock morning shift on Saturday's account, when
             * the period is perfectly usable Monday to Friday. A period that
             * fits no day at all can never be worked, and that is what this
             * refuses.
             */
            $fits = $openHours->isEmpty() || $openHours->contains(
                fn ($hour) => $hour->timeValue('opens_at') <= $starts && $hour->timeValue('closes_at') >= $ends
            );

            if (! $fits) {
                $messages['periods.'.$index.'.window'] = __('shift_rules.validation.period_outside_business_hours');
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * The business's open periods, for the branch this rule reads.
     *
     * Empty when the business keeps no hours at all — there is then nothing
     * for a period to fall outside of, and refusing every one would be
     * refusing them for a reason the reader cannot act on from this screen.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, LocationHour>
     */
    private function openHoursFor(array $data): Collection
    {
        $location = ($data['location_scope'] ?? 'all') === 'specific' && count($data['locations'] ?? []) === 1
            ? Location::query()->find($data['locations'][0])
            : Location::query()->orderByDesc('is_primary')->orderBy('name')->first();

        return $location === null
            ? collect()
            : $location->hours->where('is_open', true)->values();
    }

    /**
     * Replace the periods, rather than reconcile them.
     *
     * The form posts the whole list every time, so working out which rows
     * moved would be more code and more ways to be wrong than writing them
     * again. Inside the same transaction as the rule itself, so a failure
     * halfway cannot leave a rule with its periods deleted and nothing to
     * replace them.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncPeriods(ShiftRule $rule, array $data): void
    {
        $rule->shiftPeriods()->delete();

        $order = 0;

        foreach ($data['periods'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            /* A blank row is somebody who pressed Add and changed their mind,
               not a period from midnight to midnight. */
            if ($name === '' || empty($row['starts_at']) || empty($row['ends_at'])) {
                continue;
            }

            $rule->shiftPeriods()->create([
                'name' => $name,
                'starts_at' => $row['starts_at'],
                'ends_at' => $row['ends_at'],
                'break_minutes' => ($row['break_minutes'] ?? '') === '' ? null : (int) $row['break_minutes'],
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * The shortest day the business is open, in minutes.
     *
     * Null when it keeps no hours at all — there is then nothing for a break
     * to be too long for, and refusing the rule would be refusing it for a
     * reason the reader cannot act on from this screen.
     */
    private function shortestOpenDayMinutes(array $data): ?int
    {
        $location = ($data['location_scope'] ?? 'all') === 'specific' && count($data['locations'] ?? []) === 1
            ? Location::query()->find($data['locations'][0])
            : Location::query()->orderByDesc('is_primary')->orderBy('name')->first();

        if ($location === null) {
            return null;
        }

        $byDay = $location->hours->where('is_open', true)
            ->groupBy('day_of_week')
            ->map(fn ($periods) => $periods->sum(
                fn ($hour) => ShiftRule::minutesBetween($hour->timeValue('opens_at'), $hour->timeValue('closes_at'))
            ));

        return $byDay->isEmpty() ? null : (int) $byDay->min();
    }
}
