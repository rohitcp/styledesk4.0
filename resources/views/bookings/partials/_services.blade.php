{{--
    What was booked, line by line.

    The lines are a copy taken when the appointment was made — a service
    renamed or repriced next month must not rewrite what somebody was quoted
    — so the name and price here come from the booking, and only the things
    that are not part of the quote (its category, what it needs) are read
    from the service as it stands.
--}}
<section>
    <h2 class="styledesk_heading">{{ __('bookings.detail.services') }}</h2>

    <ul class="mt-3 space-y-2">
        @foreach ($booking->services as $line)
            <li class="bg-white border border-line rounded-card p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[13.5px] font-semibold text-head">{{ $line->name }}</p>
                        <p class="text-[12px] text-sub mt-0.5">
                            {{ collect([
                                $line->service?->category?->name,
                                trans_choice('bookings.summary.minutes', (int) $line->minutes, ['count' => (int) $line->minutes]),
                                $booking->staff?->displayName() ?? __('bookings.any_staff'),
                            ])->filter()->join(' · ') }}
                        </p>

                        {{-- Where the appointment actually is.

                             It used to list every room the service *could*
                             use, which on a spa with six of them read as
                             though one client had been given the whole
                             building. The booking now records the one it was
                             given, so that is what it says. --}}
                        @php $room = $line->resource ?? $booking->resource; @endphp

                        @if ($room)
                            <p class="text-[12px] text-sub mt-1">
                                {{ __('bookings.summary.resource') }}:
                                <span class="text-ink">{{ $room->name }}</span>
                            </p>
                        @endif
                    </div>

                    <p class="text-[13.5px] font-semibold text-head shrink-0">
                        {{ $totals->money((int) $line->price_minor) }}
                    </p>
                </div>
            </li>
        @endforeach
    </ul>
</section>
