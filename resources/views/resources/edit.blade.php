@extends('layouts.app')

@section('title', __('resources.edit'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('resources.index') }}" class="hover:text-ink transition-colors">{{ __('resources.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $resource->name }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('resources.edit') }}</h1>
        </div>

        {{-- Back and Delete as one group on the right, the destructive one
             last: a reader reaching for "leave this page" should not pass
             their pointer over "remove it" to get there. Delete asks first —
             the shared dialog, so the question looks like every other one the
             app asks. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('resources.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          @if ($canDelete)
            <button type="submit" form="resourceDelete" class="styledesk_action styledesk_action--danger"
                    data-confirm-title="{{ __('resources.delete_title') }}"
                    data-confirm="{{ __('resources.delete_confirm', ['name' => $resource->name]) }}"
                    data-confirm-label="{{ __('resources.delete') }}"
                    data-confirm-tone="danger">
              {{ __('resources.delete') }}
            </button>
          @endif
        </div>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('resources.correct_fields') }}</p>
        </div>
      @endif

      <form id="resourceForm" method="POST" action="{{ route('resources.update', $resource) }}" class="mt-6 space-y-5">
        @csrf
        @method('PATCH')

        @include('resources._form', ['resource' => $resource])

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="resourceSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('common.save_changes') }}
          </button>

          <a href="{{ route('resources.index') }}" class="styledesk_action">{{ __('common.cancel') }}</a>
        </div>
      </form>
      {{-- Outside the edit form: a form nested in a form is not a thing
           HTML has, and the browser silently drops it. --}}
      @if ($canDelete)
        <form id="resourceDelete" method="POST" action="{{ route('resources.destroy', $resource) }}" class="hidden">
          @csrf
          @method('DELETE')
        </form>
      @endif
    </div>
  </main>
@endsection

@push('scripts')
  @include('resources.partials._form-scripts')
@endpush
