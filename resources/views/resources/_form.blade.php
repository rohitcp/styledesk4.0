{{--
    The resource fields, shared by add and edit.

    One copy rather than two: the pair of screens differ only in where they
    post and what they start with, and a field added to one of two copies is a
    field that silently stops being edited. The client and service forms are
    built the same way.

    `$resource` is null when adding. Every value reads old() first, so a
    submission the server refused comes back with what was typed.
--}}
@php
    $minutes = collect(config('resources.ancillary_minutes'))
        ->mapWithKeys(fn (int $m) => [$m => $m === 0 ? __('resources.form.no_time') : __('resources.form.minutes', ['count' => $m])])
        ->all();

    $intervals = collect(config('resources.intervals'))
        ->mapWithKeys(fn (int $m) => [$m => __('resources.form.minutes', ['count' => $m])])
        ->all();

    $statusOptions = [
        1 => __('resources.form.active'),
        0 => __('resources.form.inactive'),
    ];

    $availabilityOptions = collect(config('resources.availability_statuses'))
        ->mapWithKeys(fn (string $key) => [$key => __('resources.form.availability.'.$key)])
        ->all();

    $currentType = old('availability_type', $resource?->availability_type ?? 'location');

    /* 0 is Sunday, matching Carbon — one convention, so nothing has to
       translate between two. The week is drawn Monday first because that is
       how a rota is read. */
    $week = [1, 2, 3, 4, 5, 6, 0];
    $savedHours = $resource?->hours->keyBy('day') ?? collect();
