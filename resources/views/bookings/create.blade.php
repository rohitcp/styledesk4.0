@extends('layouts.app')

@section('title', __('bookings.new'))

@section('content')
  {{-- The same full-width container the listings use. The screen is three
       columns of decisions, and a centred column would put the summary — the
       thing being committed to — below the fold. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    <div class="flex flex-wrap items-start gap-x-4 gap-y-3 mb-4">
      <div class="min-w-0">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('bookings.new') }}</h1>
        <p class="text-[13px] text-sub mt-1.5">
          {{ $walkIn ? __('bookings.walk_in_intro') : __('bookings.new_intro') }}
        </p>
      </div>
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
            /* Where a started booking is noted down, so an abandoned call
               leaves a reference behind rather than nothing. */
            'leadUrl' => route('bookings.leads.store'),
            /* The lead being returned to, where the screen was opened from
               one. Null on an ordinary new booking. */
            'lead' => $lead,
            /* Whoever this booking is being taken for, where the screen was
               opened from their profile. */
            'client' => $client,
            'walkIn' => $walkIn,
            'services' => $services,
            'categories' => $categories,
            'staff' => $staff,
            'locations' => $locations->map(fn ($location) => ['id' => $location->id, 'name' => $location->name]),
            'sources' => collect(config('bookings.sources'))
                ->mapWithKeys(fn (string $key) => [$key => __('bookings.sources.'.$key)]),
            'times' => $slots,
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
                'any_staff',
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
