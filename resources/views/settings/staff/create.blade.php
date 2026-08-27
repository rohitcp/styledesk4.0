@extends('layouts.app')

@section('title', 'Add staff member')

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[760px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.staff.index') }}" class="hover:text-ink transition-colors">Staff members</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">Add</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">Add staff member</h1>
          <p class="text-[14px] text-sub mt-2 leading-relaxed">
            Their details and role. Working hours, availability and per-service settings are configured
            once the record exists.
          </p>
        </div>

        <a href="{{ route('settings.staff.index') }}" data-back
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Back
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">Please correct the highlighted fields and try again.</p>
        </div>
      @endif

      <form id="staffForm" method="POST" action="{{ route('settings.staff.store') }}"
            enctype="multipart/form-data" class="mt-6 space-y-5">
        @csrf

        @include('settings.staff._form', ['staff' => null])

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="staffSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            Add staff member
          </button>
          <a href="{{ route('settings.staff.index') }}"
             class="h-9 px-3.5 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            Cancel
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
         click would send two. */
      var save = document.getElementById('staffSave');
      var saving = false;

      form.addEventListener('submit', function (e) {
        if (saving) {
          e.preventDefault();
          return;
        }
        saving = true;
        save.disabled = true;
        save.textContent = 'Adding…';
      });
    }());
  </script>
@endpush
