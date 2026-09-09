@extends('layouts.app')

@section('title', __('membership.title'))

{{--
    Clients → Membership → Overview.

    Two things: what is on sale, and the terms it is sold on. The terms are
    read-only here and edited in App Settings — a second place to change them
    is a second place for them to disagree.
--}}

@php
    $sellable = $counts['plans'] + $counts['packages'];

    $channels = array_values(array_filter([
        $settings->allow_purchase_in_store ? __('membership.channels.in_store') : null,
        $settings->allow_purchase_online ? __('membership.channels.online') : null,
    ]));
@endphp

@section('content')
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    @include('membership._header')

    @if ($sellable === 0)
      {{-- Not "no memberships": there may be several, all of them drafts.
           A business whose booking screen offers nothing should be told
           which of those two it is. --}}
      <div class="mt-6 sd-card p-5 border-warning">
        <p class="text-[14px] font-semibold text-head">{{ __('membership.overview.nothing_sellable') }}</p>
        <p class="text-[13px] text-sub mt-1.5">{{ __('membership.overview.nothing_sellable_hint') }}</p>
      </div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-2">

      {{-- ------------------------------------------------ recently built --}}
      <section class="sd-card p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('membership.overview.recent') }}</h2>

        @if ($recent->isEmpty())
          <p class="text-[13px] text-sub mt-2">{{ __('membership.overview.recent_empty') }}</p>
        @else
          <ul class="mt-3 divide-y divide-line">
            @foreach ($recent as $plan)
              <li class="py-3 first:pt-0 last:pb-0 flex items-start gap-3">
                <div class="min-w-0 flex-1">
                  <a href="{{ route('membership.show', $plan) }}"
                     class="text-[13.5px] font-semibold text-head hover:text-brand transition-colors">{{ $plan->name }}</a>
                  <p class="text-[12.5px] text-sub mt-0.5">
                    {{ $plan->priceLabel($currency) }}
                    · {{ __('membership.types.'.$plan->type) }}
                  </p>
                </div>

                <span class="styledesk_badge {{ $plan->statusClass() }} shrink-0">{{ $plan->statusLabel() }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </section>

      {{-- ---------------------------------------------- the selling terms --}}
      <section class="sd-card p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('membership.overview.terms') }}</h2>
        <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('membership.overview.terms_hint') }}</p>

        <dl class="mt-4 space-y-3 text-[13px]">
          <div class="flex items-baseline justify-between gap-4">
            <dt class="text-sub">{{ __('membership.overview.term_channels') }}</dt>
            <dd class="text-ink font-medium text-right">
              {{ $channels === [] ? __('membership.show.sold_nowhere') : implode(' · ', $channels) }}
            </dd>
          </div>

          <div class="flex items-baseline justify-between gap-4">
            <dt class="text-sub">{{ __('membership.overview.term_activation') }}</dt>
            <dd class="text-ink font-medium text-right">{{ __('membership.activation.'.$settings->default_activation) }}</dd>
          </div>

          <div class="flex items-baseline justify-between gap-4">
            <dt class="text-sub">{{ __('membership.overview.term_credits') }}</dt>
            <dd class="text-ink font-medium text-right">
              {{ $settings->allow_rollover
                  ? __('membership.overview.term_credits_rollover')
                  : __('membership.overview.term_credits_reset') }}
            </dd>
          </div>

          <div class="flex items-baseline justify-between gap-4">
            <dt class="text-sub">{{ __('membership.overview.term_cancellation') }}</dt>
            <dd class="text-ink font-medium text-right">
              {{ $settings->allow_cancellation
                  ? __('membership.cancellation.'.$settings->cancellation_effective)
                  : __('membership.overview.term_cancellation_off') }}
            </dd>
          </div>
        </dl>

        <a href="{{ route('settings.membership.index') }}" class="styledesk_action mt-4">
          <x-icon name="sliders" size="14" />
          {{ __('membership.overview.terms_link') }}
        </a>
      </section>
    </div>
  </main>
@endsection
