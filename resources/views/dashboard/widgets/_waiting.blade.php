{{-- Checked in and still sitting in reception.

     Measured from the check-in entry rather than the appointment time:
     somebody who arrived twenty minutes early has been waiting twenty
     minutes, whatever the diary says. --}}
@php $waiting = $data->waiting(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.waiting.title') }}</h2>

  @if ($waiting->isEmpty())
    <p class="text-[13px] text-sub mt-3">{{ __('dashboard.waiting.none') }}</p>
  @else
    <ul class="mt-3 divide-y divide-line">
      @foreach ($waiting as $row)
        <li class="flex flex-wrap items-center gap-3 py-2.5">
          <span class="min-w-0 flex-1">
            <a href="{{ route('bookings.show', $row['booking']) }}"
               class="block text-[13px] font-semibold text-head hover:text-link truncate">{{ $row['booking']->clientName() }}</a>
            <span class="block text-[12px] text-sub truncate">
              {{ collect([
                  $row['booking']->services->pluck('name')->implode(', '),
                  $row['booking']->staff?->displayName(),
                  $row['booking']->resource?->name,
              ])->filter()->join(' · ') }}
            </span>
          </span>

          @if ($row['minutes'] !== null)
            <span class="styledesk_badge shrink-0 {{ $row['too_long'] ? 'styledesk_badge--attention' : 'styledesk_badge--info' }}">
              {{ __('dashboard.waiting.for', ['count' => $row['minutes']]) }}
            </span>
          @elseif ($row['since'] === null)
            {{-- Checked in before the arrival was recorded, so how long is
                 not a question this row can answer. --}}
            <span class="styledesk_badge styledesk_badge--info shrink-0">{{ __('bookings.tabs.arrival.checked_in') }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
</section>
