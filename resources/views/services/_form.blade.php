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
        {{-- The rules sit on the fields and resources/js/live-validation.js
             reads them, so a name left blank is answered beside the field
             while the form is being filled in rather than after a submission
             the reader has to redo. The server checks the same things again
             — see ServiceController::validated. --}}
        <x-text-field name="name" :label="__('services.name')" required maxlength="120"
                      rules="required|max:120"
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
                      data-rules="max:1000"
                      maxlength="1000">{{ old('description', $service?->description) }}</textarea>

            {{-- One box for both kinds of message, like every field the
                 x-text-field component draws: the server writes into it when
                 it refuses a submission and the browser writes into the same
                 one while the reader types, so a message never appears twice
                 or in two different places. --}}
            <p data-error-for="serviceDescription" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('description')) hidden @endunless>{{ $errors->first('description') }}</p>
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
                   data-rules="required|integer|min:1|max:{{ config('service_options.max_duration_minutes') }}"
                   min="1" max="{{ config('service_options.max_duration_minutes') }}"
                   value="{{ old('duration_minutes', $service?->duration_minutes ?? config('service_options.default_duration_minutes')) }}">

            {{-- The same words the listing uses for this service, under the
                 box that decides them. A number field can only hold minutes,
                 so without this the listing says "1 hr 10 min" about a
                 service this screen calls 70 — the same duration twice, in
                 two languages, and nothing on either screen to connect them. --}}
            <p id="serviceDurationReads" class="mt-1.5 text-[12px] text-sub">{{
                ($service ?? new \App\Models\Service(['duration_minutes' => (int) config('service_options.default_duration_minutes')]))
                    ->durationLabel((int) old('duration_minutes', $service?->duration_minutes ?? config('service_options.default_duration_minutes')))
            }}</p>

            {{-- Under the field it is about. The refusal used to be printed
                 at the foot of the section, which on a row of five numbers
                 says one of them is wrong without saying which. --}}
            <p data-error-for="serviceDuration" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('duration_minutes')) hidden @endunless>{{ $errors->first('duration_minutes') }}</p>
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
                       data-rules="integer|min:0|max:{{ config('service_options.max_ancillary_minutes') }}"
                       min="0" max="{{ config('service_options.max_ancillary_minutes') }}"
                       value="{{ old($field, $service?->{$field} ?? 0) }}">

                <p data-error-for="service_{{ $field }}" role="alert" class="mt-1.5 text-[12px] text-danger"
                   @unless ($errors->has($field)) hidden @endunless>{{ $errors->first($field) }}</p>
            </div>
        @endforeach
    </div>

</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.section.price') }}</h2>

    <div class="mt-4">
        <x-price-input name="price" :label="__('services.price')" :values="$priceValues"
                       :cash-values="$cashPriceValues" :deposits="$depositValues ?? null" />
    </div>
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.section.who_where') }}</h2>

    {{-- No staff named means anyone may perform it. A location, by
         contrast, has to be named. --}}
    <div class="space-y-4 mt-4">
        <x-combo name="staff" multiple :label="__('services.staff')"
                 :hint="__('services.staff_hint')"
                 :selected="old('staff', $service?->staff->pluck('id')->all() ?? [])"
                 :options="$staff->mapWithKeys(fn ($member) => [$member->id => $member->first_name.' '.$member->last_name])" />

        {{-- A single-site business has nothing to choose, so the one
             location is already selected on both the add and edit screens.
             The field is required, and pre-filling it saves every such
             business a click it could never answer differently. --}}
        @php
            $selectedLocations = old('locations', $service?->locations->pluck('id')->all() ?? []);

            if (! $selectedLocations && $locations->count() === 1) {
                $selectedLocations = [$locations->first()->id];
            }
        @endphp

        <x-combo name="locations" multiple required :label="__('services.locations')"
                 :hint="__('services.locations_hint')"
                 :selected="$selectedLocations"
                 :options="$locations->pluck('name', 'id')" />
    </div>
</section>

{{-- Pictures. Its own card rather than a field inside another, because the
     gallery is the tallest thing on the form and reads as a section of its
     own — and because the same island serves the onboarding wizard, where it
     sits in a repeater row. --}}
