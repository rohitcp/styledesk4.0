@extends('layouts.app')

@section('title', $plan ? __('membership.form.edit_title') : __('membership.form.create_title'))

{{--
    Create / edit a membership — steps 2 to 8.

    One form rather than eight saved screens. A membership is a priced product
    a client will be charged for, and a wizard that saved as it went would
    leave half-built products in the list the desk sells from. The steps are
    still the spec's steps — they are the cards, in order, numbered — but the
    save is one atomic thing at the end.

    Step 1 is not here. Choosing recurring or package is its own screen,
    because it decides what every question below means, and it cannot be
    changed afterwards.
--}}

@php
    /* Filled from, in order: what was posted back after a failed save, the
       plan being edited, and finally a sensible default. `old()` first is
       what makes a validation error keep the reader's work. */
    $value = fn (string $key, $fallback = null) => old($key, $plan?->{$key} ?? $fallback);

    $isRecurring = $type === 'recurring';
    $money = fn (?int $minor) => $minor === null ? null : number_format($minor / 100, 2, '.', '');

    /* One price per currency the business sells in, the primary first —
       which is the order Currencies::enabledFor already returns. A business
       pricing in one currency sees one row and cannot tell this changed;
       nothing here converts between them. */
    $priceCurrencies = App\Support\Currencies::enabledFor(auth()->user()?->tenant);
    $storedPrices = $plan?->prices->keyBy('currency_code') ?? collect();

    $amountIn = fn (string $field, string $code, string $column) => old(
        $field.'.'.$code,
        $storedPrices->get($code)?->amount($column),
    );

    $discountType = old('discount_type', $plan?->discount_type ?? '');
    $discountValue = old('discount_value', $plan === null || $plan->discount_type === null
        ? null
        : ($plan->discount_type === 'percent' ? $plan->discount_value : $money($plan->discount_value)));

    $locationMode = $value('location_mode', 'all');
    $chosenLocations = old('locations', $plan?->locations->pluck('id')->all() ?? []);

    /* The lines already on the plan, or one empty row to start from — a
       repeater that opens with nothing is one where the first press is
       "add" before anything can be typed. */
    $lines = old('services', $plan?->planServices
        ->map(fn ($line) => [
            'service_id' => $line->service_id,
            'quantity' => $line->quantity,
            'credits' => $line->grantedCredits(),
        ])
        ->values()->all() ?: null) ?? [['service_id' => '', 'quantity' => '', 'credits' => '']];

    /* Three-state overrides post as '' / '0' / '1'. Blank is "follow the
       business", which is why it cannot be a checkbox. */
    $override = fn (string $key) => old($key, $plan === null || $plan->{$key} === null
        ? ''
        : ($plan->{$key} ? '1' : '0'));

    $yesNo = fn (bool $on) => $on ? __('membership.form.yes') : __('membership.form.no');

    /* The business's channel switches are a ceiling over the plan's. A plan
       cannot be offered through a shop window the business has closed. */
    $channelOpen = [
        'in_store' => $settings->allow_purchase_in_store,
        'online' => $settings->allow_purchase_online,
    ];

    /* The picture the form opens with: the one posted back after a failed
       save, or the one the plan already has. */
    $imageFileId = old('image_file_id', $plan?->image_file_id);
    $imageUrl = $imageFileId === null || $imageFileId === ''
        ? null
        : app(App\Contracts\TenantStorageContract::class)->url((int) $imageFileId);

    $step = 1;
