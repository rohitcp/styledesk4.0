@extends('layouts.app')

@section('title', __('locations.add_title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- Single column, matching the staff form: a settings form reads top to
         bottom rather than asking which column to start in. --}}
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.locations.index') }}" class="hover:text-ink transition-colors">{{ __('locations.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('common.add') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('locations.add_title') }}</h1>
          <p class="text-[14px] text-sub mt-2 leading-relaxed">
            {{ __('locations.add_intro') }}
          </p>
        </div>

        <a href="{{ route('settings.locations.index') }}"
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('locations.correct_fields') }}</p>
        </div>
      @endif

      <form id="locationForm" method="POST" action="{{ route('settings.locations.store') }}" class="mt-6 space-y-5">
        @csrf

        @include('settings.locations._form', ['location' => null])

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="locationSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('locations.add') }}
          </button>
          <a href="{{ route('settings.locations.index') }}"
             class="styledesk_action">
            {{ __('common.cancel') }}
          </a>
        </div>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  @include('settings.locations._form-scripts', ['saveLabel' => 'Adding…'])
@endpush
