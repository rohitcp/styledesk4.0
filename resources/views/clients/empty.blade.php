@extends('layouts.app')

@section('title', __('clients.module.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- Centred single column, per §Empty State Layout.
         Deliberately absent: the table, pagination, filters, search and any
         "0 results". A search box over nothing is a control that can only
         ever fail, and an empty table reads as a business that has lost its
         clients rather than one that has not added any. --}}
    <div class="max-w-[560px] mx-auto text-center">

      <span class="styledesk_settingcard__icon mx-auto" aria-hidden="true">
        <x-icon name="address-book" size="18" />
      </span>

      <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight mt-4">
        {{ __('clients.module.empty_title') }}
      </h1>

      <p class="text-[14px] text-sub mt-3 leading-relaxed">
        {{ __('clients.module.empty_body') }}
      </p>

      {{-- The explainer video, prominent as §Explainer Video asks.
           There is no file yet, so the frame says so rather than showing a
           play button that does nothing — a control that looks live and is
           not is worse than one that admits it is coming. --}}
      <figure class="mt-6 rounded-card border border-line bg-white overflow-hidden text-left">
        <div class="styledesk_videoframe" role="img" aria-label="{{ __('clients.module.video_title') }}">
          <span class="styledesk_videoframe__badge">{{ __('clients.module.video_pending') }}</span>
        </div>

        <figcaption class="p-4">
          <p class="text-[14px] font-semibold text-head">{{ __('clients.module.video_title') }}</p>
          <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ __('clients.module.video_body') }}</p>
        </figcaption>
      </figure>

      @if ($canCreate)
        <a href="{{ route('clients.create') }}"
           class="mt-6 inline-flex items-center gap-1.5 h-10 px-5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('clients.module.add_first') }}
        </a>
      @endif

      {{-- Import Clients is deliberately absent. §Secondary CTA says not to
           show it unless import ships, and a button that opens nothing is a
           promise the product has not made. --}}
    </div>
  </main>
@endsection
