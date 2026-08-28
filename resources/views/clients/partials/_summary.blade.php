{{--
    The four figures §9 asks for, as four flat bordered cards.

    Two of them come from columns bookings will fill; the other two need a
    bookings module to count at all. Rather than print an invented 24 visits
    and $2,840, each says what is true now — a profile that states a figure
    nobody can trace is worse than one that admits the number is not ready.
--}}
@php
    $cards = [
        [
            'tone' => 'blue',
            'label' => __('clients.module.workspace.summary.last_visit'),
            'value' => $client->last_visit_at?->isoFormat('D MMM Y'),
            'empty' => __('clients.module.never_visited'),
        ],
        [
            'tone' => 'violet',
            'label' => __('clients.module.workspace.summary.next_booking'),
            'value' => $client->next_booking_at?->isoFormat('D MMM Y · h:mm A'),
            'empty' => __('clients.module.nothing_booked'),
        ],
        [
            'tone' => 'teal',
            'label' => __('clients.module.workspace.summary.total_visits'),
            'value' => null,
            'empty' => __('clients.module.workspace.summary.awaiting_bookings'),
        ],
        [
            'tone' => 'amber',
            'label' => __('clients.module.workspace.summary.lifetime_spend'),
            'value' => null,
            'empty' => __('clients.module.workspace.summary.awaiting_bookings'),
        ],
    ];
@endphp

{{-- Four across when the column can hold them, two when it cannot; equal
     widths either way, so the row never reads as one card being more
     important than the others. --}}
<div class="styledesk_metrics">
    @foreach ($cards as $card)
        <div class="styledesk_metric styledesk_metric--{{ $card['tone'] }}">
            <p class="styledesk_metric__label">{{ $card['label'] }}</p>

            @if ($card['value'])
                <p class="styledesk_metric__value">{{ $card['value'] }}</p>
            @else
                <p class="styledesk_metric__empty">{{ $card['empty'] }}</p>
            @endif
        </div>
    @endforeach
</div>
