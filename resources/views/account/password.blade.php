@extends('layouts.app')

@section('title', __('account.password.title'))

@section('content')
<x-account.shell current="password" :title="__('account.password.title')" :intro="__('account.password.intro')">

  {{-- Read first, edit on request; the partial at the foot of the page does
       the toggling and explains the contract.

       "Read" is a thin thing here, because a password is the one setting the
       screen cannot show back — the closed card says only that there is one.
       It still earns its place: three password fields, a strength meter and a
       checklist are a lot of screen to hand somebody who came to check they
       were on the right page. A refused attempt leaves the fields open, since
       the messages are attached to them. --}}
  <form method="POST" action="{{ route('account.password.update') }}" class="space-y-5 max-w-[520px]" id="passwordForm"
        data-editable-card data-editing="{{ $errors->any() ? 'true' : 'false' }}">
    @csrf
    @method('PUT')

    <section class="bg-white border border-line rounded-card p-5 sm:p-6 space-y-5">
      <div class="flex items-start justify-between gap-4">
        <h3 class="text-[15px] font-semibold text-head">{{ __('account.password.card') }}</h3>

        {{-- Hidden in the markup and shown by the script: with no script the
             fields are already open, and an Edit that opens what is open is a
             button that does nothing. --}}
        <button type="button" class="styledesk_action shrink-0" data-editable-edit hidden
                aria-controls="passwordFields" aria-expanded="false">
          <x-icon name="pen-to-square" size="14" />
          {{ __('common.edit') }}
        </button>
      </div>

      {{-- Dots, because a password is the one setting a screen may not show
           back. They are here so the card reads as a card and not as a
           heading with a button beside it — what the reader learns is that
           there is a password and that changing it is one press away. --}}
      <p class="text-[16px] tracking-[0.3em] text-faint" data-editable-view hidden
         aria-label="{{ __('account.password.hidden') }}">
        &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;
      </p>

      <div id="passwordFields" class="space-y-5" data-editable-fields>
        <div>
          <label for="current_password" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.password.current') }}</label>
          <div class="relative">
            <input id="current_password" name="current_password" type="password" required
                   @class(['sd-input', 'has-suffix', 'is-error' => $errors->has('current_password')])
                   autocomplete="current-password">
            <x-password-toggle for="current_password" class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors" />
          </div>
          <p id="current_password-error" data-error-for="current_password" role="alert" class="mt-1.5 text-[12px] text-danger"
             @unless ($errors->has('current_password')) hidden @endunless>{{ $errors->first('current_password') }}</p>
        </div>

        <div>
          <label for="password" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.password.new') }}</label>
          <div class="relative">
            <input id="password" name="password" type="password" required
                   @class(['sd-input', 'has-suffix', 'is-error' => $errors->has('password')])
                   autocomplete="new-password">
            <x-password-toggle for="password" class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors" />
          </div>
          <p id="password-error" data-error-for="password" role="alert" class="mt-1.5 text-[12px] text-danger"
             @unless ($errors->has('password')) hidden @endunless>{{ $errors->first('password') }}</p>

          {{-- Strength: bar plus word. The word carries the meaning and the bar
               only reinforces it, so nothing depends on colour alone. --}}
          <div class="mt-2.5 flex items-center gap-3">
            <div id="meter" class="sd-meter flex-1" data-level="0" aria-hidden="true">
              <span class="sd-meter__seg"></span><span class="sd-meter__seg"></span>
              <span class="sd-meter__seg"></span><span class="sd-meter__seg"></span>
            </div>
            <span id="password-strength" class="text-[12px] font-medium text-faint w-[46px] text-right" role="status" aria-live="polite">—</span>
          </div>

          {{-- The requirements, ticking off as they are met. Written by script
               from the same rule list the sign-up form uses, so the two screens
               cannot describe the same password differently. --}}
          <p class="mt-3 text-[12px] font-medium text-sub">{{ __('account.password.requirements') }}</p>
          <ul id="password-rules" class="mt-1.5 grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1"></ul>
        </div>

        <div>
          <label for="password_confirmation" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.password.confirm') }}</label>
          <div class="relative">
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   class="sd-input has-suffix" autocomplete="new-password">
            <x-password-toggle for="password_confirmation" class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors" />
          </div>
          <p id="password_confirmation-error" data-error-for="password_confirmation" role="alert"
             class="mt-1.5 text-[12px] text-danger" hidden></p>
        </div>
      </div>
    </section>

    {{-- Part of the same change and shown with it: what to do about the other
         places this account is signed in is a question about the new password,
         not a standing setting. --}}
    <section class="bg-white border border-line rounded-card p-5 sm:p-6" data-editable-fields>
      <x-toggle name="logout_other_devices"
                :label="__('account.password.logout_others')"
                :hint="__('account.password.logout_others_hint')"
                :checked="true" />
    </section>

    <div class="flex flex-wrap items-center gap-3" data-editable-fields>
      <button type="submit" data-submit-once
              class="inline-flex items-center h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
        {{ __('account.password.save') }}
      </button>

      {{-- A link, so that with no script it still does the only thing it can
           do: fetch the page again and discard what was typed. The script
           turns it into a close-and-reset that costs no round trip, which
           also clears three password fields from the page. --}}
      <a href="{{ route('account.password') }}" data-editable-cancel
         class="h-11 px-4 inline-flex items-center rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
        {{ __('account.cancel') }}
      </a>
    </div>
  </form>

  @include('account.partials._editable')
