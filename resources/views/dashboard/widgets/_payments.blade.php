@php $pay = $data->payments(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.payments.title') }}</h2>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
    <div>
      <p class="text-[18px] font-bold text-head leading-tight">{{ $money($pay['collected_minor']) }}</p>
      <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.payments.collected') }}</p>
    </div>
    <div>
      <p class="text-[18px] font-bold text-head leading-tight">{{ $money($pay['outstanding_minor']) }}</p>
      <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.payments.outstanding') }}</p>
    </div>
    <div>
      <p class="text-[18px] font-bold text-head leading-tight">{{ $pay['partial'] }}</p>
      <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.payments.partial') }}</p>
    </div>

    {{-- Today's work that has not been paid for — the list the desk chases
         before locking up, so it links straight to it. --}}
    <a href="{{ route('bookings.index', ['tab' => 'today', 'payment' => 'unpaid']) }}"
       class="rounded-lg -m-2 p-2 hover:bg-hover transition-colors">
      <p class="text-[18px] font-bold text-head leading-tight">{{ $pay['due_today'] }}</p>
      <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.payments.due_today') }}</p>
    </a>
  </div>
</section>
