{{--
    The appointment itself, as one block of facts.

    Everything a person is asked about it over the phone, in the order they
    are asked: what, who with, when, where — then how it came to be taken.
--}}
<section>
    <h2 class="styledesk_heading">{{ __('bookings.sections.summary') }}</h2>

    <dl class="mt-3 grid sm:grid-cols-2 gap-x-6 gap-y-2.5 text-[13px]">
        @php
            $rows = [
                'reference' => $booking->reference,
                'status' => $booking->statusLabel(),
                'client' => $booking->clientName(),
                'services' => $booking->services->pluck('name')->implode(', '),
                'staff' => $booking->staff?->displayName() ?? __('bookings.any_staff'),
                'date' => $booking->date->translatedFormat('l j F Y'),
                'starts' => App\Support\TimeFormat::time($booking->startsAt()),
                'ends' => App\Support\TimeFormat::time($booking->endsAt()),
                'duration' => trans_choice('bookings.summary.minutes', (int) $booking->minutes, ['count' => (int) $booking->minutes]),
                'location' => $booking->location?->name,
                /* The rooms this booking was given, not every room its
                   services could have used — which on a spa with eight of
                   them read as though one client had been handed the whole
                   building. Bookings taken before rooms were recorded per
                   service answer from the booking itself. */
                'resource' => $booking->services
                    ->map(fn ($line) => $line->resource?->name)
                    ->filter()
                    ->unique()
                    ->implode(', ') ?: $booking->resource?->name,
                'source' => $booking->source ? __('bookings.sources.'.$booking->source) : null,
            ];
        @endphp

        @foreach ($rows as $key => $value)
            @if (filled($value))
                <div>
                    <dt class="text-[12px] text-sub">{{ __('bookings.summary.'.$key) }}</dt>
                    <dd class="font-semibold text-head mt-0.5">{{ $value }}</dd>
                </div>
            @endif
        @endforeach
    </dl>

    {{-- Who took it and when it last moved. Kept apart from the appointment
         itself: this is the record's own history, not the client's. --}}
    <p class="mt-3.5 pt-3.5 border-t border-line text-[12px] text-sub">
        {{ __('bookings.detail.taken_by', [
            'name' => $booking->createdBy?->name ?? __('bookings.detail.someone'),
            'when' => \App\Support\TimeFormat::dateTime($booking->created_at),
        ]) }}
        @if ($booking->updated_at && $booking->updated_at->gt($booking->created_at->addMinute()))
            · {{ __('bookings.detail.updated', ['when' => \App\Support\TimeFormat::dateTime($booking->updated_at)]) }}
        @endif
    </p>
</section>
