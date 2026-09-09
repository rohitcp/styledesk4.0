@extends('layouts.app')

@section('title', __('shift_rules.title'))

@section('content')
  {{-- One page for the whole feature: the switch that turns it off, the rules
       themselves as cards, and the form for adding or editing one. `add` and
       `edit` are states of this screen rather than screens of their own, which
       is what lets the reader keep their place. --}}
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 pb-[120px]">
    <div class="max-w-[980px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        @if ($mode === 'list')
          <span class="text-ink">{{ __('shift_rules.title') }}</span>
        @else
          {{-- A way back to the list from inside the form, in the place a
               reader looks for it. --}}
          <a href="{{ route('settings.shift-rules.index') }}" class="hover:text-ink transition-colors">{{ __('shift_rules.title') }}</a>
          <span class="mx-1.5 text-faint">/</span>
          <span class="text-ink">{{ $mode === 'edit' ? $rule->name : __('common.add') }}</span>
        @endif
      </nav>

      <header class="mt-3">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('shift_rules.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('shift_rules.intro') }}</p>
      </header>

      {{-- The feature switch, at the top and always visible: it is the answer
           to "why is this page empty", so it must not be the thing that
           disappears when it is off. --}}
      <section class="mt-5 bg-white border border-line rounded-card p-5">
        <form method="POST" action="{{ route('settings.shift-rules.feature') }}" data-feature-form>
          @csrf
          @method('PATCH')

          {{-- The unchecked value, so switching off says "off" rather than
               saying nothing and leaving the server to guess. --}}
          <input type="hidden" name="enabled" value="0">

          <label class="styledesk_toggle">
            <input type="checkbox" name="enabled" value="1" class="styledesk_toggle__input"
                   @checked($enabled) data-feature-toggle>
            <span class="styledesk_toggle__track" aria-hidden="true">
              <span class="styledesk_toggle__knob"></span>
            </span>
            <span class="min-w-0 flex-1">
              <span class="styledesk_toggle__label">{{ __('shift_rules.enable') }}</span>
              <span class="styledesk_toggle__hint">{{ __('shift_rules.enable_hint') }}</span>
            </span>
          </label>

          {{-- Submits itself when the switch moves; the button is the fallback
               for a page with no JavaScript, not a second way to do it. --}}
          <noscript>
            <button type="submit" class="styledesk_action mt-3">{{ __('common.save') }}</button>
          </noscript>
        </form>
      </section>

      @if (! $enabled)
        {{-- Off. Every rule is kept — the list is hidden, not emptied — and
             the sentence says so, because a business that has written twelve
             rules needs to know they are still there. --}}
        <div class="mt-5 border-t border-line py-14 text-center">
          <p class="text-[15px] font-semibold text-head">{{ __('shift_rules.feature_off_title') }}</p>
          <p class="text-[13px] text-sub mt-1.5 max-w-[440px] mx-auto leading-relaxed">
            {{ trans_choice('shift_rules.feature_off_kept', $rules->count(), ['count' => $rules->count()]) }}
          </p>
        </div>

      @elseif ($mode === 'list')
        <div class="mt-5 flex flex-wrap items-center gap-3">
          <h2 class="text-[15px] font-semibold text-head min-w-0 flex-1">
            {{ trans_choice('shift_rules.rule_count', $rules->count(), ['count' => $rules->count()]) }}
          </h2>

          <a href="{{ route('settings.shift-rules.index', ['add' => 1]) }}"
             class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('shift_rules.add') }}
          </a>
        </div>

        @if ($rules->isEmpty())
          <div class="mt-4 border-t border-line py-14 text-center">
            <p class="text-[15px] font-semibold text-head">{{ __('shift_rules.none_yet') }}</p>
            <p class="text-[13px] text-sub mt-1.5 max-w-[480px] mx-auto leading-relaxed">{{ __('shift_rules.none_yet_hint') }}</p>

            <a href="{{ route('settings.shift-rules.index', ['add' => 1]) }}"
               class="inline-flex items-center gap-1.5 h-9 px-3.5 mt-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              <x-icon name="plus" size="14" />
              {{ __('shift_rules.add') }}
            </a>
          </div>
        @else
          {{-- Cards rather than a table: a rule is half a dozen facts of
               different shapes — a name, a badge, a week, a limit, a date
               range — and a row forces each of them into a column width
               decided by the widest of them. --}}
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($rules as $card)
              @include('settings.shift-rules._card', ['card' => $card])
            @endforeach
          </div>
        @endif

      @else
        {{-- Adding or editing, in place. The same form, the same validation
             and the same save as before — only the address it lives at has
             changed. --}}
        <div class="mt-5 flex flex-wrap items-center gap-3">
          <h2 class="text-[15px] font-semibold text-head min-w-0 flex-1">
            {{ $mode === 'edit' ? __('shift_rules.edit_title') : __('shift_rules.add_title') }}
          </h2>

          <a href="{{ route('settings.shift-rules.index') }}" class="styledesk_action shrink-0">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('shift_rules.back_to_list') }}
          </a>
        </div>

        @if ($errors->any())
          <div class="sd-alert sd-alert--danger mt-4" role="alert">
            <p class="min-w-0">{{ $errors->first() }}</p>
          </div>
        @endif

        {{-- Live validation, the same module and the same messages as Add
             Client and the sign-up form. The rules live on the fields; this
             only says which words to refuse them in. --}}
        <form id="shiftRuleForm" method="POST" class="mt-4 space-y-5"
              data-validate-form
              data-validation-messages='@json(\App\Support\LiveValidation::messages([
                  "taken" => __("shift_rules.validation.name_taken"),
              ]))'
              action="{{ $mode === 'edit'
                  ? route('settings.shift-rules.update', $rule)
                  : route('settings.shift-rules.store') }}">
          @csrf
          @if ($mode === 'edit')
            @method('PATCH')
          @endif

          @include('settings.shift-rules._form', ['rule' => $rule])

          <div class="flex flex-wrap items-center gap-3">
            <button type="submit" data-submit-once data-busy-label="{{ __('shift_rules.saving') }}"
                    class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('shift_rules.save') }}
            </button>

            {{-- Only when adding: "add another" after editing one rule would
                 be a different act wearing the same label. --}}
            @if ($mode === 'add')
              <button type="submit" name="after_save" value="add_another" data-submit-once
                      data-busy-label="{{ __('shift_rules.saving') }}" class="styledesk_action">
                {{ __('shift_rules.save_and_add_another') }}
              </button>
            @endif

            <a href="{{ route('settings.shift-rules.index') }}" class="styledesk_action">{{ __('common.cancel') }}</a>
          </div>
        </form>
      @endif
    </div>
  </main>
@endsection
