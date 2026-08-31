{{--
    The shift-rule fields, shared by add and edit.

    One copy rather than two: the pair of screens differ only in where they
    post and what they start with, and a field added to one of two copies is a
    field that silently stops being edited.
--}}
@php
    $rule = $rule ?? null;

    $ruleValue = fn (string $field, $fallback = null) => old($field, $rule?->{$field} ?? $fallback);

    $scope = old('location_scope', $rule?->location_scope ?? 'all');
    $breakType = old('break_type', $rule?->break_type ?? 'none');

    $chosenLocations = array_map('strval', old('locations', $rule?->locations->pluck('id')->all() ?? []));

    $splitOn = (bool) old('allow_split_shift', $rule?->allow_split_shift ?? false);
    $sameEmployeeOn = (bool) old('allow_same_employee_multiple_periods', $rule?->allow_same_employee_multiple_periods ?? false);

    /**
     * The periods, old input first.
     *
     * A refused submission must not silently drop the rows somebody typed —
     * they are the longest thing on this form to re-enter.
     */
    $periodRows = collect(old('periods', $rule?->shiftPeriods->map(fn ($period) => [
        'name' => $period->name,
        'starts_at' => $period->timeValue('starts_at'),
        'ends_at' => $period->timeValue('ends_at'),
        'break_minutes' => $period->break_minutes,
        'is_active' => $period->is_active,
    ])->all() ?? []))->map(fn ($row) => [
        'name' => $row['name'] ?? '',
        'starts_at' => $row['starts_at'] ?? '',
        'ends_at' => $row['ends_at'] ?? '',
        'break_minutes' => $row['break_minutes'] ?? '',
        'is_active' => (bool) ($row['is_active'] ?? true),
    ])->values()->all();

    /**
     * Messages gathered per row.
     *
     * Validation keys are concrete — periods.1.starts_at — so a row's messages
     * are collected by prefix rather than looked up by a wildcard, which
     * matches nothing.
     */
    $periodErrors = [];

    foreach ($errors->getMessages() as $key => $messages) {
        if (str_starts_with($key, 'periods.')) {
            $periodErrors[substr($key, strlen('periods.'))] = $messages[0];
        }
    }

@endphp

