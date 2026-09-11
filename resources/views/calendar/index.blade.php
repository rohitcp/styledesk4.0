{{--
    The calendar — the diary drawn against the clock.

    Everything on this page changes one thing: which day, whose, and where.
    So the page itself is a shell and the day is a Vue island that refetches,
    rather than a form that reloads — a receptionist moving a day forward with
    somebody on hold must not lose their place.
--}}
@extends('layouts.app')

@section('title', __('calendar.title'))

@section('content')
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    @php
      $props = [
        'dataUrl' => route('calendar.data'),
        'createUrl' => route('bookings.create'),
        'date' => $date,
        'view' => $view,
        'locationId' => $location?->id,
        'staffId' => $staffId,
        'interval' => $interval,
        'lockedToOwnStaff' => $lockedToOwnStaff,
        'resourceId' => $resourceId,
        'locations' => $locations->map(fn ($place) => ['id' => $place->id, 'name' => $place->name])->values(),
        'staffOptions' => $staffOptions
            ->map(fn ($member) => ['id' => $member->id, 'name' => $member->displayName()])
            ->values(),
        'resourceOptions' => $resourceOptions->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values(),
        'serviceOptions' => $serviceOptions->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values(),
        'serviceId' => $serviceId,
        'currencySymbol' => \App\Support\Money::symbol(\App\Support\Currencies::resolve()),
        /* The app's own calendar picker, not the browser's: the native
           control renders differently in every browser and ignores the
           field styling around it. Same options every other date field on
           the app is given. */
        'datePicker' => \App\Support\DatePickerOptions::build([
            'dialogLabel' => __('calendar.nav.pick'),
            'clearable' => false,
        ]),
        /* Only the copy this island uses. The whole file would put every
           validation string and toast into the page's HTML. */
        'labels' => __('calendar'),
      ];
    @endphp

    <div data-vue-component="CalendarBoard" data-props='@json($props)'></div>

    {{-- The booking drawer, in the same panel the client profile, the leads
         listing and the Sales table open. One renderer for one payload: three
         would be three chances to describe the same appointment differently. --}}
    @php
      $sheetLabels = [
        'close' => __('leads.drawer.close'),
        'not_selected' => __('leads.drawer.not_selected'),
        'transactions' => __('bookings.detail.transactions'),
        'view_full' => __('clients.module.workspace.bookings.view_full'),
      ];
    @endphp

    <div class="styledesk_sheet" data-booking-sheet data-labels='@json($sheetLabels)' hidden>
      <aside class="styledesk_sheet__panel" role="dialog" aria-modal="true" aria-labelledby="calendarSheetName">
        <header class="styledesk_sheet__head">
          <div class="min-w-0">
            <p id="calendarSheetName" class="text-[15px] font-bold text-head truncate" data-sheet-name></p>
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
        </footer>
      </aside>
    </div>
  </main>
@endsection
