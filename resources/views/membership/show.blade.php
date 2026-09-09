@extends('layouts.app')

@section('title', $plan->name)

{{--
    One membership.

    Read as the client would be sold it — the price, then what it includes,
    then what else a member gets — with the business's own answers (credit
    rules, where it is sold, who built it) underneath. The order is the order
    somebody checks a plan in before publishing it.
--}}

@php
    $canEdit = auth()->user()?->hasPermission('clients.edit', 'own') ?? false;

    $channels = array_values(array_filter([
        $plan->sell_in_store ? __('membership.show.sold_in_store') : null,
        $plan->sell_online ? __('membership.show.sold_online') : null,
    ]));

    /* Whether a rule is this plan's own answer or the business's. Worth
       saying: a reader looking at "credits roll over" needs to know whether
       changing the business setting will change this. */
    $ownRule = [
        'expiry' => $plan->credit_expiry !== null,
        'rollover' => $plan->allow_rollover !== null,
        'substitution' => $plan->allow_service_substitution !== null,
    ];
@endphp

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[120px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('membership.index') }}" class="hover:text-ink transition-colors">{{ __('membership.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ $plan->isRecurring() ? route('membership.plans') : route('membership.packages') }}"
           class="hover:text-ink transition-colors">{{ __('membership.tabs.'.($plan->isRecurring() ? 'plans' : 'packages')) }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $plan->name }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ $plan->name }}</h1>
            <span class="styledesk_badge {{ $plan->statusClass() }}">{{ $plan->statusLabel() }}</span>
          </div>

          <p class="text-[13px] text-sub mt-1.5">
            {{ __('membership.types.'.$plan->type) }}
            @if ($plan->internal_code)
              · {{ $plan->internal_code }}
            @endif
          </p>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
          @if ($canEdit)
            <a href="{{ route('membership.edit', $plan) }}" class="styledesk_action">
              <x-icon name="pen-to-square" size="14" />
              {{ __('membership.actions.edit') }}
            </a>

            <form method="POST" action="{{ route('membership.toggle', $plan) }}">
              @csrf
              @method('PATCH')
              <button type="submit" class="styledesk_action">
                {{ $plan->is_disabled ? __('membership.actions.enable') : __('membership.actions.disable') }}
              </button>
            </form>
          @endif
        </div>
      </div>

      @if ($plan->is_disabled)
        <p class="mt-4 text-[12.5px] text-sub bg-hover rounded-lg px-3 py-2.5">{{ __('membership.show.disabled_note') }}</p>
      @elseif ($plan->is_draft)
        <div class="mt-4 sd-card p-4 flex flex-wrap items-center gap-3">
          <p class="text-[12.5px] text-sub flex-1 min-w-[240px]">{{ __('membership.show.draft_note') }}</p>

          @if ($canEdit)
            <a href="{{ route('membership.edit', $plan) }}#review"
               class="h-9 px-3.5 inline-flex items-center rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('membership.show.publish') }}
            </a>
          @endif
        </div>
      @endif

      {{-- ------------------------------------------------------- price --}}
      <section class="sd-card p-5 mt-5">
        @php $imageUrl = $plan->imageUrl(); @endphp

        @if ($imageUrl)
          {{-- The card the client is shown, at the top, because the picture
               is the first thing they read and this page is a check of what
               they will see. --}}
          <img src="{{ $imageUrl }}" alt="{{ $plan->name }}"
               class="w-full max-w-[420px] h-[180px] object-cover rounded-lg border border-line mb-4">
        @endif

        <p class="text-[28px] font-bold text-head leading-none">{{ $plan->priceLabel($currency) }}</p>

        @if ($plan->description)
          <p class="text-[13px] text-sub mt-2.5 leading-relaxed">{{ $plan->description }}</p>
        @endif

        @if (! $plan->isRecurring() && $plan->regular_value_minor !== null)
          <p class="text-[13px] text-sub mt-3">
            {{ __('membership.regular_value') }}:
            <span class="line-through">{{ \App\Support\Money::format($plan->regular_value_minor / 100, $currency) }}</span>
            @if ($plan->savingMinor() > 0)
              <span class="ml-2 font-semibold text-brand">
                {{ __('membership.saving') }}: {{ \App\Support\Money::format($plan->savingMinor() / 100, $currency) }}
              </span>
            @endif
          </p>
        @endif

        @if ($plan->isRecurring() && ($plan->joining_fee_minor || $plan->setup_fee_minor || $plan->trial_days))
          <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-[13px]">
            @if ($plan->joining_fee_minor)
              <div><dt class="text-sub inline">{{ __('membership.show.joining_fee') }}:</dt>
                <dd class="text-ink font-medium inline ml-1">{{ \App\Support\Money::format($plan->joining_fee_minor / 100, $currency) }}</dd></div>
            @endif
            @if ($plan->setup_fee_minor)
              <div><dt class="text-sub inline">{{ __('membership.show.setup_fee') }}:</dt>
                <dd class="text-ink font-medium inline ml-1">{{ \App\Support\Money::format($plan->setup_fee_minor / 100, $currency) }}</dd></div>
            @endif
            @if ($plan->trial_days)
              <div><dt class="text-sub inline">{{ __('membership.show.trial') }}:</dt>
                <dd class="text-ink font-medium inline ml-1">{{ __('membership.show.trial_days', ['days' => $plan->trial_days]) }}</dd></div>
            @endif
          </dl>
        @endif
      </section>

      <div class="mt-4 grid gap-4 lg:grid-cols-2">

        {{-- ------------------------------------------------- includes --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.show.includes') }}</h2>
          <p class="text-[12px] text-faint mt-1">
            {{ $plan->isRecurring() ? __('membership.show.per_cycle') : __('membership.show.in_total') }}
          </p>

          <ul class="mt-3 divide-y divide-line">
            @foreach ($plan->planServices as $line)
              <li class="py-2.5 first:pt-0 last:pb-0 flex items-baseline justify-between gap-4">
                <span class="text-[13.5px] text-ink">{{ $line->service?->name ?? '—' }}</span>
                <span class="text-[13.5px] font-semibold text-head shrink-0">× {{ $line->quantity }}</span>
              </li>
            @endforeach
          </ul>
        </section>

        {{-- ------------------------------------------------- benefits --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.show.benefits') }}</h2>

          @php $discount = $plan->discountLabel($currency); @endphp

          @if ($discount === null && ! $plan->priority_booking)
            <p class="text-[13px] text-sub mt-2">{{ __('membership.show.no_benefits') }}</p>
          @else
            <ul class="mt-3 space-y-2 text-[13.5px] text-ink">
              @if ($discount)
                <li class="flex items-start gap-2.5">
                  <span class="text-brand shrink-0 mt-0.5"><x-icon name="check" size="14" /></span>
                  {{ $discount }}
                </li>
              @endif

              @if ($plan->priority_booking)
                <li class="flex items-start gap-2.5">
                  <span class="text-brand shrink-0 mt-0.5"><x-icon name="check" size="14" /></span>
                  {{ __('membership.form.priority_booking') }}
                </li>
              @endif
            </ul>
          @endif
        </section>

        {{-- --------------------------------------------- credit rules --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.show.credits') }}</h2>

          <dl class="mt-3 space-y-3 text-[13px]">
            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.form.credit_expiry') }}</dt>
              <dd class="text-right">
                <span class="text-ink font-medium">{{ __('membership.credit_expiry.'.$rules['expiry']) }}</span>
                @unless ($ownRule['expiry'])
                  <span class="block text-[11.5px] text-faint">{{ __('membership.show.from_business') }}</span>
                @endunless
              </dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.form.rollover') }}</dt>
              <dd class="text-right">
                <span class="text-ink font-medium">
                  {{ $rules['rollover'] ? __('membership.form.yes') : __('membership.form.no') }}
                  @if ($rules['rollover'] && $rules['maximum_rollover'] !== null)
                    ({{ $rules['maximum_rollover'] }})
                  @endif
                </span>
                @unless ($ownRule['rollover'])
                  <span class="block text-[11.5px] text-faint">{{ __('membership.show.from_business') }}</span>
                @endunless
              </dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.form.substitution') }}</dt>
              <dd class="text-right">
                <span class="text-ink font-medium">{{ $rules['substitution'] ? __('membership.form.yes') : __('membership.form.no') }}</span>
                @unless ($ownRule['substitution'])
                  <span class="block text-[11.5px] text-faint">{{ __('membership.show.from_business') }}</span>
                @endunless
              </dd>
            </div>
          </dl>
        </section>

        {{-- --------------------------------------------- availability --}}
        <section class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('membership.show.availability') }}</h2>

          <dl class="mt-3 space-y-3 text-[13px]">
            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.form.locations') }}</dt>
              <dd class="text-ink font-medium text-right">
                {{ $plan->location_mode === 'all'
                    ? __('membership.all_locations')
                    : ($plan->locations->pluck('name')->join(', ') ?: '—') }}
              </dd>
            </div>

            <div class="flex items-baseline justify-between gap-4">
              <dt class="text-sub">{{ __('membership.form.channels') }}</dt>
              <dd class="text-ink font-medium text-right">
                {{ $channels === [] ? __('membership.show.sold_nowhere') : implode(' · ', $channels) }}
              </dd>
            </div>

            @if ($plan->createdBy)
              <div class="flex items-baseline justify-between gap-4">
                <dt class="text-sub">{{ __('membership.show.created_by') }}</dt>
                <dd class="text-ink font-medium text-right">{{ $plan->createdBy->first_name }} {{ $plan->createdBy->last_name }}</dd>
              </div>
            @endif
          </dl>
        </section>
      </div>
    </div>
  </main>
@endsection
