@extends('layouts.app')

@section('title', $promotion->name)

@section('content')
  @php $money = fn (int $minor) => \App\Support\Money::format($minor / 100, $currency); @endphp

  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[120px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('promotions.index') }}" class="hover:text-ink transition-colors">{{ __('promotions.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $promotion->name }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ $promotion->name }}</h1>
            <span class="styledesk_metachip {{ $promotion->statusClass() }}">
              <span class="styledesk_statusdot" aria-hidden="true"></span>
              {{ $promotion->statusLabel() }}
            </span>
          </div>

          @if ($promotion->code)
            <p class="mt-1.5 text-[15px] font-mono font-semibold text-head">{{ $promotion->code }}</p>
          @else
            <p class="mt-1.5 text-[13px] text-sub">{{ __('promotions.automatic') }}</p>
          @endif

          @if ($promotion->description)
            <p class="text-[13px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ $promotion->description }}</p>
          @endif
        </div>

        <div class="shrink-0 flex flex-wrap gap-2">
          <a href="{{ route('promotions.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          <a href="{{ route('promotions.edit', $promotion) }}" class="styledesk_action">{{ __('promotions.actions.edit') }}</a>
          <a href="{{ route('promotions.duplicate', $promotion) }}" class="styledesk_action">{{ __('promotions.actions.duplicate') }}</a>

          {{-- Switched off rather than deleted: the bookings it discounted
               keep pointing at it, and a promotion that vanished would leave
               last month's takings unexplained. --}}
          <form method="POST" action="{{ route('promotions.toggle', $promotion) }}">
            @csrf
            @method('PATCH')
            <button type="submit" @class(['styledesk_action', 'styledesk_action--danger' => ! $promotion->is_disabled])>
              {{ $promotion->is_disabled ? __('promotions.actions.enable') : __('promotions.actions.disable') }}
            </button>
          </form>
        </div>
      </div>

      {{-- ------------------------------------------------- details -- --}}
      <section class="sd-card p-5 mt-6">
        <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.details.title') }}</h2>

        <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 mt-4">
          <div>
            <dt class="text-[12px] font-semibold text-sub">{{ __('promotions.details.discount') }}</dt>
            <dd class="text-[15px] font-semibold text-head mt-0.5">{{ $promotion->discountLabel($currency) }}</dd>
          </div>

          <div>
            <dt class="text-[12px] font-semibold text-sub">{{ __('promotions.details.for') }}</dt>
            <dd class="text-[13px] text-head mt-0.5">{{ __('promotions.form.eligibility_'.$promotion->eligibility) }}</dd>
          </div>

          <div>
            <dt class="text-[12px] font-semibold text-sub">{{ __('promotions.details.services') }}</dt>
            <dd class="text-[13px] text-head mt-0.5">{{ $applies }}</dd>
          </div>

          <div>
            <dt class="text-[12px] font-semibold text-sub">{{ __('promotions.details.locations') }}</dt>
            <dd class="text-[13px] text-head mt-0.5">
              {{ $promotion->location_mode === 'all'
                  ? __('promotions.form.all_locations')
                  : $promotion->locations->pluck('name')->join(', ') }}
            </dd>
          </div>

          <div>
            <dt class="text-[12px] font-semibold text-sub">{{ __('promotions.details.valid') }}</dt>
            <dd class="text-[13px] text-head mt-0.5">
              {{ $promotion->starts_on->translatedFormat('j M Y') }} –
              {{ $promotion->ends_on?->translatedFormat('j M Y') ?? __('promotions.details.no_expiry') }}
            </dd>
            <dd class="text-[12px] text-faint mt-0.5">
              {{ $promotion->days === null || count($promotion->days) === 7
                  ? __('promotions.details.every_day')
                  : collect($promotion->days)->map(fn ($d) => __('promotions.weekdays.'.$d))->join(', ') }}
            </dd>
          </div>

          <div>
            <dt class="text-[12px] font-semibold text-sub">{{ __('promotions.details.usage') }}</dt>
            <dd class="text-[13px] text-head mt-0.5">{{ $promotion->usageLabel() }}</dd>
            @if ($promotion->min_spend_minor)
              <dd class="text-[12px] text-faint mt-0.5">
                {{ __('promotions.form.min_spend') }} {{ $money($promotion->min_spend_minor) }}
              </dd>
            @endif
          </div>

          <div>
            <dt class="text-[12px] font-semibold text-sub">{{ __('promotions.details.online') }}</dt>
            <dd class="text-[13px] text-head mt-0.5">
              {{ $promotion->allow_online ? __('promotions.details.online_yes') : __('promotions.details.online_no') }}
            </dd>
          </div>
        </dl>

        @if ($promotion->createdBy)
          <p class="text-[12px] text-faint mt-4 border-t border-line pt-3">
            {{ __('promotions.details.created_by', ['name' => $promotion->createdBy->name]) }}
          </p>
        @endif
      </section>

      {{-- -------------------------------------------------- report -- --}}
      <section class="sd-card p-5 mt-4">
        <h2 class="text-[15px] font-semibold text-head">{{ __('promotions.report.title') }}</h2>

        @if ($report['redemptions'] === 0)
          <p class="text-[13px] text-sub mt-3">{{ __('promotions.report.none') }}</p>
        @else
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <div>
              <p class="text-[20px] font-bold text-head leading-tight">{{ $report['redemptions'] }}</p>
              <p class="text-[11px] text-sub mt-0.5">{{ __('promotions.report.redemptions') }}</p>
            </div>
            <div>
              <p class="text-[20px] font-bold text-head leading-tight">{{ $report['clients'] }}</p>
              <p class="text-[11px] text-sub mt-0.5">{{ __('promotions.report.clients') }}</p>
            </div>
            <div>
              <p class="text-[20px] font-bold text-head leading-tight">{{ $money($report['discount_minor']) }}</p>
              <p class="text-[11px] text-sub mt-0.5">{{ __('promotions.report.discount') }}</p>
            </div>
            <div>
              <p class="text-[20px] font-bold text-head leading-tight">{{ $money($report['revenue_minor']) }}</p>
              <p class="text-[11px] text-sub mt-0.5">{{ __('promotions.report.revenue') }}</p>
              {{-- Said plainly: this is what those bookings came to, not a
                   claim that the promotion caused them. --}}
              <p class="text-[11px] text-faint mt-0.5 leading-snug">{{ __('promotions.report.revenue_hint') }}</p>
            </div>
          </div>
        @endif
      </section>
    </div>
  </main>
@endsection
