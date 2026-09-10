@extends('layouts.app')

@section('title', __('membership.sold.title'))

{{--
    Membership Activated.

    The same document the booking confirmation is, because it is the same
    moment: something was sold, and the desk is looking at the one page that
    says what went through. One centred card on a grey page, the answer and
    the reference at the top, then the facts, then the money, then what to do
    with it — a receptionist who has confirmed a booking this morning should
    not have to learn a second layout this afternoon.

    What differs is only what a membership *is*: the credits it grants and the
    benefits it carries, which a booking has no equivalent of.

    A membership dated forward says so plainly rather than claiming to be
    active: its credits do not exist until the start date arrives, and a page
    that said otherwise would have the desk promising a massage this week.
--}}

@php
    $scheduled = $membership->status() === 'scheduled';
    $startDate = $membership->starts_on->translatedFormat('j F Y');
    $benefit = $membership->plan->discountLabel($currency);
    $hasBenefits = $benefit || $membership->plan->priority_booking;
@endphp

@section('content')
  {{-- The page turns grey behind the finished document, the way it does
       behind a confirmed booking: the clearest signal that the workflow is
       over and nothing here is still waiting to be saved. --}}
  <main class="w-full min-h-screen bg-hover px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[120px]">
    <div class="flex justify-center py-2 sm:py-6">
      <article class="w-full max-w-[820px] bg-white border border-line rounded-xl shadow-sm">

        {{-- 1 · the answer, compactly. A full-width alert says the same thing
             far louder than a finished document needs to. --}}
        <header class="p-6 sm:p-8 border-b border-line">
          <div class="flex items-start gap-3">
            <span class="shrink-0 h-8 w-8 rounded-full bg-brand/10 text-brand grid place-items-center" aria-hidden="true">
              <x-icon name="check" size="17" />
            </span>

            <div class="min-w-0">
              <h1 class="text-[19px] sm:text-[21px] font-bold text-head tracking-tight">
                {{ $scheduled ? __('membership.sold.scheduled_title') : __('membership.sold.title') }}
              </h1>
              <p class="text-[13px] text-sub mt-1">
                {{ $scheduled
                    ? __('membership.sold.scheduled_intro', ['client' => $membership->client->displayName(), 'date' => $startDate])
                    : __('membership.sold.intro', ['client' => $membership->client->displayName()]) }}
              </p>
            </div>
          </div>

          {{-- The membership's own number, where the booking document puts
               its reference: the thing somebody reads out on the phone. The
               plan's code names the product; this names what this client
               bought, which is what a confirmation is about. --}}
          @if ($membership->reference)
            <div class="mt-5">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                {{ __('membership.sold.reference') }}
              </p>
              <p class="text-[20px] sm:text-[22px] font-bold font-mono text-head mt-0.5">{{ $membership->reference }}</p>
            </div>
          @endif
        </header>

        {{-- 2 · what was sold. Label left, answer right, on one line each
             where there is room and stacked where there is not. --}}
        <section class="p-6 sm:p-8">
          <h2 class="text-[11px] font-semibold uppercase tracking-wide text-faint">
            {{ __('membership.sold.summary') }}
          </h2>

          <dl class="mt-4 space-y-3.5 text-[13.5px]">
            <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
              <dt class="text-sub">{{ __('membership.sold.client') }}</dt>
              <dd class="font-semibold text-head sm:text-right min-w-0">{{ $membership->client->displayName() }}</dd>
            </div>

            <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
              <dt class="text-sub">{{ __('membership.sold.membership') }}</dt>
              <dd class="font-semibold text-head sm:text-right min-w-0">{{ $membership->plan->name }}</dd>
            </div>

            <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
              <dt class="text-sub">{{ __('membership.sold.type') }}</dt>
              <dd class="font-semibold text-head sm:text-right">{{ __('membership.types.'.$membership->type) }}</dd>
            </div>

            {{-- The plan's own code, which names the product rather than
                 this purchase of it. Both are worth having: one identifies
                 what was sold, the other identifies the sale. --}}
            @if ($membership->plan->internal_code)
              <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
                <dt class="text-sub">{{ __('membership.form.internal_code') }}</dt>
                <dd class="font-semibold text-head sm:text-right font-mono">{{ $membership->plan->internal_code }}</dd>
              </div>
            @endif

            <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
              <dt class="text-sub">{{ __('membership.sold.status') }}</dt>
              <dd class="sm:text-right">
                <span class="styledesk_badge {{ $membership->statusClass() }}">{{ $membership->statusLabel() }}</span>
              </dd>
            </div>

            <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
              <dt class="text-sub">{{ __('membership.sold.start') }}</dt>
              <dd class="font-semibold text-head sm:text-right">{{ $startDate }}</dd>
            </div>

            @if ($membership->location)
              <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
                <dt class="text-sub">{{ __('membership.sold.location') }}</dt>
                <dd class="font-semibold text-head sm:text-right">{{ $membership->location->name }}</dd>
              </div>
            @endif
          </dl>
        </section>

        {{-- 3 · the money, laid out as a bill: the lines, then the rule, then
             the one number somebody has to act on. --}}
        <section class="px-6 sm:px-8 pb-6 sm:pb-8 pt-6 border-t border-line">
          <h2 class="text-[11px] font-semibold uppercase tracking-wide text-faint">
            {{ __('membership.sold.payment') }}
          </h2>

          <dl class="mt-4 space-y-2.5 text-[13.5px]">
            <div class="flex items-baseline justify-between gap-6">
              <dt class="text-sub">{{ __('membership.sold.billing') }}</dt>
              <dd class="font-semibold text-head">
                {{ $membership->isRecurring()
                    ? __('membership.billing_frequencies.'.$membership->billing_frequency)
                    : __('membership.sold.one_off') }}
              </dd>
            </div>

            {{-- Only where there is one. A package has no next billing date
                 and never will, so the row is absent rather than showing a
                 dash somebody has to interpret. --}}
            @if ($membership->next_billing_on)
              <div class="flex items-baseline justify-between gap-6">
                <dt class="text-sub">{{ __('membership.sold.next_billing') }}</dt>
                <dd class="font-semibold text-head tabular-nums">{{ $membership->next_billing_on->translatedFormat('j F Y') }}</dd>
              </div>
            @endif

            {{-- What was actually taken, and how — the same breakdown the
                 booking document shows under its total. --}}
            @foreach ($membership->payments as $payment)
              <div class="flex items-baseline justify-between gap-6 text-[12.5px]">
                {{-- The same names the booking screens use: one word for
                     one method, wherever money is taken. --}}
                <dt class="text-faint pl-3">{{ __('bookings.methods.'.$payment->method.'.name') }}</dt>
                <dd class="text-sub tabular-nums">{{ App\Support\Money::format($payment->amount_minor / 100, $membership->currency_code) }}</dd>
              </div>
            @endforeach
          </dl>

          <div class="mt-4 pt-4 border-t border-line">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">{{ __('membership.sold.paid') }}</p>
            <p class="text-[26px] font-bold tabular-nums leading-tight mt-0.5 text-head">{{ $paid }}</p>
          </div>
        </section>

        {{-- 4 · what a membership is, which a booking has no equivalent of:
             the credits it grants and the benefits it carries. --}}
        <section class="px-6 sm:px-8 pb-6 sm:pb-8 pt-6 border-t border-line">
          <h2 class="text-[11px] font-semibold uppercase tracking-wide text-faint">
            {{ __('membership.sold.credits') }}
          </h2>

          @if ($membership->credits->isEmpty())
            <p class="text-[13px] text-sub mt-3">{{ __('membership.sold.credits_none') }}</p>
          @else
            <ul class="mt-3 divide-y divide-line">
              @foreach ($membership->credits as $credit)
                <li class="py-2.5 first:pt-0 last:pb-0 flex items-baseline justify-between gap-6">
                  <span class="text-[13.5px] text-ink min-w-0 truncate">{{ $credit->service?->name ?? '—' }}</span>
                  <span class="text-[13px] font-semibold text-head shrink-0 tabular-nums">
                    {{ __('membership.sold.credits_available', ['count' => $credit->remaining()]) }}
                  </span>
                </li>
              @endforeach
            </ul>
          @endif

          @if ($hasBenefits)
            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-faint mt-6">{{ __('membership.sold.benefits') }}</h3>

            <ul class="mt-2.5 space-y-1.5 text-[13.5px] text-ink">
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

        {{-- 5 · what to do with it. One thing to do, and the other places to
             go stacked below it — three buttons sharing a row read as one
             decision split three ways. --}}
        <footer class="px-6 sm:px-8 py-6 border-t border-line">
          <a href="{{ route('clients.show', $membership->client) }}"
             class="w-full h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white
                    text-[13.5px] font-semibold flex items-center justify-center transition-colors">
            {{ __('membership.sold.view_client') }}
          </a>

          <div class="mt-2 grid grid-cols-1 gap-2">
            <a href="{{ route('membership.show', $membership->plan) }}" class="styledesk_action w-full h-11 justify-center">
              {{ __('membership.sold.view_membership') }}
            </a>

            <a href="{{ route('bookings.create') }}" class="styledesk_action w-full h-11 justify-center">
              {{ __('membership.sold.another') }}
            </a>
          </div>
        </footer>
      </article>
    </div>
  </main>
@endsection
