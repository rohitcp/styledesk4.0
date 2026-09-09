@extends('layouts.app')

@section('title', __('marketing.title'))

@section('content')
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('marketing.title') }}</h1>
        <p class="text-[14px] font-semibold text-ink mt-1.5">{{ __('marketing.subtitle') }}</p>
        <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ __('marketing.intro') }}</p>
      </div>

      @if ($canCreate)
        <a href="{{ route('marketing.email.create') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('marketing.create') }}
        </a>
      @endif
    </header>

    {{-- Six figures, and the two rates that matter are measured against what
         was DELIVERED rather than what was sent: an address that bounced was
         never given the chance to open anything. --}}
    @php
      $widgets = [
          ['label' => __('marketing.summary.campaigns'), 'value' => number_format($summary['campaigns'])],
          ['label' => __('marketing.summary.sent'), 'value' => number_format($summary['sent'])],
          ['label' => __('marketing.summary.delivery_rate'), 'value' => $summary['delivery_rate'].'%'],
          ['label' => __('marketing.summary.open_rate'), 'value' => $summary['open_rate'].'%'],
          ['label' => __('marketing.summary.click_rate'), 'value' => $summary['click_rate'].'%'],
          ['label' => __('marketing.summary.unsubscribed'), 'value' => number_format($summary['unsubscribed'])],
      ];
    @endphp

    <div class="mt-5 grid grid-cols-2 lg:grid-cols-6 gap-3">
      @foreach ($widgets as $widget)
        <div class="rounded-card border border-line bg-white px-4 py-3">
          <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ $widget['label'] }}</p>
          <p class="text-[24px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ $widget['value'] }}</p>
        </div>
      @endforeach
    </div>

    {{-- The listing's own filter row, in the shape every other listing uses
         it: the search narrows as you type, the combos sit beside it, and the
         chips underneath say what is currently narrowing the list.

         `listing-filters.js` finds this by walking up from the active-filters
         row to its form, so the form and that row both have to be here. --}}
    <form method="GET" action="{{ route('marketing.email.index') }}" class="mt-6">
      <div class="flex flex-wrap items-start gap-2">
        <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>

          {{-- Typed, not submitted, and `type="text"` rather than "search":
               the browser's own clear cross fires no event this can hear. --}}
          <input name="search" type="text" class="sd-input styledesk_input--prefixed pr-16"
                 value="{{ request('search') }}" autocomplete="off"
                 aria-label="{{ __('marketing.search') }}" placeholder="{{ __('marketing.search') }}">

          <button type="button" data-search-clear hidden
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 h-6 w-6 grid place-items-center rounded
                         text-faint hover:text-ink hover:bg-hover transition-colors"
                  aria-label="{{ __('common.clear') }}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <x-combo name="status" :options="$statuses" :selected="request('status')"
                 :placeholder="__('marketing.all_statuses')"
                 class="w-full lg:w-[180px] shrink-0" />
      </div>

      <span hidden data-filter-labels="status" data-labels='@json($statuses)'></span>

      <div class="mt-2.5 flex flex-wrap items-center gap-2" data-active-filters hidden
           data-remove-label="{{ __('common.remove') }}">
        <span class="text-[12px] font-semibold text-sub">{{ __('marketing.filters_active') }}</span>
        <span class="flex flex-wrap items-center gap-1.5" data-active-chips></span>

        <button type="button" class="styledesk_action styledesk_action--sm" data-clear-filters>
          {{ __('common.clear_all') }}
        </button>
      </div>
    </form>

    <p class="mt-5 text-[12px] font-semibold text-sub" data-result-count></p>

    @php
      $gridLabels = [
          'columns' => __('marketing.table'),
          'actions_for' => __('marketing.actions_for', ['name' => ':name']),
          'showing' => __('marketing.showing'),
          'results' => __('marketing.results'),
          'clear_filters' => __('marketing.clear'),
          'empty' => __('marketing.empty'),
          'no_matches' => __('marketing.no_matches'),
          'no_matches_hint' => __('marketing.empty_hint'),
          'clear_search' => __('marketing.clear'),
      ];

      $gridConfig = [
          'labels' => $gridLabels,
          'columns' => [
              ['field' => 'name', 'title' => $gridLabels['columns']['name'], 'type' => 'primary', 'grow' => 2.6, 'min' => 200, 'responsive' => 0],
              ['field' => 'audience', 'title' => $gridLabels['columns']['audience'], 'grow' => 2, 'min' => 170, 'responsive' => 5],
              ['field' => 'recipients', 'title' => $gridLabels['columns']['recipients'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 105, 'responsive' => 3],
              ['field' => 'scheduled', 'title' => $gridLabels['columns']['scheduled'], 'grow' => 1.4, 'min' => 140, 'responsive' => 7, 'muted' => true],
              ['field' => 'sent', 'title' => $gridLabels['columns']['sent'], 'grow' => 1.1, 'min' => 110, 'responsive' => 6],
              ['field' => 'delivered', 'title' => $gridLabels['columns']['delivered'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 100, 'responsive' => 8, 'muted' => true],
              ['field' => 'opened', 'title' => $gridLabels['columns']['opened'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 95, 'responsive' => 2],
              ['field' => 'clicked', 'title' => $gridLabels['columns']['clicked'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 95, 'responsive' => 4],
              ['field' => 'author', 'title' => $gridLabels['columns']['author'], 'grow' => 1.4, 'min' => 130, 'responsive' => 9, 'muted' => true],
              ['field' => 'status', 'title' => $gridLabels['columns']['status'], 'type' => 'badge', 'grow' => 1.3, 'min' => 120, 'responsive' => 1],
          ],
      ];
    @endphp

    <div class="mt-3 styledesk_gridframe">
      <div data-grid data-url="{{ route('marketing.email.data') }}" data-config='@json($gridConfig)'></div>
    </div>
  </main>
@endsection
