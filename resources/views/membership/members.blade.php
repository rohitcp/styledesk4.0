@extends('layouts.app')

@section('title', __('membership.members.title'))

{{--
    Clients → Membership → Members.

    Everybody holding one, ordered by who renews soonest. That is the question
    this list is opened to answer: a renewal that fails is a member who
    quietly stops being one, and a list sorted by name would bury them.

    The shared listing grid, the same one the clients and the plans use. It
    was a plain table on the argument that this list is read rather than
    filtered — which stopped being true the moment there were members enough
    to look one up, and left one screen in the app whose table behaved
    differently from every other.
--}}

@php
    /* Built here rather than inline: the json directive cannot take a call
       with nested brackets — Blade's parser counts them rather than reading
       PHP — so every inline attempt has to assemble the array in a php block
       first, and one of them will eventually forget. */
    $memberStatuses = collect(['active', 'scheduled', 'paused', 'cancelling', 'ended'])
        ->mapWithKeys(fn (string $key) => [$key => __('membership.member_statuses.'.$key)]);

    $memberTypes = collect(App\Models\MembershipPlan::TYPES)
        ->mapWithKeys(fn (string $key) => [$key => __('membership.types.'.$key)]);

    $memberLocations = $locations->pluck('name', 'id');
@endphp

@section('content')
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    @include('membership._header')

    <form method="GET" action="{{ route('membership.members') }}" class="mt-4">
      <div class="flex flex-wrap items-start gap-2">
        <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                 value="{{ $filters['search'] }}" data-live-search
                 aria-label="{{ __('membership.members.search') }}"
                 placeholder="{{ __('membership.members.search') }}">

          @if ($filters['search'] !== '')
            <button type="button" class="styledesk_input__clear" data-search-clear
                    aria-label="{{ __('common.clear') }}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
          @endif
        </div>

        <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
          {{-- A member's status, not a plan's: what somebody holds can be
               paused or cancelling, which a plan never is. --}}
          <x-combo name="status" :options="$memberStatuses"
                   :selected="$filters['status']"
                   :placeholder="__('membership.filters.all_statuses')"
                   class="w-full lg:w-[170px] shrink-0" />

          <x-combo name="type" :options="$memberTypes"
                   :selected="$filters['type']"
                   :placeholder="__('membership.filters.all_types')"
                   class="w-full lg:w-[180px] shrink-0" />

          <x-combo name="location" :options="$memberLocations"
                   :selected="$filters['location']"
                   :placeholder="__('membership.filters.all_locations')"
                   class="w-full lg:w-[180px] shrink-0" />

          <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0">{{ __('common.search') }}</button>

          @if (collect($filters)->filter()->isNotEmpty())
            <a href="{{ route('membership.members') }}" class="styledesk_action w-full lg:w-auto shrink-0">
              {{ __('membership.filters.reset') }}
            </a>
          @endif
        </div>
      </div>

      <span hidden data-filter-labels="status" data-labels='@json($memberStatuses)'></span>
      <span hidden data-filter-labels="type" data-labels='@json($memberTypes)'></span>
      <span hidden data-filter-labels="location" data-labels='@json($memberLocations)'></span>

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
                'empty' => __('membership.members.none'),
            ],
            /* The number the row is named by. It is what somebody reading a
               card or a confirmation has in front of them, and what tells two
               clients on the same plan apart. */
            'name_field' => 'reference',
            'page_size' => 25,
            'columns' => [
                ['field' => 'reference', 'title' => __('membership.members.columns.reference'), 'type' => 'primary', 'grow' => 1.6, 'min' => 190, 'responsive' => 0],
                ['field' => 'client', 'title' => __('membership.members.columns.client'), 'grow' => 1.6, 'min' => 150, 'responsive' => 0],
                ['field' => 'membership', 'title' => __('membership.members.columns.membership'), 'grow' => 1.6, 'min' => 150, 'responsive' => 1],
                ['field' => 'type', 'title' => __('membership.columns.type'), 'width' => 150, 'responsive' => 5],
                ['field' => 'price', 'title' => __('membership.columns.price'), 'width' => 130, 'responsive' => 4],
                ['field' => 'started', 'title' => __('membership.members.columns.started'), 'width' => 120, 'responsive' => 6, 'muted' => true],
                ['field' => 'next_billing', 'title' => __('membership.members.columns.next_billing'), 'width' => 130, 'responsive' => 2, 'muted' => true],
                ['field' => 'credits', 'title' => __('membership.members.columns.credits'), 'width' => 110, 'responsive' => 3],
                ['field' => 'status', 'title' => __('membership.members.columns.status'), 'type' => 'badge', 'width' => 130, 'responsive' => 0],
                ['type' => 'actions'],
            ],
        ];
    @endphp

    <div class="mt-2 styledesk_gridframe">
      <div data-grid
           data-url="{{ route('membership.members.data', array_filter($filters)) }}"
           data-config='@json($gridConfig)'></div>
    </div>

    {{-- Plan details opens over the table rather than navigating to it: a
         desk checking three memberships in a row keeps its filters, its page
         and its place. The same panel the client profile and the Sales table
         open. --}}
    <div class="styledesk_sheet" data-membership-sheet hidden>
      <aside class="styledesk_sheet__panel" role="dialog" aria-modal="true" aria-labelledby="memberSheetName">
        <header class="styledesk_sheet__head">
          <div class="min-w-0">
            <p id="memberSheetName" class="text-[15px] font-bold text-head truncate" data-sheet-name></p>
            <p class="text-[12px] font-medium text-sub font-mono" data-sheet-reference></p>
          </div>

          <div class="ml-auto shrink-0 text-right">
            <span class="styledesk_badge" data-sheet-status></span>
          </div>

          <button type="button" class="styledesk_sheet__close" data-sheet-close
                  aria-label="{{ __('leads.drawer.close') }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
            </svg>
          </button>
        </header>

        <div class="styledesk_sheet__body" data-sheet-body></div>

        <footer class="styledesk_sheet__foot">
          <a class="styledesk_sheet__cta" data-sheet-primary></a>
        </footer>
      </aside>
    </div>
  </main>
@endsection
