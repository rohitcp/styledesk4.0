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

    <form method="GET" action="{{ route('bookings.index') }}" class="mt-4">
      <div class="flex flex-wrap items-start gap-2">
        <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                 value="{{ $filters['search'] }}"
                 aria-label="{{ __('bookings.columns.client') }}"
                 placeholder="{{ __('bookings.client.search') }}">
        </div>

        <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
          <input name="date" type="date" class="sd-input w-full lg:w-[170px] shrink-0"
                 value="{{ $filters['date'] }}" aria-label="{{ __('bookings.filters.date') }}">

          <x-combo name="status"
                   :options="collect(config('bookings.statuses'))->keys()
                       ->mapWithKeys(fn (string $key) => [$key => __('bookings.statuses.'.$key.'.label')])"
                   :selected="$filters['status']"
                   :placeholder="__('bookings.filters.all_statuses')"
                   class="w-full lg:w-[170px] shrink-0" />

          <x-combo name="staff" :options="$staff->pluck('name', 'id')"
                   :selected="$filters['staff']"
                   :placeholder="__('bookings.filters.all_staff')"
                   class="w-full lg:w-[190px] shrink-0" />

          <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0">
            {{ __('common.search') }}
          </button>

          @if (collect($filters)->filter()->isNotEmpty())
            <a href="{{ route('bookings.index') }}" class="styledesk_action w-full lg:w-auto shrink-0">
              {{ __('bookings.filters.reset') }}
            </a>
          @endif
        </div>
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
            'empty' => __('bookings.empty'),
        ];

        $gridConfig = [
            'labels' => $gridLabels,
            'name_field' => 'name',
            'page_size' => 25,
            /* The reference leads: it is what a booking is quoted by over the
               phone, and the first thing anybody scans a list of them for.
               `responsive` is the order columns are dropped in as the window
               narrows — the identity, the day and where it stands survive
               longest, and who took it goes first. */
            'columns' => [
                ['field' => 'reference', 'title' => __('bookings.columns.reference'), 'width' => 130, 'responsive' => 0],
                ['field' => 'name', 'title' => __('bookings.columns.client'), 'type' => 'primary', 'grow' => 2.2, 'min' => 190, 'responsive' => 0],
                ['field' => 'services', 'title' => __('bookings.columns.services'), 'grow' => 2, 'min' => 160, 'responsive' => 5],
                ['field' => 'staff', 'title' => __('bookings.columns.staff'), 'grow' => 1.4, 'min' => 130, 'responsive' => 6],
                ['field' => 'date', 'title' => __('bookings.columns.date'), 'grow' => 1.2, 'min' => 110, 'responsive' => 0],
                ['field' => 'time', 'title' => __('bookings.columns.time'), 'grow' => 1.5, 'min' => 145, 'responsive' => 2],
                ['field' => 'duration', 'title' => __('bookings.summary.duration'), 'width' => 110, 'responsive' => 7],
                ['field' => 'total', 'title' => __('bookings.columns.total'), 'width' => 110, 'responsive' => 3],
                ['field' => 'payment', 'title' => __('bookings.detail.payment_status'), 'type' => 'badge', 'width' => 130, 'responsive' => 4],
                ['field' => 'status', 'title' => __('bookings.columns.status'), 'type' => 'badge', 'width' => 120, 'responsive' => 1],
                ['field' => 'booked_by', 'title' => __('bookings.columns.booked_by'), 'grow' => 1.2, 'min' => 130, 'responsive' => 8],
                ['type' => 'actions'],
            ],
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
