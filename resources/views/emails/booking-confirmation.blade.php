{{--
    The client's copy of an appointment.

    Read on a phone on the way out of the salon, so the two things somebody
    actually needs — when, and where — are stated where the eye lands, and the
    money is answered plainly rather than left for them to work out.
--}}
@php
    $ink = '#0f0f10';
    $muted = '#6b7280';
    $line = '#e5e7eb';
    $owing = $booking->dueMinor() > 0;

    $details = array_filter([
        __('bookings.summary.services') => $booking->services->pluck('name')->implode(', '),
        __('bookings.summary.staff') => $booking->staff?->displayName() ?? __('bookings.any_staff'),
        __('bookings.summary.location') => $booking->location?->name,
        __('bookings.summary.reference') => $booking->reference,
    ]);
@endphp

<x-mail.business :brand="$brand"
                 :headline="__('bookings.email.headline')"
                 :greeting="__('bookings.email.intro', ['name' => $booking->clientName()])"
                 :preheader="$booking->date->translatedFormat('l j F').' · '.$booking->timeLabel()"
                 :footer-lines="$footerLines">

    {{-- When, first and largest. Everything else on this page is a detail of
         an appointment somebody has to turn up to. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="width:100%; background-color:#f7f7fa; border-radius:10px;">
        <tr>
            <td style="padding:20px 22px;">
                <p style="margin:0 0 4px 0; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.6px; color:{{ $muted }};">
                    {{ __('bookings.summary.when') }}
                </p>
                <p style="margin:0; font-size:19px; line-height:1.35; font-weight:700; color:{{ $ink }};">
                    {{ $booking->date->translatedFormat('l j F Y') }}
                </p>
                <p style="margin:2px 0 0 0; font-size:16px; line-height:1.4; font-weight:600; color:{{ $brand['primary'] }};">
                    {{ $booking->timeLabel() }}
                </p>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin-top:8px;">
        @foreach ($details as $label => $value)
            <tr>
                <td class="sd-key" width="34%" style="width:34%; padding:12px 12px 12px 0; font-size:14px; color:{{ $muted }}; border-bottom:1px solid {{ $line }}; vertical-align:top;">
                    {{ $label }}
                </td>
                <td class="sd-val" style="padding:12px 0; font-size:14px; font-weight:600; color:{{ $ink }}; border-bottom:1px solid {{ $line }}; vertical-align:top;">
                    {{ $value }}
                </td>
            </tr>
        @endforeach
    </table>

    {{-- What it comes to, line by line. A confirmation that states only a
         total is one the client cannot check against what they were told at
         the desk. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin-top:24px;">
        @foreach ($totals->lines() as $totalLine)
            @php $strong = $totalLine['strong'] ?? false; @endphp
            <tr>
                <td style="padding:5px 0; font-size:14px; color:{{ $strong ? $ink : $muted }}; {{ $strong ? 'font-weight:600; padding-top:12px; border-top:1px solid '.$line.';' : '' }}">
                    {{ $totalLine['label'] }}
                </td>
                <td align="right" style="padding:5px 0; font-size:{{ $strong ? '16px' : '14px' }}; font-weight:{{ $strong ? '700' : '600' }}; color:{{ $ink }}; {{ $strong ? 'padding-top:12px; border-top:1px solid '.$line.';' : '' }}">
                    {{ $totalLine['value'] }}
                </td>
            </tr>
        @endforeach
    </table>

    {{-- Settled, or not. A confirmation that says nothing about money leaves
         the client to guess whether they still owe it — and somebody who has
         already paid should not arrive expecting to pay again. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="width:100%; margin-top:18px; background-color:{{ $owing ? '#fdf6ec' : '#eff8f1' }}; border-radius:10px;">
        <tr>
            <td style="padding:14px 18px; font-size:14px; font-weight:600; color:{{ $owing ? '#8a4b09' : '#166534' }};">
                {{ $owing ? __('bookings.summary.due') : __('bookings.summary.paid') }}
            </td>
            <td align="right" style="padding:14px 18px; font-size:16px; font-weight:700; color:{{ $owing ? '#8a4b09' : '#166534' }};">
                {{ $owing ? $due : $paid }}
            </td>
        </tr>
    </table>

    <x-slot:closing>{{ __('bookings.email.footer_note') }}</x-slot:closing>
</x-mail.business>
