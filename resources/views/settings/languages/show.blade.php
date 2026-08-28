@extends('layouts.app')

@section('title', __('languages.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('languages.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('languages.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('languages.description') }}</p>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}"
             class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          <a href="{{ route('settings.languages.edit') }}"
             class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('languages.edit') }}
          </a>
        </div>
      </div>

      {{-- Said once, at the top. What a language setting does *not* touch is
           the part a business cannot be expected to assume, and finding out by
           watching your service names change would be far too late. --}}
      <div class="sd-alert sd-alert--info mt-5" role="status">
        <div class="flex items-start gap-2.5">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
          <p class="min-w-0">{{ __('languages.scope_note') }}</p>
        </div>
      </div>

      <div class="mt-6 space-y-5">
        <x-settings.card :title="__('languages.title')">
          <x-settings.field :label="__('languages.primary')">
            {{ App\Support\Locale::nativeName($primary) }}
            <span class="text-sub">— {{ App\Support\Locale::name($primary) }}</span>
          </x-settings.field>

          <x-settings.field :label="__('languages.secondary')">
            @if ($secondary->isNotEmpty())
              <span class="flex flex-wrap gap-1.5">
                @foreach ($secondary as $code)
                  <span class="styledesk_badge styledesk_badge--soon">{{ App\Support\Locale::nativeName($code) }}</span>
                @endforeach
              </span>
            @else
              <span class="text-faint">{{ __('common.none') }}</span>
            @endif
          </x-settings.field>

          <x-settings.field :label="__('languages.enabled')">
            <span class="flex flex-wrap gap-1.5">
              @foreach ($enabled as $code)
                <span class="styledesk_badge styledesk_badge--active">
                  {{ App\Support\Locale::nativeName($code) }}
                </span>
              @endforeach
            </span>
          </x-settings.field>
        </x-settings.card>

        @if ($enabled->count() === 1)
          <p class="text-[13px] text-sub">{{ __('languages.single_language') }}</p>
        @endif
      </div>
    </div>
  </main>
@endsection
