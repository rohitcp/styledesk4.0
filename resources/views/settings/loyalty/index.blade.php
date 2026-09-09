@extends('layouts.app')

@section('title', __('loyalty.settings.title'))

{{--
    App Settings → Loyalty & Rewards.

    The same shape Reviews and Tips use: the switch is its own form at the top
    and saves itself, and nothing below it exists until it is on. None of these
    questions mean anything to a business that is not running a scheme, and a
    screenful of settings that do nothing is a screen that has to be read
    before it can be dismissed.

    Switching off hides them; it erases nothing. The switch form carries every
    other answer as hidden fields, so pausing rewards in November and starting
    again in March finds the rate, the rule and every client's balance exactly
    as they were.
--}}

@php
    $canManage = $canManage ?? false;
    $rewardValue = old('reward_value', number_format($settings->reward_value_minor / 100, 2, '.', ''));
    $maximumReward = old('maximum_reward', $settings->maximum_reward_minor === null
        ? null
        : number_format($settings->maximum_reward_minor / 100, 2, '.', ''));
@endphp

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('loyalty.settings.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('loyalty.settings.title') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('loyalty.settings.intro') }}</p>
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
           so switching the scheme on cannot quietly reset the rate somebody
           chose last year. --}}
      <form method="POST" action="{{ route('settings.loyalty.update') }}" class="mt-6" data-loyalty-switch>
        @csrf
        @method('PATCH')

        <input type="hidden" name="program_name" value="{{ $settings->program_name }}">
        <input type="hidden" name="description" value="{{ $settings->description }}">
        <input type="hidden" name="spend_amount" value="{{ $settings->spend_amount }}">
        <input type="hidden" name="points_earned" value="{{ $settings->points_earned }}">
        <input type="hidden" name="points_required" value="{{ $settings->points_required }}">
        <input type="hidden" name="reward_value" value="{{ number_format($settings->reward_value_minor / 100, 2, '.', '') }}">
        <input type="hidden" name="minimum_redemption" value="{{ $settings->minimum_redemption }}">
        @if ($settings->maximum_reward_minor !== null)
          <input type="hidden" name="maximum_reward" value="{{ number_format($settings->maximum_reward_minor / 100, 2, '.', '') }}">
        @endif
        <input type="hidden" name="expiry" value="{{ $settings->expiry }}">
        @foreach ($settings->eligible_purchases ?? [] as $purchase)
          <input type="hidden" name="eligible_purchases[]" value="{{ $purchase }}">
        @endforeach

        <div class="sd-card p-5">
          {{-- A reader who may look but not change gets the switch as it
               stands, refused by the browser rather than by a 403 after they
               have already flicked it. A disabled fieldset disables the real
               checkbox inside the component, which passing an attribute
               could not — it would land on the label. --}}
          <fieldset @disabled(! $canManage) class="contents">
            <x-toggle name="is_enabled" :label="__('loyalty.settings.enable')"
                      :hint="__('loyalty.settings.enable_hint')" :checked="$settings->is_enabled"
                      data-loyalty-enabled />
          </fieldset>

          @unless ($settings->is_enabled)
            <p class="mt-3 text-[12px] text-sub bg-hover rounded-lg px-3 py-2">{{ __('loyalty.settings.disabled_note') }}</p>
          @endunless

          {{-- Only ever seen with the script blocked, which hides it and
               saves on the toggle instead. Without it the switch would be the
               one control on the screen that cannot be operated at all. --}}
          @if ($canManage)
            <button type="submit" class="styledesk_action mt-3" data-loyalty-fallback>
              {{ __('common.save') }}
            </button>
          @endif
        </div>
      </form>

      {{-- Nothing below means anything until the scheme is on, so until it
           is, there is nothing below. --}}
      @if ($settings->is_enabled)
      <form method="POST" action="{{ route('settings.loyalty.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('PATCH')

        {{-- Saving the rules must not switch the scheme off: the toggle is
             not in this form, so its answer travels as a hidden field. --}}
        <input type="hidden" name="is_enabled" value="1">

        {{-- Look but do not touch, for a reader holding the view permission
             and not the manage one. Refused by the browser rather than by a
             403 after the form has been filled in. --}}
        {{-- `space-y-5` as well as `contents`.

             The form's own `space-y-5` is a `> * + *` rule, so it only ever
             sees this fieldset — one child, nothing to space. Every card
             inside it came out flush against its neighbour. The gap has to be
             declared where the cards actually are. --}}
        <fieldset @disabled(! $canManage) class="contents space-y-5">

        {{-- --------------------------------------------- Card 1: program --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('loyalty.settings.program') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.settings.program_hint') }}</p>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-text-field name="program_name" :label="__('loyalty.settings.program_name')"
                          :value="$settings->program_name" :hint="__('loyalty.settings.program_name_hint')"
                          maxlength="60" required />
          </div>

          <div class="mt-4">
            <label for="description" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.settings.description') }}</label>
            <input id="description" name="description" type="text" maxlength="255" class="sd-input"
                   placeholder="{{ __('loyalty.settings.description_placeholder') }}"
                   value="{{ old('description', $settings->description) }}">
            <p class="mt-1.5 text-[12px] text-sub">{{ __('loyalty.settings.description_hint') }}</p>
          </div>
        </div>

        {{-- ------------------------------------------ Card 2: earn points --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('loyalty.settings.earn') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.settings.earn_hint') }}</p>

          {{-- The rule read as a sentence rather than as two labelled boxes:
               "$1 spent = 1 point" is how a business states it out loud, and
               a form that matches the sentence is a form nobody has to work
               out the direction of. --}}
          <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="w-[150px]">
              <label for="spend_amount" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.settings.spend_amount') }}</label>
              <div class="relative">
                <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">{{ $symbol }}</span>
                <input id="spend_amount" name="spend_amount" type="number" min="1" step="1" required
                       class="sd-input styledesk_input--prefixed"
                       value="{{ old('spend_amount', $settings->spend_amount) }}">
              </div>
            </div>

            <span class="text-[15px] font-semibold text-sub pb-2" aria-hidden="true">=</span>

            <div class="w-[150px]">
              <label for="points_earned" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.settings.points_earned') }}</label>
              <input id="points_earned" name="points_earned" type="number" min="1" step="1" required
                     class="sd-input" value="{{ old('points_earned', $settings->points_earned) }}">
            </div>
          </div>

          <h3 class="text-[13px] font-semibold text-head mt-6">{{ __('loyalty.settings.eligible') }}</h3>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.settings.eligible_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($purchases as $key => $purchase)
              {{-- A purchase type nothing can sell yet is shown and disabled
                   rather than hidden: the screen should say what is planned,
                   and a business must not be able to switch on an earning
                   rule that would silently award nothing.

                   Services are ticked and locked. They are what a booking is
                   made of, and a scheme that is on with nothing earning reads
                   as a bug rather than as a choice. --}}
              @php
                $locked = $key === 'services';
                $ticked = $locked || in_array($key, old('eligible_purchases', $settings->eligible_purchases ?? []), true);
              @endphp

              <x-choice type="checkbox" name="eligible_purchases[]" :value="$key"
                        :label="__('loyalty.purchases.'.$key)"
                        :hint="$locked
                            ? __('loyalty.settings.always_on')
                            : ($purchase['available'] ? null : __('loyalty.settings.coming_soon'))"
                        :checked="$ticked"
                        :disabled="$locked || ! $purchase['available']" />

              {{-- A disabled box posts nothing, and services must post. --}}
              @if ($locked)
                <input type="hidden" name="eligible_purchases[]" value="services">
              @endif
            @endforeach
          </div>
        </div>

        {{-- ---------------------------------------- Card 3: redeem points --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('loyalty.settings.redeem') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.settings.redeem_hint') }}</p>

          <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="w-[170px]">
              <label for="points_required" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.settings.points_required') }}</label>
              <input id="points_required" name="points_required" type="number" min="1" step="1" required
                     class="sd-input" value="{{ old('points_required', $settings->points_required) }}">
            </div>

            <span class="text-[15px] font-semibold text-sub pb-2" aria-hidden="true">=</span>

            <div class="w-[170px]">
              <label for="reward_value" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.settings.reward_value') }}</label>
              <div class="relative">
                <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">{{ $symbol }}</span>
                <input id="reward_value" name="reward_value" type="number" min="0.01" step="0.01" required
                       class="sd-input styledesk_input--prefixed" value="{{ $rewardValue }}">
              </div>
            </div>
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="minimum_redemption" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.settings.minimum_redemption') }}</label>
              <input id="minimum_redemption" name="minimum_redemption" type="number" min="0" step="1" required
                     class="sd-input" value="{{ old('minimum_redemption', $settings->minimum_redemption) }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('loyalty.settings.minimum_hint') }}</p>
            </div>

            <div>
              <label for="maximum_reward" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.settings.maximum_reward') }}</label>
              <div class="relative">
                <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">{{ $symbol }}</span>
                <input id="maximum_reward" name="maximum_reward" type="number" min="0.01" step="0.01"
                       class="sd-input styledesk_input--prefixed" value="{{ $maximumReward }}">
              </div>
              <p class="mt-1.5 text-[12px] text-sub">{{ __('loyalty.settings.maximum_hint') }}</p>
            </div>
          </div>
        </div>

        {{-- ------------------------------------------ Card 4: expiration --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('loyalty.settings.expiry') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.settings.expiry_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($expiries as $expiry)
              <x-choice type="radio" name="expiry" :value="$expiry"
                        :label="__('loyalty.expiry.'.$expiry)"
                        :checked="old('expiry', $settings->expiry) === $expiry" />
            @endforeach
          </div>
        </div>

        {{-- --------------------------------------- Card 5: notifications --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('loyalty.settings.notifications') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.settings.notifications_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($notifications as $key => $notification)
              {{-- Shown and disabled, the same way an undeliverable review
                   channel is: the engine landed before the messages did, and
                   a toggle that promised a client an email nobody has written
                   is worse than one that says "coming soon". --}}
              <x-choice type="checkbox" :name="'notify_'.$key" value="1"
                        :label="__('loyalty.notifications.'.$key)"
                        :hint="$notification['available'] ? null : __('loyalty.settings.coming_soon')"
                        :checked="(bool) $settings->{'notify_'.$key}"
                        :disabled="! $notification['available']" />
            @endforeach
          </div>
        </div>

        {{-- -------------------------------------- Card 6: business rules --}}
        {{-- What StyleDesk decides rather than what the business does. Stated
             here rather than left to be discovered on a Friday afternoon when
             a refund takes points back and nobody expected it to. --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('loyalty.settings.rules') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.settings.rules_hint') }}</p>

          <dl class="mt-4 space-y-4">
            @foreach (['locations', 'awarded', 'refunds', 'calculation'] as $rule)
              <div class="flex items-start gap-3">
                <span class="styledesk_settingcard__icon shrink-0 !h-8 !w-8" aria-hidden="true">
                  <x-icon name="shield-halved" size="14" />
                </span>
                <div class="min-w-0">
                  <dt class="text-[13px] font-semibold text-head">{{ __('loyalty.settings.rule_'.$rule) }}</dt>
                  <dd class="text-[12.5px] text-sub mt-0.5 leading-relaxed">{{ __('loyalty.settings.rule_'.$rule.'_body') }}</dd>
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
      var form = document.querySelector('[data-loyalty-switch]');
      if (!form) return;

      var toggle = form.querySelector('[data-loyalty-enabled] input[type="checkbox"]');
      if (!toggle) return;

      var fallback = form.querySelector('[data-loyalty-fallback]');
      if (fallback) fallback.hidden = true;

      toggle.addEventListener('change', function () {
        form.submit();
      });
    }());
  </script>
@endpush
