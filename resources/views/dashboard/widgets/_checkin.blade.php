{{-- The check-in queue.

     The one panel that is a working list rather than a summary: the front
     desk acts on it all day, so it carries the action rather than sending
     somebody to another screen to find it. --}}
@php $queue = $data->checkInQueue(); @endphp

@php $bare = $bare ?? false; @endphp

<section @class(['sd-card p-5' => ! $bare, 'px-4 pb-3' => $bare])>
  {{-- Clear of the tab bar. Without it the link sits right under the
       underline and reads as part of the tab rather than as the panel's
       own. --}}
  <div @class([
      'flex flex-wrap items-baseline justify-between gap-3',
      /* Clear of the tab bar. Without it the link sits right under the
         underline and reads as part of the tab rather than as the panel's
         own. */
      'pt-3' => $bare,
  ])>
    {{-- The tab already says which panel this is, so the heading is only
         drawn where there is no tab. --}}
    @unless ($bare)
      <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.checkin.title') }}</h2>
    @endunless

    @if ($queue->isNotEmpty())
      <a href="{{ route('bookings.index', ['tab' => 'check-in']) }}"
         class="text-[12px] font-semibold text-link hover:underline">{{ __('dashboard.checkin.view_all') }}</a>
    @endif
  </div>

  @if ($queue->isEmpty())
    {{-- Good news rather than a dead end, so it does not read like a
         search that found nothing. --}}
    <p class="text-[13px] text-sub mt-3">{{ __('dashboard.checkin.none') }}</p>
    <p class="text-[12px] text-faint mt-1">{{ __('dashboard.checkin.none_hint') }}</p>
  @else
    <ul class="mt-2 divide-y divide-line">
      @foreach ($queue as $booking)
        @php
            $late = $booking->date->copy()->setTimeFromTimeString($booking->startsAt())->isPast();
            $minutes = (int) round(now()->diffInMinutes(
                $booking->date->copy()->setTimeFromTimeString($booking->startsAt()), false
            ));
        @endphp

        {{-- Two lines in the side column, one across the page.

             The same row squeezed into 380px turned every client into
             "Ca…" — a queue that cannot show a name is not a queue. --}}
        <li @class(['py-2.5', 'flex flex-wrap items-center gap-3' => ! $bare])>
          @if ($bare)
            {{-- The name leads, because it is what the person at the desk is
                 looking for; how late they are sits opposite it, because it
                 is what decides whether to telephone. The time drops to the
                 second line with the service — by the time somebody is
                 reading it they already know which appointment. --}}
            <div class="flex items-baseline gap-2">
              {{-- The name takes only the width it needs, so how late they
                   are sits against it rather than adrift at the far edge —
                   the two are one fact and read as one. It still truncates
                   where a name is long enough to crowd the badge out. --}}
              <a href="{{ route('bookings.show', $booking) }}"
                 class="min-w-0 text-[13px] font-semibold text-head hover:text-link truncate">{{ $booking->clientName() }}</a>

              @if ($late)
                <span class="styledesk_badge shrink-0 {{ $minutes <= -10 ? 'styledesk_badge--danger' : 'styledesk_badge--attention' }}">
                  {{ __('bookings.tabs.arrival.late', ['count' => abs($minutes)]) }}
                </span>
              @endif
            </div>

            <div class="flex items-center gap-2 mt-0.5">
              <span class="min-w-0 flex-1 text-[12px] text-sub truncate">
                {{ \App\Support\TimeFormat::time($booking->startsAt()) }}
                <span class="text-faint">/</span>
                {{ $booking->services->pluck('name')->implode(', ') }}
              </span>

              @if ($canCheckIn && $booking->allows('check-in', $user))
                <form method="POST" action="{{ route('bookings.check-in', $booking) }}" class="shrink-0">
                  @csrf
                  <button type="submit" class="styledesk_action styledesk_action--sm">
                    {{ __('dashboard.checkin.action') }}
                  </button>
                </form>
              @endif
            </div>
          @else
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

            @if ($late)
              <span class="styledesk_badge {{ $minutes <= -10 ? 'styledesk_badge--danger' : 'styledesk_badge--attention' }} shrink-0">
                {{ __('bookings.tabs.arrival.late', ['count' => abs($minutes)]) }}
              </span>
            @endif

            @if ($canCheckIn && $booking->allows('check-in', $user))
              <form method="POST" action="{{ route('bookings.check-in', $booking) }}" class="shrink-0">
                @csrf
                <button type="submit" class="styledesk_action styledesk_action--sm">
                  {{ __('dashboard.checkin.action') }}
                </button>
              </form>
            @endif
          @endif
        </li>
      @endforeach
    </ul>
  @endif
</section>
