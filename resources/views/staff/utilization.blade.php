@extends('layouts.app')

@section('title', __('staff.utilization.title'))

@section('content')
  {{-- The same header shape as the staff listing, because it is the same
       module read a different way. Everything below it is one Vue island: the
       bubbles, the team's day and the drill-down all read one list, and three
       copies of "how busy is she" is three that can disagree.

       The props are built above rather than inline: a directive argument with
       a comma inside brackets does not parse, because Blade counts brackets
       rather than reading PHP. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">
          {{ __('staff.utilization.title') }}
        </h1>
        <p class="text-[14px] font-semibold text-ink mt-1.5">{{ __('staff.utilization.subtitle') }}</p>
        <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ __('staff.utilization.intro') }}</p>
      </div>
    </header>

    @php
      $staffUtilizationProps = [
          'urls' => [
              'data' => route('staff.utilization.data'),
              'rows' => route('staff.utilization.rows'),
              'detail' => route('staff.utilization.show', ['staff' => ':staff']),
          ],
          'staff' => $staff,
          'day' => $day,
          'locations' => $locations->map(fn ($location) => ['id' => $location->id, 'name' => $location->name])->all(),
          'roles' => $roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->all(),
          /* The server's today. The picker works out "tomorrow" and "this
             week" for itself, and it must do it from the same clock the
             server used or the two disagree — by a timezone in production,
             and by whatever the machine says on a developer's laptop. */
          'today' => now()->toDateString(),
          /* The figure the business is aiming at. Config rather than a number
             in the component: a spa running four ninety-minute treatments a
             day and a barber running twenty cuts do not have the same healthy
             figure. */
          'target' => $target,
          'presets' => collect($presets)
              ->mapWithKeys(fn (string $preset) => [$preset => __('sales.periods.'.$preset)])
              ->all(),
          'filters' => [
              'range' => $range,
              'from' => $from,
              'until' => $until,
              'location' => $locationId,
          ],
          /* The business's own symbol, resolved on the server: a browser
             formatting money itself would eventually say it differently from
             the page around it. */
          'currency' => \App\Support\Money::symbol(),
          'canSeeRevenue' => $canSeeRevenue,
          'scheduleUrl' => $scheduleUrl,
          'labels' => __('staff.utilization'),
      ];
    @endphp

    <div class="mt-5" data-vue-component="StaffUtilization" data-props='@json($staffUtilizationProps)'></div>

    {{-- The list, on the app's own listing grid.

         The same component the staff, client and service listings use, so this
         table pages, collapses its columns and reads exactly like those. The
         board above is a summary OF this list, never a replacement for it, so
         it is here by default rather than behind a button.

         The grid fetches its own rows, and the filters live in the island
         above — which re-points it at a new URL whenever any of them moves,
         through the `styledeskGrid` handle data-grid.js hangs on this
         element. --}}
    @php
        $gridLabels = [
            'columns' => __('staff.utilization.table'),
            'actions_for' => __('staff.actions_for', ['name' => ':name']),
            'showing' => __('staff.showing'),
            'results' => __('staff.results'),
            'clear_filters' => __('staff.utilization.clear'),
            'empty' => __('staff.utilization.empty'),
            'no_matches' => __('staff.utilization.no_matches'),
            'no_matches_hint' => __('staff.utilization.empty_hint'),
            'clear_search' => __('staff.utilization.clear'),
        ];

        $columns = [
            ['field' => 'name', 'title' => $gridLabels['columns']['staff'], 'type' => 'primary', 'grow' => 2.4, 'min' => 180, 'responsive' => 0],
            ['field' => 'location', 'title' => $gridLabels['columns']['location'], 'grow' => 1.5, 'min' => 130, 'responsive' => 6],
            ['field' => 'scheduled', 'title' => $gridLabels['columns']['scheduled'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 100, 'responsive' => 7, 'muted' => true],
            ['field' => 'bookable', 'title' => $gridLabels['columns']['bookable'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 100, 'responsive' => 5],
            ['field' => 'booked', 'title' => $gridLabels['columns']['booked'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 95, 'responsive' => 4],
            ['field' => 'idle', 'title' => $gridLabels['columns']['idle'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 95, 'responsive' => 8, 'muted' => true],
            ['field' => 'bookings', 'title' => $gridLabels['columns']['bookings'], 'hozAlign' => 'right', 'grow' => 0.9, 'min' => 95, 'responsive' => 3],
            ['field' => 'utilization', 'title' => $gridLabels['columns']['utilization'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 110, 'responsive' => 1],
            ['field' => 'target', 'title' => $gridLabels['columns']['target'], 'hozAlign' => 'right', 'grow' => 0.9, 'min' => 90, 'responsive' => 9, 'muted' => true],
            ['field' => 'variance', 'title' => $gridLabels['columns']['variance'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 100, 'responsive' => 2],
        ];

        /* Money is a second authority: the column is absent rather than empty
           for somebody who may not see what the team earned. */
        if ($canSeeRevenue) {
            $columns[] = ['field' => 'revenue', 'title' => $gridLabels['columns']['revenue'], 'hozAlign' => 'right', 'grow' => 1.2, 'min' => 110, 'responsive' => 2];
        }

        $columns[] = ['field' => 'status', 'title' => $gridLabels['columns']['status'], 'type' => 'badge', 'grow' => 1.6, 'min' => 140, 'responsive' => 1];

        $gridConfig = ['labels' => $gridLabels, 'columns' => $columns];
    @endphp

    <p class="mt-6 text-[12px] font-semibold text-sub" data-result-count></p>

    <div class="mt-3 styledesk_gridframe">
      <div data-grid
           data-url="{{ route('staff.utilization.rows', ['range' => $range]) }}"
           data-config='@json($gridConfig)'></div>
    </div>
  </main>
@endsection
