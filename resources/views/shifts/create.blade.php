@extends('layouts.app')

@section('title', __('shifts.add_title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('shifts.index') }}" class="hover:text-ink transition-colors">{{ __('shifts.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('common.add') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('shifts.add_title') }}</h1>
        </div>

        <a href="{{ route('shifts.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ $errors->first() }}</p>
        </div>
      @endif

      <form id="shiftForm" method="POST" action="{{ route('shifts.store') }}" class="mt-6 space-y-5">
        @csrf

        @include('shifts._form', ['shift' => null])

        {{-- Two ways to save, one form. The second posts the same body with
             after_save set, so the difference is where the reader is put down
             afterwards rather than a second code path through the save. --}}
        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" name="after_save" value="index" data-submit-once
                  data-busy-label="{{ __('shifts.adding') }}"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('shifts.add') }}
          </button>

          {{-- For the morning somebody sits down to enter a whole week. --}}
          <button type="submit" name="after_save" value="add_another" data-submit-once
                  data-busy-label="{{ __('shifts.adding') }}" class="styledesk_action">
            {{ __('shifts.save_and_add_another') }}
          </button>

          <a href="{{ route('shifts.index') }}" class="styledesk_action">{{ __('common.cancel') }}</a>
        </div>
      </form>
    </div>
  </main>
@endsection