<section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('shift_rules.sections.basics') }}</h2>

    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
        <x-text-field name="name" :label="__('shift_rules.fields.name')" required maxlength="120"
                      :value="$ruleValue('name')" rules="required|max:120" />

        <x-combo name="status" required rules="required" :label="__('shift_rules.fields.status')"
                 :options="collect(config('shift_rules.statuses'))->mapWithKeys(fn ($s, $key) => [$key => __('shift_rules.statuses.'.$key)])"
                 :selected="$ruleValue('status', 'active')" />
    </div>

    <div>
        <label for="description" class="block text-[13px] font-medium text-ink mb-1.5">
            {{ __('shift_rules.fields.description') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
        </label>
        <textarea id="description" name="description" rows="2" class="sd-input !h-auto py-2.5" maxlength="1000"
                  placeholder="{{ __('shift_rules.fields.description_placeholder') }}">{{ $ruleValue('description') }}</textarea>
        @error('description')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    {{-- "Everywhere" and "no branch chosen yet" are different answers, so the
         scope is its own field rather than inferred from an empty list. --}}
    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4" data-scope>
        <x-combo name="location_scope" required rules="required" :label="__('shift_rules.fields.location_scope')"
                 :options="collect(config('shift_rules.location_scopes'))->mapWithKeys(fn ($s, $key) => [$key => __('shift_rules.location_scopes.'.$key)])"
                 :selected="$scope" />

        <div data-scope-locations @unless ($scope === 'specific') hidden @endunless>
            <x-combo name="locations" multiple required
                     :label="__('shift_rules.fields.locations')"
                     :options="$locations->pluck('name', 'id')"
                     :selected="$chosenLocations"
                     :placeholder="__('shift_rules.fields.locations_placeholder')" />
        </div>
    </div>
</section>

{{-- Working days and hours, read-only.

     A shift rule keeps no hours of its own. When the business is open is one
     fact, held once in the business's working hours and read by scheduling,
     booking, resources and the calendar alike — a copy here would be a second
     answer, free to drift the moment somebody changed the business's Monday
     and not the rule's.

     So this section shows that week rather than asking for one, and offers the
     way to change it at its source. --}}
<section class="bg-white border border-line rounded-card p-5">
    <div class="flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
            <h2 class="text-[15px] font-semibold text-head">{{ __('shift_rules.sections.days') }}</h2>
            <p class="text-[13px] text-sub mt-1 leading-relaxed max-w-[560px]">
                {{ __('shift_rules.hours_are_global') }}
            </p>
        </div>

        {{-- Only for somebody who may change business settings. Everyone who
             can open this screen can, but the check is made where the action
             is offered rather than assumed from the address. --}}
        @if ($hoursLocation)
            @can('update', $hoursLocation)
                <a href="{{ route('settings.hours.edit', $hoursLocation) }}" class="styledesk_action shrink-0">
                    <x-icon name="pen-to-square" size="13" />
                    {{ __('shift_rules.edit_business_hours') }}
                </a>
            @endcan
        @endif
    </div>

    @if ($hoursLocation === null)
        <p class="mt-4 text-[13px] text-sub">{{ __('shift_rules.no_location_yet') }}</p>
    @else
        {{-- Named, because the hours belong to a branch: a business with three
             of them would otherwise be shown a week and left to guess whose. --}}
        <p class="mt-4 text-[12px] font-semibold text-sub">{{ $hoursLocation->name }}</p>

        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left text-sub border-b border-line">
                        <th class="font-medium py-2 pr-4">{{ __('shift_rules.columns.day') }}</th>
                        <th class="font-medium py-2">{{ __('shift_rules.columns.business_hours') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (__('locations.weekdays') as $day => $dayName)
                        @php
                            $periods = $businessHours[$day] ?? collect();
                        @endphp
                        <tr class="border-b border-line last:border-0">
                            <td class="py-2 pr-4 text-ink">{{ $dayName }}</td>
                            <td class="py-2 {{ $periods->isEmpty() ? 'text-faint' : 'text-ink' }}">
                                {{-- A day the business is shut is a fact, said
                                     rather than left as a gap that reads as
                                     missing data. --}}
                                {{ $periods->isEmpty()
                                    ? __('shift_rules.closed')
                                    : $periods->map->rangeLabel()->implode(', ') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="bg-white border border-line rounded-card p-5 space-y-4" data-break>
    <h2 class="text-[15px] font-semibold text-head">{{ __('shift_rules.sections.break') }}</h2>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-4">
        <x-combo name="break_type" required rules="required" :label="__('shift_rules.fields.break_type')"
                 :options="collect(config('shift_rules.break_types'))->mapWithKeys(fn ($t, $key) => [$key => __('shift_rules.break_types.'.$key)])"
                 :selected="$breakType" />

        {{-- A length, for a break placed inside the shift when it is
             scheduled. Any number of minutes is accepted, which is what makes
             "Custom" possible without a second control. --}}
        <div data-break-duration @unless ($breakType === 'duration') hidden @endunless>
            <label for="break_minutes" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('shift_rules.fields.break_minutes') }}
            </label>
            <input id="break_minutes" name="break_minutes" type="number" class="sd-input" min="1" max="480"
                   data-rules="integer|min:1|max:480"
                   list="break-durations" value="{{ $ruleValue('break_minutes') }}">
            <datalist id="break-durations">
                @foreach (config('shift_rules.break_durations') as $minutes)
                    <option value="{{ $minutes }}">{{ __('shift_rules.minutes', ['count' => $minutes]) }}</option>
                @endforeach
            </datalist>
            {{-- One box for both kinds of message: the server writes into it
                 when it refuses a submission, the browser writes into the same
                 one while the reader types. --}}
            <p data-error-for="break_minutes" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('break_minutes')) hidden @endunless>{{ $errors->first('break_minutes') }}</p>
        </div>

        {{-- A named hour — twelve until one. --}}
        <div data-break-fixed class="contents" @unless ($breakType === 'fixed') hidden @endunless>
            <x-time-field name="break_starts_at" :label="__('shift_rules.fields.break_starts_at')"
                          :value="$rule?->break_starts_at" />
            <x-time-field name="break_ends_at" :label="__('shift_rules.fields.break_ends_at')"
                          :value="$rule?->break_ends_at" />
        </div>
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('shift_rules.sections.limits') }}</h2>
    <p class="text-[13px] text-sub leading-relaxed">{{ __('shift_rules.fields.min_rest_hours_hint') }}</p>

    {{-- Every limit optional: one nobody has set is not a limit of zero. --}}
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-4">
        @foreach ([
            'max_hours_per_day' => config('shift_rules.limits.max_hours_per_day'),
            'max_hours_per_week' => config('shift_rules.limits.max_hours_per_week'),
            'min_hours_per_shift' => config('shift_rules.limits.max_hours_per_day'),
            'max_hours_per_shift' => config('shift_rules.limits.max_hours_per_day'),
            'min_rest_hours' => config('shift_rules.limits.max_rest_hours'),
            'max_consecutive_days' => config('shift_rules.limits.max_consecutive_days'),
        ] as $field => $max)
            <div>
                <label for="{{ $field }}" class="block text-[13px] font-medium text-ink mb-1.5">
                    {{ __('shift_rules.fields.'.$field) }}
                    <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <div class="flex items-center gap-2">
                    <input id="{{ $field }}" name="{{ $field }}" type="number" class="sd-input" min="1" max="{{ $max }}"
                           data-rules="integer|min:1|max:{{ $max }}"
                           value="{{ $ruleValue($field) }}">
                    <span class="text-[12px] text-sub shrink-0">
                        {{ $field === 'max_consecutive_days' ? __('shift_rules.fields.days_unit') : __('shift_rules.fields.hours_unit') }}
                    </span>
                </div>
                <p data-error-for="{{ $field }}" role="alert" class="mt-1.5 text-[12px] text-danger"
                   @unless ($errors->has($field)) hidden @endunless>{{ $errors->first($field) }}</p>
            </div>
        @endforeach
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('shift_rules.sections.flexibility') }}</h2>

    <div class="space-y-2.5" data-overtime>
        <x-toggle name="allow_overtime" :label="__('shift_rules.fields.allow_overtime')"
                  :checked="(bool) old('allow_overtime', $rule?->allow_overtime ?? false)"
                  data-overtime-toggle />

        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4 pl-[3.25rem]" data-overtime-fields
             @unless ((bool) old('allow_overtime', $rule?->allow_overtime ?? false)) hidden @endunless>
            @foreach (['overtime_after_hours', 'max_overtime_hours'] as $field)
                <div>
                    <label for="{{ $field }}" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('shift_rules.fields.'.$field) }}
                    </label>
                    <div class="flex items-center gap-2">
                        <input id="{{ $field }}" name="{{ $field }}" type="number" class="sd-input" min="1"
                               max="{{ config('shift_rules.limits.max_hours_per_week') }}"
                               data-rules="integer|min:1|max:{{ config('shift_rules.limits.max_hours_per_week') }}"
                               value="{{ $ruleValue($field) }}">
                        <span class="text-[12px] text-sub shrink-0">{{ __('shift_rules.fields.hours_per_week') }}</span>
                    </div>
                    <p data-error-for="{{ $field }}" role="alert" class="mt-1.5 text-[12px] text-danger"
                       @unless ($errors->has($field)) hidden @endunless>{{ $errors->first($field) }}</p>
                </div>
            @endforeach
        </div>

        <x-toggle name="allow_adjustment" :label="__('shift_rules.fields.allow_adjustment')"
                  :hint="__('shift_rules.fields.allow_adjustment_hint')"
                  :checked="(bool) old('allow_adjustment', $rule?->allow_adjustment ?? true)" />

        {{-- Three separate ideas, and the copy is where they are kept apart:
             the business's hours say when it is open, the periods below say
             how that day is divided, and this says whether one person may
             work more than one of them. --}}
        <x-toggle name="allow_split_shift" :label="__('shift_rules.fields.allow_split_shift')"
                  :hint="__('shift_rules.fields.allow_split_shift_hint')"
                  :checked="$splitOn" data-split-toggle />
    </div>
