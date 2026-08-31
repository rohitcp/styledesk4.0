@extends('layouts.app')

@section('title', __('staff.add_title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        {{-- Only in App Settings. The same screens are reached from the
             Staff module, where the trail does not run through a section the
             reader was never in — and where a link into an administrator-only
             area would be one they cannot open. --}}
        @if (\App\Support\StaffSection::isSettings())
          <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
          <span class="mx-1.5 text-faint">/</span>
        @endif
        <a href="{{ \App\Support\StaffSection::route('index') }}" class="hover:text-ink transition-colors">{{ __('staff.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('common.add') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('staff.add_title') }}</h1>
          <p class="text-[14px] text-sub mt-2 leading-relaxed">
            {{ __('staff.add_intro') }}
          </p>
        </div>

        <a href="{{ \App\Support\StaffSection::route('index') }}" data-back
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('staff.correct_fields') }}</p>
        </div>
      @endif

      <form id="staffForm" method="POST" action="{{ \App\Support\StaffSection::route('store') }}"
            enctype="multipart/form-data" class="mt-6 space-y-5">
        @csrf

        @include('settings.staff._form', ['staff' => null])

        {{-- Two ways to save, one form. The second posts the same body with
             after_save set, so the difference is where the reader is put down
             afterwards rather than a second code path through the save. --}}
        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="staffSave" name="after_save" value="index"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('staff.add') }}
          </button>

          {{-- For the morning somebody sits down to enter the whole team.
               Returning to the directory between each one costs a page load
               and a scroll to find the button again. --}}
          <button type="submit" name="after_save" value="add_another"
                  class="styledesk_action">
            {{ __('staff.save_and_add_another') }}
          </button>

          <a href="{{ \App\Support\StaffSection::route('index') }}"
             class="styledesk_action">
            {{ __('common.cancel') }}
          </a>
        </div>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    (function () {
      var form = document.getElementById('staffForm');
      if (!form) return;

      /* The invitation options only mean anything with a login, so they are
         hidden rather than left to be filled in and silently ignored. */
      /* The invitation block only exists when adding someone, so both are
         checked before either is used. */
      var login = document.getElementById('login_enabled');
      var block = document.querySelector('[data-invite-block]');

      if (login && block) {
        var syncInvite = function () { block.hidden = !login.checked; };
        login.addEventListener('change', syncInvite);
        syncInvite();
      }

      /* One submission. Creating a staff member sends an email, and a double
         click would send two. Both buttons are stopped, and the one that was
         actually pressed is the one that reports progress — "Adding…" on the
         button nobody clicked reads as the wrong thing happening. */
      var saving = false;

      form.addEventListener('submit', function (e) {
        if (saving) {
          e.preventDefault();
          return;
        }
        saving = true;

        var pressed = e.submitter;

        /* On the next tick, not this one: a disabled control is left out of
           the submitted data, and after_save lives on the button that was
           pressed — disabling it here would post without it. */
        window.setTimeout(function () {
          form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = true;
            button.classList.add('opacity-60', 'pointer-events-none');
          });

          if (pressed) {
            pressed.textContent = @json(__('staff.adding'));
          }
        }, 0);
      });
    }());
  </script>
@endpush
