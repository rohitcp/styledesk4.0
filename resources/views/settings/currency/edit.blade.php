@extends('layouts.app')

@section('title', __('currency.edit'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.currency.show') }}" class="hover:text-ink transition-colors">{{ __('currency.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('common.edit') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('currency.edit') }}</h1>
          <p class="text-[14px] text-sub mt-2 leading-relaxed">{{ __('currency.intro') }}</p>
        </div>

        <a href="{{ route('settings.currency.show') }}"
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ $errors->first() }}</p>
        </div>
      @endif

      <form method="POST" action="{{ route('settings.currency.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PATCH')

        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          @php $chosenSecondary = array_map('strval', old('secondary', $secondary->all())); @endphp

          <x-combo name="primary" :label="__('currency.primary')" required :options="$options"
                   :selected="old('primary', $primary)" :hint="__('currency.primary_hint')" />

          <fieldset class="pt-4 border-t border-line">
            <legend class="text-[13px] font-medium text-ink mb-2">
              {{ __('currency.secondary') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </legend>

            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2 max-h-[320px] overflow-y-auto pr-1">
              @foreach ($options as $code => $label)
                {{-- The primary is listed but disabled rather than hidden.
                     Removing the row as the primary changes would make the
                     list jump under the cursor; a disabled row explains
                     itself. --}}
                <label class="styledesk_choice" data-secondary-row="{{ $code }}">
                  <input type="checkbox" name="secondary[]" value="{{ $code }}" class="sd-check"
                         data-secondary-box="{{ $code }}"
                         @checked(in_array($code, $chosenSecondary, true))>
                  <span class="styledesk_choice__label">{{ $label }}</span>
                </label>
              @endforeach
            </div>

            <p class="mt-2 text-[12px] text-sub">{{ __('currency.secondary_hint') }}</p>
          </fieldset>

          <p class="text-[12px] text-sub pt-4 border-t border-line">{{ __('currency.no_conversion') }}</p>
        </section>

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('common.save_changes') }}
          </button>
          <a href="{{ route('settings.currency.show') }}"
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
    /* The primary currency cannot also be a secondary. The server drops it
       either way, so this is only about not showing a tick that will not
       survive the save. */
    (function () {
      var primary = document.querySelector('[data-vue-component="MultiSelect"] input[name="primary"]');
      if (!primary) return;

      function sync() {
        document.querySelectorAll('[data-secondary-row]').forEach(function (row) {
          var code = row.getAttribute('data-secondary-row');
          var box = row.querySelector('[data-secondary-box]');
          var isPrimary = code === primary.value;

          box.disabled = isPrimary;
          if (isPrimary) box.checked = false;

          row.classList.toggle('opacity-50', isPrimary);
          row.classList.toggle('cursor-not-allowed', isPrimary);
        });
      }

      /* The combo writes to a hidden input, which fires no event of its own,
         so the value is watched rather than listened for. */
      var last = primary.value;
      setInterval(function () {
        if (primary.value !== last) { last = primary.value; sync(); }
      }, 200);

      sync();
    }());
  </script>
@endpush
