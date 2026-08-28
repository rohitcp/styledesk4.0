@extends('layouts.app')

@section('title', __('clients.module.title'))

@section('content')
  {{-- One content width for the header, the toolbar and the grid. No
       max-width container: the table is the point of this screen, and a
       nine-column grid inside a 1180px column wastes half a desktop. --}}
  {{-- 100px under the pagination before the footer. On the page rather than
       inside the grid: a spacer within the scroller would be something the
       reader has to scroll past to reach the last row. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('clients.module.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ __('clients.module.intro') }}</p>
      </div>

      @if ($canCreate)
        {{-- The same label as the empty state's CTA once a business has
             clients, per §Add Client Button: two names for one action is two
             things to learn. --}}
        <a href="{{ route('clients.create') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('clients.module.add') }}
        </a>
      @endif
    </header>

    {{-- The four figures, above the toolbar they act on.
         Each card is a filter: pressing one narrows the grid the same way a
         dropdown does, and leaves a chip saying so — so the reader can always
         see why the list is what it is, and undo it. --}}
    @php
        $cards = [
            [
                'key' => 'total',
                'tone' => 'blue',
                'label' => __('clients.module.stats.total'),
                'value' => number_format($stats['total']),
                'icon' => 'users',
                'filter' => null,
                'note' => __('clients.module.stats.total_note'),
            ],
            [
                'key' => 'created',
                'tone' => 'green',
                'label' => __('clients.module.stats.new_this_month'),
                'value' => number_format($stats['new_this_month']),
                'icon' => 'user-plus',
                'filter' => 'created',
                'trend' => $stats['new_trend'],
            ],
            [
                'key' => 'upcoming',
                'tone' => 'violet',
                'label' => __('clients.module.stats.upcoming'),
                'value' => number_format($stats['upcoming']),
                'icon' => 'calendar-check',
                'filter' => 'upcoming',
                'note' => $stats['upcoming'] === 0 ? __('clients.module.stats.awaiting_bookings') : null,
            ],
            [
                'key' => 'inactive',
                'tone' => 'amber',
                'label' => __('clients.module.stats.inactive'),
                'value' => number_format($stats['inactive']),
                'icon' => 'user-clock',
                'filter' => 'status:inactive',
                'note' => __('clients.module.stats.inactive_note'),
            ],
        ];
    @endphp

    <div class="mt-4 styledesk_statrow styledesk_scroll">
      @foreach ($cards as $card)
        <button type="button"
                @if ($card['filter']) data-stat-filter="{{ $card['filter'] }}" @else data-stat-filter="" @endif
                class="styledesk_statcard styledesk_statcard--{{ $card['tone'] }} text-left">
          <span class="styledesk_statcard__icon" aria-hidden="true">
            <x-icon :name="$card['icon']" size="15" />
          </span>

          <span class="min-w-0">
            <span class="styledesk_label block">{{ $card['label'] }}</span>
            <span class="block text-[20px] font-bold text-head leading-tight mt-0.5">{{ $card['value'] }}</span>

            @if (! empty($card['trend']))
              {{-- Colour only where the direction means something: more new
                   clients this month is good news, and that is the whole
                   reason this line is green rather than grey. --}}
              <span class="block text-[12px] mt-1 font-medium {{ $card['trend'] > 0 ? 'text-success' : 'text-danger' }}">
                {{ $card['trend'] > 0 ? '↑' : '↓' }} {{ abs($card['trend']) }}%
                <span class="text-faint font-normal">{{ __('clients.module.stats.vs_last_month') }}</span>
              </span>
            @elseif (! empty($card['note']))
              <span class="block text-[12px] text-faint mt-1">{{ $card['note'] }}</span>
            @endif
          </span>
        </button>
      @endforeach
    </div>

    {{-- Toolbar: search and the four filters on one line where there is room,
         wrapping before they shrink into unreadability. --}}
    <form method="GET" action="{{ route('clients.index') }}" class="mt-4">
      {{-- Tops aligned, not bottoms: a filter holding three chips grows
           downward and takes the toolbar with it. Aligned at the bottom it
           would grow upward instead, over the heading above it. --}}
      {{-- The card filters, held in the form so they travel with a search
           and appear in the address bar like every other filter. --}}
      <input type="hidden" name="created" value="{{ $filters['created'] }}" data-card-filter="created">
      <input type="hidden" name="upcoming" value="{{ $filters['upcoming'] }}" data-card-filter="upcoming">

      {{-- The search keeps a whole row to itself when the window is narrow
           and the filters take the next one. Splitting the search field
           itself would be splitting one control in two. --}}
      <div class="flex flex-wrap items-start gap-2">
        <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                 value="{{ $filters['search'] }}"
                 aria-label="{{ __('clients.module.search_placeholder') }}"
                 placeholder="{{ __('clients.module.search_placeholder') }}">
        </div>

        {{-- The filters move as one group: wrapped one at a time they would
             leave a single dropdown stranded on a line of its own.

             They wrap rather than scroll on purpose — each control opens a
             panel positioned inside itself, and a container with overflow set
             would cut those panels off at its edge. --}}
        <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
        <x-combo name="status" :options="$statuses" :selected="$filters['status']"
                 :placeholder="__('clients.module.filters.all_statuses')"
                 class="w-full lg:w-[150px] shrink-0" />

        {{-- These three take more than one value. The control says how many
             are chosen; the row below says which, where the names have room
             to be read and each can be removed on its own. --}}
        <x-combo name="location" multiple :options="$locations->pluck('name', 'id')" :selected="$filters['location']"
                 :placeholder="__('clients.module.filters.all_locations')"
                 :summary="__('clients.module.filters.location_short')"
                 class="w-full lg:w-[170px] shrink-0" />

        <x-combo name="staff" multiple :options="$staff->mapWithKeys(fn ($m) => [$m->id => $m->displayName()])"
                 :selected="$filters['staff']" :placeholder="__('clients.module.filters.all_staff')"
                 :summary="__('clients.module.filters.staff_short')"
                 class="w-full lg:w-[170px] shrink-0" />

        <x-combo name="tag" multiple :options="$tags->pluck('label', 'id')" :selected="$filters['tag']"
                 :placeholder="__('clients.module.filters.all_tags')"
                 :summary="__('clients.module.filters.tag_short')"
                 class="w-full lg:w-[150px] shrink-0" />

        {{-- Full width below the desktop breakpoint, like every control
             above it: a button half the width of the field it acts on is a
             smaller target than the fields themselves. --}}
        <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0">
          {{ __('common.search') }}
        </button>
        </div>
      </div>

      {{-- Active filters. Hidden entirely when nothing is chosen rather than
           left as an empty band: a row that is sometimes blank is a row the
           reader has to check. Built by the page's own script from what the
           controls hold, so it cannot disagree with them. --}}
      {{-- The names behind the ids, so a chip can be labelled without asking
           the server again. --}}
      <span hidden data-filter-labels="location" data-labels='@json($locations->pluck('name', 'id'))'></span>
      <span hidden data-filter-labels="staff" data-labels='@json($staff->mapWithKeys(fn ($m) => [$m->id => $m->displayName()]))'></span>
      <span hidden data-filter-labels="tag" data-labels='@json($tags->pluck('label', 'id'))'></span>
      <span hidden data-filter-labels="status" data-labels='@json($statuses)'></span>
      <span hidden data-filter-labels="created" data-labels='@json(['this_month' => __('clients.module.stats.new_this_month')])'></span>
      <span hidden data-filter-labels="upcoming" data-labels='@json(['1' => __('clients.module.stats.upcoming')])'></span>

      <div class="mt-2.5 flex flex-wrap items-center gap-2" data-active-filters hidden
           data-remove-label="{{ __('common.remove') }}">
        <span class="text-[12px] font-semibold text-sub">{{ __('clients.module.filters.active') }}</span>
        <span class="flex flex-wrap items-center gap-1.5" data-active-chips></span>

        <button type="button" class="styledesk_action styledesk_action--sm" data-clear-filters>
          {{ __('common.clear_all') }}
        </button>
      </div>
    </form>

    @php
        $gridLabels = [
            'columns' => [
                'client' => __('clients.module.columns.client'),
                'mobile' => __('clients.module.columns.mobile'),
                'email' => __('clients.module.columns.email'),
                'staff' => __('clients.module.columns.staff'),
                'location' => __('clients.module.columns.location'),
                'last_visit' => __('clients.module.columns.last_visit'),
                'next_booking' => __('clients.module.columns.next_booking'),
                'status' => __('clients.module.columns.status'),
            ],
            'view' => __('clients.module.view'),
            'edit' => __('common.edit'),
            'create_booking' => __('clients.module.create_booking'),
            'archive' => __('clients.module.archive'),
            'restore' => __('clients.module.restore'),
            'archive_confirm' => __('clients.module.archive_confirm', ['name' => ':name']),
            'restore_confirm' => __('clients.module.restore_confirm', ['name' => ':name']),
            'actions_for' => __('clients.module.actions_for', ['name' => ':name']),
            'showing' => __('clients.module.showing'),
            'results' => [
                'zero' => __('clients.module.results.zero'),
                'one' => __('clients.module.results.one'),
                'many' => __('clients.module.results.many'),
            ],
            'clear_filters' => __('clients.module.results.clear'),

            /*
             * What the grid says when it has no rows. Which sentence that is
             * depends on why: a search that matched nothing is not a business
             * whose clients are all archived, and neither is a business with
             * no clients — that one never reaches this page.
             */
            'empty' => __('clients.module.results.empty'),
        ];
    @endphp

    {{-- How many the current search and filters return. Filled by the grid
         from the same response that drew the rows, so the two cannot
         disagree — a count worked out separately is a count that will
         eventually describe a different list. --}}
    <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

    @if (! $hasMatches && collect($filters)->filter()->isEmpty())
      {{-- Every client is archived. Said in full, with the way to go and see
           them: the grid could only have shown an empty table, and a reader
           who has not searched for anything should not be told their search
           found nothing. --}}
      <div class="mt-4 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('clients.module.all_archived') }}</p>
        <p class="text-[13px] text-sub mt-1.5">{{ __('clients.module.all_archived_hint') }}</p>

        <a href="{{ route('clients.index', ['status' => \App\Models\Client::STATUS_ARCHIVED]) }}"
           class="styledesk_action mt-4">
          {{ __('clients.module.view_archived') }}
        </a>
      </div>
    @else
    {{-- The grid. Full width, filling the height left under the toolbar and
         scrolling inside itself, with rows fetched a page at a time as the
         reader scrolls. --}}
    <div class="mt-4 styledesk_gridframe">
      {{-- The shared listing grid. The columns are named here and the row
           actions travel with each row, so this page describes its table
           rather than carrying a copy of one. --}}
      @php
          $gridConfig = [
              'labels' => $gridLabels,
              'name_field' => 'name',
              'columns' => [
                  ['field' => 'name', 'title' => $gridLabels['columns']['client'], 'type' => 'primary', 'grow' => 3, 'min' => 200, 'responsive' => 0],
                  ['field' => 'mobile', 'title' => $gridLabels['columns']['mobile'], 'grow' => 1.4, 'min' => 130, 'responsive' => 1],
                  ['field' => 'email', 'title' => $gridLabels['columns']['email'], 'grow' => 2, 'min' => 180, 'responsive' => 1],
                  ['field' => 'staff', 'title' => $gridLabels['columns']['staff'], 'grow' => 1.5, 'min' => 140, 'responsive' => 5],
                  ['field' => 'location', 'title' => $gridLabels['columns']['location'], 'grow' => 1.5, 'min' => 140, 'responsive' => 5],
                  ['field' => 'last_visit', 'title' => $gridLabels['columns']['last_visit'], 'grow' => 1.3, 'min' => 120, 'responsive' => 4, 'muted' => true],
                  ['field' => 'next_booking', 'title' => $gridLabels['columns']['next_booking'], 'grow' => 1.3, 'min' => 120, 'responsive' => 2, 'muted' => true],
                  ['field' => 'status', 'title' => $gridLabels['columns']['status'], 'type' => 'badge', 'width' => 110, 'responsive' => 1],
                  ['field' => 'actions', 'type' => 'actions'],
              ],
          ];
      @endphp

      <div data-grid
           data-url="{{ route('clients.data', array_filter($filters)) }}"
           data-config='@json($gridConfig)'></div>
    </div>
    @endif
  </main>
@endsection
