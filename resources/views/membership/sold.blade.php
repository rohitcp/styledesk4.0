@extends('layouts.app')

@section('title', __('membership.sold.title'))

{{--
    Membership Activated.

    Its own page rather than the booking confirmation. What somebody wants to
    see after buying a membership is what they now hold — the credits, the
    next billing date, the benefits — and the booking confirmation answers
    "when should I turn up", which has no answer here.

    A membership dated forward says so plainly rather than claiming to be
    active: its credits do not exist until the start date arrives, and a page
    that said otherwise would have the desk promising a massage this week.
--}}

@php
    $scheduled = $membership->status() === 'scheduled';
    $startDate = $membership->starts_on->translatedFormat('j F Y');
@endphp

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[120px]">
    <div class="styledesk_form">

      <div class="sd-card p-6 text-center">
        <span class="mx-auto grid place-items-center h-12 w-12 rounded-full bg-brand/10 text-brand" aria-hidden="true">
          <x-icon name="check" size="22" />
        </span>

        <h1 class="text-[22px] sm:text-[26px] font-bold text-head tracking-tight mt-4">
          {{ $scheduled ? __('membership.sold.scheduled_title') : __('membership.sold.title') }}
        </h1>

        <p class="text-[13px] text-sub mt-2 max-w-[440px] mx-auto leading-relaxed">
          {{ $scheduled
              ? __('membership.sold.scheduled_intro', ['client' => $membership->client->displayName(), 'date' => $startDate])
              : __('membership.sold.intro', ['client' => $membership->client->displayName()]) }}
        </p>
      </div>

      <div class="mt-4 grid gap-4 lg:grid-cols-2">

        {{-- --------------------------------------------------- what it is --}}
        <section class="sd-card p-5">
          <dl class="space-y-3 text-[13px]">
            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.sold.client') }}</dt>
              <dd class="font-semibold text-head text-right">{{ $membership->client->displayName() }}</dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.sold.membership') }}</dt>
              <dd class="font-semibold text-head text-right">{{ $membership->plan->name }}</dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.sold.type') }}</dt>
              <dd class="font-semibold text-head text-right">{{ __('membership.types.'.$membership->type) }}</dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.sold.status') }}</dt>
              <dd class="text-right">
                <span class="styledesk_badge {{ $membership->statusClass() }}">{{ $membership->statusLabel() }}</span>
              </dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.sold.start') }}</dt>
              <dd class="font-semibold text-head text-right">{{ $startDate }}</dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.sold.paid') }}</dt>
              <dd class="font-semibold text-head text-right">{{ $paid }}</dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.sold.billing') }}</dt>
              <dd class="font-semibold text-head text-right">
                {{ $membership->isRecurring()
                    ? __('membership.billing_frequencies.'.$membership->billing_frequency)
                    : __('membership.sold.one_off') }}
              </dd>
            </div>

            {{-- Only where there is one. A package has no next billing date
                 and never will, so the row is absent rather than showing a
                 dash somebody has to interpret. --}}
            @if ($membership->next_billing_on)
              <div class="flex items-baseline justify-between gap-4">
                <dt class="text-sub">{{ __('membership.sold.next_billing') }}</dt>
                <dd class="font-semibold text-head text-right">{{ $membership->next_billing_on->translatedFormat('j F Y') }}</dd>
              </div>
            @endif
          </dl>
        </section>

        {{-- ------------------------------------------------- what they get --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.sold.credits') }}</h2>

          @if ($membership->credits->isEmpty())
            <p class="text-[13px] text-sub mt-2">{{ __('membership.sold.credits_none') }}</p>
          @else
            <ul class="mt-3 divide-y divide-line">
              @foreach ($membership->credits as $credit)
                <li class="py-2.5 first:pt-0 last:pb-0 flex items-baseline justify-between gap-4">
                  <span class="text-[13.5px] text-ink min-w-0 truncate">{{ $credit->service?->name ?? '—' }}</span>
                  <span class="text-[13px] font-semibold text-head shrink-0">
                    {{ __('membership.sold.credits_available', ['count' => $credit->remaining()]) }}
                  </span>
                </li>
              @endforeach
            </ul>
          @endif

          @php $benefit = $membership->plan->discountLabel($currency); @endphp

          @if ($benefit || $membership->plan->priority_booking)
            <h3 class="text-[13px] font-semibold text-head mt-5">{{ __('membership.sold.benefits') }}</h3>

            <ul class="mt-2 space-y-1.5 text-[13px] text-ink">
              @if ($benefit)
                <li class="flex items-start gap-2.5">
                  <span class="text-brand shrink-0 mt-0.5"><x-icon name="check" size="14" /></span>{{ $benefit }}
                </li>
              @endif

              @if ($membership->plan->priority_booking)
                <li class="flex items-start gap-2.5">
                  <span class="text-brand shrink-0 mt-0.5"><x-icon name="check" size="14" /></span>{{ __('membership.form.priority_booking') }}
                </li>
              @endif
            </ul>
          @endif
        </section>
      </div>

      <div class="mt-5 flex flex-wrap gap-2">
        <a href="{{ route('bookings.create') }}"
           class="h-9 px-4 inline-flex items-center rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          {{ __('membership.sold.another') }}
        </a>

        <a href="{{ route('clients.show', $membership->client) }}" class="styledesk_action">
          {{ __('membership.sold.view_client') }}
        </a>

        <a href="{{ route('membership.show', $membership->plan) }}" class="styledesk_action">
          {{ __('membership.sold.view_membership') }}
        </a>
      </div>
    </div>
  </main>
@endsection
