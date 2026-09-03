{{--
    A business's email to one of its clients.

    The same skeleton, type scale and spacing as the account mail in
    `mail/auth.blade.php` — so everything StyleDesk sends is recognisably one
    piece of work — with one difference that decides the whole design: the
    brand belongs to the salon, not to us. The client booked with Smile Spa
    and has never heard of StyleDesk, so the band, the logo and the footer are
    theirs, and nothing here signs our name to their appointment.

    Hand-written tables with inline styles, and every measurement stated twice
    — once as an attribute for Outlook, once in the style for everything else.
    Mail clients strip <link>, most ignore <style>, and Outlook renders through
    Word. Nothing below depends on flexbox, CSS variables or classes.
--}}
@props([
    /** From App\Support\EmailBrand: name, logo, primary, ink, banner, accent. */
    'brand',
    'headline',
    'preheader' => null,
    'greeting' => null,
    /** Address, phone — whatever the business has. Blank lines are dropped. */
    'footerLines' => [],
])

@php
    $ink = '#0f0f10';
    $body = '#3f4451';
    $muted = '#6b7280';
    $line = '#e5e7eb';
    $lines = array_values(array_filter($footerLines));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    {{-- Stated, so a dark-mode client tints the design rather than inverting
         it and turning the brand band into a colour nobody chose. --}}
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $headline }}</title>

    <style>
        @media only screen and (max-width: 600px) {
            .sd-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .sd-headline { font-size: 24px !important; line-height: 1.25 !important; }
            .sd-key { display: block !important; width: 100% !important; padding-bottom: 2px !important; }
            .sd-val { display: block !important; width: 100% !important; padding-bottom: 12px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f4f4f7; -webkit-font-smoothing:antialiased; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:{{ $ink }};">

    {{-- The grey line an inbox shows beside the subject. Hidden in the body
         itself, or it reads as a stray duplicate sentence. --}}
    @if ($preheader)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">{{ $preheader }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f7;">
        <tr>
            <td align="center" style="padding:32px 12px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                       style="width:100%; max-width:600px; text-align:left; background-color:#ffffff; border-radius:14px; overflow:hidden;">

                    {{-- Who is writing, in their own colour. The logo where
                         there is one; the name in words where there is not,
                         because a broken image icon is worse than no logo. --}}
                    <tr>
                        <td class="sd-pad" bgcolor="{{ $brand['primary'] }}"
                            style="padding:28px 40px; background-color:{{ $brand['primary'] }};">
                            @if ($brand['logo'])
                                <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" height="34"
                                     style="display:block; height:34px; max-height:34px; width:auto; border:0; outline:none;">
                            @else
                                <p style="margin:0; font-size:19px; font-weight:700; letter-spacing:-0.2px; color:{{ $brand['ink'] }};">
                                    {{ $brand['name'] }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td class="sd-pad" style="padding:40px 40px 0 40px;">
                            <h1 class="sd-headline" style="margin:0; font-size:27px; line-height:1.25; font-weight:700; letter-spacing:-0.4px; color:{{ $ink }};">
                                {{ $headline }}
                            </h1>
                        </td>
                    </tr>

                    @if ($greeting)
                        <tr>
                            <td class="sd-pad" style="padding:16px 40px 0 40px;">
                                <p style="margin:0; font-size:16px; line-height:1.6; color:{{ $body }};">{{ $greeting }}</p>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td class="sd-pad" style="padding:28px 40px 0 40px; font-size:15px; line-height:1.6; color:{{ $body }};">
                            {{ $slot }}
                        </td>
                    </tr>

                    <tr>
                        <td class="sd-pad" style="padding:36px 40px 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr><td style="height:1px; background-color:{{ $line }}; line-height:1px; font-size:0;">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    {{-- The salon's own footer: who they are and how to reach
                         them. A client with a question about this appointment
                         needs the salon, not us. --}}
                    <tr>
                        <td class="sd-pad" style="padding:22px 40px 36px 40px;">
                            <p style="margin:0 0 6px 0; font-size:13px; font-weight:600; color:{{ $ink }};">{{ $brand['name'] }}</p>

                            @foreach ($lines as $footerLine)
                                <p style="margin:0 0 3px 0; font-size:12px; line-height:1.6; color:{{ $muted }};">{{ $footerLine }}</p>
                            @endforeach

                            @isset($closing)
                                <p style="margin:12px 0 0 0; font-size:12px; line-height:1.6; color:{{ $muted }};">{{ $closing }}</p>
                            @endisset
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
