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

      <form id="clientForm" method="POST" action="{{ route('clients.store') }}" class="mt-6 space-y-5">
        @csrf

        @include('clients._form', ['client' => null])

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="clientSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('clients.module.add') }}
          </button>

          @if (session('duplicates'))
            {{-- Only offered once a warning has been shown. Before that there
                 is nothing to confirm, and a standing "add anyway" would let
                 someone skip a check they never saw. --}}
            <button type="submit" name="confirm_duplicate" value="1"
                    class="styledesk_action">
              {{ __('clients.module.duplicates_confirm') }}
            </button>
          @endif

          <a href="{{ route('clients.index') }}"
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
