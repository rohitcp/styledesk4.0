@extends('layouts.app')

@section('title', __('clients.module.edit_title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('clients.index') }}" class="hover:text-ink transition-colors">{{ __('clients.module.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('clients.show', $client) }}" class="hover:text-ink transition-colors">{{ $client->displayName() }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('common.edit') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('clients.module.edit_title') }}</h1>
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

      <form id="clientForm" method="POST" action="{{ route('clients.update', $client) }}"
            {{-- Live validation, the same module and the same messages as the
                 sign-up form. The rules live on the fields; this only says
                 which words to refuse them in. --}}
            data-validate-form
            data-validation-messages='@json(\App\Support\LiveValidation::messages([
                "taken" => __("clients.module.validation.email_taken"),
            ]))' class="mt-6 space-y-5">
        @csrf
        @method('PATCH')

        @include('clients._form', ['client' => $client])

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="clientSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('common.save_changes') }}
          </button>

          <a href="{{ route('clients.show', $client) }}"
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
    /* One submission. A second POST would create a second client with the
       same details — which is exactly what the duplicate warning exists to
       prevent. */
    (function () {
      var form = document.getElementById('clientForm');
      if (!form) return;

      var saving = false;

      form.addEventListener('submit', function (e) {
        if (saving) { e.preventDefault(); return; }
        saving = true;

        var save = document.getElementById('clientSave');
        save.disabled = true;
        save.textContent = @json(__('common.saving'));
      });
    }());
  </script>
@endpush
