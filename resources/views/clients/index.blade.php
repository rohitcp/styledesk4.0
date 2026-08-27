@extends('layouts.app')

@section('title', __('clients.module.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="max-w-[900px]">

      <div class="flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('clients.module.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('clients.module.intro') }}</p>
        </div>

        @if ($canConfigure)
          <a href="{{ route('settings.clients.show') }}"
             class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            <x-icon name="gear" size="14" />
            {{ __('clients.module.configure') }}
          </a>
        @endif
      </div>

      {{-- The honest empty state.
           No client records exist yet, and an empty table with column headings
           would read as a business that has lost its clients rather than as a
           module that has not arrived. --}}
      <div class="mt-6 rounded-card border border-line bg-white p-10 text-center">
        <span class="styledesk_settingcard__icon mx-auto" aria-hidden="true">
          <x-icon name="address-book" size="18" />
        </span>

        <p class="text-[15px] font-semibold text-head mt-3">{{ __('clients.module.empty_title') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[460px] mx-auto leading-relaxed">
          {{ __('clients.module.empty_body') }}
        </p>
      </div>

      {{-- What has already been decided.
           Shown as evidence rather than as a promise: a page that says "no
           clients yet" and nothing else gives the reader no reason to believe
           anything is coming. --}}
      <section class="mt-5 bg-white border border-line rounded-card p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('clients.module.ready_title') }}</h2>
        <p class="text-[13px] text-sub mt-0.5">{{ __('clients.module.ready_hint') }}</p>

        <dl class="mt-3">
          <div class="py-2.5 border-b border-line">
            <dt class="text-[12px] text-sub">{{ __('clients.module.required_fields') }}</dt>
            <dd class="mt-0.5 text-[14px] text-head">
              {{ collect($requiredFields)->join(', ') }}
            </dd>
          </div>

          <div class="py-2.5 border-b border-line">
            <dt class="text-[12px] text-sub">{{ __('clients.module.name_format') }}</dt>
            <dd class="mt-0.5 text-[14px] text-head">{{ $nameFormat }}</dd>
          </div>

          <div class="py-2.5 border-b border-line last:border-0">
            <dt class="text-[12px] text-sub">{{ __('clients.cards.lists') }}</dt>
            <dd class="mt-0.5 text-[14px] text-head">
              {{ trans_choice('clients.module.preferences', $preferenceCount, ['count' => $preferenceCount]) }},
              {{ trans_choice('clients.module.tags', $tagCount, ['count' => $tagCount]) }}
            </dd>
          </div>
        </dl>

        @if ($canConfigure)
          <div class="pt-3">
            <a href="{{ route('settings.clients.show') }}" class="text-[13px] font-medium text-link hover:underline">
              {{ __('clients.module.configure') }} →
            </a>
            <p class="text-[12px] text-sub mt-1">{{ __('clients.module.configure_hint') }}</p>
          </div>
        @endif
      </section>

      {{-- Said to everyone, because the person most likely to wonder why they
           cannot change something is the person who cannot change it. --}}
      <p class="mt-5 text-[12px] text-faint leading-relaxed">{{ __('clients.module.permission_note') }}</p>
    </div>
  </main>
@endsection
