{{--
    <x-consent-status :granted="$client->marketing_email" /> — whether a
    permission was given, said the same way everywhere.

    Consent shows up in several places that have nothing else in common:
    a client's profile, the client settings defaults, marketing preferences.
    They are all answering one question, so they get one component rather
    than each page picking its own colour and wording.

    `label` overrides the wording for a screen that needs to be more
    specific ("Subscribed" / "Unsubscribed"); the icon and colour stay.
--}}
@props(['granted' => false, 'label' => null, 'size' => 14])

@php
    $granted = (bool) $granted;
    $text = $label ?? ($granted ? __('common.consent.opted_in') : __('common.consent.opted_out'));
@endphp

<span {{ $attributes->class(['styledesk_consent', 'styledesk_consent--in' => $granted, 'styledesk_consent--out' => ! $granted]) }}>
    <span class="styledesk_consent__icon" aria-hidden="true">
        @if ($granted)
            {{-- A tick inside a circle: the same weight as the dash below,
                 so the two states sit on the same baseline and occupy the
                 same width in a column of them. --}}
            <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                <path d="M8 12.4l2.6 2.6L16 9.6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @else
            <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                <path d="M8.2 12h7.6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
            </svg>
        @endif
    </span>

    {{ $text }}
</span>