</x-account.shell>
@endsection

@push('scripts')
<script>
    /*
     * The rule checklist and the strength meter.
     *
     * The scoring is not reimplemented here: SD.checkPassword and
     * SD.PASSWORD_RULES come from the shared module the sign-up form uses, so
     * a password graded on one screen is graded the same on the other — and
     * the server enforces that same list through PasswordValidationRules.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var password = document.getElementById('password');
        var confirm = document.getElementById('password_confirmation');
        var meter = document.getElementById('meter');
        var strength = document.getElementById('password-strength');
        var rulesList = document.getElementById('password-rules');

        if (!password || !window.SD) return;

        var CHECK = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="currentColor"/><path d="M8 12l2.5 2.5L16 9" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        var DOT = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/></svg>';

        rulesList.innerHTML = SD.PASSWORD_RULES.map(function (rule) {
            return '<li data-rule="' + rule.id + '" class="flex items-center gap-1.5 text-[12px] text-faint">' +
                   '<span class="shrink-0">' + DOT + '</span>' + rule.label + '</li>';
        }).join('');

        function paint() {
            var result = SD.checkPassword(password.value);

            result.rules.forEach(function (r) {
                var li = rulesList.querySelector('[data-rule="' + r.id + '"]');
                if (!li) return;
                li.className = 'flex items-center gap-1.5 text-[12px] ' + (r.pass ? 'text-success' : 'text-faint');
                li.firstElementChild.innerHTML = r.pass ? CHECK : DOT;
            });

            meter.setAttribute('data-level', String(result.score));
            strength.textContent = result.label || '—';
            strength.className = 'text-[12px] font-medium w-[46px] text-right ' +
                (result.score >= 4 ? 'text-success' : result.score === 3 ? 'text-link'
                 : result.score === 2 ? 'text-warning' : result.score === 1 ? 'text-danger' : 'text-faint');

            return result;
        }

        function matches() {
            if (confirm.value && confirm.value !== password.value) {
                SD.setError(confirm, @json(__('account.password.mismatch')));
                return false;
            }
            SD.setError(confirm, '');
            return true;
        }

        password.addEventListener('input', function () {
            paint();
            if (password.classList.contains('is-error')) SD.setError(password, '');
            if (confirm.value) matches();
        });

        confirm.addEventListener('input', matches);

        /* Nothing is submitted until the checklist and the confirmation agree
           — the server checks both again, but a round trip to be told what
           the page already knew is a round trip wasted. */
        document.getElementById('passwordForm').addEventListener('submit', function (e) {
            var ok = true;

            if (SD.checkPassword(password.value).rules.some(function (r) { return !r.pass; })) {
                SD.setError(password, @json(__('account.password.requirements')));
                ok = false;
            }

            if (!matches()) ok = false;

            if (!ok) {
                e.preventDefault();
                SD.focusFirstError(this);
            }
        });

        paint();
    });
</script>
@endpush