@endphp

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('membership.index') }}" class="hover:text-ink transition-colors">{{ __('membership.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $plan ? $plan->name : __('membership.form.create_title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">
            {{ $plan ? __('membership.form.edit_title') : __('membership.form.create_title') }}
          </h1>
          <p class="text-[13px] text-sub mt-1.5">{{ __('membership.types.'.$type) }}</p>
        </div>

        {{-- Back to where they came from: the plan when editing one, the
             listing when making a new one. --}}
        <a href="{{ $plan ? route('membership.show', $plan) : route('membership.index') }}"
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      <form method="POST" action="{{ $plan ? route('membership.update', $plan) : route('membership.store') }}"
            class="mt-5 space-y-4" data-membership-form
            data-currency="{{ $symbol }}">
        @csrf
        @if ($plan) @method('PATCH') @endif

        {{-- The kind travels as a hidden field and the server refuses a
             change to it. It is not a question on this form. --}}
        <input type="hidden" name="type" value="{{ $type }}">

        {{-- ------------------------------------------- Step 2: basics -- --}}
        <section class="sd-card p-5" id="basics">
          <p class="text-[11.5px] font-semibold uppercase tracking-wide text-faint">{{ __('membership.form.step', ['number' => ++$step]) }}</p>
          <h2 class="text-[15px] font-semibold text-head mt-1">{{ __('membership.form.basics') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.form.basics_hint') }}</p>

          <div class="mt-4 space-y-4">
            <div>
              <label for="mName" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('membership.form.name') }} <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <input id="mName" name="name" type="text" class="sd-input" maxlength="120" required
                     value="{{ $value('name') }}" placeholder="{{ __('membership.form.name_placeholder') }}">
              <p data-error-for="name" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('name')) hidden @endunless>{{ $errors->first('name') }}</p>
            </div>

            <div>
              <label for="mDescription" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('membership.form.description') }}
                <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <textarea id="mDescription" name="description" rows="2" class="sd-input !h-auto py-2.5"
                        maxlength="500">{{ $value('description') }}</textarea>
              <p class="text-[12px] text-faint mt-1.5">{{ __('membership.form.description_hint') }}</p>
            </div>

            {{-- The picture on the card the client is shown. Uploaded on
                 choosing rather than on save, so a file that will not upload
                 says so here rather than after eight steps of form — and the
                 preview is the real file rather than a browser-side guess.

                 What travels with the form is the id of the stored file. It
                 is claimed by MembershipImageSync when the plan is written,
                 because on the create form there is no plan to attach it to
                 yet. --}}
            <div>
              <span class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('membership.form.image') }}
                <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </span>

              <div class="flex items-center gap-4">
                <span data-image-preview
                      class="h-20 w-28 shrink-0 rounded-lg border border-line bg-hover grid place-items-center overflow-hidden text-faint">
                  @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover">
                  @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="9" cy="10" r="1.8" stroke="currentColor" stroke-width="1.7"/><path d="M4 17l4.5-4 4 3.2L16 13l4 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  @endif
                </span>

                <div class="min-w-0 flex-1 space-y-2">
                  <input id="mImage" type="file" class="sr-only"
                         accept=".jpg,.jpeg,.png,.webp"
                         data-image-input
                         data-endpoint="{{ route('membership.images.store') }}"
                         data-discard="{{ route('membership.images.destroy', ['storedFile' => '__ID__']) }}">

                  <div class="flex flex-wrap items-center gap-2">
                    <label for="mImage" class="styledesk_action">
                      {{ $imageUrl ? __('membership.form.image_replace') : __('membership.form.image_upload') }}
                    </label>

                    <button type="button" data-image-remove @unless ($imageUrl) hidden @endunless
                            class="h-9 px-3 rounded-md text-sub hover:text-danger hover:bg-hover text-[13px] font-semibold transition-colors">
                      {{ __('common.remove') }}
                    </button>
                  </div>

                  <p class="text-[12px] text-sub truncate" data-image-status></p>
                  <p class="text-[12px] text-faint">{{ __('membership.form.image_hint') }}</p>
                </div>
              </div>

              <input type="hidden" name="image_file_id" value="{{ $imageFileId }}" data-image-id>
            </div>

            <div class="sm:w-[260px]">
              <label for="mCode" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('membership.form.internal_code') }}
                <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="mCode" name="internal_code" type="text" class="sd-input" maxlength="40"
                     value="{{ $value('internal_code') }}">
              <p class="text-[12px] text-faint mt-1.5">{{ __('membership.form.internal_code_hint') }}</p>
              <p data-error-for="internal_code" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('internal_code')) hidden @endunless>{{ $errors->first('internal_code') }}</p>
            </div>
          </div>
        </section>

        {{-- ------------------------------------------ Step 3: pricing -- --}}
        <section class="sd-card p-5" id="pricing">
          <p class="text-[11.5px] font-semibold uppercase tracking-wide text-faint">{{ __('membership.form.step', ['number' => ++$step]) }}</p>
          <h2 class="text-[15px] font-semibold text-head mt-1">{{ __('membership.form.pricing') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">
            {{ $isRecurring ? __('membership.form.pricing_hint_recurring') : __('membership.form.pricing_hint_package') }}
          </p>

          {{-- The billing frequency is one answer for the plan, not one per
               currency: a membership billed monthly is billed monthly in
               every money it is sold in. --}}
          @if ($isRecurring)
            <div class="mt-4 sm:w-1/2 sm:pr-2">
              <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.billing_frequency') }}</span>
              <x-combo name="billing_frequency"
                       :options="collect($frequencies)->mapWithKeys(fn (string $f) => [$f => __('membership.billing_frequencies.'.$f)])"
                       :selected="$value('billing_frequency', config('membership.defaults.billing_frequency'))" />
            </div>
          @endif

          {{-- One block per currency, the business's own first — which is the
               order Currencies::enabledFor already returns. A business
               pricing in one currency gets exactly the row it always had, no
               code beside the label and nothing to read past. Nothing here
               converts: each price is a decision the business made. --}}
          <div class="mt-4 space-y-4">
            @foreach ($priceCurrencies as $code)
              @php $symbolFor = App\Support\Money::symbol($code); @endphp

              <div data-price-row data-currency="{{ $code }}" data-symbol="{{ $symbolFor }}">
                @if ($priceCurrencies->count() > 1)
                  <p class="text-[12px] font-semibold uppercase tracking-wide text-faint mb-2">
                    {{ $code }} <span class="font-normal normal-case tracking-normal">· {{ $symbolFor }}</span>
                  </p>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label for="mPrice_{{ $code }}" class="block text-[13px] font-medium text-ink mb-1.5">
                      {{ $isRecurring ? __('membership.form.price') : __('membership.form.package_price') }}
                      @if ($loop->first)
                        <span class="text-danger" aria-hidden="true">*</span>
                      @else
                        <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                      @endif
                    </label>

                    <div class="relative">
                      <input id="mPrice_{{ $code }}" name="price[{{ $code }}]" type="number" min="0.01" step="0.01"
                             @required($loop->first)
                             class="sd-input" data-price
                             value="{{ $amountIn('price', $code, 'price_minor') }}"
                             aria-label="{{ ($isRecurring ? __('membership.form.price') : __('membership.form.package_price')).' — '.$code }}">
                    </div>

                    @if ($priceCurrencies->count() > 1 && ! $loop->first)
                      <p class="text-[12px] text-faint mt-1.5">{{ __('membership.form.currency_optional_hint') }}</p>
                    @endif

                    <p data-error-for="price.{{ $code }}" role="alert" class="mt-1.5 text-[12px] text-danger"
                       @unless ($errors->has('price.'.$code)) hidden @endunless>{{ $errors->first('price.'.$code) }}</p>
                  </div>

                  @unless ($isRecurring)
                    <div>
                      <label for="mRegular_{{ $code }}" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('membership.form.regular_value') }}
                        <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                      </label>

                      <div class="relative">
                        <input id="mRegular_{{ $code }}" name="regular_value[{{ $code }}]" type="number" min="0.01" step="0.01"
                               class="sd-input" data-regular-value
                               value="{{ $amountIn('regular_value', $code, 'regular_value_minor') }}"
                               aria-label="{{ __('membership.form.regular_value').' — '.$code }}">
                      </div>

                      <p class="text-[12px] text-faint mt-1.5">{{ __('membership.form.regular_value_hint') }}</p>

                      <p data-error-for="regular_value.{{ $code }}" role="alert" class="mt-1.5 text-[12px] text-danger"
                         @unless ($errors->has('regular_value.'.$code)) hidden @endunless>{{ $errors->first('regular_value.'.$code) }}</p>
                    </div>
                  @endunless
                </div>

                @if ($isRecurring)
                  <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                      <label for="mJoining_{{ $code }}" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.joining_fee') }}</label>
                      <div class="relative">
                        <input id="mJoining_{{ $code }}" name="joining_fee[{{ $code }}]" type="number" min="0.01" step="0.01"
                               class="sd-input"
                               value="{{ $amountIn('joining_fee', $code, 'joining_fee_minor') }}"
                               aria-label="{{ __('membership.form.joining_fee').' — '.$code }}">
                      </div>
                    </div>

                    <div>
                      <label for="mSetup_{{ $code }}" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.setup_fee') }}</label>
                      <div class="relative">
                        <input id="mSetup_{{ $code }}" name="setup_fee[{{ $code }}]" type="number" min="0.01" step="0.01"
                               class="sd-input"
                               value="{{ $amountIn('setup_fee', $code, 'setup_fee_minor') }}"
                               aria-label="{{ __('membership.form.setup_fee').' — '.$code }}">
                      </div>
                    </div>
                  </div>
                @endif

                @unless ($isRecurring)
                  {{-- The saving, worked out as they type. It is the claim the
                       business is making to the client, so they should see it
                       while choosing the two numbers that make it — and per
                       currency, because that is the only pair it can be
                       worked out from. --}}
                  <p class="mt-3 text-[13px] font-semibold text-brand" data-saving hidden></p>
                @endunless
              </div>

              {{-- Between blocks, never after the last. --}}
              @unless ($loop->last)
                <hr class="border-line">
              @endunless
            @endforeach
          </div>

          @if ($priceCurrencies->count() > 1)
            <p class="mt-3 text-[12px] text-sub">{{ __('currency.no_conversion') }}</p>
          @endif

          @if ($isRecurring)
            {{-- Days, not money: one answer for the plan however many
                 currencies it is sold in. The joining and setup fees moved
                 up into the per-currency blocks, where they belong — a CAD
                 sale taking a USD joining fee is the bug that split would
                 otherwise leave waiting. --}}
            <div class="mt-5 sm:w-1/2 sm:pr-2">
              <label for="mTrial" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.trial_days') }}</label>
              <input id="mTrial" name="trial_days" type="number" min="1" max="365" step="1"
                     class="sd-input" value="{{ $value('trial_days') }}">
              <p class="text-[12px] text-faint mt-1.5">{{ __('membership.form.trial_days_hint') }}</p>
            </div>
          @endif
        </section>

        {{-- ----------------------------------------- Step 4: services -- --}}
        {{-- What the membership includes, where it includes anything.

             With credits switched off in App Settings a membership is its
             discount and its standing — there is nothing to draw down, so
             there is no list to build. --}}
        @if ($settings->grantsCredits())
        <section class="sd-card p-5" id="services">
          <p class="text-[11.5px] font-semibold uppercase tracking-wide text-faint">{{ __('membership.form.step', ['number' => ++$step]) }}</p>
          <h2 class="text-[15px] font-semibold text-head mt-1">{{ __('membership.form.services') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.form.services_intro') }}</p>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">
            {{ $isRecurring ? __('membership.form.services_hint_recurring') : __('membership.form.services_hint_package') }}
          </p>

          <p data-error-for="services" role="alert" class="mt-3 text-[12px] text-danger"
             @unless ($errors->has('services')) hidden @endunless>{{ $errors->first('services') }}</p>

          <div class="mt-4 space-y-2" data-service-rows>
            @foreach ($lines as $index => $line)
              <div class="flex flex-wrap items-end gap-2" data-service-row>
                <div class="min-w-[200px] flex-1">
                  <label class="block text-[12.5px] font-medium text-ink mb-1.5">{{ __('membership.form.service') }}</label>
                  {{-- Upgraded to the app's searchable combo. The native
                       select stays the value holder — SD.combo wraps it
                       rather than replacing it — so the repeater, the form
                       serialisation and validation all keep working on the
                       element they already know about. --}}
                  <select name="services[{{ $index }}][service_id]" class="sd-input" required
                          data-service-select data-combo
                          data-combo-options='@json(['searchPlaceholder' => __('membership.form.service_search')])'>
                    <option value="">—</option>
                    @foreach ($services as $service)
                      <option value="{{ $service->id }}" @selected((string) ($line['service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
                    @endforeach
                  </select>
                </div>

                <div class="w-[100px]">
                  <label class="block text-[12.5px] font-medium text-ink mb-1.5">{{ __('membership.form.quantity') }}</label>
                  <input name="services[{{ $index }}][quantity]" type="number" min="1" max="99" step="1" required
                         class="sd-input" data-service-quantity
                         placeholder="{{ __('membership.form.count_placeholder') }}"
                         value="{{ $line['quantity'] ?? '' }}">
                </div>

                {{-- What can actually be redeemed. Quantity describes the
                     benefit; this is the number the redemption engine draws
                     down, and it is the one that governs where they differ. --}}
                <div class="w-[100px]">
                  <label class="block text-[12.5px] font-medium text-ink mb-1.5">{{ __('membership.form.credits_column') }}</label>
                  <input name="services[{{ $index }}][credits]" type="number" min="1" max="99" step="1"
                         class="sd-input" data-service-credits
                         placeholder="{{ __('membership.form.count_placeholder') }}"
                         value="{{ $line['credits'] ?? '' }}">
                </div>

                <button type="button" class="styledesk_action shrink-0" data-remove-service
                        aria-label="{{ __('membership.form.remove') }}">
                  <x-icon name="trash-can" size="14" />
                </button>
              </div>
            @endforeach
          </div>

          <p class="mt-2.5 text-[12px] text-faint leading-snug">
            {{ $isRecurring ? __('membership.form.credits_recurring') : __('membership.form.credits_package') }}
          </p>

          <button type="button" class="styledesk_action mt-3" data-add-service>
            {{ __('membership.form.add_service') }}
          </button>

          {{-- The template a new row is cloned from. Outside the list so it
               is never posted, and carrying __INDEX__ so the names stay
               distinct however many are added and removed. --}}
          <template data-service-template>
            <div class="flex flex-wrap items-end gap-2" data-service-row>
              <div class="min-w-[200px] flex-1">
                <label class="block text-[12.5px] font-medium text-ink mb-1.5">{{ __('membership.form.service') }}</label>
                <select name="services[__INDEX__][service_id]" class="sd-input" required
                        data-service-select data-combo
                        data-combo-options='@json(['searchPlaceholder' => __('membership.form.service_search')])'>
                  <option value="">—</option>
                  @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="w-[100px]">
                <label class="block text-[12.5px] font-medium text-ink mb-1.5">{{ __('membership.form.quantity') }}</label>
                <input name="services[__INDEX__][quantity]" type="number" min="1" max="99" step="1" required
                       class="sd-input" data-service-quantity
                       placeholder="{{ __('membership.form.count_placeholder') }}" value="">
              </div>

              <div class="w-[100px]">
                <label class="block text-[12.5px] font-medium text-ink mb-1.5">{{ __('membership.form.credits_column') }}</label>
                <input name="services[__INDEX__][credits]" type="number" min="1" max="99" step="1"
                       class="sd-input" data-service-credits
                       placeholder="{{ __('membership.form.count_placeholder') }}" value="">
              </div>

              <button type="button" class="styledesk_action shrink-0" data-remove-service
                      aria-label="{{ __('membership.form.remove') }}">
                <x-icon name="trash-can" size="14" />
              </button>
            </div>
          </template>
        </section>
        @endif

        {{-- ----------------------------------------- Step 5: benefits -- --}}
        <section class="sd-card p-5" id="benefits">
          <p class="text-[11.5px] font-semibold uppercase tracking-wide text-faint">{{ __('membership.form.step', ['number' => ++$step]) }}</p>
          <h2 class="text-[15px] font-semibold text-head mt-1">{{ __('membership.form.benefits') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.form.benefits_hint') }}</p>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.discount_type') }}</span>
              <x-combo name="discount_type"
                       :options="['percent' => __('membership.form.percent'), 'fixed' => __('membership.form.fixed')]"
                       :selected="$discountType"
                       :placeholder="__('membership.form.discount_none')"
                       data-discount-type />
            </div>

            {{-- A percentage and a sum of money are not the same question,
                 and one box labelled "Amount" for both is how a business ends
                 up offering 10% off and meaning $10. The label, the affix and
                 the step all follow the type — the same arrangement the
                 promotion form uses. --}}
            <div>
              <label for="mDiscount" class="block text-[13px] font-medium text-ink mb-1.5">
                <span data-discount-label>
                  {{ $discountType === 'fixed' ? __('membership.form.discount_value') : __('membership.form.discount_percentage') }}
                </span>
              </label>

              <div class="relative">
                {{-- The currency in front where it is money. --}}
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-faint pointer-events-none"
                      data-discount-prefix @unless ($discountType === 'fixed') hidden @endunless>{{ $symbol }}</span>

                <input id="mDiscount" name="discount_value" type="number" min="0"
                       class="sd-input" value="{{ $discountValue }}" data-discount-value
                       step="{{ $discountType === 'fixed' ? '0.01' : '1' }}"
                       @unless ($discountType === 'fixed') max="100" @endunless
                       style="{{ $discountType === 'fixed' ? 'padding-left:1.75rem' : 'padding-right:2rem' }}">

                {{-- And the per cent behind it where it is a rate. --}}
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[13px] text-faint pointer-events-none"
                      data-discount-suffix @if ($discountType === 'fixed') hidden @endif>%</span>
              </div>

              <p data-error-for="discount_value" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('discount_value')) hidden @endunless>{{ $errors->first('discount_value') }}</p>
            </div>
          </div>

          <div class="mt-5">
            <x-toggle name="priority_booking" :label="__('membership.form.priority_booking')"
                      :hint="__('membership.form.priority_booking_hint')"
                      :checked="(bool) old('priority_booking', $plan?->priority_booking ?? false)" />
          </div>
        </section>

        {{-- ------------------------------------------ Step 6: credits -- --}}
        {{-- Only where this business's memberships include anything.

             Every question in this card is an override of a credit rule set
             in App Settings → Membership. With credits switched off there are
             no credits to have rules about, so asking a plan to overrule them
             is asking somebody to decide something that cannot happen.

             The step numbering is worked out as the cards render, so the
             cards below simply close the gap rather than leaving a hole where
             this one was. --}}
        @if ($settings->grantsCredits())
        <section class="sd-card p-5" id="credits">
          <p class="text-[11.5px] font-semibold uppercase tracking-wide text-faint">{{ __('membership.form.step', ['number' => ++$step]) }}</p>
          <h2 class="text-[15px] font-semibold text-head mt-1">{{ __('membership.form.credits') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.form.credits_hint') }}</p>

          {{-- Every one of these is three-state: follow the business, yes,
               or no. Blank rather than a copy of the business's answer,
               because a copy would silently stop following the setting the
               day somebody changed it. --}}
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.credit_expiry') }}</span>
              <x-combo name="credit_expiry"
                       :options="collect($creditExpiries)->mapWithKeys(fn (string $e) => [$e => __('membership.credit_expiry.'.$e)])"
                       :selected="old('credit_expiry', $plan?->credit_expiry ?? '')"
                       :placeholder="__('membership.form.follow_business', ['value' => __('membership.credit_expiry.'.$settings->credit_expiry)])" />
            </div>

            <div>
              <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.rollover') }}</span>
              <x-combo name="allow_rollover"
                       :options="['1' => __('membership.form.yes'), '0' => __('membership.form.no')]"
                       :selected="$override('allow_rollover')"
                       :placeholder="__('membership.form.follow_business', ['value' => $yesNo($settings->allow_rollover)])" />
            </div>

            <div>
              <label for="mMaxRollover" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.maximum_rollover') }}</label>
              <input id="mMaxRollover" name="maximum_rollover" type="number" min="1" max="1000" step="1"
                     class="sd-input" value="{{ $value('maximum_rollover') }}">
            </div>

            <div>
              <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.form.substitution') }}</span>
              <x-combo name="allow_service_substitution"
                       :options="['1' => __('membership.form.yes'), '0' => __('membership.form.no')]"
                       :selected="$override('allow_service_substitution')"
                       :placeholder="__('membership.form.follow_business', ['value' => $yesNo($settings->allow_service_substitution)])" />
            </div>
          </div>
        </section>
        @else
          {{-- The card is not asked for, but a plan that already carries
               overrides keeps them: a business switching rollover off for a
               month must not silently rewrite what every plan agreed to.
               Null stays null, which reads as "follow the business". --}}
          @if ($plan?->credit_expiry !== null)
            <input type="hidden" name="credit_expiry" value="{{ $plan->credit_expiry }}">
          @endif
          @if ($plan?->allow_rollover !== null)
            <input type="hidden" name="allow_rollover" value="{{ (int) $plan->allow_rollover }}">
          @endif
          @if ($plan?->maximum_rollover !== null)
            <input type="hidden" name="maximum_rollover" value="{{ $plan->maximum_rollover }}">
          @endif
          @if ($plan?->allow_service_substitution !== null)
            <input type="hidden" name="allow_service_substitution" value="{{ (int) $plan->allow_service_substitution }}">
          @endif
        @endif

        {{-- ------------------------------------- Step 7: availability -- --}}
        <section class="sd-card p-5" id="availability">
          <p class="text-[11.5px] font-semibold uppercase tracking-wide text-faint">{{ __('membership.form.step', ['number' => ++$step]) }}</p>
          <h2 class="text-[15px] font-semibold text-head mt-1">{{ __('membership.form.availability') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.form.availability_hint') }}</p>

          <h3 class="text-[13px] font-semibold text-head mt-5">{{ __('membership.form.locations') }}</h3>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach (['all', 'selected'] as $mode)
              <x-choice type="radio" name="location_mode" :value="$mode"
                        :label="__('membership.form.'.($mode === 'all' ? 'all_locations' : 'selected_locations'))"
                        :checked="$locationMode === $mode"
                        data-location-mode />
            @endforeach
          </div>

          {{-- Kept in the DOM and posting while hidden, so switching back
               and forth does not cost somebody the list they picked. --}}
          <div class="mt-3" data-location-list @unless ($locationMode === 'selected') hidden @endunless>
            <div class="grid gap-2 sm:grid-cols-2">
              @foreach ($locations as $location)
                <x-choice type="checkbox" name="locations[]" :value="$location->id"
                          :label="$location->name"
                          :checked="in_array((string) $location->id, array_map('strval', $chosenLocations), true)" />
              @endforeach
            </div>
            <p data-error-for="locations" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('locations')) hidden @endunless>{{ $errors->first('locations') }}</p>
          </div>

          <h3 class="text-[13px] font-semibold text-head mt-6">{{ __('membership.form.channels') }}</h3>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.form.channels_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach (['in_store' => 'sell_in_store', 'online' => 'sell_online'] as $channel => $field)
              {{-- A channel the business has closed is shown and disabled
                   rather than hidden: it says why this plan cannot be sold
                   there, which "the option is missing" does not. --}}
              <x-choice type="checkbox" :name="$field" value="1"
                        :label="__('membership.channels.'.$channel)"
                        :hint="$channelOpen[$channel] ? null : __('membership.form.channel_closed')"
                        :checked="$channelOpen[$channel] && (bool) old($field, $plan?->{$field} ?? ($channel === 'in_store'))"
                        :disabled="! $channelOpen[$channel]" />

              {{-- A disabled box posts nothing, which would quietly clear
                   what this plan says about a channel the business has only
                   paused. The plan's own answer travels instead. --}}
              @if (! $channelOpen[$channel] && $plan?->{$field})
                <input type="hidden" name="{{ $field }}" value="1">
              @endif
            @endforeach
          </div>
        </section>

        {{-- ------------------------------------------- Step 8: review -- --}}
        <section class="sd-card p-5" id="review">
          <p class="text-[11.5px] font-semibold uppercase tracking-wide text-faint">{{ __('membership.form.step', ['number' => ++$step]) }}</p>
          <h2 class="text-[15px] font-semibold text-head mt-1">{{ __('membership.form.review') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.form.review_hint') }}</p>

          <div class="mt-5 flex flex-wrap gap-2">
            {{-- Two buttons posting one form, differing only in what they
                 say about is_draft. A "publish" that was a second request
                 after a save is a plan that exists for a moment as neither.

                 Publishing leads, because it is what almost everybody came
                 to do. It is therefore also the button the Enter key
                 presses — the first submit in a form always is. --}}
            <button type="submit" name="is_draft" value="0"
                    class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ $plan && ! $plan->is_draft ? __('membership.form.save') : __('membership.form.publish') }}
            </button>

            <button type="submit" name="is_draft" value="1" class="styledesk_action">
              {{ __('membership.form.save_draft') }}
            </button>
          </div>
        </section>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  @include('membership._scripts')
@endpush
