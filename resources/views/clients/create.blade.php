@extends('layouts.app')

@section('title', __('clients.module.add_title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('clients.index') }}" class="hover:text-ink transition-colors">{{ __('clients.module.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('common.add') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('clients.module.add_title') }}</h1>
        </div>

        <a href="{{ route('clients.index') }}"
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('clients.module.correct_fields') }}</p>
        </div>
      @endif

      {{-- Possible duplicates, per §7.
           A warning that names who it found: "this might be a duplicate" with
           nothing to look at leaves the receptionist no way to decide. Nothing
           is merged, and the form is not blocked — someone who has checked and
           knows they are different people confirms and continues. --}}
      @if (session('duplicates'))
        <div class="sd-alert sd-alert--info mt-5" role="alert">
          <div class="min-w-0">
            <p class="font-semibold">{{ __('clients.module.duplicates_title') }}</p>
            <p class="mt-1">{{ __('clients.module.duplicates_body') }}</p>

            <ul class="mt-2.5 space-y-1.5">
              @foreach (session('duplicates') as $match)
                <li class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[13px]">
                  <a href="{{ $match['url'] }}" class="font-medium text-link hover:underline">{{ $match['name'] }}</a>
                  <span class="font-mono text-[12px] text-sub">{{ $match['ref'] }}</span>
                  @if ($match['email'])<span class="text-sub">{{ $match['email'] }}</span>@endif
                  @if ($match['mobile'])<span class="text-sub">{{ $match['mobile'] }}</span>@endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      <form id="clientForm" method="POST" action="{{ route('clients.store') }}"
            {{-- Live validation, the same module and the same messages as the
                 sign-up form. The rules live on the fields; this only says
                 which words to refuse them in. --}}
            data-validate-form
            data-validation-messages='@json(\App\Support\LiveValidation::messages([
                "taken" => __("clients.module.validation.email_taken"),
            ]))' class="mt-6 space-y-5">
        @csrf

        @include('clients._form', ['client' => null])

        {{-- The two ways of saving first and kept together — they are the
             same decision, "this client is finished", differing only in where
             the reader goes next — and then Cancel, which is not a save at
             all and sits apart from the pair.

             Both saves carry `after_save` as their own name and value, so the
             server learns which was pressed without a line of JavaScript. --}}
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex flex-wrap items-center gap-2">
            <button type="submit" id="clientSave" name="after_save" value="show"
                    class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
              {{ __('clients.module.add') }}
            </button>

            {{-- Secondary: adding a run of clients in one sitting is the less
                 common errand, and the reader who wants it is looking for it.
                 The reader who is not must not have to read past it. --}}
            <button type="submit" name="after_save" value="another"
                    class="styledesk_action">
              {{ __('clients.module.add_another') }}
            </button>
          </div>

          <a href="{{ route('clients.index') }}"
             class="styledesk_action">
            {{ __('common.cancel') }}
          </a>

          @if (session('duplicates'))
            {{-- Only offered once a warning has been shown. Before that there
                 is nothing to confirm, and a standing "add anyway" would let
                 someone skip a check they never saw.

                 It sends no `after_save` of its own, deliberately: the server
                 falls back to the intent flashed alongside the warning, so
                 confirming a duplicate keeps the reader on whichever path
                 they were already on. --}}
            <button type="submit" name="confirm_duplicate" value="1"
                    class="styledesk_action">
              {{ __('clients.module.duplicates_confirm') }}
            </button>
          @endif
        </div>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* One submission. A second POST would create a second client with the
       same details — which is exactly what the duplicate warning exists to
       prevent.

       Every submit button is disabled, not just the primary one: there are
       three of them now, and guarding one leaves the other two able to post
       the form again while the first request is still in flight. */
    (function () {
      var form = document.getElementById('clientForm');
      if (!form) return;

      var saving = false;
      var busyLabel = @json(__('common.saving'));

      form.addEventListener('submit', function (e) {
        if (saving) { e.preventDefault(); return; }
        saving = true;

        var pressed = e.submitter;
        var buttons = form.querySelectorAll('button[type="submit"]');

        /* On the next tick, not this one. A disabled control is left out of
           the submitted data, and these buttons carry the name and value that
           tell the server which one was pressed — disabling them here would
           post a form that no longer says where the reader wanted to go.

           The delay is also what lets this ask whether the submit actually
           went ahead: live validation runs after this handler and cancels the
           event when a field is wrong, and a form that was never sent must not
           be left with three dead buttons. */
        window.setTimeout(function () {
          if (e.defaultPrevented) {
            saving = false;

            return;
          }

          for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = true;
          }

          if (pressed) { pressed.textContent = busyLabel; }
        }, 0);
      });

      /* A page restored from the back/forward cache keeps the DOM it was
         unloaded with, buttons included. Without this the reader comes back
         to a form they cannot submit. */
      window.addEventListener('pageshow', function (event) {
        if (!event.persisted) return;

        saving = false;
        form.querySelectorAll('button[type="submit"]').forEach(function (button) {
          button.disabled = false;
        });
      });
    }());
  </script>
@endpush
