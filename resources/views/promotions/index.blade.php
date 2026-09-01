@extends('layouts.app')

@section('title', __('promotions.title'))

@section('content')
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('promotions.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5">{{ __('promotions.intro') }}</p>
      </div>

      <a href="{{ route('promotions.create') }}"
         class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
        <x-icon name="plus" size="14" />
        {{ __('promotions.new') }}
      </a>
    </header>

    @if (! $hasAny)
      {{-- Nothing at all, which is a different fact from nothing matching —
           and gets a different answer. --}}
      <div class="mt-6 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('promotions.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5">{{ __('promotions.none_yet_hint') }}</p>

        <a href="{{ route('promotions.create') }}"
           class="inline-flex items-center gap-1.5 h-9 px-3.5 mt-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('promotions.new') }}
        </a>
      </div>
    @else

    {{-- Four numbers, and each one filters the table beneath it: a count
         somebody wants to act on should be one press from the list. --}}
    <div class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-3">
      @foreach (['active', 'scheduled', 'expired'] as $key)
        <a href="{{ route('promotions.index', ['status' => $key]) }}"
           @class(['sd-card px-4 py-3 transition-colors hover:border-brand', 'border-brand' => $filters['status'] === $key])>
          <p class="text-[12px] font-semibold text-sub">{{ __('promotions.summary.'.$key) }}</p>
          <p class="text-[22px] font-bold text-head leading-tight mt-0.5">{{ $counts[$key] }}</p>
        </a>
      @endforeach

      <div class="sd-card px-4 py-3">
        <p class="text-[12px] font-semibold text-sub">{{ __('promotions.summary.redemptions') }}</p>
        <p class="text-[22px] font-bold text-head leading-tight mt-0.5">{{ $counts['redemptions'] }}</p>
      </div>
    </div>

    <form method="GET" action="{{ route('promotions.index') }}" class="mt-4">
      <div class="flex flex-wrap items-start gap-2">
        <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                 value="{{ $filters['search'] }}" data-live-search
                 aria-label="{{ __('promotions.search') }}"
                 placeholder="{{ __('promotions.search') }}">

          @if ($filters['search'] !== '')
            <button type="button" class="styledesk_input__clear" data-search-clear
                    aria-label="{{ __('common.clear') }}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
          @endif
        </div>

        <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
          <x-combo name="status"
                   :options="collect(App\Models\Promotion::STATUSES)->mapWithKeys(fn (string $k) => [$k => __('promotions.statuses.'.$k)])"
                   :selected="$filters['status']"
                   :placeholder="__('promotions.filters.all_statuses')"
                   class="w-full lg:w-[170px] shrink-0" />

          <x-combo name="type"
                   :options="collect(App\Models\Promotion::TYPES)->mapWithKeys(fn (string $k) => [$k => __('promotions.types.'.$k)])"
                   :selected="$filters['type']"
                   :placeholder="__('promotions.filters.all_types')"
                   class="w-full lg:w-[150px] shrink-0" />

          <x-combo name="location" :options="$locations->pluck('name', 'id')"
                   :selected="$filters['location']"
                   :placeholder="__('promotions.filters.all_locations')"
                   class="w-full lg:w-[180px] shrink-0" />

          <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0">{{ __('common.search') }}</button>

          @if (collect($filters)->filter()->isNotEmpty())
            <a href="{{ route('promotions.index') }}" class="styledesk_action w-full lg:w-auto shrink-0">
              {{ __('promotions.filters.reset') }}
            </a>
          @endif
        </div>
      </div>

      <span hidden data-filter-labels="status" data-labels='@json(collect(App\Models\Promotion::STATUSES)->mapWithKeys(fn (string $k) => [$k => __('promotions.statuses.'.$k)]))'></span>
      <span hidden data-filter-labels="type" data-labels='@json(collect(App\Models\Promotion::TYPES)->mapWithKeys(fn (string $k) => [$k => __('promotions.types.'.$k)]))'></span>
      <span hidden data-filter-labels="location" data-labels='@json($locations->pluck('name', 'id'))'></span>

      <div class="mt-2.5 flex flex-wrap items-center gap-2" data-active-filters hidden
           data-remove-label="{{ __('common.remove') }}">
        <span class="text-[12px] font-semibold text-sub">{{ __('services.filters_active') }}</span>
        <span class="flex flex-wrap items-center gap-1.5" data-active-chips></span>

        <button type="button" class="styledesk_action styledesk_action--sm" data-clear-filters>
          {{ __('common.clear_all') }}
        </button>
      </div>
    </form>

    <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

    @php
        $gridConfig = [
            'labels' => [
                'actions_for' => __('promotions.actions_for', ['name' => ':name']),
                'showing' => __('promotions.showing'),
                'results' => [
                    'zero' => __('promotions.results.zero'),
                    'one' => __('promotions.results.one'),
                    'many' => __('promotions.results.many'),
                ],
                'clear_filters' => __('promotions.results.clear'),
                'empty' => __('promotions.empty'),
            ],
            'name_field' => 'name',
            'page_size' => 25,
            'columns' => [
                ['field' => 'name', 'title' => __('promotions.columns.name'), 'type' => 'primary', 'grow' => 2, 'min' => 180, 'responsive' => 0],
                ['field' => 'code', 'title' => __('promotions.columns.code'), 'width' => 150, 'responsive' => 0],
                ['field' => 'type', 'title' => __('promotions.columns.type'), 'width' => 100, 'responsive' => 6],
                ['field' => 'discount', 'title' => __('promotions.columns.discount'), 'width' => 110, 'responsive' => 1],
                ['field' => 'applies', 'title' => __('promotions.columns.applies'), 'grow' => 1.6, 'min' => 150, 'responsive' => 5],
                ['field' => 'starts', 'title' => __('promotions.columns.starts'), 'width' => 120, 'responsive' => 4],
                ['field' => 'ends', 'title' => __('promotions.columns.ends'), 'width' => 120, 'responsive' => 3],
                ['field' => 'used', 'title' => __('promotions.columns.used'), 'width' => 100, 'responsive' => 2],
                ['field' => 'status', 'title' => __('promotions.columns.status'), 'type' => 'badge', 'width' => 120, 'responsive' => 0],
                ['type' => 'actions'],
            ],
        ];
    @endphp

    <div class="mt-2 styledesk_gridframe">
      <div data-grid
           data-url="{{ route('promotions.data', array_filter($filters)) }}"
           data-config='@json($gridConfig)'></div>
    </div>
    @endif
  </main>
@endsection
