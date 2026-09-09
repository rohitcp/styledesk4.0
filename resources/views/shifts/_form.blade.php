{{--
    The shift fields, shared by add and edit.

    One copy rather than two: the pair of screens differ only in where they
    post and what they start with, and a field added to one of two copies is a
    field that silently stops being edited.
--}}
@php
    $shift = $shift ?? null;

    $shiftValue = fn (string $field, $fallback = null) => old($field, $shift?->{$field} ?? $fallback);

    $breakOptions = collect(config('shifts.breaks'))
        ->mapWithKeys(fn (int $minutes) => [
            $minutes => $minutes === 0 ? __('shifts.no_break') : __('shifts.minutes', ['count' => $minutes]),
        ]);
@endphp

<section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('shifts.fields.staff') }}</h2>

    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
        {{-- Only people who are actually here: a rota offering somebody who
             has left is a rota that will be wrong the moment it is used. --}}
        <x-combo name="staff_id" required
                 :label="__('shifts.fields.staff')"
                 :options="$staffOptions->mapWithKeys(fn ($member) => [$member->id => $member->displayName()])"
                 :selected="$shiftValue('staff_id')"
                 :placeholder="__('shifts.fields.staff_placeholder')" />

        <x-combo name="location_id"
                 :label="__('shifts.fields.location')"
                 :options="$locations->pluck('name', 'id')"
                 :selected="$shiftValue('location_id')"
                 :placeholder="__('shifts.fields.location_placeholder')" />
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('shifts.columns.hours') }}</h2>
    <p class="text-[13px] text-sub leading-relaxed">{{ __('shifts.intro') }}</p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-4">
        {{-- A shift may be set for a date already past — a rota is corrected
             after the fact as often as it is planned ahead. --}}
        <x-date-field name="date" required
                      :label="__('shifts.fields.date')"
                      :value="old('date', $shift?->date?->toDateString())"
                      :min-year="now()->year - 1"
                      :max-year="now()->year + 2"
                      rules="required|date" />

        <x-time-field name="starts_at" required
                      :label="__('shifts.fields.starts_at')"
                      :value="$shift?->timeValue('starts_at')" />

        <x-time-field name="ends_at" required
                      :label="__('shifts.fields.ends_at')"
                      :value="$shift?->timeValue('ends_at')" />

        {{-- Offered as a list rather than a free number: every business uses
             the same handful, and a text box invites "45 mins", "0.75" and
             "three quarters of an hour" into one column. --}}
        <x-combo name="break_minutes"
                 :label="__('shifts.fields.break')"
                 :hint="__('shifts.fields.break_hint')"
                 :options="$breakOptions"
                 :selected="$shiftValue('break_minutes', 0)" />
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('shifts.fields.type') }}</h2>

    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
        <x-combo name="type" required
                 :label="__('shifts.fields.type')"
                 :options="collect(config('shifts.types'))->mapWithKeys(fn ($t, $key) => [$key => __('shifts.types.'.$key)])"
                 :selected="$shiftValue('type', 'regular')" />

        <x-combo name="status" required
                 :label="__('shifts.fields.status')"
                 :options="collect(config('shifts.statuses'))->mapWithKeys(fn ($s, $key) => [$key => __('shifts.statuses.'.$key)])"
                 :selected="$shiftValue('status', 'scheduled')" />
    </div>

    <div>
        <label for="notes" class="block text-[13px] font-medium text-ink mb-1.5">
            {{ __('shifts.fields.notes') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
        </label>
        <textarea id="notes" name="notes" rows="3" class="sd-input !h-auto py-2.5" maxlength="1000"
                  placeholder="{{ __('shifts.fields.notes_placeholder') }}">{{ $shiftValue('notes') }}</textarea>
        @error('notes')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>
</section>
