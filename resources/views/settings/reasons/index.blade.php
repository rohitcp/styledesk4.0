@extends('layouts.app')

@section('title', __('reasons.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('reasons.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('reasons.title') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('reasons.intro') }}</p>
        </div>

        <a href="{{ route('settings.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      {{-- One card per list. A table would put nine rows of "Booking
           cancellation · 22 · 22" on the screen and make the reader open each
           to find out what it is for; the intro line is the thing that tells
           them which one they want. --}}
      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach ($types as $type)
          <a href="{{ route('settings.reasons.show', $type['key']) }}"
             class="block bg-white border border-line rounded-card p-4 hover:border-brand/40 transition-colors">
            <p class="text-[14px] font-semibold text-head">{{ $type['label'] }}</p>
            <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ $type['intro'] }}</p>

            {{-- How much of the list is live, not how long it is: a business
                 that switched off fifteen of twenty-two wants to see that. --}}
            <p class="text-[12px] text-faint mt-2">
              {{ __('reasons.counts', ['active' => $type['active'], 'total' => $type['total']]) }}
            </p>
          </a>
        @endforeach
      </div>
    </div>
  </main>
@endsection
