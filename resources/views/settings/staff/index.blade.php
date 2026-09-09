@extends('layouts.app')

@section('title', __('staff.title'))

@section('content')
  {{-- One content width for the header, the toolbar and the grid, exactly as
       the clients listing has it. No max-width column: the table is the point
       of this screen, and a nine-column grid inside 1180px wastes half a
       desktop.

       100px under the pagination before the footer. On the page rather than
       inside the grid: a spacer within the scroller is something the reader
       has to scroll past to reach the last row. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    {{-- Only in App Settings. The same screen is reached from the Staff
         module, where the trail does not run through a section the reader was
         never in — and where a link into an administrator-only area would be
         one they cannot open. --}}
    @if (\App\Support\StaffSection::isSettings())
      <nav class="text-[13px] text-sub mb-3" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('staff.title') }}</span>
      </nav>
    @endif

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('staff.title') }}</h1>

        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">
          @php
              /**
               * Built here rather than inline.
               *
               * Each half is a whole phrase in its own language: Str::plural()
               * only knows English and would have produced "2 invitación
               * pendientes".
               */
              $summary = trans_choice('staff.summary', $activeCount, ['count' => $activeCount]);

              if ($pendingCount) {
                  $summary .= ', '.trans_choice('staff.summary_pending', $pendingCount, ['count' => $pendingCount]);
              }
          @endphp
          {{ $summary }}.
        </p>
      </div>

      <div class="shrink-0 flex items-center gap-2">
        {{-- Designed, not built. Disabled rather than absent, the same way
             the navigation carries it: an entry that quietly disappears reads
             as a permission the reader lacks. --}}
        <button type="button" class="styledesk_action opacity-45 cursor-not-allowed" disabled
                data-pending-route="staff-schedule-add.html">
          {{ __('staff.add_schedule') }}
        </button>

        @can('create', App\Models\Staff::class)
          <a href="{{ \App\Support\StaffSection::route('create') }}"
             class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('staff.add') }}
          </a>
        @endcan
      </div>
    </header>

    @if (! $hasStaff)
      {{-- Nobody at all, which is a different fact from nobody matching — and
           gets a different answer. The reader has not searched for anything,
           so they must not be told their search found nothing. --}}
      <div class="mt-6 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('staff.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto leading-relaxed">{{ __('staff.none_yet_hint') }}</p>

        @can('create', App\Models\Staff::class)
          <a href="{{ \App\Support\StaffSection::route('create') }}"
             class="inline-flex items-center gap-1.5 h-9 px-3.5 mt-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('staff.add') }}
          </a>
        @endcan
      </div>
    @else

    {{-- Toolbar: search and the filters on one line where there is room,
         wrapping before they shrink into unreadability. The same arrangement
         as the clients listing, down to the reasons. --}}
    <form method="GET" action="{{ \App\Support\StaffSection::route('index') }}" class="mt-4">
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
                 aria-label="{{ __('staff.search_label') }}"
                 placeholder="{{ __('staff.search_placeholder') }}">
        </div>

        {{-- The filters move as one group: wrapped one at a time they would
             leave a single dropdown stranded on a line of its own.

             They wrap rather than scroll on purpose — each control opens a
             panel positioned inside itself, and a container with overflow set
             would cut those panels off at its edge. --}}
        <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
          <x-combo name="status" :options="App\Support\StaffOptions::statuses()" :selected="$filters['status']"
                   :placeholder="__('staff.filters.all_statuses')"
                   class="w-full lg:w-[150px] shrink-0" />

          <x-combo name="location" :options="$locations->pluck('name', 'id')" :selected="$filters['location']"
                   :placeholder="__('staff.filters.all_locations')"
                   class="w-full lg:w-[170px] shrink-0" />

          <x-combo name="role" :options="$roles->mapWithKeys(fn ($r) => [$r->key => $r->label()])"
                   :selected="$filters['role']"
                   :placeholder="__('staff.filters.all_roles')"
                   class="w-full lg:w-[150px] shrink-0" />

          <x-combo name="service" :options="$services->pluck('name', 'id')" :selected="$filters['service']"
                   :placeholder="__('staff.filters.all_services')"
                   class="w-full lg:w-[170px] shrink-0" />

          {{-- Full width below the desktop breakpoint, like every control
               above it: a button half the width of the field it acts on is a
               smaller target than the fields themselves. --}}
          <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0">
            {{ __('common.search') }}
          </button>
        </div>
      </div>

      {{-- The names behind the ids, so a chip can be labelled without asking
           the server again. --}}
      <span hidden data-filter-labels="status" data-labels='@json(App\Support\StaffOptions::statuses())'></span>
      <span hidden data-filter-labels="location" data-labels='@json($locations->pluck('name', 'id'))'></span>
      <span hidden data-filter-labels="role" data-labels='@json($roles->mapWithKeys(fn ($r) => [$r->key => $r->label()]))'></span>
      <span hidden data-filter-labels="service" data-labels='@json($services->pluck('name', 'id'))'></span>

      {{-- Active filters. Hidden entirely when nothing is chosen rather than
           left as an empty band: a row that is sometimes blank is a row the
           reader has to check. Built by the shared script from what the
           controls hold, so it cannot disagree with them. --}}
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
            'view' => __('staff.view'),
            'edit' => __('staff.edit'),
            'actions_for' => __('staff.actions_for', ['name' => ':name']),
            'showing' => __('staff.showing'),
            'results' => [
                'zero' => __('staff.results.zero'),
                'one' => __('staff.results.one'),
                'many' => __('staff.results.many'),
            ],
            'clear_filters' => __('staff.results.clear'),
            'empty' => __('staff.results.empty'),
        ];

        /* The columns, in the order they may leave as the table narrows:
           responsive 0 never goes, and the ones that tell one person from
           another are the last out. */
        $gridConfig = [
            'labels' => $gridLabels,
            'name_field' => 'name',
            'columns' => [
                ['field' => 'name', 'title' => __('staff.columns.name'), 'type' => 'primary', 'grow' => 3, 'min' => 200, 'responsive' => 0],
                ['field' => 'role', 'title' => __('staff.columns.role'), 'grow' => 1.6, 'min' => 140, 'responsive' => 2],
                /* Beside the role rather than anywhere else: the two are
                   read together — what somebody may do, and what they are
                   called — and the pair is what tells two Service Providers
                   apart. It leaves before the role does, because access is
                   the one the table narrows down to. */
                ['field' => 'job_title', 'title' => __('staff.columns.job_title'), 'grow' => 1.6, 'min' => 140, 'responsive' => 4],
                ['field' => 'location', 'title' => __('staff.columns.location'), 'grow' => 1.5, 'min' => 140, 'responsive' => 6],
                ['field' => 'phone', 'title' => __('staff.fields.phone'), 'grow' => 1.4, 'min' => 130, 'responsive' => 3],
                ['field' => 'email', 'title' => __('staff.profile.email'), 'grow' => 2, 'min' => 180, 'responsive' => 5],
                ['field' => 'services', 'title' => __('staff.columns.services'), 'width' => 90, 'responsive' => 7],
                ['field' => 'status', 'title' => __('staff.columns.status'), 'type' => 'badge', 'width' => 120, 'responsive' => 1],
                ['field' => 'actions', 'type' => 'actions'],
            ],
        ];
    @endphp

    {{-- How many the current search and filters return. Filled by the grid
         from the same response that drew the rows, so the two cannot
         disagree — a count worked out separately is a count that will
         eventually describe a different list. --}}
    <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

    {{-- The grid. Full width, filling the height left under the toolbar and
         scrolling inside itself, with rows fetched a page at a time. --}}
    <div class="mt-4 styledesk_gridframe">
      <div data-grid
           data-url="{{ \App\Support\StaffSection::route('data', array_filter($filters)) }}"
           data-config='@json($gridConfig)'></div>
    </div>
    @endif
  </main>
@endsection
