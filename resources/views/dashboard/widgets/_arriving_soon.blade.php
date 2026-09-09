@php $soon = $data->arrivingSoon(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.arriving.title') }}</h2>

  @if ($soon->isEmpty())
    <p class="text-[13px] text-sub mt-3">{{ __('dashboard.arriving.none') }}</p>
  @else
    <ul class="mt-3 divide-y divide-line">
      @foreach ($soon as $booking)
        <li class="flex items-center gap-3 py-2.5">
          <span class="w-[86px] shrink-0 text-[13px] font-semibold text-head">{{ \App\Support\TimeFormat::time($booking->startsAt()) }}</span>
          <span class="min-w-0 flex-1">
            <a href="{{ route('bookings.show', $booking) }}"
               class="block text-[13px] font-semibold text-head hover:text-link truncate">{{ $booking->clientName() }}</a>
            <span class="block text-[12px] text-sub truncate">
              {{ collect([
                  $booking->services->pluck('name')->implode(', '),
                  $booking->staff?->displayName(),
                  $booking->resource?->name,
              ])->filter()->join(' · ') }}
            </span>
          </span>
        </li>
      @endforeach
    </ul>
  @endif
</section>
