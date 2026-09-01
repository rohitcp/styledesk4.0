@extends('layouts.app')

@section('title', __('bookings.title'))

@section('content')
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('bookings.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5">{{ __('bookings.intro') }}</p>
      </div>

      <div class="shrink-0 flex flex-wrap items-center gap-2">
        <a href="{{ route('bookings.create', ['walk-in' => 1]) }}" class="styledesk_action">
          {{ __('bookings.modes.walkin') }}
        </a>

        <a href="{{ route('bookings.create') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('bookings.new') }}
        </a>
      </div>
    </header>

    @if (! $hasBookings)
      {{-- Nothing at all, which is a different fact from nothing matching —
           and gets a different answer. The reader has not searched for
           anything, so they must not be told their search found nothing. --}}
      <div class="mt-6 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('bookings.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5">{{ __('bookings.none_yet_hint') }}</p>

        <a href="{{ route('bookings.create') }}"
           class="inline-flex items-center gap-1.5 h-9 px-3.5 mt-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('bookings.new') }}
        </a>
      </div>
    @else

    {{-- The four a receptionist uses all day, and the rest behind More.

         Each tab is its own address rather than a script that swaps the
         table out: a desk that lives on Check-in pending should be able to
         bookmark it, and the back button should mean what it says. --}}
    <nav class="mt-5 border-b border-line" aria-label="{{ __('bookings.title') }}">
      <div class="flex flex-wrap items-end gap-1">
        @foreach (collect($tabs)->filter(fn (array $tab) => $tab['primary'])->keys() as $key)
          @php $current = $filters['tab'] === $key; @endphp
          <a href="{{ route('bookings.index', ['tab' => $key]) }}"
             @class([
                 'inline-flex items-center gap-2 h-9 px-3.5 rounded-t-lg text-[13px] font-semibold border-b-2 -mb-px transition-colors',
                 'border-brand text-brand' => $current,
                 'border-transparent text-sub hover:text-head' => ! $current,
             ])
             @if ($current) aria-current="page" @endif>
            {{ __('bookings.tabs.'.$key) }}

            {{-- A number only where it means work waiting. Nine counts
                 across a tab bar is nine things to read before finding the
                 one that matters. --}}
            @if (($counts[$key] ?? 0) > 0)
              <span @class([
                  'inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] font-bold',
                  'bg-brand text-white' => $key === 'check-in',
                  'bg-line text-sub' => $key !== 'check-in',
              ])>{{ $counts[$key] }}</span>
            @endif
          </a>
        @endforeach

        @php $inMore = ! ($tabs[$filters['tab']]['primary'] ?? true); @endphp

        <details class="relative -mb-px" @if ($inMore) open @endif data-more-tabs>
          <summary @class([
              'inline-flex items-center gap-1.5 h-9 px-3.5 rounded-t-lg text-[13px] font-semibold border-b-2 cursor-pointer list-none transition-colors',
              'border-brand text-brand' => $inMore,
              'border-transparent text-sub hover:text-head' => ! $inMore,
          ])>
            {{ $inMore ? __('bookings.tabs.'.$filters['tab']) : __('bookings.tabs.more') }}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </summary>

          <div class="absolute left-0 top-full z-30 mt-1 min-w-[190px] py-1 rounded-lg border border-line bg-card shadow-lg">
            @foreach (collect($tabs)->reject(fn (array $tab) => $tab['primary'])->keys() as $key)
              <a href="{{ route('bookings.index', ['tab' => $key]) }}"
                 @class([
                     'block px-3.5 py-2 text-[13px] hover:bg-hover transition-colors',
                     'text-brand font-semibold' => $filters['tab'] === $key,
                     'text-ink' => $filters['tab'] !== $key,
                 ])>{{ __('bookings.tabs.'.$key) }}</a>
            @endforeach
          </div>
        </details>
      </div>
    </nav>

    {{-- How today is going, in five numbers. Clickable, because "six waiting
         to check in" is only useful if pressing it shows you which six. --}}
    @if ($summary !== [])
      <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">
        @foreach ($summary as $card)
          @php $active = $filters['status'] === $card['key'] && $card['key'] !== ''; @endphp
          <a href="{{ route('bookings.index', array_filter(['tab' => 'today', 'status' => $card['key']])) }}"
             @class([
                 'sd-card px-4 py-3 transition-colors hover:border-brand',
                 'border-brand' => $active,
             ])>
            <p class="text-[12px] font-semibold text-sub">{{ $card['label'] }}</p>
            <p class="text-[22px] font-bold text-head leading-tight mt-0.5">{{ $card['count'] }}</p>
          </a>
        @endforeach
      </div>
    @endif

    {{-- The month being read, with an arrow either side. Only on the tab it
         belongs to: a month picker above Today would be a control that does
         nothing. --}}
    @if ($filters['tab'] === 'month')
      <div class="mt-4 flex items-center gap-2">
        <a href="{{ route('bookings.index', ['tab' => 'month', 'month' => $month->subMonth()->format('Y-m')]) }}"
           class="styledesk_action" aria-label="{{ __('bookings.tabs.previous_month') }}">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>

        <p class="text-[14px] font-semibold text-head min-w-[150px] text-center">{{ $month->translatedFormat('F Y') }}</p>

        <a href="{{ route('bookings.index', ['tab' => 'month', 'month' => $month->addMonth()->format('Y-m')]) }}"
           class="styledesk_action" aria-label="{{ __('bookings.tabs.next_month') }}">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
    @endif

    {{-- The filters, and the tab travelling with them so narrowing a search
         does not throw the reader back to Today. --}}
    <form method="GET" action="{{ route('bookings.index') }}" class="mt-4" data-listing-filters>
      <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
      @if ($filters['tab'] === 'month')
        <input type="hidden" name="month" value="{{ $filters['month'] }}">
      @endif

      <div class="flex flex-wrap items-start gap-2">
        <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          {{-- Live, on a debounce: a desk looking for one client should not
               have to find the Search button first. --}}
          <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                 value="{{ $filters['search'] }}" data-live-search
                 aria-label="{{ __('bookings.columns.client') }}"
                 placeholder="{{ __('bookings.search_placeholder') }}">

          @if ($filters['search'] !== '')
            <button type="button" class="styledesk_input__clear" data-search-clear
                    aria-label="{{ __('common.clear') }}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
          @endif
        </div>

        <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
          <x-combo name="location" :options="$locations->pluck('name', 'id')"
                   :selected="$filters['location']"
                   :placeholder="__('bookings.filters.all_locations')"
                   class="w-full lg:w-[170px] shrink-0" />

          <x-combo name="staff" :options="$staff->pluck('name', 'id')"
                   :selected="$filters['staff']"
                   :placeholder="__('bookings.filters.all_staff')"
                   class="w-full lg:w-[180px] shrink-0" />

          <x-combo name="service" :options="$services->pluck('name', 'id')"
                   :selected="$filters['service']"
                   :placeholder="__('bookings.filters.all_services')"
                   class="w-full lg:w-[190px] shrink-0" />

          <x-combo name="status"
                   :options="collect(config('bookings.statuses'))->keys()
                       ->mapWithKeys(fn (string $key) => [$key => __('bookings.statuses.'.$key.'.label')])"
                   :selected="$filters['status']"
                   :placeholder="__('bookings.filters.all_statuses')"
                   class="w-full lg:w-[160px] shrink-0" />

          <x-combo name="payment"
                   :options="collect(config('bookings.payment_statuses'))->keys()
                       ->mapWithKeys(fn (string $key) => [$key => __('bookings.payment_statuses.'.$key.'.label')])"
                   :selected="$filters['payment']"
                   :placeholder="__('bookings.filters.all_payments')"
                   class="w-full lg:w-[160px] shrink-0" />

          <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0">
            {{ __('common.search') }}
          </button>

          @if (collect($filters)->except(['tab', 'month'])->filter()->isNotEmpty())
            <a href="{{ route('bookings.index', ['tab' => $filters['tab']]) }}" class="styledesk_action w-full lg:w-auto shrink-0">
              {{ __('bookings.filters.reset') }}
            </a>
          @endif
        </div>
      </div>

      {{-- The names behind the ids, so a chip can be labelled without asking
           the server again. --}}
      <span hidden data-filter-labels="location" data-labels='@json($locations->pluck('name', 'id'))'></span>
      <span hidden data-filter-labels="staff" data-labels='@json($staff->mapWithKeys(fn ($m) => [$m->id => $m->first_name.' '.$m->last_name]))'></span>
      <span hidden data-filter-labels="service" data-labels='@json($services->pluck('name', 'id'))'></span>
      <span hidden data-filter-labels="status" data-labels='@json(collect(config('bookings.statuses'))->keys()->mapWithKeys(fn (string $k) => [$k => __('bookings.statuses.'.$k.'.label')]))'></span>
      <span hidden data-filter-labels="payment" data-labels='@json(collect(config('bookings.payment_statuses'))->keys()->mapWithKeys(fn (string $k) => [$k => __('bookings.payment_statuses.'.$k.'.label')]))'></span>

      {{-- Active filters. Hidden entirely when nothing is chosen rather than
           left as an empty band: a row that is sometimes blank is a row the
           reader has to check. Built by the shared filter script from what
           the controls hold, so it cannot disagree with them. --}}
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
        $gridLabels = [
            'actions_for' => __('bookings.actions_for', ['name' => ':name']),
            'showing' => __('bookings.showing'),
            'results' => [
                'zero' => __('bookings.results.zero'),
                'one' => __('bookings.results.one'),
                'many' => __('bookings.results.many'),
            ],
            'clear_filters' => __('bookings.results.clear'),
            /* What an empty tab says. "No bookings match" is wrong on a
               queue nobody is waiting in — that is good news, not a dead
               search. */
            'empty' => __('bookings.tabs.empty.'.$filters['tab'], [], null) === 'bookings.tabs.empty.'.$filters['tab']
                ? __('bookings.empty')
                : __('bookings.tabs.empty.'.$filters['tab']),
        ];

        /* The columns change with the tab, because the questions do. Today
           and the queue are read by time and by whether the client is here;
           a month is read by date and by what it came to. */
        $isQueue = $filters['tab'] === 'check-in';
        $isToday = in_array($filters['tab'], ['today', 'check-in'], true);

        $columns = array_values(array_filter([
            $isToday ? null : ['field' => 'date', 'title' => __('bookings.columns.date'), 'grow' => 1.2, 'min' => 110, 'responsive' => 0],
            ['field' => 'time', 'title' => __('bookings.columns.time'), 'grow' => 1.5, 'min' => 145, 'responsive' => 0],
            /* On the queue this leads, because it is the column the desk
               acts on: "12 min late" is the whole reason to look. */
            $isQueue ? ['field' => 'arrival', 'title' => __('bookings.columns.arrival'), 'type' => 'badge', 'width' => 130, 'responsive' => 0] : null,
            ['field' => 'reference', 'title' => __('bookings.columns.reference'), 'width' => 130, 'responsive' => 6],
            ['field' => 'name', 'title' => __('bookings.columns.client'), 'type' => 'primary', 'grow' => 2.2, 'min' => 190, 'responsive' => 0],
            ['field' => 'services', 'title' => __('bookings.columns.services'), 'grow' => 2, 'min' => 160, 'responsive' => 5],
            ['field' => 'staff', 'title' => __('bookings.columns.staff'), 'grow' => 1.4, 'min' => 130, 'responsive' => 4],
            ['field' => 'location', 'title' => __('bookings.columns.location'), 'grow' => 1.2, 'min' => 120, 'responsive' => 7],
            $isQueue ? null : ['field' => 'total', 'title' => __('bookings.columns.total'), 'width' => 110, 'responsive' => 3],
            ['field' => 'payment', 'title' => __('bookings.detail.payment_status'), 'type' => 'badge', 'width' => 130, 'responsive' => 2],
            $isQueue ? null : ['field' => 'status', 'title' => __('bookings.columns.status'), 'type' => 'badge', 'width' => 120, 'responsive' => 1],
            $isToday ? ['field' => 'checkin', 'title' => __('bookings.columns.checkin'), 'grow' => 1.2, 'min' => 130, 'responsive' => 8] : null,
            ['type' => 'actions'],
        ]));

        $gridConfig = [
            'labels' => $gridLabels,
            'name_field' => 'name',
            'page_size' => 25,
            'columns' => $columns,
        ];
    @endphp

    <div class="mt-2 styledesk_gridframe">
      <div data-grid
           data-url="{{ route('bookings.data', array_filter($filters)) }}"
           data-config='@json($gridConfig)'></div>
    </div>
    @endif
  </main>
@endsection
