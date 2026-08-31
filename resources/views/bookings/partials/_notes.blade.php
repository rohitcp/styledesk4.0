{{--
    The note about this appointment.

    Kept apart from the client's own notes on purpose: one is read on the day
    and the other for as long as they are a client, and the booking screen
    asks for them separately for the same reason.
--}}
<section>
    <h2 class="styledesk_heading">{{ __('bookings.detail.notes') }}</h2>

    @if (filled($booking->notes))
        <p class="mt-3 bg-white border border-line rounded-card p-4 text-[13px] text-ink whitespace-pre-line">{{ $booking->notes }}</p>
    @else
        <p class="mt-2 text-[13px] text-sub">{{ __('bookings.detail.no_notes') }}</p>
    @endif
</section>
