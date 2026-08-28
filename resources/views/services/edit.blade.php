@extends('layouts.app')

@section('title', __('services.edit_title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('services.index') }}" class="hover:text-ink transition-colors">{{ __('services.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $service->name }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('services.edit_title') }}</h1>
        </div>

        {{-- Back and Delete as one group on the right, the destructive one
             last: a reader reaching for "leave this page" should not pass
             their pointer over "remove it" to get there. Delete asks first —
             the shared dialog, so the question looks like every other one the
             app asks. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('services.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          @if ($canDelete)
            <button type="submit" form="serviceDelete" class="styledesk_action styledesk_action--danger"
                    data-confirm-title="{{ __('services.delete_title') }}"
                    data-confirm="{{ __('services.delete_confirm', ['name' => $service->name]) }}"
                    data-confirm-label="{{ __('services.delete') }}"
                    data-confirm-tone="danger">
              {{ __('services.delete') }}
            </button>
          @endif
        </div>
      </div>

      {{-- Said once, at the top, on the way in from Duplicate. Without it the
           reader is looking at a form full of another service's settings
           under a name one word different from it. --}}
      @if (session('duplicated_from'))
        <div class="sd-alert sd-alert--info mt-5" role="status">
          <div class="flex items-start gap-2.5">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            <p class="min-w-0">{{ __('services.duplicated_from', ['name' => session('duplicated_from')]) }}</p>
          </div>
        </div>
      @endif

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('services.correct_fields') }}</p>
        </div>
      @endif

      <form id="serviceForm" method="POST" action="{{ route('services.update', $service) }}" class="mt-6 space-y-5">
        @csrf
        @method('PATCH')

        @include('services._form', ['service' => $service, 'priceValues' => $priceValues])

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="serviceSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('common.save_changes') }}
          </button>

          <a href="{{ route('services.show', $service) }}" class="styledesk_action">{{ __('common.cancel') }}</a>
        </div>
      </form>
      {{-- Outside the edit form: a form nested in a form is not a thing
           HTML has, and the browser silently drops it. --}}
      @if ($canDelete)
        <form id="serviceDelete" method="POST" action="{{ route('services.destroy', $service) }}" class="hidden">
          @csrf
          @method('DELETE')
        </form>
      @endif
    </div>
  </main>
@endsection

@push('scripts')
  @include('services.partials._form-scripts')
@endpush
