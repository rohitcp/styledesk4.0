@extends('layouts.app')

@section('title', __('sms.settings.title'))

{{--
    App Settings → SMS.

    The same shape Reviews uses: the switch is its own form at the top and
    saves itself, and nothing below it exists until it is on. None of those
    questions mean anything to a business that is not texting, and a
    screenful of settings that do nothing is a screen that has to be read
    before it can be dismissed.

    Switching off hides them; it erases nothing. The switch form carries the
    current choices as hidden fields, so stopping for a week and starting
    again finds them exactly as they were.
--}}

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('sms.settings.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('sms.settings.title') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('sms.settings.intro') }}</p>
        </div>

        <a href="{{ route('settings.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      {{-- The number.

           Not a setting this business makes. Every business on StyleDesk
           sends from one shared number, which is what makes a single 10DLC
           registration cover everybody — and what makes a client's reply
           ambiguous, since it arrives naming no salon at all.

           Stated rather than editable, because a business that could change
           it could send from a number StyleDesk has not registered. --}}
      <div class="sd-card p-5 mt-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="styledesk_eyebrow">{{ __('sms.settings.sender') }}</p>
            <p class="text-[17px] font-semibold text-head mt-1 tabular-nums">
              {{ $settings->senderNumber() ?: __('sms.settings.no_number') }}
            </p>
            <p class="text-[12.5px] text-sub mt-1 leading-relaxed max-w-[520px]">{{ __('sms.settings.sender_hint') }}</p>
          </div>

          <div class="shrink-0 text-right">
            <p class="styledesk_eyebrow">{{ __('sms.settings.registration') }}</p>
            <span class="styledesk_badge mt-1 {{ $settings->registrationClass() }}">{{ $settings->registrationLabel() }}</span>
          </div>
        </div>
      </div>

      {{-- One message, to prove the wiring.

           Its own form, above the switch and outside it: this is not a
           setting, it is a question — does any of this work — and the answer
           is worth having before a business turns texting on for its whole
           client list.

           It goes through the ordinary path: same service, same provider,
           same row in the log. A test that took a shortcut would prove the
           shortcut works. --}}
      <form method="POST" action="{{ route('settings.sms.test') }}" class="mt-5">
        @csrf

        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('sms.settings.test') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed max-w-[560px]">{{ __('sms.settings.test_hint') }}</p>

          <div class="mt-3 flex flex-wrap items-end gap-2.5">
            <div class="w-full sm:w-[240px]">
              <label for="testTo" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('sms.settings.test_to') }}
              </label>
              <input id="testTo" type="tel" name="to" class="sd-input @error('to') is-error @enderror"
                     value="{{ old('to', $testNumber) }}" placeholder="+1 201 555 0101" required>
            </div>

            <button type="submit"
                    class="h-11 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('sms.settings.send_test') }}
            </button>
          </div>

          @error('to')
            <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
          @enderror

          {{-- Which provider will actually carry it. "Sent" means something
               different when nothing is connected, and the desk should not
               have to guess which of the two it just proved. --}}
          <p class="mt-3 text-[12px] text-sub bg-hover rounded-lg px-3 py-2">
            {{ config('services.clicksend.key')
                ? __('sms.settings.test_live')
                : __('sms.settings.test_local') }}
          </p>
        </div>
      </form>

      {{-- The switch, on its own and posting on its own.

           It is not one of the settings below it — it decides whether they
           are asked about at all — so it sits above them rather than inside
           their form. --}}
      <form method="POST" action="{{ route('settings.sms.update') }}" class="mt-5">
        @csrf
        @method('PATCH')

        @foreach ($settings->messages ?? [] as $message)
          <input type="hidden" name="messages[]" value="{{ $message }}">
        @endforeach
        @foreach ($settings->reminder_hours ?? [] as $hour)
          <input type="hidden" name="reminder_hours[]" value="{{ $hour }}">
        @endforeach
        <input type="hidden" name="birthday_send_at" value="{{ $settings->birthday_send_at ? substr((string) $settings->birthday_send_at, 0, 5) : '09:00' }}">
        <input type="hidden" name="monthly_limit" value="{{ $settings->monthly_limit }}">
        <input type="hidden" name="alert_percent" value="{{ $settings->alert_percent }}">

        <div class="sd-card p-5">
          <x-toggle name="is_enabled" :label="__('sms.settings.enable')"
                    :hint="__('sms.settings.enable_hint')" :checked="$settings->is_enabled" />

          @unless ($settings->is_enabled)
            <p class="mt-3 text-[12px] text-sub bg-hover rounded-lg px-3 py-2">{{ __('sms.settings.disabled_note') }}</p>
          @endunless

          <button type="submit" class="styledesk_action mt-3">{{ __('common.save') }}</button>
        </div>
      </form>

      {{-- Nothing below means anything until SMS is on, so until it is,
           there is nothing below. --}}
      @if ($settings->is_enabled)
      <form method="POST" action="{{ route('settings.sms.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('PATCH')

        {{-- Saving the messages must not switch the texting off: the switch
             is not in this form, so its answer travels as a hidden field. --}}
        <input type="hidden" name="is_enabled" value="1">

        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('sms.settings.messages') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('sms.settings.messages_hint') }}</p>

          <div class="mt-3 space-y-2">
            @foreach ($messages as $key => $message)
              {{-- A message nothing can produce is shown and disabled rather
                   than hidden: the screen should say what is planned, and a
                   business must not be able to switch on a text that never
                   goes out. --}}
              <x-choice type="checkbox" name="messages[]" :value="$key"
                        :label="__('sms.types.'.$key)"
                        :hint="$message['available'] ? null : __('sms.settings.not_yet')"
                        :checked="in_array($key, $settings->messages ?? [], true)"
                        :disabled="! $message['available']" />
            @endforeach
          </div>
        </div>

        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('sms.settings.reminders') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('sms.settings.reminders_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($reminderHours as $hour)
              <x-choice type="checkbox" name="reminder_hours[]" :value="$hour"
                        :label="trans_choice('sms.settings.hours_before', $hour, ['count' => $hour])"
                        :checked="in_array($hour, $settings->reminder_hours ?? [], true)" />
            @endforeach
          </div>

          <div class="mt-4 max-w-[220px]">
            <label for="birthdayAt" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('sms.settings.birthday_at') }}
            </label>
            <input id="birthdayAt" type="time" name="birthday_send_at" class="sd-input"
                   value="{{ $settings->birthday_send_at ? substr((string) $settings->birthday_send_at, 0, 5) : '09:00' }}">
            <p class="mt-1.5 text-[12px] text-sub">{{ __('sms.settings.birthday_at_hint') }}</p>
          </div>
        </div>

        {{-- What the business is willing to spend.

             A runaway loop or a bulk import must not be able to text ten
             thousand people, and the first anybody hears of it should not be
             the invoice. --}}
        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('sms.settings.spend') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('sms.settings.spend_hint') }}</p>

          <div class="mt-3 grid gap-3 sm:grid-cols-2 max-w-[520px]">
            <div>
              <label for="monthlyLimit" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('sms.settings.monthly_limit') }}
                <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="monthlyLimit" type="number" name="monthly_limit" min="1" class="sd-input"
                     value="{{ $settings->monthly_limit }}" placeholder="{{ __('sms.settings.no_limit') }}">
            </div>

            <div>
              <label for="alertPercent" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('sms.settings.alert_at') }}
              </label>
              <div class="relative">
                <input id="alertPercent" type="number" name="alert_percent" min="10" max="100"
                       class="sd-input pr-8" value="{{ $settings->alert_percent }}">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-faint text-[13px]">%</span>
              </div>
            </div>
          </div>

          {{-- What has actually gone out. Segments beside messages, because
               that is what a carrier bills — a template that quietly became
               two segments doubles the bill without the count moving. --}}
          <dl class="mt-4 grid gap-2 sm:grid-cols-2 max-w-[520px]">
            <div class="rounded-lg border border-line px-3 py-2.5">
              <dt class="text-[11.5px] text-sub">{{ __('sms.settings.used_this_month') }}</dt>
              <dd class="text-[17px] font-bold text-head tabular-nums leading-tight mt-0.5">{{ $used }}</dd>
            </div>

            <div class="rounded-lg border border-line px-3 py-2.5">
              <dt class="text-[11.5px] text-sub">{{ __('sms.settings.segments_this_month') }}</dt>
              <dd class="text-[17px] font-bold text-head tabular-nums leading-tight mt-0.5">{{ $segments }}</dd>
            </div>
          </dl>
        </div>

        <div>
          <button type="submit"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('common.save') }}
          </button>
        </div>
      </form>
      @endif
    </div>
  </main>
@endsection
