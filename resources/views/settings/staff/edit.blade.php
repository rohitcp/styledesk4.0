@extends('layouts.app')

@section('title', 'Edit '.$staff->displayName())

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[760px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.staff.index') }}" class="hover:text-ink transition-colors">Staff members</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.staff.show', $staff) }}" class="hover:text-ink transition-colors">{{ $staff->displayName() }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">Edit</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">Edit {{ $staff->displayName() }}</h1>
        </div>

        <a href="{{ route('settings.staff.show', $staff) }}" data-back
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

      <form id="staffForm" method="POST" action="{{ route('settings.staff.update', $staff) }}"
            enctype="multipart/form-data" class="mt-6 space-y-5">
        @csrf
        @method('PATCH')

        @include('settings.staff._form', ['staff' => $staff])

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="staffSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            Save changes
          </button>
          <a href="{{ route('settings.staff.show', $staff) }}"
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

      var login = document.getElementById('login_enabled');
      var block = document.querySelector('[data-invite-block]');

      if (login && block) {
        var syncInvite = function () { block.hidden = !login.checked; };
        login.addEventListener('change', syncInvite);
        syncInvite();
      }

      var save = document.getElementById('staffSave');
      var saving = false;

      form.addEventListener('submit', function (e) {
        if (saving) { e.preventDefault(); return; }
        saving = true;
        save.disabled = true;
        save.textContent = 'Saving…';
      });
    }());
  </script>
@endpush
