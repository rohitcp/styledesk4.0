{{--
    The coupon block.

    A code the client is meant to read off a screen and quote at the desk or
    type into a booking form, so it is set large, spaced and monospaced —
    0 beside O at 14px is a support call. The same reasoning as the Backoffice
    sign-in code, and it was learned there.

    Drawn only when the campaign is live. An expired code in somebody's inbox
    is a promise the business has to break at the till.
--}}
@props(['coupon', 'currency' => null])

@php
    $ink = '#0f0f10';
    $muted = '#6b7280';
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="width:100%; margin-top:24px;">
    <tr>
        <td align="center" style="padding:24px; background-color:#fbf7ef; border:1px dashed #d9c9a8; border-radius:10px;">

            <p style="margin:0; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:{{ $muted }};">
                {{ $coupon->name }}
            </p>

            <p style="margin:10px 0 0 0; font-size:26px; line-height:1.1; font-weight:700; letter-spacing:6px;
                      font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace; color:{{ $ink }};">
                {{ $coupon->code }}
            </p>

            <p style="margin:10px 0 0 0; font-size:14px; font-weight:600; color:{{ $ink }};">
                {{ $coupon->discountLabel($currency) }}
            </p>

            @if ($coupon->description)
                <p style="margin:8px 0 0 0; font-size:13px; line-height:1.6; color:{{ $muted }};">
                    {{ $coupon->description }}
                </p>
            @endif

            {{-- The date it stops working, because a coupon without one reads
                 as open-ended and the client finds out otherwise at the desk. --}}
            @if ($coupon->ends_on)
                <p style="margin:8px 0 0 0; font-size:12px; color:{{ $muted }};">
                    {{ __('email_templates.coupon.expires', ['date' => $coupon->ends_on->translatedFormat('j F Y')]) }}
                </p>
            @endif
        </td>
    </tr>
</table>
