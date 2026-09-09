@php $services = $data->services(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.services.title') }}</h2>

  @if ($services->isEmpty())
    <p class="text-[13px] text-sub mt-3">{{ __('dashboard.services.none') }}</p>
  @else
    <ul class="mt-3 divide-y divide-line">
      @foreach ($services as $service)
        <li class="flex items-center gap-3 py-2.5">
          <span class="min-w-0 flex-1 text-[13px] text-head truncate">{{ $service['name'] }}</span>
          <span class="text-[12px] text-sub shrink-0">{{ __('dashboard.services.bookings', ['count' => $service['bookings']]) }}</span>
          <span class="w-[90px] text-right text-[13px] font-semibold text-head shrink-0">{{ $money($service['revenue_minor']) }}</span>
        </li>
      @endforeach
    </ul>
  @endif
</section>
