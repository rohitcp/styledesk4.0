@extends('layouts.app')

@section('title', __('staff_schedules.title'))

@section('content')
  {{-- The same full-width container the staff directory and the clients
       listing use. The board is the point of this screen, and a twelve-column
       matrix inside a narrow column would scroll sideways from the first
       month. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('staff_schedules.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ __('staff_schedules.intro') }}</p>
      </div>

      <div class="shrink-0 flex flex-wrap items-center gap-2">
        {{-- A shift is a named date, not a pattern: it belongs to the shift
             settings screen, and sending the reader there is more honest than
             a second form that writes the same row. --}}
        <a href="{{ route('settings.shift-rules.index') }}" class="styledesk_action">
          <x-icon name="plus" size="13" />
          {{ __('staff_schedules.add_shift') }}
        </a>

        @can('create', App\Models\Staff::class)
          <button type="button"
                  class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                  data-assign-toggle aria-expanded="false" aria-controls="assignSchedulePanel">
            {{ __('staff_schedules.assign') }}
          </button>
        @endcan
      </div>
    </header>

    {{-- Assigning from here needs a person and a month before the flow that
         already exists can take over, so that is all this asks. A dialog
         rather than a panel: the board underneath is a wide table, and a form
         opening inside it would push the months off the screen. --}}
    <div id="assignSchedulePanel" class="styledesk_modal" hidden>
      <div class="styledesk_modal__scrim" data-assign-cancel></div>

      <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="assignScheduleTitle">
        <div class="styledesk_modal__head">
          <h2 id="assignScheduleTitle" class="text-[15px] font-semibold text-head">
            {{ __('staff_schedules.start.title') }}
          </h2>

          <button type="button" class="styledesk_modal__close" data-assign-cancel
                  aria-label="{{ __('common.cancel') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <form method="GET" action="{{ route('staff.schedules.start') }}">
          <div class="styledesk_modal__body">
            <p class="text-[13px] text-sub leading-relaxed">{{ __('staff_schedules.start.intro') }}</p>

            <div class="mt-3">
              <x-combo name="staff" :label="__('staff_schedules.start.staff')"
                       :options="$staffOptions"
                       :placeholder="__('staff_schedules.start.choose_staff')" />
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
              <x-combo name="month" :label="__('staff_schedules.filters.month')"
                       :options="collect(range(1, 12))->mapWithKeys(fn (int $m) => [
                           $m => \Carbon\CarbonImmutable::create($filters['year'], $m, 1)->translatedFormat('F'),
                       ])"
                       :selected="$filters['month']"
                       :placeholder="__('staff_schedules.filters.month')" />

              <x-combo name="year" :label="__('staff_schedules.filters.year')"
                       :options="collect($years)->mapWithKeys(fn (int $y) => [$y => (string) $y])"
                       :selected="$filters['year']"
                       :placeholder="(string) $filters['year']" />
            </div>
          </div>

          <div class="styledesk_modalfoot">
            <button type="submit"
                    class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('staff_schedules.start.continue') }}
            </button>
            <button type="button" class="styledesk_action" data-assign-cancel>{{ __('common.cancel') }}</button>
          </div>
        </form>
      </div>
    </div>

    {{-- A published month, read in place. Its rows are fetched when it is
         opened rather than drawn with the board: a month is thirty-one rows
         and a team is thirty months, and building all of them for the two
         somebody will look at is a page nobody waits for.

         Header and footer are fixed and only the days scroll, because the
         thing a reader loses in a long list is who and what they are looking
         at — see the modal CSS. --}}
    <div id="monthSchedule" class="styledesk_modal" hidden data-month-modal
         data-month-labels='@json(Illuminate\Support\Arr::only(__('staff_schedules.month'), ['loading', 'failed']))'>
      <div class="styledesk_modal__scrim" data-month-close></div>

      <div class="styledesk_modal__panel styledesk_monthmodal" role="dialog" aria-modal="true"
           aria-labelledby="monthScheduleTitle">
        <div class="styledesk_modal__head">
          <div class="min-w-0">
            <h2 id="monthScheduleTitle" class="text-[15px] font-semibold text-head truncate" data-month-title></h2>
            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-[12px] text-sub">
              <span class="styledesk_badge styledesk_badge--active" data-month-state></span>
              <span data-month-count></span>
            </p>
          </div>

          <button type="button" class="styledesk_modal__close" data-month-close
                  aria-label="{{ __('common.cancel') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <div class="styledesk_monthmodal__body styledesk_scroll" data-month-body>
          <table class="sd-table">
            <thead>
              <tr>
                <th scope="col">{{ __('staff_schedules.month.date') }}</th>
                <th scope="col">{{ __('staff_schedules.month.day') }}</th>
                <th scope="col">{{ __('staff_schedules.month.shift') }}</th>
                <th scope="col">{{ __('staff_schedules.month.start') }}</th>
                <th scope="col">{{ __('staff_schedules.month.end') }}</th>
                <th scope="col">{{ __('staff_schedules.month.break') }}</th>
                <th scope="col">{{ __('staff_schedules.month.hours') }}</th>
                <th scope="col">{{ __('staff_schedules.month.status') }}</th>
              </tr>
            </thead>
            <tbody data-month-rows></tbody>
          </table>
        </div>

        <div class="styledesk_modalfoot">
          <a href="#" class="h-9 px-4 inline-flex items-center rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
             data-month-edit>{{ __('staff_schedules.month.edit') }}</a>

          <button type="button" class="styledesk_action" data-month-close>{{ __('common.cancel') }}</button>

          {{-- Informational, not a barrier: the lock says this month has been
               sent, and the button beside it still opens for anyone allowed
               to change it. --}}
          <span class="styledesk_modalfoot__note inline-flex items-center gap-1.5 text-sub">
            <x-icon name="lock" size="13" />
            {{ __('staff_schedules.states.published') }}
          </span>
        </div>
      </div>
    </div>

    {{-- One row: the four filters on the left, the key hard right. They
         wrap rather than scroll, because each combo opens a panel inside
         itself and a container with overflow set would cut it off.

         No search box. A team fits on one screen and every name is already
         in the first column — a search that filters a list you can see is a
         control that costs more than it saves. --}}
    <form method="GET" action="{{ route('staff.schedules') }}" class="mt-4" data-board-filters>
      <div class="flex flex-wrap items-center gap-2">
        <x-combo name="month"
                 :options="collect(range(1, 12))->mapWithKeys(fn (int $m) => [
                     $m => \Carbon\CarbonImmutable::create($filters['year'], $m, 1)->translatedFormat('F'),
                 ])"
                 :selected="$filters['month']"
                 :placeholder="__('staff_schedules.filters.month')"
                 :aria-label="__('staff_schedules.filters.month')"
                 class="w-full sm:w-[150px] shrink-0" />

        <x-combo name="year"
                 :options="collect($years)->mapWithKeys(fn (int $y) => [$y => (string) $y])"
                 :selected="$filters['year']"
                 :placeholder="__('staff_schedules.filters.year')"
                 :aria-label="__('staff_schedules.filters.year')"
                 class="w-full sm:w-[120px] shrink-0" />

        <x-combo name="status"
                 :options="collect(App\Http\Controllers\StaffScheduleBoardController::statuses())
                     ->mapWithKeys(fn (string $key) => [$key => __('staff_schedules.statuses.'.$key)])"
                 :selected="$filters['status']"
                 :placeholder="__('staff_schedules.filters.all_statuses')"
                 :aria-label="__('staff_schedules.filters.status')"
                 class="w-full sm:w-[170px] shrink-0" />

        <x-combo name="coverage"
                 :options="collect(App\Http\Controllers\StaffScheduleBoardController::coverages())
                     ->mapWithKeys(fn (string $key) => [$key => __('staff_schedules.coverage.'.$key)])"
                 :selected="$filters['coverage']"
                 :placeholder="__('staff_schedules.filters.all_coverage')"
                 :aria-label="__('staff_schedules.filters.coverage')"
                 class="w-full sm:w-[180px] shrink-0" />

        @if ($filters['status'] !== '' || $filters['coverage'] !== ''
             || $filters['month'] !== now()->month || $filters['year'] !== now()->year)
          <a href="{{ route('staff.schedules') }}" class="styledesk_action shrink-0">
            {{ __('staff_schedules.filters.reset') }}
          </a>
        @endif

        {{-- Applied the moment a choice is made — see the filter handler in
             resources/js/schedule-board.js. A four-control row with a fifth
             button that only means "yes, really" is a row with one control
             too many. The button stays for a browser running no script,
             hidden from everyone else. --}}
        <button type="submit" class="styledesk_search shrink-0" data-board-apply>
          {{ __('staff_schedules.filters.apply') }}
        </button>

        {{-- The key, on the same line and hard right: three colours nobody
             has been taught yet is three colours nobody can read. --}}
        <div class="w-full lg:w-auto lg:ml-auto flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12px] text-sub">
          <span class="font-semibold text-ink">{{ __('staff_schedules.legend.title') }}</span>
          <span class="inline-flex items-center gap-1.5">
            <span class="styledesk_monthkey styledesk_monthcell--published" aria-hidden="true"></span>
            {{ __('staff_schedules.states.published') }}
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="styledesk_monthkey styledesk_monthcell--draft" aria-hidden="true"></span>
            {{ __('staff_schedules.states.draft') }}
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="styledesk_monthkey styledesk_monthcell--none" aria-hidden="true"></span>
            {{ __('staff_schedules.states.not-scheduled') }}
          </span>
        </div>
      </div>
    </form>

    {{-- The period the board is showing, on a line of its own between the
         controls that set it and the grid that answers it. Louder than the
         helper text under the title and quieter than the title itself: it is
         the answer to "which month am I looking at", which is the question a
         board of twelve columns raises every time it is opened. --}}
    <div class="mt-4 pt-4 border-t border-line flex flex-wrap items-baseline gap-x-3 gap-y-1">
      <p class="text-[16px] font-bold text-head tracking-tight">
        {{ __('staff_schedules.summary_line', [
            'month' => \Carbon\CarbonImmutable::create($filters['year'], $filters['month'], 1)->translatedFormat('M'),
            'year' => $filters['year'],
        ]) }}
      </p>
    </div>

    {{-- How many the current search and filters return. Filled by the grid
         from the same response that drew the rows, so the two cannot
         disagree — a count worked out separately is a count that will
         eventually describe a different list. --}}
    <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

    @php
        $gridLabels = [
            'actions_for' => __('staff_schedules.actions_for', ['name' => ':name']),
            'showing' => __('staff_schedules.showing'),
            'results' => [
                'zero' => __('staff_schedules.results.zero'),
                'one' => __('staff_schedules.results.one'),
                'many' => __('staff_schedules.results.many'),
            ],
            'clear_filters' => __('staff_schedules.results.clear'),
            'empty' => __('staff_schedules.empty'),
        ];

        /* One column per month, on the same shared grid every other listing
           in the app uses. The months are the only thing about this table
           that is unusual: the header, the row height, the hover, the pager,
           the empty state and the row menu all come from the grid.

           The staff column is frozen rather than sticky by hand — the grid
           owns the row heights, so it is the only place the pinned half and
           the scrolling half can be kept in step. */
        $gridColumns = [[
            'field' => 'name',
            'title' => __('staff_schedules.columns.staff'),
            'type' => 'primary',
            'frozen' => true,
            'width' => 260,
            'responsive' => 0,
        ]];

        foreach ($columns as $column) {
            $gridColumns[] = [
                'field' => App\Http\Controllers\StaffScheduleBoardController::field($column['key']),
                'title' => $column['label'],
                'type' => 'schedule',
                'width' => 132,
                'responsive' => $column['coverage'] === 'current' ? 0 : 1,
                'muted' => $column['coverage'] === 'completed',
                /* The month the reader is standing in, tinted from the header
                   down through every row so it can be found without counting
                   along the top. */
                'class' => $column['coverage'] === 'current' ? 'styledesk_board__current' : null,
            ];
        }

        $gridColumns[] = ['field' => 'actions', 'type' => 'actions'];

        /* The column the marker above the table points at. Named rather than
           counted: the coverage filter can drop the months in front of it. */
        $currentField = collect($columns)->firstWhere('coverage', 'current');

        $gridConfig = [
            'labels' => $gridLabels,
            'name_field' => 'name',
            'page_size' => 25,
            /* Every month keeps its width and the board scrolls sideways.
               Hiding March to make the table fit would be answering "who is
               covered in March" by removing the question. */
            'layout' => 'fitDataFill',
            'responsive' => false,
            'columns' => $gridColumns,
        ];
    @endphp

    {{-- The marker rides above the table rather than inside a header cell:
         the grid owns the header DOM and every cell in it is a fixed box, so
         anything above the month name has to sit outside the table. It is
         pointed at its column by script — see resources/js/schedule-board.js
         — and travels with it through a sideways scroll. --}}
    <div class="mt-2 styledesk_board" data-board-marker>
      <div class="styledesk_boardmarker">
        @if ($currentField)
          <span class="styledesk_board__now" data-board-now hidden>{{ __('staff_schedules.this_month') }}</span>
        @endif
      </div>


      <div class="styledesk_gridframe">
        <div data-grid
             @if ($currentField)
               data-month-field="{{ App\Http\Controllers\StaffScheduleBoardController::field($currentField['key']) }}"
             @endif
             data-url="{{ route('staff.schedules.data', array_filter($filters, fn ($value) => $value !== '' && $value !== null)) }}"
             data-config='@json($gridConfig)'></div>
      </div>

      {{-- The months' scrollbar, under the months and only the months. The
           one the table draws runs the full width, which puts a scrollbar
           under a column that does not scroll — see
           resources/js/schedule-board.js, which indents this one past the
           pinned column and keeps the two in step. --}}
      <div class="styledesk_boardscroll" data-board-scroll hidden aria-hidden="true"
           data-tip="{{ __('staff_schedules.scroll_hint') }}"><div></div></div>
    </div>

  </main>
@endsection