<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.images.label') }}</h2>

    @php
        $storage = app(\App\Contracts\TenantStorageContract::class);
        $maxOtherImages = \App\Services\ServiceImageSync::MAX_IMAGES - 1;

        $imageProps = [
            'name' => '',
            'initial' => $service?->orderedImages()->map(fn ($f) => [
                'id' => $f->id,
                'url' => $storage->url($f),
                'name' => $f->original_filename,
            ])->all() ?? [],
            'initialDefaultId' => $service?->image_file_id,
            'maxOthers' => $maxOtherImages,
            'uploadUrl' => route('services.images.store'),
            'deleteUrl' => route('services.images.destroy', ['storedFile' => '__ID__']),
            /* array_merge, not +: the union operator keeps the left-hand value
               for a duplicate key, which would hand the component the strings
               still carrying their :max placeholder. */
            'labels' => array_merge(__('services.images'), [
                'help' => __('services.images.help', ['max' => $maxOtherImages]),
                'too_many' => __('services.images.too_many', ['max' => $maxOtherImages + 1]),
            ]),
        ];
    @endphp

    {{-- An empty name prefix: on this form the ids post as plain images[] and
         default_image_id, where the wizard needs them nested per row. --}}
    <div class="mt-4" data-vue-component="ServiceImages" data-props='@json($imageProps)'></div>
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

            {{-- The switch and the list it governs, in one container.

                 Together rather than as two settings in a row: the list is
                 not a separate question but the rest of this one, and a
                 reader who turns the switch on has not finished answering
                 until they have said which rooms will do.

                 The list keeps posting while it is hidden — the inputs are
                 still in the form — so switching off and on again does not
                 cost somebody the mapping they built. --}}
            @php
                $requiresResource = (bool) old('requires_resource', $service?->requires_resource ?? false);
                $mappedResources = old('resources', $service?->resources->pluck('id')->all() ?? []);

                /* The list offers the resources in use, plus any this service
                   is already mapped to. Without the second half a room
                   retired since the mapping was made would be missing from
                   the options, and the chip for it would show a bare id. */
                $resourceOptions = $resources->pluck('name', 'id')
                    ->union($service?->resources->pluck('name', 'id') ?? collect());
            @endphp

            <div data-resource-requirement data-resource-message="{{ __('services.resources_required') }}">
                <x-toggle name="requires_resource" :label="__('services.requires_resource')"
                          :hint="__('services.requires_resource_hint')"
                          :checked="$requiresResource" data-resource-toggle />

                <div class="mt-3 pl-[3.25rem]" data-resource-fields @unless ($requiresResource) hidden @endunless>
                    <x-combo name="resources" multiple required
                             :label="__('services.resources')"
                             :hint="__('services.resources_hint')"
                             :placeholder="__('services.resources_placeholder')"
                             :selected="$mappedResources"
                             :options="$resourceOptions" />
                </div>
            </div>

            {{-- The deposit switch used to sit here as well as on the Price
                 card. It posted a value the save then threw away — the
                 service column is a summary of its prices, written by
                 syncPrices — so a reader who turned it on here found it off
                 again when they reopened the service. One switch, on the
                 price it is a deposit on. --}}
        </div>
    </div>
</section>

{{-- Tips.

     Only where the business takes them: a card asking how much to suggest,
     in a salon that has never tipped anybody, is a question with no answer.
     They switch it on in App settings → Tips and it appears here.

     Every field may be left as it is, and "follows the default" is a real
     value rather than a blank — a service that never disagreed should move
     when the business changes its mind, which a copied number would not. --}}
