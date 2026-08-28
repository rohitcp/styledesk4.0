{{--
    The service fields, shared by add and edit.

    One copy rather than two: the pair of screens differ only in where they
    post and what they start with, and a field added to one of two copies is
    a field that silently stops being edited.

    `$service` is null when adding. Every value reads old() first, so a
    submission the server refused comes back with what was typed rather than
    with what was stored — the reason a page beats the dialog this replaced.
--}}

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.section.about') }}</h2>

    <div class="space-y-4 mt-4">
        <x-text-field name="name" :label="__('services.name')" required maxlength="120"
                      :value="old('name', $service?->name)" />

        <x-combo name="service_category_id" :label="__('services.category')"
                 :options="$categories->pluck('name', 'id')"
                 :selected="old('service_category_id', $service?->service_category_id)"
                 :placeholder="__('services.uncategorised')" />

        <div>
            <label for="serviceDescription" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('services.description') }}
                <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </label>
            <textarea id="serviceDescription" name="description" rows="3" class="sd-input !h-auto py-2.5"
                      maxlength="1000">{{ old('description', $service?->description) }}</textarea>
        </div>
    </div>
</section>

{{-- The four periods together, under one heading. Apart they read as four
     unrelated numbers; together it is visibly one question — how much of the
     diary does this take. --}}
<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.timings') }}</h2>
    <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ __('services.processing_hint') }}</p>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-4">
        <div>
            <label for="serviceDuration" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('services.duration') }} <span class="text-danger">*</span>
            </label>
            <input id="serviceDuration" name="duration_minutes" type="number" class="sd-input" required
                   min="1" max="{{ config('service_options.max_duration_minutes') }}"
                   value="{{ old('duration_minutes', $service?->duration_minutes ?? config('service_options.default_duration_minutes')) }}">
        </div>

        @foreach ([
            'preparation_minutes' => 'preparation',
            'processing_minutes' => 'processing',
            'cleanup_minutes' => 'cleanup',
            'buffer_minutes' => 'buffer',
        ] as $field => $key)
            <div>
                <label for="service_{{ $field }}" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('services.'.$key) }}</label>
                <input id="service_{{ $field }}" name="{{ $field }}" type="number" class="sd-input"
                       min="0" max="{{ config('service_options.max_ancillary_minutes') }}"
                       value="{{ old($field, $service?->{$field} ?? 0) }}">
            </div>
        @endforeach
    </div>

    @error('duration_minutes')<p class="mt-2 text-[12px] text-danger">{{ $message }}</p>@enderror
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.section.price') }}</h2>

    <div class="mt-4">
        <x-price-input name="price" :label="__('services.price')" :values="$priceValues" />
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.section.who_where') }}</h2>

    {{-- Empty means "anyone" and "everywhere", which is what a single-site
         business never has to think about. --}}
    <div class="space-y-4 mt-4">
        <x-combo name="staff" multiple :label="__('services.staff')"
                 :hint="__('services.staff_hint')"
                 :selected="old('staff', $service?->staff->pluck('id')->all() ?? [])"
                 :options="$staff->mapWithKeys(fn ($member) => [$member->id => $member->first_name.' '.$member->last_name])" />

        <x-combo name="locations" multiple :label="__('services.locations')"
                 :hint="__('services.locations_hint')"
                 :selected="old('locations', $service?->locations->pluck('id')->all() ?? [])"
                 :options="$locations->pluck('name', 'id')" />
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.section.booking') }}</h2>

    <div class="space-y-4 mt-4">
        <x-color-picker name="color" :label="__('services.color')" :value="$service?->color" />

        {{-- A rule across the card, separating the colour from the rules
             below it: without it the swatches and the switches read as one
             run of settings. --}}
        <hr class="border-line -mx-5">

        <div class="space-y-2.5">
            <x-toggle name="online_booking_enabled" :label="__('services.online_booking')"
                      :hint="__('services.online_booking_hint')"
                      :checked="(bool) old('online_booking_enabled', $service?->online_booking_enabled ?? true)" />

            <x-toggle name="requires_resource" :label="__('services.requires_resource')"
                      :hint="__('services.requires_resource_hint')"
                      :checked="(bool) old('requires_resource', $service?->requires_resource ?? false)" />

            <x-toggle name="deposit_required" :label="__('services.deposit_required')"
                      :hint="__('services.deposit_required_hint')"
                      :checked="(bool) old('deposit_required', $service?->deposit_required ?? false)" />
        </div>
    </div>
</section>
