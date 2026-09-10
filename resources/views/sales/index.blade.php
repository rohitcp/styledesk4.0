{{--
    Sales — the module's landing page.

    Six figures and the transactions behind them. The figures are not
    decoration: Outstanding Balance filters the table to what is still owed,
    which is the question that brings most people to this screen.
--}}
@extends('layouts.app')

@section('title', __('sales.title'))

@section('content')
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('sales.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ __('sales.intro') }}</p>
      </div>

      <form method="GET" action="{{ route('sales.index') }}" class="shrink-0 flex flex-wrap items-end gap-2.5">
        <div>
          <label for="period" class="block text-[12px] font-medium text-ink mb-1.5">{{ __('sales.period') }}</label>
          <select id="period" name="period" class="sd-input" data-period>
            @foreach (\App\Support\SalesPeriod::SALES as $preset)
              <option value="{{ $preset }}" @selected($period->preset === $preset)>
                {{ __('sales.periods.'.$preset) }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- What kind of thing was sold. A booking and a membership are both
             transactions and both belong in the list; this is for a reader
             reconciling one of them at a time. --}}
        <div>
          <label for="type" class="block text-[12px] font-medium text-ink mb-1.5">{{ __('sales.type') }}</label>
          <select id="type" name="type" class="sd-input">
            <option value="">{{ __('sales.types.all') }}</option>
            @foreach (['service', 'membership'] as $kind)
              <option value="{{ $kind }}" @selected($filters['type'] === $kind)>{{ __('sales.types.'.$kind) }}</option>
            @endforeach
          </select>
        </div>

        {{-- Only where it means something. Two date boxes beside "Today" are
             two controls that do nothing. --}}
        <div data-custom-range @class(['flex items-end gap-2.5', 'hidden' => $period->preset !== 'custom'])>
          <div>
            <label for="from" class="block text-[12px] font-medium text-ink mb-1.5">{{ __('sales.from') }}</label>
            <input id="from" name="from" type="date" class="sd-input" value="{{ $period->from->toDateString() }}">
          </div>
          <div>
            <label for="to" class="block text-[12px] font-medium text-ink mb-1.5">{{ __('sales.to') }}</label>
            <input id="to" name="to" type="date" class="sd-input" value="{{ $period->to->toDateString() }}">
          </div>
        </div>

        <button type="submit" class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          {{ __('sales.apply') }}
        </button>
      </form>
    </header>

    {{-- ---------------------------------------------------------- widgets --}}
    @php
      $cards = [
          ['key' => 'total_sales', 'tone' => 'blue', 'filter' => null],
          ['key' => 'collected', 'tone' => 'green', 'filter' => null],
          ['key' => 'outstanding', 'tone' => 'amber', 'filter' => 'balance'],
          ['key' => 'refunds', 'tone' => 'violet', 'filter' => null],
          ['key' => 'tips', 'tone' => 'green', 'filter' => null],
          ['key' => 'transactions', 'tone' => 'blue', 'filter' => null],
      ];
      $currency = \App\Support\Currencies::resolve();
    @endphp

    <div class="mt-4 grid grid-cols-2 lg:grid-cols-6 gap-3">
      @foreach ($cards as $card)
        @php
          $figure = $summary[$card['key']];
          $isCount = array_key_exists('count', $figure);
          $value = $isCount
              ? number_format($figure['count'])
              : \App\Support\Money::format($figure['value'] / 100, $currency);
        @endphp

        {{-- Outstanding Balance is a filter as well as a figure — it is the
             one number people click. The rest are read, so they are not
             dressed up as buttons. --}}
        <x-dynamic-component :component="$card['filter'] ? 'sales.stat-link' : 'sales.stat'"
                             :label="__('sales.widgets.'.$card['key'])"
                             :value="$value"
                             :note="__('sales.notes.'.$card['key'])"
                             :change="$figure['change'] ?? null"
                             :href="$card['filter'] ? route('sales.index', $period->toQuery() + ['balance' => 1]) : null" />
      @endforeach
    </div>

    {{-- Said once, because the two halves are counted differently and a
         reader who does not know that will find the totals do not reconcile. --}}
    <p class="mt-2 text-[12px] text-faint">{{ __('sales.counting_note') }}</p>

    {{-- ------------------------------------------------------ transactions --}}
    <h2 class="styledesk_heading mt-6">{{ __('sales.transactions') }}</h2>

    @php
      $gridFilters = $period->toQuery() + array_filter([
          'search' => $filters['search'],
          'location' => $filters['location'],
          'staff' => $filters['staff'],
          'service' => $filters['service'],
          'method' => $filters['method'],
          'status' => $filters['status'],
          'type' => $filters['type'],
          'balance' => $filters['balance'] ? 1 : null,
      ]);

      $gridConfig = [
          'labels' => [
              'search_placeholder' => __('sales.search_placeholder'),
              'actions_for' => __('sales.actions_for', ['name' => ':name']),
              'showing' => __('sales.showing'),
              'results' => [
                  'zero' => __('sales.results.zero'),
                  'one' => __('sales.results.one'),
                  'many' => __('sales.results.many'),
              ],
              'clear_filters' => __('sales.results.clear'),
              'empty' => __('sales.empty'),
          ],
          'name_field' => 'reference',
          'page_size' => 25,
          'columns' => [
              ['field' => 'reference', 'title' => __('sales.columns.reference'), 'type' => 'primary', 'grow' => 1.6, 'min' => 190, 'responsive' => 0],
              ['field' => 'at', 'title' => __('sales.columns.at'), 'width' => 160, 'responsive' => 3],
              ['field' => 'booking', 'title' => __('sales.columns.booking'), 'width' => 150, 'responsive' => 5],
              ['field' => 'client', 'title' => __('sales.columns.client'), 'grow' => 1.2, 'min' => 140, 'responsive' => 1],
              /* Which kind of sale this row is. Beside the client rather than
                 at the end: it changes how every column to its right reads —
                 a membership has no staff member and no appointment. */
              ['field' => 'type', 'title' => __('sales.type'), 'width' => 120, 'responsive' => 6],
              ['field' => 'services', 'title' => __('sales.columns.services'), 'grow' => 1.4, 'min' => 150, 'responsive' => 8],
              ['field' => 'staff', 'title' => __('sales.columns.staff'), 'width' => 140, 'responsive' => 7],
              ['field' => 'location', 'title' => __('sales.columns.location'), 'width' => 130, 'responsive' => 9],
              ['field' => 'total', 'title' => __('sales.columns.total'), 'width' => 110, 'responsive' => 6],
              ['field' => 'amount', 'title' => __('sales.columns.amount'), 'width' => 110, 'responsive' => 2],
              ['field' => 'balance', 'title' => __('sales.columns.balance'), 'width' => 110, 'responsive' => 4],
              ['field' => 'method', 'title' => __('sales.columns.method'), 'width' => 130, 'responsive' => 10],
              ['field' => 'status', 'title' => __('sales.columns.status'), 'type' => 'badge', 'width' => 130, 'responsive' => 0],
              ['type' => 'actions'],
          ],
      ];
    @endphp

    <div class="mt-2 styledesk_gridframe">
      <div data-grid
           data-url="{{ route('sales.data', $gridFilters) }}"
           data-config='@json($gridConfig)'></div>
    </div>

    {{-- The booking, over the table rather than instead of it.
         The same panel the client profile and the leads listing open, filled
         from the same endpoint: a bookkeeper checking four transactions in a
         row should not lose the date range, the filters and their place in
         the table each time. --}}
    {{-- The same keys the client profile passes, from the same places.
         Arr::only drops a key it cannot find without complaining, so a label
         taken from the wrong file arrives as undefined and the panel prints
         it — the section headings and the footer button both come from
         leads.drawer and bookings.detail, not from one of them. --}}
    @php
        $sheetLabels = [
            'not_selected' => __('leads.drawer.not_selected'),
            'summary' => __('leads.drawer.summary'),
            'client' => __('leads.drawer.client'),
            'booking' => __('leads.drawer.booking'),
            'payment' => __('leads.drawer.payment'),
            'transactions' => __('bookings.detail.transactions'),
            'view_full' => __('clients.module.workspace.bookings.view_full'),
            'complete' => __('leads.actions.complete'),
            'view_client' => __('sales.drawer.view_client'),
            'view_receipt' => __('sales.drawer.view_receipt'),
            'download' => __('sales.drawer.download'),
        ];
    @endphp

    <div class="styledesk_sheet" data-booking-sheet data-labels='@json($sheetLabels)' hidden>
      <aside class="styledesk_sheet__panel" role="dialog" aria-modal="true" aria-labelledby="salesSheetName">
        <header class="styledesk_sheet__head">
          <div class="min-w-0">
            <p id="salesSheetName" class="text-[15px] font-bold text-head truncate" data-sheet-name></p>
            <p class="text-[12px] font-medium text-sub font-mono" data-sheet-reference></p>
          </div>

          <div class="ml-auto shrink-0 text-right">
            <span class="styledesk_badge" data-sheet-status></span>
            <span class="block text-[11.5px] text-sub mt-1" data-sheet-step></span>
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

          {{-- Only a receipt has something to keep, so this is hidden until
               the panel is showing one. It opens the receipt page ready to
               print, which is what every browser offers as "Save as PDF". --}}
          <a class="styledesk_action justify-center" data-sheet-download
             target="_blank" rel="noopener noreferrer" hidden></a>
        </footer>
      </aside>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* The custom date boxes appear only for the custom range — two date
       fields beside "Today" are two controls that do nothing. */
    (function () {
      const period = document.querySelector('[data-period]');
      const custom = document.querySelector('[data-custom-range]');

      period?.addEventListener('change', function () {
        custom?.classList.toggle('hidden', period.value !== 'custom');
      });
    }());
  </script>
@endpush
