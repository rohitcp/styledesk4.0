@extends('layouts.app')

@section('title', __('services.title'))

@section('content')
  {{-- The same shape as the clients listing, because it is the same kind of
       screen: header, then the toolbar that narrows the list, then the list.
       One content width and no max-width container — the table is the point
       of the screen, and a ten-column grid inside a 1180px column wastes half
       a desktop. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('services.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ __('services.intro') }}</p>
      </div>

      @if ($canCreate)
        {{-- The same label as the empty state's CTA below: two names for one
             action is two things to learn. --}}
        <a href="{{ route('services.create') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('services.add') }}
        </a>
      @endif
    </header>

    @if ($total === 0)
      {{-- Nothing yet, and nothing pretending otherwise: no search over an
           empty list, no filters that can only return nothing. --}}
      <div class="mt-4 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('services.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto leading-relaxed">{{ __('services.none_yet_hint') }}</p>

        @if ($canCreate)
          <a href="{{ route('services.create') }}"
             class="mt-4 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('services.add') }}
          </a>
        @endif
      </div>
    @else
      {{-- Toolbar: search and the filters on one line where there is room,
           wrapping before they shrink into unreadability. --}}
      <form method="GET" action="{{ route('services.index') }}" class="mt-4">
        {{-- The search keeps a whole row to itself when the window is narrow
             and the filters take the next one. Splitting the search field
             itself would be splitting one control in two. --}}
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
                   aria-label="{{ __('services.search_placeholder') }}"
                   placeholder="{{ __('services.search_placeholder') }}">

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

          {{-- The filters move as one group: wrapped one at a time they would
               leave a single dropdown stranded on a line of its own.

               They wrap rather than scroll on purpose — each control opens a
               panel positioned inside itself, and a container with overflow
               set would cut those panels off at its edge. --}}
          <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
            <x-combo name="category" :options="$categories->pluck('name', 'id')" :selected="$filters['category']"
                     :placeholder="__('services.all_categories')"
                     class="w-full lg:w-[150px] shrink-0" />

            {{-- These two take more than one value. The control says how many
                 are chosen; the row below says which, where the names have
                 room to be read and each can be removed on its own. --}}
            <x-combo name="location" multiple :options="$locations->pluck('name', 'id')" :selected="$filters['location']"
                     :placeholder="__('services.all_locations')"
                     :summary="__('services.columns.location')"
                     class="w-full lg:w-[170px] shrink-0" />

            <x-combo name="staff" multiple
                     :options="$staff->mapWithKeys(fn ($m) => [$m->id => $m->first_name.' '.$m->last_name])"
                     :selected="$filters['staff']" :placeholder="__('services.all_staff')"
                     :summary="__('services.columns.staff')"
                     class="w-full lg:w-[170px] shrink-0" />

            <x-combo name="status" :selected="$filters['status']"
                     :placeholder="__('services.all_statuses')"
                     :options="['active' => __('services.filter.active'), 'inactive' => __('services.filter.inactive')]"
                     class="w-full lg:w-[150px] shrink-0" />

            <x-combo name="booking" :selected="$filters['booking']"
                     :placeholder="__('services.all_booking')"
                     :options="['online' => __('services.filter.online'), 'internal' => __('services.filter.internal')]"
                     class="w-full lg:w-[150px] shrink-0" />

            <x-combo name="resource" :selected="$filters['resource']"
                     :placeholder="__('services.all_resource')"
                     :options="['required' => __('services.resource_required'), 'not_required' => __('services.resource_not_required')]"
                     class="w-full lg:w-[150px] shrink-0" />

          </div>
        </div>

        {{-- The names behind the ids, so a chip can be labelled without
             asking the server again. --}}
        <span hidden data-filter-labels="category" data-labels='@json($categories->pluck('name', 'id'))'></span>
        <span hidden data-filter-labels="location" data-labels='@json($locations->pluck('name', 'id'))'></span>
        <span hidden data-filter-labels="staff" data-labels='@json($staff->mapWithKeys(fn ($m) => [$m->id => $m->first_name.' '.$m->last_name]))'></span>
        <span hidden data-filter-labels="status" data-labels='@json(['active' => __('services.filter.active'), 'inactive' => __('services.filter.inactive')])'></span>
        <span hidden data-filter-labels="booking" data-labels='@json(['online' => __('services.filter.online'), 'internal' => __('services.filter.internal')])'></span>
        <span hidden data-filter-labels="resource" data-labels='@json(['required' => __('services.resource_required'), 'not_required' => __('services.resource_not_required')])'></span>

        {{-- Active filters. Hidden entirely when nothing is chosen rather
             than left as an empty band: a row that is sometimes blank is a
             row the reader has to check. Built by the shared filter script
             from what the controls hold, so it cannot disagree with them. --}}
        <div class="mt-2.5 flex flex-wrap items-center gap-2" data-active-filters hidden
             data-remove-label="{{ __('common.remove') }}">
          <span class="text-[12px] font-semibold text-sub">{{ __('services.filters_active') }}</span>
          <span class="flex flex-wrap items-center gap-1.5" data-active-chips></span>

          <button type="button" class="styledesk_action styledesk_action--sm" data-clear-filters>
            {{ __('common.clear_all') }}
          </button>
        </div>
      </form>

      @php
          $gridLabels = [
              'columns' => __('services.columns'),
              'actions_for' => __('services.actions_for', ['name' => ':name']),
              'showing' => __('services.showing'),
              'results' => [
                  'zero' => __('services.results.zero'),
                  'one' => __('services.results.one'),
                  'many' => __('services.results.many'),
              ],
              'clear_filters' => __('services.results.clear'),
              'empty' => __('services.results.empty'),
              /* What an empty list says when a search emptied it,
                 which is a different dead end from a filter doing
                 it: one is answered by another word, the other by
                 dropping a filter. */
              'no_matches' => __('services.results.no_matches'),
              'no_matches_hint' => __('services.results.no_matches_hint'),
              'clear_search' => __('services.results.clear_search'),
          ];

          /* Columns leave worst-first as the window narrows: the ones that
             answer "which service, and can it be booked" are the last to go,
             which is why status and the actions menu never leave at all. */
          $gridConfig = [
              'labels' => $gridLabels,
              'columns' => [
                  ['field' => 'name', 'title' => $gridLabels['columns']['service'], 'type' => 'primary', 'grow' => 3, 'min' => 200, 'responsive' => 0],
                  ['field' => 'category', 'title' => $gridLabels['columns']['category'], 'grow' => 1.5, 'min' => 130, 'responsive' => 5],
                  ['field' => 'duration', 'title' => $gridLabels['columns']['duration'], 'grow' => 1, 'min' => 100, 'responsive' => 2],
                  ['field' => 'price', 'title' => $gridLabels['columns']['price'], 'grow' => 1, 'min' => 100, 'responsive' => 2],
                  ['field' => 'cash_price', 'title' => $gridLabels['columns']['cash_price'], 'grow' => 1, 'min' => 100, 'responsive' => 4],
                  ['field' => 'deposit', 'title' => $gridLabels['columns']['deposit'], 'grow' => 1, 'min' => 110, 'responsive' => 5],
                  ['field' => 'staff', 'title' => $gridLabels['columns']['staff'], 'grow' => 1.5, 'min' => 130, 'responsive' => 4],
                  ['field' => 'resource', 'title' => $gridLabels['columns']['resource'], 'grow' => 1.2, 'min' => 120, 'responsive' => 6],
                  ['field' => 'location', 'title' => $gridLabels['columns']['location'], 'grow' => 1.5, 'min' => 130, 'responsive' => 5],
                  ['field' => 'online', 'title' => $gridLabels['columns']['online'], 'type' => 'badge', 'width' => 130, 'responsive' => 3],
                  ['field' => 'status', 'title' => $gridLabels['columns']['status'], 'type' => 'badge', 'width' => 110, 'responsive' => 1],
                  ['field' => 'actions', 'type' => 'actions'],
              ],
          ];
      @endphp

      {{-- How many the current search and filters return. Filled by the grid
           from the same response that drew the rows, so the two cannot
           disagree — a count worked out separately is a count that will
           eventually describe a different list. --}}
      <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

      <div class="mt-4 styledesk_gridframe">
        <div data-grid
             data-url="{{ route('services.data', array_filter($filters)) }}"
             data-config='@json($gridConfig)'></div>
      </div>
    @endif
  </main>
@endsection
