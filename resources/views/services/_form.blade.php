{{--
    The service fields, shared by add and edit.

    One copy rather than two: the pair of screens differ only in where they
    post and what they start with, and a field added to one of two copies is
    a field that silently stops being edited.

    `$service` is null when adding. Every value reads old() first, so a
    submission the server refused comes back with what was typed rather than
    with what was stored — the reason a page beats the dialog this replaced.
--}}

@php
    /* The deposit, gathered into one answer.

       It is stored on each price — which is what the booking screen reads,
       and what lets a percentage settle against the price actually being
       charged — but it is asked for once. Every stored row carries the same
       policy, so the card reads whichever row answers first and the save
       writes the one answer back to all of them.

       old() first throughout, so a submission the server refused comes back
       with what was typed. */
    $storedDeposits = collect($depositValues ?? []);
    $storedDeposit = $storedDeposits->first(fn ($row) => (bool) ($row['required'] ?? false));

    $depositCurrencies = App\Support\Currencies::enabledFor(auth()->user()?->tenant);

    $depositOn = (bool) old('deposit_required', $storedDeposit !== null);
    $depositType = old('deposit_type', $storedDeposit['type'] ?? 'percent');

    $depositPercent = ($storedDeposit['type'] ?? null) === 'percent'
        ? ($storedDeposit['value'] ?? '')
        : '';

    /* Keyed by currency, because a fixed deposit is money and $25 is not
       also €25. A percentage needs no such split. */
    $depositAmounts = $storedDeposits
        ->map(fn ($row) => ($row['type'] ?? null) === 'fixed' ? (string) ($row['value'] ?? '') : '')
        ->all();

    $depositConfig = [
        'required' => $depositOn,
        'type' => $depositType,
        'percent' => old('deposit_percent', $depositPercent),
        'amounts' => $depositCurrencies
            ->mapWithKeys(fn (string $code) => [
                $code => old('deposit_amount.'.$code, $depositAmounts[$code] ?? ''),
            ])
            ->all(),
    ];
@endphp

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

{{-- The deposit, once, before the prices it governs.

     It used to be configured on each price, which meant a business pricing
     in three currencies answered the same question three times and could
     answer it three different ways. One card here, and every price below
     inherits it: a percentage works itself out against whatever each price
     is, so it never has to be restated. --}}
<section class="bg-white border border-line rounded-card p-5"
         data-deposit-card data-deposit-saved-type="{{ $depositType }}">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.section.deposit') }}</h2>
    <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ __('services.deposit_card_hint') }}</p>

    <div class="mt-4">
        <x-toggle class="styledesk_toggle--bare" name="deposit_required"
                  :label="__('services.deposit_take')" :hint="__('services.deposit_required_hint')"
                  :checked="$depositOn" data-deposit-toggle />
    </div>

    {{-- Hidden until the switch is on, so a service that takes no deposit is
         a card with one control in it. The fields keep posting while hidden;
         switching back and forth does not cost the number already typed. --}}
    <div class="mt-4 flex flex-wrap items-end gap-3" data-deposit-fields @unless ($depositOn) hidden @endunless>
        <div class="w-[170px]">
            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('services.deposit_type') }}</span>

            <x-combo data-deposit-type name="deposit_type"
                     :options="['percent' => __('services.deposit_types.percent'), 'fixed' => __('services.deposit_types.fixed')]"
                     :selected="$depositType"
                     :ariaLabel="__('services.deposit_type')" />
        </div>

        {{-- A percentage is one number however many currencies the business
             prices in — twenty per cent of each price is still twenty per
             cent. A fixed amount is money, and money is per currency: $25
             cannot also be €25, so that one gets a field per currency. --}}
        <div data-deposit-percent-field @if ($depositType === 'fixed') hidden @endif>
            <label for="serviceDepositPercent" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('services.deposit_percent') }}
            </label>

            <div class="relative w-[130px]">
                <span class="styledesk_input__suffix pointer-events-none text-sub" aria-hidden="true">%</span>

                <input id="serviceDepositPercent" type="text" inputmode="decimal" name="deposit_percent"
                       class="sd-input w-[130px]" data-deposit-percent autocomplete="off"
                       value="{{ old('deposit_percent', $depositPercent) }}"
                       aria-label="{{ __('services.deposit_percent') }}">
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3" data-deposit-amount-fields
             @unless ($depositType === 'fixed') hidden @endunless>
            @foreach ($depositCurrencies as $code)
                {{-- One box per currency the business prices in, whether or
                     not this service is priced in it yet: a flat sum is
                     money, and $25 is not also €25, so the question genuinely
                     has one answer per currency. A currency left blank
                     simply takes no deposit. --}}
                <div data-deposit-amount-for="{{ $code }}">
                    <label for="serviceDepositAmount_{{ $code }}" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ $depositCurrencies->count() > 1
                            ? __('services.deposit_amount').' · '.$code
                            : __('services.deposit_amount') }}
                    </label>

                    {{-- No symbol in the box: the label above says which
                         money this is, and a second reading of it inside
                         only crowds the amount. The prices below are written
                         the same way. --}}
                    <div class="relative w-[130px]">
                        <input id="serviceDepositAmount_{{ $code }}" type="text" inputmode="decimal"
                               name="deposit_amount[{{ $code }}]"
                               class="sd-input w-[130px]" data-deposit-amount
                               autocomplete="off"
                               value="{{ old('deposit_amount.'.$code, $depositAmounts[$code] ?? '') }}"
                               aria-label="{{ __('services.deposit_amount').' — '.$code }}">
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- One place for the card's refusals. The check runs on the switch,
         because which of the fields below has to hold something depends on
         answers no single field can see. --}}
    @foreach (['deposit_required', 'deposit_type', 'deposit_percent', 'deposit_amount'] as $field)
        @error($field)<p class="mt-3 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror
    @endforeach

    @foreach ($depositCurrencies as $code)
        @error('deposit_amount.'.$code)<p class="mt-3 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror
    @endforeach
