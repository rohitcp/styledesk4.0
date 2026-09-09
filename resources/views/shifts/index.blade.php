@extends('layouts.app')

@section('title', __('shifts.title'))

@section('content')
  {{-- The listing frame the clients and staff listings use: one content
       width for the header, the toolbar and the grid. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('shifts.title') }}</h1>
        {{-- The distinction this screen exists to make. A shift and a staff
             schedule are easy to confuse, and the sentence under the heading
             is where that is actually settled. --}}
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ __('shifts.intro') }}</p>
      </div>

      @can('create', App\Models\Staff::class)
        <a href="{{ route('shifts.create') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('shifts.add') }}
        </a>
      @endcan
    </header>

    @if (! $hasShifts)
      {{-- No shifts at all, which is a different fact from none matching —
           the reader has not searched for anything. --}}
      <div class="mt-6 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('shifts.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[460px] mx-auto leading-relaxed">{{ __('shifts.none_yet_hint') }}</p>

        @can('create', App\Models\Staff::class)
          <a href="{{ route('shifts.create') }}"
             class="inline-flex items-center gap-1.5 h-9 px-3.5 mt-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('shifts.add') }}
          </a>
        @endcan
      </div>
    @else

    <form method="GET" action="{{ route('shifts.index') }}" class="mt-4">
      <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2">
        <x-combo name="staff" :options="$staff->mapWithKeys(fn ($m) => [$m->id => $m->displayName()])"
                 :selected="$filters['staff']"
                 :placeholder="__('shifts.filters.all_staff')"
                 class="w-full lg:w-[190px] shrink-0" />

        <x-combo name="location" :options="$locations->pluck('name', 'id')" :selected="$filters['location']"
                 :placeholder="__('shifts.filters.all_locations')"
                 class="w-full lg:w-[170px] shrink-0" />

        <x-combo name="type"
                 :options="collect(config('shifts.types'))->mapWithKeys(fn ($t, $key) => [$key => __('shifts.types.'.$key)])"
                 :selected="$filters['type']"
                 :placeholder="__('shifts.filters.all_types')"
                 class="w-full lg:w-[150px] shrink-0" />

        <x-combo name="status"
                 :options="collect(config('shifts.statuses'))->mapWithKeys(fn ($s, $key) => [$key => __('shifts.statuses.'.$key)])"
                 :selected="$filters['status']"
                 :placeholder="__('shifts.filters.all_statuses')"
                 class="w-full lg:w-[150px] shrink-0" />

        {{-- A rota is read a week or a month at a time, so the range is a
             filter rather than something to scroll to. --}}
        <x-date-field name="from" :label="__('shifts.filters.from')" :value="$filters['from']"
                      class="w-full lg:w-[160px] shrink-0" />
        <x-date-field name="until" :label="__('shifts.filters.until')" :value="$filters['until']"
                      class="w-full lg:w-[160px] shrink-0" />

        <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0 lg:self-end">
          {{ __('common.search') }}
        </button>
      </div>

      <span hidden data-filter-labels="staff" data-labels='@json($staff->mapWithKeys(fn ($m) => [$m->id => $m->displayName()]))'></span>
      <span hidden data-filter-labels="location" data-labels='@json($locations->pluck('name', 'id'))'></span>
      <span hidden data-filter-labels="type" data-labels='@json(collect(config('shifts.types'))->mapWithKeys(fn ($t, $key) => [$key => __('shifts.types.'.$key)]))'></span>
      <span hidden data-filter-labels="status" data-labels='@json(collect(config('shifts.statuses'))->mapWithKeys(fn ($s, $key) => [$key => __('shifts.statuses.'.$key)]))'></span>

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
        $gridConfig = [
            'labels' => [
                'actions_for' => __('staff.actions_for', ['name' => ':name']),
                'showing' => __('shifts.showing'),
                'results' => [
                    'zero' => __('shifts.results.zero'),
                    'one' => __('shifts.results.one'),
                    'many' => __('shifts.results.many'),
                ],
                'clear_filters' => __('shifts.results.clear'),
                'empty' => __('shifts.results.empty'),
            ],
            'name_field' => 'name',
            /* Worst-first in the order they may leave as the table narrows:
               who and when are the last two out, because a rota with neither
               answers nothing. */
            'columns' => [
                ['field' => 'name', 'title' => __('shifts.columns.staff'), 'type' => 'primary', 'grow' => 2.4, 'min' => 190, 'responsive' => 0],
                ['field' => 'date', 'title' => __('shifts.columns.date'), 'grow' => 1.4, 'min' => 140, 'responsive' => 0],
                ['field' => 'hours', 'title' => __('shifts.columns.hours'), 'grow' => 1.6, 'min' => 160, 'responsive' => 1],
                ['field' => 'break', 'title' => __('shifts.columns.break'), 'width' => 90, 'responsive' => 5, 'muted' => true],
                ['field' => 'location', 'title' => __('shifts.columns.location'), 'grow' => 1.5, 'min' => 140, 'responsive' => 4],
                ['field' => 'type', 'title' => __('shifts.columns.type'), 'type' => 'badge', 'width' => 120, 'responsive' => 3],
                ['field' => 'status', 'title' => __('shifts.columns.status'), 'type' => 'badge', 'width' => 120, 'responsive' => 2],
                ['field' => 'actions', 'type' => 'actions'],
            ],
        ];
    @endphp

    <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

    <div class="mt-4 styledesk_gridframe">
      <div data-grid
           data-url="{{ route('shifts.data', array_filter($filters)) }}"
           data-config='@json($gridConfig)'></div>
    </div>
    @endif
  </main>
@endsection