@endphp

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('resources.section.about') }}</h2>

    <div class="space-y-4 mt-4">
        <div class="grid sm:grid-cols-2 gap-4">
            <x-text-field name="name" :label="__('resources.name')" required maxlength="120"
                          :value="old('name', $resource?->name)" />

            <x-text-field name="code" :label="__('resources.form.code')" maxlength="40"
                          :value="old('code', $resource?->code)" />
        </div>

        {{-- Searchable, and each name carrying the section it sits under: the
             catalogue is thirty entries long and several read alike out of
             context — "Treatment room" and "Treatment bed" are different
             kinds of thing. --}}
        <x-combo name="resource_category_id" :label="__('resources.category')" required
                 :options="$categoryOptions"
                 :selected="old('resource_category_id', $resource?->resource_category_id)"
                 :placeholder="__('resources.choose_category')" />

        {{-- The same component the service form uses, from the same palette:
             a service block and the room it occupies appear on one calendar,
             and two pickers would eventually disagree. --}}
        <x-color-picker name="color" :label="__('resources.form.color')"
                        :hint="__('resources.form.color_hint')"
                        :value="$resource?->color" />

        <div>
            <label for="resourceDescription" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('resources.description') }}
                <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </label>
            <textarea id="resourceDescription" name="description" rows="3" class="sd-input !h-auto py-2.5"
                      maxlength="1000">{{ old('description', $resource?->description) }}</textarea>
        </div>
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('resources.form.place') }}</h2>

    <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <x-combo name="location_id" :label="__('resources.location')" required
                 :options="$locations->pluck('name', 'id')"
                 :selected="old('location_id', $resource?->location_id)"
                 :placeholder="__('resources.form.choose_location')" />

        <div>
            <label for="capacity" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('resources.capacity') }} <span class="text-danger">*</span>
            </label>
            <input id="capacity" name="capacity" type="number" class="sd-input" required
                   min="1" max="{{ config('resources.max_capacity') }}"
                   value="{{ old('capacity', $resource?->capacity ?? config('resources.default_capacity')) }}">
            <p class="text-[12px] text-sub mt-1.5 leading-relaxed">{{ __('resources.capacity_hint') }}</p>
            @error('capacity')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('resources.form.status') }}</h2>

    {{-- Two questions that read alike and are not: one is whether the
         business still has this thing, the other is whether it can be booked
         this week. --}}
    <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <x-combo name="is_active" :label="__('resources.form.resource_status')" required
                 :options="$statusOptions"
                 :selected="old('is_active', (int) ($resource?->is_active ?? true))"
                 :hint="__('resources.form.resource_status_hint')" />

        <x-combo name="availability_status" :label="__('resources.form.availability_status')" required
                 :options="$availabilityOptions"
                 :selected="old('availability_status', $resource?->availability_status ?? 'available')"
                 :hint="__('resources.form.availability_status_hint')" />
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('resources.form.availability_schedule') }}</h2>

    <div class="space-y-4 mt-4">
        {{-- Radios rather than a dropdown: there are two answers and the
             second reveals a week of fields, which a reader should be able to
             see the cost of before choosing it. --}}
        <div class="styledesk_choicelist" data-availability-type>
            @foreach (['location', 'custom'] as $type)
                <label class="styledesk_choice">
                    <input type="radio" name="availability_type" value="{{ $type }}"
                           @checked($currentType === $type)>
                    <span class="styledesk_choice__body">
                        <span class="styledesk_choice__label">{{ __('resources.form.type.'.$type) }}</span>
                        <span class="styledesk_choice__hint">{{ __('resources.form.type.'.$type.'_hint') }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        {{-- Shown only when the resource keeps its own week. Hidden rather
             than removed, so switching back and forth does not lose what was
             typed before the reader changed their mind. --}}
        <div class="space-y-2" data-custom-hours @if ($currentType !== 'custom') hidden @endif>
            @foreach ($week as $day)
                @php
                    $saved = $savedHours->get($day);
                    $isOpen = (bool) old("hours.$day.is_available", $saved?->is_available ?? ($day !== 0));
                    $from = old("hours.$day.starts_at", $saved?->starts_at ? substr((string) $saved->starts_at, 0, 5) : '09:00');
                    $until = old("hours.$day.ends_at", $saved?->ends_at ? substr((string) $saved->ends_at, 0, 5) : '17:00');
                @endphp

                <div class="styledesk_dayrow" data-day-row>
                    <label class="styledesk_daytoggle">
                        <input type="hidden" name="hours[{{ $day }}][is_available]" value="0">
                        <input type="checkbox" name="hours[{{ $day }}][is_available]" value="1"
                               @checked($isOpen) data-day-toggle>
                        <span class="styledesk_daytoggle__name">{{ __('locations.weekdays.'.$day) }}</span>
                    </label>

                    <div class="flex items-center gap-2 min-w-0" data-day-times @unless ($isOpen) hidden @endunless>
                        <div data-vue-component="TimePicker"
                             data-props='@json(['modelValue' => $from, 'name' => "hours[$day][starts_at]", 'ariaLabel' => __('resources.form.from')])'></div>

                        <span class="text-[12px] text-faint shrink-0">{{ __('resources.form.to') }}</span>

                        <div data-vue-component="TimePicker"
                             data-props='@json(['modelValue' => $until, 'name' => "hours[$day][ends_at]", 'ariaLabel' => __('resources.form.to')])'></div>
                    </div>

                    <span class="text-[12px] text-faint" data-day-closed @if ($isOpen) hidden @endif>
                        {{ __('resources.form.closed') }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('resources.form.booking') }}</h2>
    <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ __('resources.form.booking_hint') }}</p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        <x-combo name="booking_interval_minutes" :label="__('resources.form.interval')"
                 :options="$intervals"
                 :selected="old('booking_interval_minutes', $resource?->booking_interval_minutes)"
                 :placeholder="__('resources.form.inherit')" />

        <x-combo name="preparation_minutes" :label="__('resources.form.preparation')"
                 :options="$minutes"
                 :selected="old('preparation_minutes', $resource?->preparation_minutes ?? 0)" />

        <x-combo name="cleanup_minutes" :label="__('resources.form.cleanup')"
                 :options="$minutes"
                 :selected="old('cleanup_minutes', $resource?->cleanup_minutes ?? 0)" />

        <x-combo name="buffer_minutes" :label="__('resources.form.buffer')"
                 :options="$minutes"
                 :selected="old('buffer_minutes', $resource?->buffer_minutes ?? 0)" />
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('resources.form.services') }}</h2>

    <div class="mt-4">
        <x-combo name="services" multiple :label="__('resources.form.assigned_services')"
                 :hint="__('resources.form.assigned_services_hint')"
                 :options="$services->pluck('name', 'id')"
                 :selected="old('services', $resource?->services->pluck('id')->all() ?? [])" />
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('resources.form.notes') }}</h2>

    <div class="mt-4">
        <label for="internalNotes" class="block text-[13px] font-medium text-ink mb-1.5">
            {{ __('resources.form.internal_notes') }}
            <span class="text-faint font-normal">{{ __('common.optional') }}</span>
        </label>
        <textarea id="internalNotes" name="internal_notes" rows="3" class="sd-input !h-auto py-2.5"
                  maxlength="2000">{{ old('internal_notes', $resource?->internal_notes) }}</textarea>
        <p class="text-[12px] text-sub mt-1.5 leading-relaxed">{{ __('resources.form.internal_notes_hint') }}</p>
    </div>
</section>
