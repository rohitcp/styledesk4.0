@php $c = $data->clients(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.clients.title') }}</h2>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
    @foreach (['new_today', 'booked_today', 'returning_today', 'active'] as $key)
      <div>
        <p class="text-[18px] font-bold text-head leading-tight">{{ $c[$key] }}</p>
        <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.clients.'.$key) }}</p>
      </div>
    @endforeach
  </div>
</section>