@if ($tips->is_enabled)
    @php
        $acceptsTips = (bool) old('accepts_tips', $service?->accepts_tips ?? true);

        /* A new service opens on what the business already suggests, so the
           common case is saved without opening this card at all. A service
           that exists keeps its own answer — including the blank one, which
           is a deliberate "follow the business" and must not be overwritten
           the next time the settings change. */
        $tipType = old('tip_type', $service ? $service->tip_type : $tips->default_tip_type);
        $tipValue = old('tip_value', $service ? $service->tip_value : $tips->default_tip_value);

        /* A flat sum is a sum of something: the quick picks wear the money
           this business prices in. */
        $tipSymbol = App\Support\Money::symbol(App\Support\Currencies::primaryFor(auth()->user()?->tenant));

        /* Built here rather than in the loop: a directive argument holding a
           comma inside brackets is not parsed, it is counted. */
        $tipTypeOptions = ['' => __('tips.follows_default')];

        foreach (App\Models\TipSettings::TYPES as $tipTypeOption) {
            $tipTypeOptions[$tipTypeOption] = __('tips.types.'.$tipTypeOption);
        }
    @endphp

    <section class="bg-white border border-line rounded-card p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('tips.title') }}</h2>
        <p class="text-[12px] text-sub mt-1">{{ __('tips.service_card_hint') }}</p>

        <div class="mt-4" data-tip-card>
            <x-toggle name="accepts_tips" :label="__('tips.accepts')"
                      :hint="__('tips.accepts_hint')"
                      :checked="$acceptsTips" data-tip-toggle />

            {{-- Everything below only means anything while that switch is on.
                 It keeps posting while hidden, so switching off and back on
                 does not cost somebody the tip they had set. --}}
            <div class="mt-4 pl-[3.25rem] space-y-5" data-tip-fields @unless ($acceptsTips) hidden @endunless>
                {{-- Three answers on one line rather than a dropdown: they
                     are the whole set, and the middle one changes what the
                     amounts below mean. --}}
                <fieldset>
                    <legend class="block text-[13px] font-medium text-ink mb-1.5">{{ __('tips.tip_type') }}</legend>

                    <div class="styledesk_seg" data-tip-type>
                        @foreach ($tipTypeOptions as $type => $typeLabel)
                            <label class="styledesk_seg__item">
                                <input type="radio" name="tip_type" value="{{ $type }}" class="sr-only" @checked((string) $tipType === (string) $type)>
                                <span>{{ $typeLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div>
                    <label for="serviceTipValue" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('tips.default_tip') }}
                    </label>

                    {{-- The quick picks are the business's own suggestions,
                         so the common answer is one click. They fill the box
                         beside them rather than replacing it: a service that
                         wants 18 where the business offers 15, 20 and 25 is
                         exactly the case this card exists for. --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex flex-wrap gap-2" data-tip-presets>
                            @foreach ($tips->offeredPercentages() as $percent)
                                <button type="button" class="styledesk_chipbtn" data-tip-preset="{{ $percent }}"
                                        data-tip-preset-for="percent">{{ $percent }}%</button>
                            @endforeach

                            @foreach (\App\Models\TipSettings::QUICK_FIXED_AMOUNTS as $amount)
                                <button type="button" class="styledesk_chipbtn" data-tip-preset="{{ $amount }}"
                                        data-tip-preset-for="fixed" hidden>{{ $tipSymbol }}{{ $amount }}</button>
                            @endforeach
                        </div>

                        <div class="w-[110px]">
                            <input id="serviceTipValue" name="tip_value" type="number" min="0" max="100"
                                   class="sd-input" placeholder="{{ __('tips.follows_default') }}"
                                   value="{{ $tipValue }}" data-tip-value>
                        </div>
                    </div>

                    <p class="text-[12px] text-faint mt-1.5">{{ __('tips.default_tip_hint') }}</p>
                </div>

                <div class="space-y-4 border-t border-line pt-4">
                    <x-toggle name="tip_required" :label="__('tips.require_selection')"
                              :hint="__('tips.require_selection_hint')"
                              :checked="(bool) old('tip_required', $service?->tip_required ?? $tips->require_selection)" />

                    <x-toggle name="allow_no_tip" :label="__('tips.allow_no_tip')"
                              :hint="__('tips.allow_no_tip_hint')"
                              :checked="(bool) old('allow_no_tip', $service?->allow_no_tip ?? $tips->allow_no_tip)" />
                </div>
            </div>
        </div>
    </section>
@endif
