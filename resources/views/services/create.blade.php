@extends('layouts.app')

@section('title', __('services.add_title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('services.index') }}" class="hover:text-ink transition-colors">{{ __('services.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('common.add') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('services.add_title') }}</h1>
        </div>

        <a href="{{ route('services.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('services.correct_fields') }}</p>
        </div>
      @endif

      <form id="serviceForm" method="POST" action="{{ route('services.store') }}"
            {{-- Live validation, the same module and the same messages as the
                 sign-up and resource forms. The rules live on the fields;
                 this only says which words to refuse them in. --}}
            data-validate-form
            data-validation-messages='@json(\App\Support\LiveValidation::messages())' class="mt-6 space-y-5">
        @csrf

        @include('services._form', ['service' => null, 'priceValues' => [], 'cashPriceValues' => [], 'depositValues' => null])

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="serviceSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('services.add') }}
          </button>

          <a href="{{ route('services.index') }}" class="styledesk_action">{{ __('common.cancel') }}</a>
        </div>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  @include('services.partials._form-scripts')
@endpush