</section>

{{-- The split-shift settings, shown only while the switch above is on. The
     periods keep posting while hidden, so switching off and on again does not
     cost the reader the periods they wrote. --}}
<section class="bg-white border border-line rounded-card p-5 space-y-5" data-split-fields
         @unless ($splitOn) hidden @endunless>
    <div>
        <h2 class="text-[15px] font-semibold text-head">{{ __('shift_rules.sections.split') }}</h2>
        <p class="text-[13px] text-sub mt-1 leading-relaxed max-w-[600px]">
            {{ __('shift_rules.split_intro') }}
        </p>
    </div>

    @php
        $periodProps = [
            'initial' => $periodRows,
            'errors' => (object) $periodErrors,
            'breakDurations' => config('shift_rules.break_durations'),
            'labels' => __('shift_rules.periods'),
        ];
    @endphp

    <div data-vue-component="ShiftPeriods" data-props='@json($periodProps)'></div>

    @error('periods')<p class="text-[12px] text-danger">{{ $message }}</p>@enderror

    <hr class="border-line -mx-5">

    <div class="space-y-2.5" data-same-employee>
        <x-toggle name="allow_same_employee_multiple_periods"
                  :label="__('shift_rules.fields.allow_same_employee_multiple_periods')"
                  :hint="__('shift_rules.fields.allow_same_employee_multiple_periods_hint')"
                  :checked="$sameEmployeeOn" data-same-employee-toggle />

        {{-- Only meaningful once one person may work more than one period:
             a ceiling of two on somebody who may only work one says nothing. --}}
        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4 pl-[3.25rem]" data-same-employee-fields
             @unless ($sameEmployeeOn) hidden @endunless>
            <div>
                <label for="max_periods_per_employee_per_day" class="block text-[13px] font-medium text-ink mb-1.5">
                    {{ __('shift_rules.fields.max_periods_per_employee_per_day') }}
                </label>
                <input id="max_periods_per_employee_per_day" name="max_periods_per_employee_per_day"
                       type="number" class="sd-input" min="1" max="6"
                       data-rules="integer|min:1|max:6"
                       value="{{ $ruleValue('max_periods_per_employee_per_day', 2) }}">
                <p data-error-for="max_periods_per_employee_per_day" role="alert" class="mt-1.5 text-[12px] text-danger"
                   @unless ($errors->has('max_periods_per_employee_per_day')) hidden @endunless>{{ $errors->first('max_periods_per_employee_per_day') }}</p>
            </div>

            <div>
                <label for="min_gap_minutes" class="block text-[13px] font-medium text-ink mb-1.5">
                    {{ __('shift_rules.fields.min_gap_minutes') }}
                    <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <div class="flex items-center gap-2">
                    <input id="min_gap_minutes" name="min_gap_minutes" type="number" class="sd-input"
                           min="0" max="720" step="15" data-rules="integer|min:0|max:720"
                           value="{{ $ruleValue('min_gap_minutes') }}">
                    <span class="text-[12px] text-sub shrink-0">{{ __('shift_rules.fields.minutes_unit') }}</span>
                </div>
                <p class="mt-1.5 text-[12px] text-sub">{{ __('shift_rules.fields.min_gap_hint') }}</p>
                <p data-error-for="min_gap_minutes" role="alert" class="mt-1.5 text-[12px] text-danger"
                   @unless ($errors->has('min_gap_minutes')) hidden @endunless>{{ $errors->first('min_gap_minutes') }}</p>
            </div>
        </div>
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('shift_rules.sections.dates') }}</h2>
    <p class="text-[13px] text-sub leading-relaxed">{{ __('shift_rules.fields.effective_hint') }}</p>

    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
        <x-date-field name="effective_from" :label="__('shift_rules.fields.effective_from')" optional
                      :value="old('effective_from', $rule?->effective_from?->toDateString())"
                      :min-year="now()->year - 1" :max-year="now()->year + 5" rules="date" />

        <x-date-field name="effective_until" :label="__('shift_rules.fields.effective_until')" optional
                      :value="old('effective_until', $rule?->effective_until?->toDateString())"
                      :min-year="now()->year - 1" :max-year="now()->year + 5" rules="date" />
    </div>
</section>

@if ($rule)
    {{-- Read-only here on purpose. Putting somebody on a rule is the Staff
         Schedule screen's job; App Settings is where the pattern is written,
         not where people are assigned to it. --}}
    <section class="bg-white border border-line rounded-card p-5">
        @php $assigned = $rule->staff; @endphp

        <h2 class="text-[15px] font-semibold text-head">{{ __('shift_rules.assigned_staff') }}</h2>
        <p class="text-[13px] text-ink mt-2">
            {{ trans_choice('shift_rules.assigned_count', $assigned->count(), ['count' => $assigned->count()]) }}
        </p>

        {{-- Named, not just counted: "3 staff members" leaves an administrator
             deciding whether to retire a rule with no way to see whose rota it
             would change. --}}
        @if ($assigned->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($assigned as $member)
                    <span class="styledesk_metachip">{{ $member->displayName() }}</span>
                @endforeach
            </div>
        @endif

        <p class="text-[12px] text-sub mt-3 leading-relaxed">{{ __('shift_rules.assigned_where') }}</p>
    </section>
@endif
