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
                'resource' => $booking->services
                    ->flatMap(fn ($line) => $line->service?->resources->pluck('name') ?? collect())
                    ->unique()->implode(', '),
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
            'when' => $booking->created_at?->translatedFormat('j M Y · H:i'),
        ]) }}
        @if ($booking->updated_at && $booking->updated_at->gt($booking->created_at->addMinute()))
            · {{ __('bookings.detail.updated', ['when' => $booking->updated_at->translatedFormat('j M Y · H:i')]) }}
        @endif
    </p>
</section>
