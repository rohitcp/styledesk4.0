@extends('layouts.app')

@section('title', __('resources.utilization.title'))

@section('content')
  {{-- The same header shape as the resources listing, because it is the same
       module read a different way. Everything below the header is one Vue
       island: the chips filter the bubbles in the browser without a round
       trip, and the bubbles, the table and the drill-down all read one list —
       three copies of "which resources are showing" is three that can
       disagree.

       The props are built above rather than inline: a directive argument with
       a comma inside brackets does not parse, because Blade counts brackets
       rather than reading PHP. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">
          {{ __('resources.utilization.title') }}
        </h1>
        <p class="text-[14px] font-semibold text-ink mt-1.5">{{ __('resources.utilization.subtitle') }}</p>
        {{-- No max-width: the sentence reads as one line, and the header has
             the whole content width to give it. It still wraps on a narrow
             screen, which is the only place it should. --}}
        <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ __('resources.utilization.intro') }}</p>
      </div>
    </header>

    @php
      $utilizationProps = [
          'urls' => [
              'data' => route('resources.utilization.data'),
              'detail' => route('resources.utilization.show', ['resource' => ':resource']),
              'rows' => route('resources.utilization.rows'),
              'resource' => route('resources.show', ['resource' => ':resource']),
          ],
          'resources' => $resources,
          'locations' => $locations->map(fn ($location) => ['id' => $location->id, 'name' => $location->name])->all(),
          /* The server's today. The picker works out "yesterday" and "last 7
             days" for itself, and it must do it from the same clock the
             server used or the two disagree — by a timezone in production,
             and by whatever the machine says on a developer's laptop. */
          'today' => now()->toDateString(),
          /* The figure the summary panel celebrates. Config rather than a
             number in the component: a business that runs at 90% wants a
             different bar from one that runs at 55%. */
          'target' => (int) config('resources.utilization_target', 75),
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
          'labels' => __('resources.utilization'),
      ];
    @endphp

    <div class="mt-5" data-vue-component="ResourceUtilization" data-props='@json($utilizationProps)'></div>

    {{-- The list, on the app's own listing grid.

         The same component the client, service and resource listings use, so
         this table pages, collapses its columns and reads exactly like those
         rather than like a table invented for one screen. The board above is
         a summary OF this list, never a replacement for it, so it is here by
         default rather than behind a button.

         The grid fetches its own rows. The filters live in the Vue island
         above — the date range, the branch, the category chips, the search
         and the status — and it re-points the grid at a new URL when any of
         them changes, through the `styledeskGrid` handle data-grid.js hangs
         on this element. That is the same handle the shared filter row uses
         on every other listing. --}}
    @php
        $gridLabels = [
            'columns' => __('resources.utilization.table'),
            'actions_for' => __('resources.actions_for', ['name' => ':name']),
            'showing' => __('resources.showing'),
            'results' => [
                'zero' => __('resources.results.zero'),
                'one' => __('resources.results.one'),
                'many' => __('resources.results.many'),
            ],
            'clear_filters' => __('resources.results.clear'),
            'empty' => __('resources.results.empty'),
            'no_matches' => __('resources.results.no_matches'),
            'no_matches_hint' => __('resources.results.no_matches_hint'),
            'clear_search' => __('resources.results.clear_search'),
        ];

        $gridConfig = [
            'labels' => $gridLabels,
            'columns' => [
                ['field' => 'name', 'title' => $gridLabels['columns']['resource'], 'type' => 'primary', 'grow' => 2.4, 'min' => 180, 'responsive' => 0],
                ['field' => 'category', 'title' => $gridLabels['columns']['category'], 'grow' => 1.6, 'min' => 140, 'responsive' => 4],
                ['field' => 'available', 'title' => $gridLabels['columns']['available'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 105, 'responsive' => 6, 'muted' => true],
                ['field' => 'used', 'title' => $gridLabels['columns']['used'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 95, 'responsive' => 5],
                ['field' => 'idle', 'title' => $gridLabels['columns']['idle'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 95, 'responsive' => 7, 'muted' => true],
                ['field' => 'utilization', 'title' => $gridLabels['columns']['utilization'], 'hozAlign' => 'right', 'grow' => 1, 'min' => 110, 'responsive' => 1],
                ['field' => 'bookings', 'title' => $gridLabels['columns']['bookings'], 'hozAlign' => 'right', 'grow' => 0.9, 'min' => 95, 'responsive' => 3],
                ['field' => 'revenue', 'title' => $gridLabels['columns']['revenue'], 'hozAlign' => 'right', 'grow' => 1.2, 'min' => 110, 'responsive' => 2],
                ['field' => 'per_hour', 'title' => $gridLabels['columns']['per_hour'], 'hozAlign' => 'right', 'grow' => 1.2, 'min' => 115, 'responsive' => 8, 'muted' => true],
                ['field' => 'status', 'title' => $gridLabels['columns']['status'], 'type' => 'badge', 'grow' => 1.4, 'min' => 130, 'responsive' => 1],
            ],
        ];
    @endphp

    <p class="mt-6 text-[12px] font-semibold text-sub" data-result-count></p>

    <div class="mt-3 styledesk_gridframe">
      <div data-grid
           data-url="{{ route('resources.utilization.rows', ['range' => $range]) }}"
           data-config='@json($gridConfig)'></div>
    </div>
  </main>
@endsection
