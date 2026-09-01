@extends('layouts.app')

@section('title', $promotion ? __('promotions.form.edit_title') : __('promotions.form.create_title'))

@section('content')
  @php
      /* The form is filled from, in order: what was posted back after a
         failed save, the promotion being edited, the template that was
         chosen, and finally a sensible default. `old()` first is what makes
         a validation error keep the reader's work. */
      $t = $template ?? [];
      $value = fn (string $key, $fallback = null) => old($key, $promotion?->{$key} ?? ($t[$key] ?? $fallback));

      $type = $value('type', 'coupon');
      $discountType = $value('discount_type', 'percent');
      $appliesTo = $value('applies_to', 'all_services');
      $eligibility = $value('eligibility', 'all');
      $locationMode = $value('location_mode', 'all');
      $days = old('days', $promotion?->days ?? ($t['days'] ?? [0, 1, 2, 3, 4, 5, 6]));
      $noExpiry = (bool) old('no_expiry', $promotion !== null && $promotion->ends_on === null);

      /* Stored as minor units and whole points; the form works in the
         units a person types. */
      $discountValue = old('discount_value', $promotion === null
          ? ($t['discount_value'] ?? null)
          : ($promotion->discount_type === 'percent'
              ? $promotion->discount_value
              : number_format($promotion->discount_value / 100, 2, '.', '')));
  @endphp

  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('promotions.index') }}" class="hover:text-ink transition-colors">{{ __('promotions.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $promotion ? $promotion->name : __('promotions.form.create_title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <h1 class="min-w-0 flex-1 text-[24px] sm:text-[28px] font-bold text-head tracking-tight">
          {{ $promotion ? __('promotions.form.edit_title') : __('promotions.form.create_title') }}
        </h1>

        {{-- Back to where they came from: the promotion when editing one,
             the listing when making a new one. A Back that always went to
             the listing would throw somebody out of the record they were
             halfway through. --}}
        <a href="{{ $promotion ? route('promotions.show', $promotion) : route('promotions.index') }}"
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      {{-- Templates, only when making a new one. A template does not decide
           anything — it fills the form in and every value stays editable. --}}
      @if ($promotion === null)
        <section class="sd-card p-5 mt-6">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.templates') }}</h2>
          <p class="text-[12px] text-sub mt-1">{{ __('promotions.form.templates_hint') }}</p>

          <div class="flex flex-wrap gap-2 mt-3">
            <a href="{{ route('promotions.create') }}"
               @class(['styledesk_action', 'border-brand text-brand' => $template === null])>
              {{ __('promotions.form.scratch') }}
            </a>

            @foreach ($templates as $key => $preset)
              <a href="{{ route('promotions.create', ['template' => $key]) }}"
                 @class(['styledesk_action', 'border-brand text-brand' => ($t['name'] ?? null) === $preset['name']])>
                {{ $preset['name'] }}
              </a>
            @endforeach
          </div>
        </section>
      @endif

      <form method="POST" action="{{ $promotion ? route('promotions.update', $promotion) : route('promotions.store') }}"
            class="mt-4 space-y-4" data-promotion-form>
        @csrf
        @if ($promotion) @method('PATCH') @endif

        {{-- ------------------------------------------------- basics -- --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.basics') }}</h2>

          <div class="mt-4 space-y-4">
            <div>
              <label for="pName" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('promotions.form.name') }} <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <input id="pName" name="name" type="text" class="sd-input" maxlength="120" required
                     value="{{ $value('name') }}" placeholder="{{ __('promotions.form.name_placeholder') }}">
              <p data-error-for="name" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('name')) hidden @endunless>{{ $errors->first('name') }}</p>
            </div>

            <div>
              <label for="pDescription" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('promotions.form.description') }}
                <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <textarea id="pDescription" name="description" rows="2" class="sd-input !h-auto py-2.5"
                        maxlength="500">{{ $value('description') }}</textarea>
              <p class="text-[12px] text-faint mt-1.5">{{ __('promotions.form.description_hint') }}</p>
            </div>

            <div>
              <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('promotions.form.type') }}</span>

              <div class="grid sm:grid-cols-2 gap-2">
                @foreach (['coupon', 'offer'] as $option)
                  <label @class([
                      'flex items-start gap-2.5 rounded-lg border px-3.5 py-3 cursor-pointer transition-colors',
                      'border-brand' => $type === $option,
                      'border-line hover:border-brand' => $type !== $option,
                  ])>
                    <input type="radio" name="type" value="{{ $option }}" class="mt-0.5"
                           @checked($type === $option) data-promotion-type>
                    <span class="min-w-0">
                      <span class="block text-[13px] font-semibold text-ink">{{ __('promotions.form.type_'.$option) }}</span>
                      <span class="block text-[12px] text-faint mt-0.5">{{ __('promotions.form.type_'.$option.'_hint') }}</span>
                    </span>
                  </label>
                @endforeach
              </div>
            </div>

            {{-- Only a coupon has a code — an offer applies itself. --}}
            <div data-promotion-code @unless ($type === 'coupon') hidden @endunless>
              <label for="pCode" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('promotions.form.code') }} <span class="text-danger" aria-hidden="true">*</span>
              </label>

              <div class="flex gap-2">
                <input id="pCode" name="code" type="text" class="sd-input font-mono uppercase" maxlength="40"
                       value="{{ $value('code') }}" data-code-field>
                <button type="button" class="styledesk_action shrink-0" data-generate-code>
                  {{ __('promotions.form.generate') }}
                </button>
              </div>

              <p class="text-[12px] text-faint mt-1.5">{{ __('promotions.form.code_hint') }}</p>
              <p data-error-for="code" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('code')) hidden @endunless>{{ $errors->first('code') }}</p>
            </div>
          </div>
        </section>

        {{-- ----------------------------------------------- discount -- --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.discount') }}</h2>

          <div class="grid sm:grid-cols-2 gap-4 mt-4">
            <div>
              <label for="pDiscountType" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('promotions.form.discount_type') }}</label>
              <select id="pDiscountType" name="discount_type" class="sd-input" data-discount-type>
                @foreach (['percent', 'fixed'] as $option)
                  <option value="{{ $option }}" @selected($discountType === $option)>{{ __('promotions.form.'.$option) }}</option>
                @endforeach
              </select>
            </div>

            {{-- The second field is the first field's answer.

                 A percentage and a sum of money are not the same question:
                 one is capped at a hundred and counted in whole points, the
                 other is pennies with a currency symbol on it. Showing one
                 box labelled "Amount" for both is how a business ends up
                 offering 20% off and meaning £20. --}}
            <div>
              <label for="pDiscountValue" class="block text-[13px] font-medium text-ink mb-1.5">
                <span data-value-label>{{ $discountType === 'percent' ? __('promotions.form.percent') : __('promotions.form.amount') }}</span>
                <span class="text-danger" aria-hidden="true">*</span>
              </label>

              <div class="relative">
                {{-- The currency in front where it is money. --}}
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-faint pointer-events-none"
                      data-value-prefix @if ($discountType === 'percent') hidden @endif>{{ \App\Support\Money::symbol($currency) }}</span>

                <input id="pDiscountValue" name="discount_value" type="number" min="0" class="sd-input"
                       required value="{{ $discountValue }}" data-discount-value
                       step="{{ $discountType === 'percent' ? '1' : '0.01' }}"
                       @if ($discountType === 'percent') max="100" @endif
                       style="{{ $discountType === 'percent' ? 'padding-right:2rem' : 'padding-left:1.75rem' }}">

                {{-- And the per cent behind it where it is a rate. --}}
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[13px] text-faint pointer-events-none"
                      data-value-suffix @unless ($discountType === 'percent') hidden @endunless>%</span>
              </div>

              <p class="text-[12px] text-faint mt-1.5" data-value-hint>
                {{ $discountType === 'percent' ? __('promotions.form.percent_hint') : __('promotions.form.fixed_hint') }}
              </p>

              <p data-error-for="discount_value" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('discount_value')) hidden @endunless>{{ $errors->first('discount_value') }}</p>
            </div>
          </div>
        </section>

        {{-- ------------------------------------------------ applies -- --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.applies') }}</h2>
          <p class="text-[12px] text-sub mt-1">{{ __('promotions.form.applies_hint') }}</p>

          <div class="mt-4 space-y-2">
            @foreach (App\Models\Promotion::APPLIES_TO as $option)
              <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="radio" name="applies_to" value="{{ $option }}"
                       @checked($appliesTo === $option) data-applies-to>
                <span class="text-[13px] text-ink">{{ __('promotions.applies.'.$option) }}</span>
              </label>
            @endforeach
          </div>

          <div class="mt-3" data-applies-services @unless ($appliesTo === 'services') hidden @endunless>
            <x-combo name="services" multiple
                     :label="__('promotions.form.services')"
                     :selected="old('services', $promotion?->services->pluck('id')->all() ?? [])"
                     :options="$services->pluck('name', 'id')" />
          </div>

          <div class="mt-3" data-applies-categories @unless ($appliesTo === 'categories') hidden @endunless>
            <x-combo name="categories" multiple
                     :label="__('promotions.form.categories')"
                     :selected="old('categories', $promotion?->serviceCategories->pluck('id')->all() ?? [])"
                     :options="$categories->pluck('name', 'id')" />
          </div>
        </section>

        {{-- ---------------------------------------------- locations -- --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.locations') }}</h2>

          <div class="mt-4 space-y-2">
            @foreach (['all' => 'all_locations', 'selected' => 'selected_locations'] as $option => $label)
              <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="radio" name="location_mode" value="{{ $option }}"
                       @checked($locationMode === $option) data-location-mode>
                <span class="text-[13px] text-ink">{{ __('promotions.form.'.$label) }}</span>
              </label>
            @endforeach
          </div>

          <div class="mt-3" data-location-list @unless ($locationMode === 'selected') hidden @endunless>
            <x-combo name="locations" multiple
                     :label="__('promotions.form.locations')"
                     :selected="old('locations', $promotion?->locations->pluck('id')->all() ?? [])"
                     :options="$locations->pluck('name', 'id')" />
          </div>
        </section>

        {{-- ----------------------------------------------- validity -- --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.validity') }}</h2>

          {{-- The shared calendar picker, the same one the client form uses
               for a date of birth. Not a native date input: it renders
               differently in every browser and ignores the app's own field
               styling, so two screens using both would not look like one
               product. --}}
          <div class="grid sm:grid-cols-2 gap-4 mt-4">
            <x-date-field name="starts_on"
                          :label="__('promotions.form.starts')"
                          required
                          :value="old('starts_on', $promotion?->starts_on?->toDateString() ?? now()->toDateString())"
                          :min-year="now()->year - 1"
                          :max-year="now()->year + 5"
                          :clearable="false"
                          rules="required|date"
                          :dialog-label="__('promotions.form.starts')" />

            <div data-ends-wrapper>
              <x-date-field name="ends_on"
                            :label="__('promotions.form.ends')"
                            optional
                            :value="old('ends_on', $promotion?->ends_on?->toDateString())"
                            :min-year="now()->year - 1"
                            :max-year="now()->year + 5"
                            rules="date"
                            :dialog-label="__('promotions.form.ends')" />

              <p data-error-for="ends_on" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('ends_on')) hidden @endunless>{{ $errors->first('ends_on') }}</p>
            </div>
          </div>

          <label class="flex items-center gap-2.5 mt-3 cursor-pointer">
            <input type="hidden" name="no_expiry" value="0">
            <input type="checkbox" name="no_expiry" value="1" @checked($noExpiry) data-no-expiry>
            <span class="text-[13px] text-ink">{{ __('promotions.form.no_expiry') }}</span>
          </label>

          <div class="mt-4 border-t border-line pt-4">
            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('promotions.form.days') }}</span>

            <div class="flex flex-wrap gap-2">
              @foreach (__('promotions.weekdays') as $index => $label)
                <label @class([
                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-[12.5px] cursor-pointer transition-colors',
                    'border-brand text-brand' => in_array((int) $index, array_map('intval', (array) $days), true),
                    'border-line text-sub' => ! in_array((int) $index, array_map('intval', (array) $days), true),
                ])>
                  <input type="checkbox" name="days[]" value="{{ $index }}"
                         @checked(in_array((int) $index, array_map('intval', (array) $days), true))>
                  {{ $label }}
                </label>
              @endforeach
            </div>

            <p class="text-[12px] text-faint mt-2">{{ __('promotions.form.days_hint') }}</p>
          </div>
        </section>

        {{-- -------------------------------------------- eligibility -- --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.eligibility') }}</h2>

          <div class="mt-4 space-y-2">
            @foreach (App\Models\Promotion::ELIGIBILITY as $option)
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="radio" name="eligibility" value="{{ $option }}" class="mt-0.5"
                       @checked($eligibility === $option) data-eligibility>
                <span class="min-w-0">
                  <span class="block text-[13px] text-ink">{{ __('promotions.form.eligibility_'.$option) }}</span>
                  @if ($option === 'new')
                    <span class="block text-[12px] text-faint">{{ __('promotions.form.eligibility_new_hint') }}</span>
                  @endif
                </span>
              </label>
            @endforeach
          </div>

          <div class="mt-3" data-eligibility-clients @unless ($eligibility === 'selected') hidden @endunless>
            <x-combo name="clients" multiple
                     :label="__('promotions.form.clients')"
                     :selected="old('clients', $promotion?->clients->pluck('id')->all() ?? [])"
                     :options="$clients->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])" />
          </div>
        </section>

        {{-- --------------------------------------------- redemption -- --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.redemption') }}</h2>

          <div class="grid sm:grid-cols-3 gap-4 mt-4">
            <div>
              <label for="pMinSpend" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('promotions.form.min_spend') }}</label>
              <input id="pMinSpend" name="min_spend" type="number" step="0.01" min="0" class="sd-input"
                     value="{{ old('min_spend', $promotion?->min_spend_minor === null ? null : number_format($promotion->min_spend_minor / 100, 2, '.', '')) }}">
              <p class="text-[12px] text-faint mt-1.5">{{ __('promotions.form.min_spend_hint') }}</p>
            </div>

            <div>
              <label for="pTotalLimit" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('promotions.form.total_limit') }}</label>
              <input id="pTotalLimit" name="total_limit" type="number" min="1" class="sd-input"
                     value="{{ old('total_limit', $promotion?->total_limit) }}">
              <p class="text-[12px] text-faint mt-1.5">{{ __('promotions.form.total_limit_hint') }}</p>
            </div>

            <div>
              <label for="pPerClient" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('promotions.form.per_client_limit') }}</label>
              <input id="pPerClient" name="per_client_limit" type="number" min="1" class="sd-input"
                     value="{{ old('per_client_limit', $promotion === null ? 1 : $promotion->per_client_limit) }}">
              <p class="text-[12px] text-faint mt-1.5">{{ __('promotions.form.per_client_limit_hint') }}</p>
            </div>
          </div>
        </section>

        {{-- ------------------------------------------- availability -- --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.form.availability') }}</h2>

          <div class="mt-4 space-y-4">
            <x-toggle name="allow_online" :label="__('promotions.form.allow_online')"
                      :checked="(bool) old('allow_online', $promotion?->allow_online ?? false)" />

            <x-toggle name="combinable" :label="__('promotions.form.combinable')"
                      :hint="__('promotions.form.combinable_hint')"
                      :checked="(bool) old('combinable', $promotion?->combinable ?? false)" />

            <x-toggle name="is_draft" :label="__('promotions.form.draft')"
                      :hint="__('promotions.form.draft_hint')"
                      :checked="(bool) old('is_draft', $promotion?->is_draft ?? false)" />
          </div>
        </section>

        <div class="flex flex-wrap gap-2">
          <button type="submit"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('promotions.form.save') }}
          </button>

          <a href="{{ $promotion ? route('promotions.show', $promotion) : route('promotions.index') }}"
             class="styledesk_action">{{ __('promotions.form.cancel') }}</a>
        </div>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  @include('promotions._scripts')
@endpush
