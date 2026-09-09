{{-- This provider's own day, and nobody else's.

     Deliberately not the branch's takings: what another therapist earned is
     not this person's business, and a panel that showed it would be the
     first thing anybody complained about. --}}
@php $me = $data->myPerformance(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.my_performance.title') }}</h2>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
    <div>
      <p class="text-[18px] font-bold text-head leading-tight">{{ $me['clients'] }}</p>
      <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.my_performance.clients') }}</p>
    </div>
    <div>
      <p class="text-[18px] font-bold text-head leading-tight">{{ $me['completed'] }}</p>
      <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.my_performance.completed') }}</p>
    </div>
    <div>
      <p class="text-[18px] font-bold text-head leading-tight">{{ __('dashboard.my_performance.minutes', ['count' => $me['average_minutes']]) }}</p>
      <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.my_performance.average') }}</p>
    </div>

    {{-- Tips only where the business takes them and this reader may see
         their own: a nought beside "Tips" in a salon that does not tip is a
         number that means nothing. --}}
    @if ($showsTips)
      <div>
        <p class="text-[18px] font-bold text-head leading-tight">{{ $money($me['tips_minor']) }}</p>
        <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.my_performance.tips') }}</p>
      </div>
    @endif
  </div>
</section>
