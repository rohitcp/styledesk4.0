@extends('layouts.app')

@section('title', __('tips.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('tips.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('tips.title') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('tips.intro') }}</p>
        </div>

        <a href="{{ route('settings.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('tips.back') }}
        </a>
      </div>

      {{-- The switch, on its own and posting on its own.

           It is not one of the settings below it — it decides whether they
           are asked about at all — so it sits above the tabs rather than
           inside one of them. The rest of the settings travel with it as
           hidden fields, so switching tipping on cannot quietly reset the
           percentages somebody spent a minute choosing. --}}
      <form method="POST" action="{{ route('settings.tips.update') }}" class="mt-6" data-tips-switch>
        @csrf
        @method('PATCH')

        <input type="hidden" name="default_tip_type" value="{{ $settings->default_tip_type }}">
        <input type="hidden" name="default_tip_value" value="{{ $settings->default_tip_value }}">
        <input type="hidden" name="require_selection" value="{{ $settings->require_selection ? 1 : 0 }}">
        <input type="hidden" name="allow_no_tip" value="{{ $settings->allow_no_tip ? 1 : 0 }}">
        @foreach ($settings->offeredPercentages() as $percent)
          <input type="hidden" name="percentages[]" value="{{ $percent }}">
        @endforeach
        @foreach ($settings->offeredAmounts() as $amount)
          <input type="hidden" name="amounts[]" value="{{ $amount }}">
        @endforeach

        <div class="sd-card p-5">
          <x-toggle name="is_enabled" :label="__('tips.enable')" :hint="__('tips.enable_hint')"
                    :checked="$settings->is_enabled" data-tips-enabled />

          @unless ($settings->is_enabled)
            <p class="mt-3 text-[12px] text-sub bg-hover rounded-lg px-3 py-2">{{ __('tips.disabled_note') }}</p>
          @endunless
        </div>
      </form>

      {{-- Nothing below means anything until tipping is on, so until it is,
           there is nothing below. --}}
      @if ($settings->is_enabled)
        <nav class="mt-6 border-b border-line" aria-label="{{ __('tips.title') }}">
          <div class="flex flex-wrap items-end gap-1">
            @foreach (['suggest', 'services'] as $key)
              @php $current = $tab === $key; @endphp
              <a href="{{ route('settings.tips.index', ['tab' => $key]) }}"
                 @class([
                     'inline-flex items-center h-9 px-3.5 rounded-t-lg text-[13px] font-semibold border-b-2 -mb-px transition-colors',
                     'border-brand text-brand' => $current,
                     'border-transparent text-sub hover:text-head' => ! $current,
                 ])
                 @if ($current) aria-current="page" @endif>
                {{ __('tips.tabs.'.$key) }}
              </a>
            @endforeach
          </div>
        </nav>

        {{-- ------------------------------------------- what to suggest -- --}}
        @if ($tab === 'suggest')
          <form method="POST" action="{{ route('settings.tips.index') }}" class="mt-5">
            @csrf
            @method('PATCH')

            {{-- The switch travels with this form too, so saving the
                 suggestions cannot turn tipping off behind the reader. --}}
            <input type="hidden" name="is_enabled" value="1">

            <div class="sd-card p-5">
              <h2 class="text-[15px] font-semibold text-head">{{ __('tips.defaults') }}</h2>
              <p class="text-[12px] text-sub mt-1">{{ __('tips.defaults_hint') }}</p>

              <div class="grid sm:grid-cols-2 gap-4 mt-4">
                <div>
                  <label for="tipType" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('tips.tip_type') }}</label>
                  <select id="tipType" name="default_tip_type" class="sd-input" data-tip-type-select>
                    @foreach ($types as $type)
                      <option value="{{ $type }}" @selected($settings->default_tip_type === $type)>
                        {{ __('tips.types.'.$type) }}
                      </option>
                    @endforeach
                  </select>
                </div>

                {{-- One box, wearing whichever unit the type calls for. A
                     percentage and a sum of money read the same as a bare
                     number, and a business that has just chosen "amount" and
                     sees a % will type a percentage. --}}
                @php $tipsInAmounts = old('default_tip_type', $settings->default_tip_type) === 'fixed'; @endphp

                <div>
                  <label for="tipValue" class="block text-[13px] font-medium text-ink mb-1.5">
                    <span data-tip-default-label>
                      {{ $tipsInAmounts ? __('tips.default_tip_amount') : __('tips.default_tip_percent') }}
                    </span>
                  </label>

                  <div class="relative">
                    <span class="styledesk_input__prefix pointer-events-none text-sub" aria-hidden="true"
                          data-tip-default-prefix @unless ($tipsInAmounts) hidden @endunless>{{ $symbol }}</span>

                    <span class="styledesk_input__suffix pointer-events-none text-sub" aria-hidden="true"
                          data-tip-default-suffix @if ($tipsInAmounts) hidden @endif>%</span>

                    <input id="tipValue" name="default_tip_value" type="number" min="0"
                           max="{{ $tipsInAmounts ? 100000 : 100 }}"
                           @class(['sd-input', 'styledesk_input--prefixed' => $tipsInAmounts])
                           data-tip-default-value
                           value="{{ old('default_tip_value', $settings->default_tip_value) }}">
                  </div>

                  <p class="text-[12px] text-faint mt-1.5">{{ __('tips.default_tip_hint') }}</p>
                  <p data-error-for="default_tip_value" role="alert" class="mt-1.5 text-[12px] text-danger"
                     @unless ($errors->has('default_tip_value')) hidden @endunless>{{ $errors->first('default_tip_value') }}</p>
                </div>
              </div>

              {{-- What the client is actually offered. Six boxes rather than
                   a comma-separated string: a business typing "15,18,20"
                   into one field is one stray character away from a till
                   that offers nothing.

                   Both rows are always here and both always post. Only the
                   one the type calls for is shown — the other is the answer
                   this business gave last time it tipped that way, and
                   switching back should find it where it was left. --}}
              <div class="mt-4" data-tip-percent-row @if ($tipsInAmounts) hidden @endif>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('tips.percentages') }}</span>
                <div class="flex flex-wrap gap-2">
                  @foreach (array_pad($settings->offeredPercentages(), 6, null) as $index => $percent)
                    <div class="relative w-[86px]">
                      <input name="percentages[]" type="number" min="1" max="100" class="sd-input pr-7"
                             value="{{ $percent }}" aria-label="{{ __('tips.percentages') }} {{ $index + 1 }}">
                      <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[13px] text-faint pointer-events-none">%</span>
                    </div>
                  @endforeach
                </div>
                <p class="text-[12px] text-faint mt-1.5">{{ __('tips.percentages_hint') }}</p>
              </div>

              <div class="mt-4" data-tip-amount-row @unless ($tipsInAmounts) hidden @endunless>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('tips.amounts') }}</span>
                <div class="flex flex-wrap gap-2">
                  @foreach (array_pad($settings->offeredAmounts(), 6, null) as $index => $amount)
                    <div class="relative w-[96px]">
                      <span class="styledesk_input__prefix pointer-events-none text-sub" aria-hidden="true">{{ $symbol }}</span>
                      <input name="amounts[]" type="number" min="1" max="100000"
                             class="sd-input styledesk_input--prefixed"
                             value="{{ $amount }}" aria-label="{{ __('tips.amounts') }} {{ $index + 1 }}">
                    </div>
                  @endforeach
                </div>
                <p class="text-[12px] text-faint mt-1.5">{{ __('tips.amounts_hint') }}</p>
              </div>

              <div class="mt-5 space-y-4 border-t border-line pt-4">
                {{-- A prompt, not a charge. "Must choose" and "must tip" are
                     different things, and only the first is what this
                     does. --}}
                <x-toggle name="require_selection" :label="__('tips.require_selection')"
                          :hint="__('tips.require_selection_hint')"
                          :checked="$settings->require_selection" />

                <x-toggle name="allow_no_tip" :label="__('tips.allow_no_tip')"
                          :hint="__('tips.allow_no_tip_hint')"
                          :checked="$settings->allow_no_tip" />
              </div>
            </div>

            <div class="mt-4">
              <button type="submit"
                      class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                {{ __('common.save') }}
              </button>
            </div>
          </form>
        @endif

        {{-- ------------------------------------------------- services -- --}}
        @if ($tab === 'services')
          <section class="mt-5">
            <p class="text-[13px] text-sub">{{ __('tips.services_hint') }}</p>

            @if ($services->isEmpty())
              <p class="text-[13px] text-sub mt-4">{{ __('tips.no_services') }}</p>
            @else
              <div class="mt-4 sd-table sd-table--cards">
                <table class="w-full">
                  <thead>
                    <tr>
                      <th class="text-left">{{ __('tips.columns.service') }}</th>
                      <th class="text-left">{{ __('tips.columns.category') }}</th>
                      <th class="text-left">{{ __('tips.columns.price') }}</th>
                      <th class="text-left">{{ __('tips.columns.tips') }}</th>
                      <th class="text-left">{{ __('tips.columns.default') }}</th>
                      <th class="text-left">{{ __('tips.columns.required') }}</th>
                      <th class="w-10"></th>
                    </tr>
                  </thead>

                  <tbody>
                    @foreach ($services as $service)
                      @php
                          /* Null on the service means "whatever the business
                             says", so the table shows what would actually
                             happen rather than a blank. */
                          $accepts = $service->accepts_tips ?? true;
                          $ownValue = $service->tip_value !== null;
                          $type = $service->tip_type ?? $settings->default_tip_type;
                          $value = $service->tip_value ?? $settings->default_tip_value;
                          $required = $service->tip_required ?? $settings->require_selection;

                          /* Built here rather than inline in the attribute: a
                             multi-line @json inside a tag is not something
                             Blade can parse. */
                          $edit = [
                              'name' => $service->name,
                              'url' => route('settings.tips.service', $service),
                              'accepts_tips' => $accepts,
                              'tip_type' => $service->tip_type,
                              'tip_value' => $service->tip_value,
                              'tip_required' => $required,
                              'allow_no_tip' => $service->allow_no_tip ?? $settings->allow_no_tip,
                          ];
                      @endphp

                      <tr data-service-row>
                        <td class="font-medium text-head">{{ $service->name }}</td>
                        <td class="text-sub">{{ $service->category?->name ?? '—' }}</td>
                        {{-- The currency named rather than read off the
                             tenant relation: these rows are loaded without
                             it, and priceFormatted() comes back
                             symbol-less. --}}
                        <td class="text-sub">{{ $service->priceLabel($currency) }}</td>

                        <td>
                          <span class="styledesk_badge {{ $accepts ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                            {{ $accepts ? __('tips.accepted') : __('tips.not_accepted') }}
                          </span>
                        </td>

                        <td class="text-sub">
                          @if ($accepts)
                            {{ $type === 'percent' ? $value.'%' : \App\Support\Money::format($value, $currency) }}
                            @unless ($ownValue)
                              <span class="block text-[11px] text-faint">{{ __('tips.follows_default') }}</span>
                            @endunless
                          @else
                            —
                          @endif
                        </td>

                        <td class="text-sub">{{ $accepts && $required ? __('common.yes') : '—' }}</td>

                        <td class="text-right">
                          <button type="button" class="styledesk_action styledesk_action--sm"
                                  data-tip-edit='@json($edit)'>
                            {{ __('common.edit') }}
                          </button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </section>
        @endif
      @endif
    </div>
  </main>

  @include('settings.tips._modal')
@endsection

@push('scripts')
  @include('settings.tips._scripts')
@endpush
