@extends('layouts.app')

@section('title', __('membership.settings.title'))

{{--
    App Settings → Membership.

    The same shape Loyalty, Reviews and Tips use: the switch is its own form at
    the top and saves itself, and nothing below it exists until it is on. None
    of these questions mean anything to a business that does not sell
    memberships, and a screenful of settings that do nothing is a screen that
    has to be read before it can be dismissed.

    Switching off hides them; it erases nothing. The switch form carries every
    other answer as hidden fields, so pausing sales in November and starting
    again in March finds the terms exactly as they were — and every existing
    member keeps their plan, their credits and their history either way.
--}}

@php
    $canManage = $canManage ?? false;

    /* The channels as a list of ticked keys, so the checkbox group reads the
       same shape the form posts rather than two booleans. */
    $activeChannels = array_values(array_filter([
        $settings->allow_purchase_in_store ? 'in_store' : null,
        $settings->allow_purchase_online ? 'online' : null,
    ]));
    $chosenChannels = old('channels', $activeChannels);
@endphp

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('membership.settings.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('membership.settings.title') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('membership.settings.intro') }}</p>
        </div>

        <a href="{{ route('settings.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      {{-- Card 1 — the switch, on its own and posting on its own.

           It is not one of the settings below it — it decides whether they
           are asked about at all — so it sits above them rather than inside
           their form. Every other answer travels with it as a hidden field,
           so switching membership on cannot quietly reset terms somebody
           agreed with their members last year. --}}
      <form method="POST" action="{{ route('settings.membership.update') }}" class="mt-6" data-membership-switch>
        @csrf
        @method('PATCH')

        @foreach ($activeChannels as $channel)
          <input type="hidden" name="channels[]" value="{{ $channel }}">
        @endforeach
        <input type="hidden" name="allow_staff_to_sell" value="{{ (int) $settings->allow_staff_to_sell }}">
        <input type="hidden" name="allow_start_date_selection" value="{{ (int) $settings->allow_start_date_selection }}">
        <input type="hidden" name="default_activation" value="{{ $settings->default_activation }}">
        <input type="hidden" name="allow_rollover" value="{{ (int) $settings->allow_rollover }}">
        @if ($settings->maximum_rollover !== null)
          <input type="hidden" name="maximum_rollover" value="{{ $settings->maximum_rollover }}">
        @endif
        <input type="hidden" name="credit_expiry" value="{{ $settings->credit_expiry }}">
        <input type="hidden" name="allow_credits_across_locations" value="{{ (int) $settings->allow_credits_across_locations }}">
        <input type="hidden" name="allow_service_substitution" value="{{ (int) $settings->allow_service_substitution }}">
        <input type="hidden" name="allow_cancellation" value="{{ (int) $settings->allow_cancellation }}">
        <input type="hidden" name="allow_pause" value="{{ (int) $settings->allow_pause }}">
        <input type="hidden" name="minimum_commitment_months" value="{{ $settings->minimum_commitment_months }}">
        <input type="hidden" name="cancellation_notice_days" value="{{ $settings->cancellation_notice_days }}">
        <input type="hidden" name="cancellation_effective" value="{{ $settings->cancellation_effective }}">

        <div class="sd-card p-5">
          {{-- A reader who may look but not change gets the switch as it
               stands, refused by the browser rather than by a 403 after they
               have already flicked it. --}}
          <fieldset @disabled(! $canManage) class="contents">
            <x-toggle name="is_enabled" :label="__('membership.settings.enable')"
                      :hint="__('membership.settings.enable_hint')" :checked="$settings->is_enabled"
                      data-membership-enabled />
          </fieldset>

          @unless ($settings->is_enabled)
            <p class="mt-3 text-[12px] text-sub bg-hover rounded-lg px-3 py-2">{{ __('membership.settings.disabled_note') }}</p>
          @endunless

          {{-- Only ever seen with the script blocked, which hides it and
               saves on the toggle instead. --}}
          @if ($canManage)
            <button type="submit" class="styledesk_action mt-3" data-membership-fallback>
              {{ __('common.save') }}
            </button>
          @endif
        </div>
      </form>

      {{-- Nothing below means anything until membership is on, so until it
           is, there is nothing below. --}}
      @if ($settings->is_enabled)
      <form method="POST" action="{{ route('settings.membership.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('PATCH')

        {{-- Saving the terms must not switch membership off: the toggle is
             not in this form, so its answer travels as a hidden field. --}}
        <input type="hidden" name="is_enabled" value="1">

        <fieldset @disabled(! $canManage) class="contents">

        {{-- ---------------------------------------------- Card 1: selling --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.settings.selling') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.selling_hint') }}</p>

          <h3 class="text-[13px] font-semibold text-head mt-5">{{ __('membership.settings.channels') }}</h3>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.channels_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($channels as $key => $channel)
              {{-- A channel nothing can sell through yet is shown and
                   disabled rather than hidden: the screen should say what is
                   planned, and a business must not be able to switch on a
                   shop window that would never take an order. --}}
              <x-choice type="checkbox" name="channels[]" :value="$key"
                        :label="__('membership.channels.'.$key)"
                        :hint="$channel['available']
                            ? __('membership.channels.'.$key.'_hint')
                            : __('membership.settings.coming_soon')"
                        :checked="in_array($key, $chosenChannels, true)"
                        :disabled="! $channel['available']" />
            @endforeach
          </div>

          <div class="mt-5">
            <x-toggle name="allow_staff_to_sell" :label="__('membership.settings.allow_staff_to_sell')"
                      :hint="__('membership.settings.allow_staff_to_sell_hint')"
                      :checked="(bool) old('allow_staff_to_sell', $settings->allow_staff_to_sell)" />
          </div>
        </div>

        {{-- --------------------------------------------- Card 2: starting --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.settings.starting') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.starting_hint') }}</p>

          <div class="mt-4">
            <x-toggle name="allow_start_date_selection" :label="__('membership.settings.allow_start_date_selection')"
                      :hint="__('membership.settings.allow_start_date_selection_hint')"
                      :checked="(bool) old('allow_start_date_selection', $settings->allow_start_date_selection)" />
          </div>

          <h3 class="text-[13px] font-semibold text-head mt-6">{{ __('membership.settings.default_activation') }}</h3>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.default_activation_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($activations as $activation)
              <x-choice type="radio" name="default_activation" :value="$activation"
                        :label="__('membership.activation.'.$activation)"
                        :checked="old('default_activation', $settings->default_activation) === $activation" />
            @endforeach
          </div>
        </div>

        {{-- ---------------------------------------------- Card 3: credits --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.settings.credits') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.credits_hint') }}</p>

          {{-- Rollover only. "Reset each cycle" is the same question asked
               backwards, and offering both is how a business ends up with
               credits that survive the month and are wiped by it — so the
               server stores reset as this switch's opposite. --}}
          <div class="mt-4">
            <x-toggle name="allow_rollover" :label="__('membership.settings.allow_rollover')"
                      :hint="__('membership.settings.allow_rollover_hint')"
                      :checked="(bool) old('allow_rollover', $settings->allow_rollover)" />
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="maximum_rollover" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.settings.maximum_rollover') }}</label>
              <input id="maximum_rollover" name="maximum_rollover" type="number" min="1" step="1" max="1000"
                     class="sd-input" value="{{ old('maximum_rollover', $settings->maximum_rollover) }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('membership.settings.maximum_rollover_hint') }}</p>
            </div>
          </div>

          <h3 class="text-[13px] font-semibold text-head mt-6">{{ __('membership.settings.credit_expiry') }}</h3>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.credit_expiry_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($creditExpiries as $expiry)
              <x-choice type="radio" name="credit_expiry" :value="$expiry"
                        :label="__('membership.credit_expiry.'.$expiry)"
                        :checked="old('credit_expiry', $settings->credit_expiry) === $expiry" />
            @endforeach
          </div>

          <div class="mt-6 space-y-3">
            <x-toggle name="allow_credits_across_locations" :label="__('membership.settings.allow_credits_across_locations')"
                      :hint="__('membership.settings.allow_credits_across_locations_hint')"
                      :checked="(bool) old('allow_credits_across_locations', $settings->allow_credits_across_locations)" />

            <x-toggle name="allow_service_substitution" :label="__('membership.settings.allow_service_substitution')"
                      :hint="__('membership.settings.allow_service_substitution_hint')"
                      :checked="(bool) old('allow_service_substitution', $settings->allow_service_substitution)" />
          </div>
        </div>

        {{-- ----------------------------------------- Card 4: cancellation --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.settings.cancellation') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.cancellation_hint') }}</p>

          <div class="mt-4 space-y-3">
            <x-toggle name="allow_cancellation" :label="__('membership.settings.allow_cancellation')"
                      :hint="__('membership.settings.allow_cancellation_hint')"
                      :checked="(bool) old('allow_cancellation', $settings->allow_cancellation)" />

            <x-toggle name="allow_pause" :label="__('membership.settings.allow_pause')"
                      :hint="__('membership.settings.allow_pause_hint')"
                      :checked="(bool) old('allow_pause', $settings->allow_pause)" />
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="minimum_commitment_months" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.settings.minimum_commitment_months') }}</label>
              <input id="minimum_commitment_months" name="minimum_commitment_months" type="number" min="0" step="1" max="24" required
                     class="sd-input" value="{{ old('minimum_commitment_months', $settings->minimum_commitment_months) }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('membership.settings.minimum_commitment_months_hint') }}</p>
            </div>

            <div>
              <label for="cancellation_notice_days" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('membership.settings.cancellation_notice_days') }}</label>
              <input id="cancellation_notice_days" name="cancellation_notice_days" type="number" min="0" step="1" max="90" required
                     class="sd-input" value="{{ old('cancellation_notice_days', $settings->cancellation_notice_days) }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('membership.settings.cancellation_notice_days_hint') }}</p>
            </div>
          </div>

          <h3 class="text-[13px] font-semibold text-head mt-6">{{ __('membership.settings.cancellation_effective') }}</h3>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.cancellation_effective_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($cancellations as $timing)
              <x-choice type="radio" name="cancellation_effective" :value="$timing"
                        :label="__('membership.cancellation.'.$timing)"
                        :checked="old('cancellation_effective', $settings->cancellation_effective) === $timing" />
            @endforeach
          </div>
        </div>

        {{-- --------------------------------------------- Card 5: payments --}}
        {{-- Nothing to set. Memberships are paid for with the methods the
             business already accepts, and a second list of payment methods
             here is the one that drifts from the first. --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.settings.payments') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.payments_hint') }}</p>

          <a href="{{ route('settings.payments.show') }}" class="styledesk_action mt-4">
            <x-icon name="credit-card" size="14" />
            {{ __('membership.settings.payments_link') }}
          </a>
        </div>

        {{-- ------------------------------------------------ Card 6: rules --}}
        {{-- What StyleDesk decides rather than what the business does. Stated
             here rather than left to be discovered the first time a cash
             membership fails to renew. --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.settings.rules') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.settings.rules_hint') }}</p>

          <dl class="mt-4 space-y-4">
            @foreach (['recurring', 'package', 'scheduled', 'off'] as $rule)
              <div class="flex items-start gap-3">
                <span class="styledesk_settingcard__icon shrink-0 !h-8 !w-8" aria-hidden="true">
                  <x-icon name="shield-halved" size="14" />
                </span>
                <div class="min-w-0">
                  <dt class="text-[13px] font-semibold text-head">{{ __('membership.settings.rule_'.$rule) }}</dt>
                  <dd class="text-[12.5px] text-sub mt-0.5 leading-relaxed">{{ __('membership.settings.rule_'.$rule.'_body') }}</dd>
                </div>
              </div>
            @endforeach
          </dl>
        </div>

        </fieldset>

        @if ($canManage)
          <div>
            <button type="submit"
                    class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('common.save') }}
            </button>
          </div>
        @endif
      </form>
      @endif

    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* The switch saves itself.

       It is the only control a reader touches in its form, and a toggle that
       needed a Save button beside it would be one that looks like it has
       already taken effect and has not. Without this script the form still
       posts — the button below it is the fallback, and the page reloads
       either way, which is what makes the settings underneath appear. */
    (function () {
      var form = document.querySelector('[data-membership-switch]');
      if (!form) return;

      var toggle = form.querySelector('[data-membership-enabled] input[type="checkbox"]');
      if (!toggle) return;

      var fallback = form.querySelector('[data-membership-fallback]');
      if (fallback) fallback.hidden = true;

      toggle.addEventListener('change', function () {
        form.submit();
      });
    }());
  </script>
@endpush
