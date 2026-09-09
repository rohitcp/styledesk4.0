@extends('layouts.app')

@section('title', __('resources.title'))

@section('content')
  {{-- The same shape as the clients and services listings, because it is the
       same kind of screen: header, then the toolbar that narrows the list,
       then the list. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('resources.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ __('resources.subtitle') }}</p>
      </div>

      @if ($canCreate)
        <a href="{{ route('resources.create') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('resources.add') }}
        </a>
      @endif
    </header>

    @if ($total === 0)
      {{-- Nothing yet, and nothing pretending otherwise: no search over an
           empty list, no filters that can only return nothing. --}}
      <div class="mt-4 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('resources.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto leading-relaxed">{{ __('resources.none_yet_hint') }}</p>

        @if ($canCreate)
          <a href="{{ route('resources.create') }}"
             class="mt-4 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('resources.add') }}
          </a>
        @endif
      </div>
    @else
      @php
          /* Assembled here rather than inline. Blade's json directive counts
             brackets instead of reading PHP, so an array literal written
             inside it ends at its first closing bracket — the same trap the
             combo and the resource card both carry a warning about. */
          $statusOptions = [
              'available' => __('resources.availability.available'),
              'blocked' => __('resources.availability.blocked'),
              'inactive' => __('resources.availability.inactive'),
          ];
      @endphp

      <form method="GET" action="{{ route('resources.index') }}" class="mt-4">
        <div class="flex flex-wrap items-start gap-2">
          <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
            <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
              <x-icon name="magnifying-glass" size="15" />
            </span>
            {{-- Typed, not submitted: the list narrows as the reader
                 types, on a debounce. `type="text"` rather than "search",
                 because the browser's own clear cross fires no event this
                 can hear — the one below is the app's, and it works. --}}
            <input name="search" type="text" class="sd-input styledesk_input--prefixed pr-16"
                   value="{{ $filters['search'] }}"
                   autocomplete="off"
                   aria-label="{{ __('resources.search') }}"
                   placeholder="{{ __('resources.search') }}">

            {{-- Spinning only while a request is actually in the air; the
                 grid says when it lands. --}}
            <span class="absolute right-9 top-1/2 -translate-y-1/2 text-faint pointer-events-none"
                  data-search-busy hidden aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="animate-spin">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" opacity="0.25"/>
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
              </svg>
            </span>

            <button type="button" data-search-clear hidden
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 h-6 w-6 grid place-items-center rounded
                           text-faint hover:text-ink hover:bg-hover transition-colors"
                    aria-label="{{ __('common.clear') }}">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
            </button>
          </div>

          <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
            <x-combo name="category" :options="$categoryOptions" :selected="$filters['category']"
                     :placeholder="__('resources.all_categories')"
                     class="w-full lg:w-[170px] shrink-0" />

            <x-combo name="location" multiple :options="$locations->pluck('name', 'id')" :selected="$filters['location']"
                     :placeholder="__('resources.all_locations')"
                     :summary="__('resources.location')"
                     class="w-full lg:w-[170px] shrink-0" />

            <x-combo name="status" :selected="$filters['status']"
                     :placeholder="__('resources.all_statuses')"
                     :options="$statusOptions"
                     class="w-full lg:w-[150px] shrink-0" />

          </div>
        </div>

        <span hidden data-filter-labels="category" data-labels='@json($categoryOptions)'></span>
        <span hidden data-filter-labels="location" data-labels='@json($locations->pluck('name', 'id'))'></span>
        <span hidden data-filter-labels="status" data-labels='@json($statusOptions)'></span>

        <div class="mt-2.5 flex flex-wrap items-center gap-2" data-active-filters hidden
             data-remove-label="{{ __('common.remove') }}">
          <span class="text-[12px] font-semibold text-sub">{{ __('resources.filters_active') }}</span>
          <span class="flex flex-wrap items-center gap-1.5" data-active-chips></span>

          <button type="button" class="styledesk_action styledesk_action--sm" data-clear-filters>
            {{ __('common.clear_all') }}
          </button>
        </div>
      </form>

      @php
          $gridLabels = [
              'columns' => __('resources.columns'),
              'actions_for' => __('resources.actions_for', ['name' => ':name']),
              'showing' => __('resources.showing'),
              'results' => [
                  'zero' => __('resources.results.zero'),
                  'one' => __('resources.results.one'),
                  'many' => __('resources.results.many'),
              ],
              'clear_filters' => __('resources.results.clear'),
              'empty' => __('resources.results.empty'),
              /* What an empty list says when a search emptied it,
                 which is a different dead end from a filter doing
                 it: one is answered by another word, the other by
                 dropping a filter. */
              'no_matches' => __('resources.results.no_matches'),
              'no_matches_hint' => __('resources.results.no_matches_hint'),
              'clear_search' => __('resources.results.clear_search'),
          ];

          $gridConfig = [
              'labels' => $gridLabels,
              'columns' => [
                  ['field' => 'name', 'title' => $gridLabels['columns']['resource'], 'type' => 'primary', 'grow' => 2.5, 'min' => 180, 'responsive' => 0],
                  ['field' => 'category', 'title' => $gridLabels['columns']['category'], 'grow' => 1.6, 'min' => 140, 'responsive' => 3],
                  ['field' => 'location', 'title' => $gridLabels['columns']['location'], 'grow' => 1.5, 'min' => 130, 'responsive' => 4],
                  ['field' => 'capacity', 'title' => $gridLabels['columns']['capacity'], 'grow' => 1.2, 'min' => 120, 'responsive' => 5],
                  ['field' => 'description', 'title' => $gridLabels['columns']['description'], 'grow' => 2, 'min' => 160, 'responsive' => 6, 'muted' => true],
                  ['field' => 'availability', 'title' => $gridLabels['columns']['availability'], 'type' => 'badge', 'grow' => 1.6, 'min' => 150, 'responsive' => 1],
                  ['field' => 'actions', 'type' => 'actions'],
              ],
          ];
      @endphp

      <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

      <div class="mt-4 styledesk_gridframe">
        <div data-grid
             data-url="{{ route('resources.data', array_filter($filters)) }}"
             data-config='@json($gridConfig)'></div>
      </div>
    @endif

    @if ($canBlock ?? false)
      @include('resources.partials._block-modal')
    @endif
  </main>
@endsection

@push('scripts')
  @include('resources.partials._scripts')
@endpush