</section>

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('services.section.price') }}</h2>

    <div class="mt-4">
        <x-price-input name="price" :label="__('services.price')" :values="$priceValues"
                       :cash-values="$cashPriceValues" :deposit="$depositConfig" />
    </div>

    {{-- What it costs in credits, under what it costs in money.

         A second price, in a second currency. They are not convertible and
         neither follows the other: a business charging the same credit for a
         ninety-minute massage as for a thirty-minute one is making a decision
         about its memberships, and a field that moved with the price would
         overwrite it.

         Only where there are credits to spend. A business that does not sell
         memberships has no use for the question. --}}
    @if ($membership->grantsCredits())
        <div class="mt-5 pt-5 border-t border-line">
            <label for="serviceCreditUsage" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('services.credit_usage') }}
            </label>

            <div class="w-[160px]">
                <input id="serviceCreditUsage" name="credit_usage" type="number" min="1" max="99" step="1"
                       class="sd-input" data-rules="integer|min:1|max:99"
                       value="{{ old('credit_usage', $service?->creditUsage() ?? 1) }}">
            </div>

            <p class="text-[12px] text-sub mt-1.5">{{ __('services.credit_usage_hint') }}</p>

            <p data-error-for="serviceCreditUsage" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('credit_usage')) hidden @endunless>{{ $errors->first('credit_usage') }}</p>
        </div>
    @endif
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

        /* Two answers, not three: follow the business, or name a flat sum.
           The percentage is the business's own — set once in App settings →
           Tips and changeable on the booking — so anything that is not a
           fixed sum here is "follows the default", including a percentage a
           service was given before this card asked only these two. */
        $tipType = old('tip_type', $service?->tip_type) === 'fixed' ? 'fixed' : '';

        /* Only meaningful as a flat sum now. A number left over from a
           percentage would otherwise sit in the box looking like an answer,
           and outrank the business default the moment it was saved. */
        $tipValue = $tipType === 'fixed' ? old('tip_value', $service?->tip_value) : '';

        /* A flat sum is a sum of something: the quick picks wear the money
           this business prices in. */
        $tipSymbol = App\Support\Money::symbol(App\Support\Currencies::primaryFor(auth()->user()?->tenant));

        /* What following the default actually gets you, in words, so the
           choice is not between a phrase and a number. */
        $tipDefaultWords = $tips->default_tip_type === 'fixed'
            ? $tipSymbol.number_format((float) $tips->default_tip_value, 2)
            : $tips->default_tip_value.'%';

        /* Built here rather than in the loop: a directive argument holding a
           comma inside brackets is not parsed, it is counted.

           The default wears its own figure — "Follows the default (20%)" —
           because the choice is otherwise between a phrase and a number, and
           only one of them says what the client will be asked for. */
        $tipTypeOptions = ['' => __('tips.follows_default_with', ['amount' => $tipDefaultWords])];

        foreach (App\Models\TipSettings::SERVICE_TYPES as $tipTypeOption) {
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
                {{-- Both answers on one line rather than a dropdown: they
                     are the whole set, and the second one brings a field
                     with it. --}}
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

                {{-- What following the business actually gets you. Shown
                     instead of the amount field rather than beside it: a
                     service that has not disagreed has no number of its own,
                     and an empty box next to "follows the default" invites
                     one to be typed.

                     The till's own row, with the default marked — read-only,
                     because these are the business's answer and are edited in
                     App settings → Tips. Shown rather than described so that
                     "follows the default" is a thing somebody can see. --}}
                <div data-tip-default-note @if ($tipType === 'fixed') hidden @endif>
                    @if ($tips->default_tip_type !== 'fixed')
                        <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('tips.offered_at_till') }}</span>

                        <div class="flex flex-wrap gap-2" aria-hidden="true">
                            @foreach ($tips->offeredPercentages() as $percent)
                                <span @class([
                                    'styledesk_chipbtn styledesk_chipbtn--static',
                                    'is-active' => (int) $percent === (int) $tips->default_tip_value,
                                ])>{{ $percent }}%</span>
                            @endforeach
                        </div>
                    @endif

                    <p class="text-[12px] text-sub mt-2">
                        {{ __('tips.follows_default_note', ['amount' => $tipDefaultWords]) }}
                    </p>
                </div>

                <div data-tip-amount @unless ($tipType === 'fixed') hidden @endunless>
                    <label for="serviceTipValue" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('tips.tip_amount') }}
                    </label>

                    {{-- The quick picks fill the box beside them rather than
                         replacing it: three round numbers save the typing
                         without claiming to be the only answers. --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex flex-wrap gap-2" data-tip-presets>
                            @foreach (\App\Models\TipSettings::QUICK_FIXED_AMOUNTS as $amount)
                                <button type="button" class="styledesk_chipbtn" data-tip-preset="{{ $amount }}"
                                        data-tip-preset-for="fixed">{{ $tipSymbol }}{{ $amount }}</button>
                            @endforeach
                        </div>

                        <div class="w-[110px]">
                            <input id="serviceTipValue" name="tip_value" type="number" min="0" max="100"
                                   class="sd-input" placeholder="{{ $tipSymbol }}0"
                                   value="{{ $tipValue }}" data-tip-value>
                        </div>
                    </div>

                    <p class="text-[12px] text-faint mt-1.5">{{ __('tips.tip_amount_hint') }}</p>
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
