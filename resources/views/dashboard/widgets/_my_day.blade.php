@php $day = $data->myDay(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.my_day.title') }}</h2>

  @if ($day->isEmpty())
    <p class="text-[13px] text-sub mt-3">{{ __('dashboard.my_day.none') }}</p>
  @else
    <ul class="mt-3 divide-y divide-line">
      @foreach ($day as $booking)
        <li class="flex flex-wrap items-center gap-3 py-2.5">
          <span class="w-[86px] shrink-0 text-[13px] font-semibold text-head">{{ \App\Support\TimeFormat::time($booking->startsAt()) }}</span>

          <span class="min-w-0 flex-1">
            <a href="{{ route('bookings.show', $booking) }}"
               class="block text-[13px] font-semibold text-head hover:text-link truncate">{{ $booking->clientName() }}</a>
            <span class="block text-[12px] text-sub truncate">
              {{ collect([
                  $booking->services->pluck('name')->implode(', '),
                  $booking->resource?->name,
              ])->filter()->join(' · ') }}
            </span>
          </span>

          <span class="styledesk_badge {{ $booking->statusClass() }} shrink-0">{{ $booking->statusLabel() }}</span>
        </li>
      @endforeach
    </ul>
  @endif
</section>
