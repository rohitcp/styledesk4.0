@extends('layouts.app')

@section('title', __('membership.tabs.'.($type === 'recurring' ? 'plans' : 'packages')))

{{--
    Clients → Membership → Membership Plans / Membership Packages.

    One view for both tabs. They are the same list of the same model filtered
    by kind, and two views would be two places to add a column to. The only
    difference is which columns earn their width: a package has a saving and
    no billing frequency, a plan the other way round.
--}}

@php
    $dataRoute = $type === 'recurring' ? 'membership.plans.data' : 'membership.packages.data';
    $listRoute = $type === 'recurring' ? 'membership.plans' : 'membership.packages';
    $canCreate = auth()->user()?->hasPermission('clients.create', 'own') ?? false;
@endphp

@section('content')
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    @include('membership._header')

    @if (! $hasAny)
      {{-- Nothing of this kind at all, which is a different fact from
           nothing matching — and gets a different answer. --}}
      <div class="mt-6 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('membership.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[440px] mx-auto">{{ __('membership.none_yet_hint') }}</p>

        @if ($canCreate)
          <a href="{{ route('membership.create', ['type' => $type]) }}"
             class="inline-flex items-center gap-1.5 h-9 px-3.5 mt-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('membership.new') }}
          </a>
        @endif
      </div>
    @else

    <form method="GET" action="{{ route($listRoute) }}" class="mt-4">
      <div class="flex flex-wrap items-start gap-2">
        <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                 value="{{ $filters['search'] }}" data-live-search
                 aria-label="{{ __('membership.search') }}"
                 placeholder="{{ __('membership.search') }}">

          @if ($filters['search'] !== '')
            <button type="button" class="styledesk_input__clear" data-search-clear
                    aria-label="{{ __('common.clear') }}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
          @endif
        </div>

        <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
          <x-combo name="status"
                   :options="collect(App\Models\MembershipPlan::STATUSES)->mapWithKeys(fn (string $k) => [$k => __('membership.statuses.'.$k)])"
                   :selected="$filters['status']"
                   :placeholder="__('membership.filters.all_statuses')"
                   class="w-full lg:w-[170px] shrink-0" />

          <x-combo name="location" :options="$locations->pluck('name', 'id')"
                   :selected="$filters['location']"
                   :placeholder="__('membership.filters.all_locations')"
                   class="w-full lg:w-[180px] shrink-0" />

          <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0">{{ __('common.search') }}</button>

          @if (collect($filters)->filter()->isNotEmpty())
            <a href="{{ route($listRoute) }}" class="styledesk_action w-full lg:w-auto shrink-0">
              {{ __('membership.filters.reset') }}
            </a>
          @endif
        </div>
      </div>

      <span hidden data-filter-labels="status" data-labels='@json(collect(App\Models\MembershipPlan::STATUSES)->mapWithKeys(fn (string $k) => [$k => __('membership.statuses.'.$k)]))'></span>
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
                'actions_for' => __('membership.actions_for', ['name' => ':name']),
                'showing' => __('membership.showing'),
                'results' => [
                    'zero' => __('membership.results.zero'),
                    'one' => __('membership.results.one'),
                    'many' => __('membership.results.many'),
                ],
                'clear_filters' => __('membership.results.clear'),
                'empty' => __('membership.empty'),
            ],
            'name_field' => 'name',
            'page_size' => 25,
            'columns' => array_values(array_filter([
                ['field' => 'name', 'title' => __('membership.columns.name'), 'type' => 'primary', 'grow' => 2, 'min' => 180, 'responsive' => 0],
                ['field' => 'price', 'title' => __('membership.columns.price'), 'width' => 130, 'responsive' => 0],
                ['field' => 'includes', 'title' => __('membership.columns.includes'), 'grow' => 1.8, 'min' => 160, 'responsive' => 1],
                ['field' => 'benefit', 'title' => __('membership.columns.benefit'), 'width' => 160, 'responsive' => 4],
                /* A saving is a package's headline and a recurring plan does
                   not have one, so the column only exists where it means
                   something rather than showing a dash down the page. */
                $type === 'package'
                    ? ['field' => 'saving', 'title' => __('membership.columns.saving'), 'width' => 110, 'responsive' => 3]
                    : null,
                ['field' => 'code', 'title' => __('membership.columns.code'), 'width' => 120, 'responsive' => 6],
                ['field' => 'locations', 'title' => __('membership.columns.locations'), 'grow' => 1.2, 'min' => 130, 'responsive' => 5],
                ['field' => 'status', 'title' => __('membership.columns.status'), 'type' => 'badge', 'width' => 120, 'responsive' => 0],
                ['type' => 'actions'],
            ])),
        ];
    @endphp

    <div class="mt-2 styledesk_gridframe">
      <div data-grid
           data-url="{{ route($dataRoute, array_filter($filters)) }}"
           data-config='@json($gridConfig)'></div>
    </div>
    @endif
  </main>
@endsection
