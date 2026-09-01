@extends('layouts.app')

@section('title', __('bookings.new'))

@section('content')
  {{-- The same full-width container the listings use. The screen is three
       columns of decisions, and a centred column would put the summary — the
       thing being committed to — below the fold. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    {{-- Hidden by the island once the booking is taken: "New Booking" over a
         finished confirmation is the page arguing with itself, and the point
         of that screen is that there is nothing left to do. --}}
    {{-- The way back. Where they came from rather than a fixed page: a
         booking started from somebody's profile returns to that profile, and
         one started from the diary returns to the diary. Anything else sends
         the reader somewhere they have to navigate out of. --}}
    @php
        $backUrl = $client
            ? route('clients.show', $client['id'])
            : ($lead ? route('bookings.leads') : route('bookings.index'));
    @endphp

    <div id="bookingFormHeader" class="flex flex-wrap items-start gap-x-4 gap-y-3 mb-4">
      <div class="min-w-0">
        <a href="{{ $backUrl }}"
           class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-sub hover:text-ink transition-colors mb-1.5">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          {{ __('common.back') }}
        </a>

        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('bookings.new') }}</h1>
        <p class="text-[13px] text-sub mt-1.5">
          {{ $walkIn ? __('bookings.walk_in_intro') : __('bookings.new_intro') }}
        </p>
      </div>

      {{-- The reference the auto-save handed out, and whether the last
           change is in yet. Subtle and beside the title, because it is not
           the reader's job — it is the number they reach for when the call
           drops. Filled by the Vue island once a draft exists. --}}
      <div id="bookingRefSlot" class="w-full sm:w-auto sm:ml-auto"></div>

      {{-- Where the booking is being taken, in the header rather than four
           scrolls down inside the time step.

           It decides the hours, the rota, the services and the chairs, so it
           belongs where the reader can see it without going looking — and a
           booking taken against the wrong branch is not a mistake anybody
           notices from a dropdown they never opened.

           An empty slot filled by the Vue island: the title is server
           rendered and the card has to change the moment the branch does, so
           the island teleports it here rather than the page keeping a second
           copy of which location is chosen.

           Full width below sm, where a card beside a two-line title leaves
           neither enough room. --}}
      <div id="bookingLocationSlot" class="w-full sm:w-auto"></div>
    </div>

    @if ($errors->any())
      <div class="sd-alert sd-alert--danger mb-4" role="alert">
        <p class="font-semibold">{{ $errors->first() }}</p>
      </div>
    @endif

    @php
        /* The times a booking can start on. Built here rather than in the
           browser: the step comes from the business's own appointment
           interval, and a browser guessing fifteen minutes would offer slots
           a salon on twenties never uses. */
        $interval = max(5, (int) ($tenantInterval ?? 15));
        $slots = [];

        for ($minutes = 6 * 60; $minutes <= 21 * 60; $minutes += $interval) {
            $slots[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        $props = [
            'action' => route('bookings.store'),
            'csrf' => csrf_token(),
            'cancelUrl' => route('bookings.index'),
            'clientSearchUrl' => route('bookings.clients'),
            /* The id is put in by the browser once a client is chosen; the
               route is built here so the URL is never assembled by hand. */
            'clientContextUrl' => route('bookings.clients.context', ['client' => ':id']),
            'newClientUrl' => route('clients.create'),
            'createClientUrl' => route('bookings.clients.store'),
            /* Whether the walk-in being typed is already on the book. Posted,
               not asked in the query string: a number and an address in a URL
               is personal data written into every access log on the way. */
            'matchClientUrl' => route('bookings.clients.match'),
            /* Where the screen saves itself as it is filled in. It writes a
               booking lead, so a call that drops leaves a draft with a
               reference behind it under Bookings → Leads. */
            'autosaveUrl' => route('bookings.draft'),
            /* The lead being returned to, where the screen was opened from
               one. Null on an ordinary new booking. */
            'lead' => $lead,
            /* Whoever this booking is being taken for, where the screen was
               opened from their profile. */
            'client' => $client,
            'walkIn' => $walkIn,
            /* Whether this reader may let a booking off its payment. Taking
               an appointment and waiving what it costs are not the same
               authority, so they are not the same permission. */
            'canWaive' => auth()->user()->hasPermission('payments.apply_discount', 'own'),
            'services' => $services,
            'categories' => $categories,
            'staff' => $staff,
            'locations' => $locations->map(fn ($location) => ['id' => $location->id, 'name' => $location->name]),
            'sources' => collect(config('bookings.sources'))
                ->mapWithKeys(fn (string $key) => [$key => __('bookings.sources.'.$key)]),
            'times' => $slots,
            /* Which of those times could actually be booked, asked again as
               the location, services, staff member or date change. The list
               above is only what the page opens with. */
            'availabilityUrl' => route('bookings.availability'),
            'resourcesUrl' => route('bookings.resources'),
            'quoteUrl' => route('bookings.quote'),
            'today' => now()->toDateString(),
            /* The business's own clock. Times are chosen and posted as 24-hour
               "H:i" whatever this says — it decides only what a person reads,
               and a screen showing 14:15 beside a summary saying 2:15 PM is a
               fault the reader is right to notice. */
            'use12Hours' => \App\Support\TimeFormat::use12Hours(),
            'currencySymbol' => \App\Support\Money::symbol($currency),
            /* The third column's workflow: what it may ask for, and where it
               posts. The booking's own URLs come back with the booking, so
               only the two that exist before it does are passed here. */
            'methods' => $methods,
            'tax' => $tax,
            'updateUrlPattern' => route('bookings.update', ['booking' => ':id']),
            /* Only the copy this screen uses. The whole file would put the
               validation strings and the toasts in the page's HTML. */
            'labels' => \Illuminate\Support\Arr::only(__('bookings'), [
                'sections', 'client', 'service', 'when', 'details', 'payment',
                'comms', 'summary', 'blockers', 'confirm', 'draft', 'cancel', 'context',
                'new_client', 'pay', 'methods', 'payment_statuses', 'confirmation', 'steps', 'lead',
                'any_staff', 'autosave', 'resources',
            ]) + ['summary' => __('bookings.summary') + ['minutes_short' => __('bookings.service.minutes')]],
        ];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
      <div data-vue-component="BookingBuilder" data-props='@json($props)' class="contents"></div>
    </div>

    {{-- The builder is a Vue island; without it the screen would be blank
         rather than degraded, so the way on is stated in plain HTML. --}}
    <noscript>
      <p class="text-[13px] text-sub">{{ __('schedule.needs_javascript') }}</p>
    </noscript>
  </main>
@endsection
