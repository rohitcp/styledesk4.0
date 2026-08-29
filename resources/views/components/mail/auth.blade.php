{{--
    The authentication email layout.

    One template for every account and security message — verification, reset,
    sign-in, password-changed, welcome — so they arrive looking like the same
    product rather than like five different senders.

    Hand-written tables with inline styles, not the app stylesheet: mail
    clients strip <link>, most ignore <style>, and Outlook renders through
    Word. Nothing here depends on flexbox, CSS variables or classes. The
    invitation email carries the same warning and learned it the same way.

    Every measurement below is stated twice on purpose — once as an attribute
    and once in the style — because Outlook honours the attribute and
    everything else honours the style.
--}}
@props([
    'preheader' => null,
    'headline',
    'greeting' => null,
    'ctaLabel' => null,
    'ctaUrl' => null,
    'supporting' => null,
    /** The line about what StyleDesk will never ask for. On by default. */
    'security' => true,
])

@php
    $brand = '#3d348b';
    $ink = '#0f0f10';
    $body = '#3f4451';
    $muted = '#6b7280';
    $line = '#e5e7eb';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $headline }}</title>

    {{-- Honoured by the clients that support it, ignored safely by the rest.
         The mobile rules only shrink padding and type; nothing depends on
         them, so Outlook getting none of it still reads correctly. --}}
    <style>
        @media only screen and (max-width: 600px) {
            .sd-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .sd-headline { font-size: 26px !important; line-height: 1.2 !important; }
            .sd-cta a { display: block !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#ffffff; -webkit-font-smoothing:antialiased; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:{{ $ink }};">

    {{-- The grey line an inbox shows beside the subject. Hidden in the body
         itself, or it reads as a stray duplicate sentence. --}}
    @if ($preheader)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">{{ $preheader }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;">
        <tr>
            <td align="center" style="padding:0;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                       style="width:100%; max-width:600px; text-align:left;">

                    {{-- Logo, top left. Small and unobtrusive: it says who is
                         writing, it is not the message. --}}
                    <tr>
                        <td class="sd-pad" style="padding:32px 40px 0 40px;">
                            <a href="{{ url('/') }}" style="text-decoration:none;">
                                <img src="{{ asset('images/styledesk-logo.svg') }}" alt="StyleDesk"
                                     width="104" height="26"
                                     style="display:block; width:104px; height:26px; border:0; outline:none;">
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td class="sd-pad" style="padding:48px 40px 0 40px;">
                            <h1 class="sd-headline" style="margin:0; font-size:30px; line-height:1.2; font-weight:700; letter-spacing:-0.4px; color:{{ $ink }};">
                                {{ $headline }}
                            </h1>
                        </td>
                    </tr>

                    @if ($greeting)
                        <tr>
                            <td class="sd-pad" style="padding:32px 40px 0 40px;">
                                <p style="margin:0; font-size:16px; line-height:1.6; color:{{ $body }};">{{ $greeting }}</p>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td class="sd-pad" style="padding:16px 40px 0 40px; font-size:16px; line-height:1.6; color:{{ $body }};">
                            {{ $slot }}
                        </td>
                    </tr>

                    @if ($ctaLabel && $ctaUrl)
                        <tr>
                            <td class="sd-cta" align="center" style="padding:40px 40px 0 40px;">
                                {{-- A table around the link, because Outlook
                                     will not put padding on an <a>. --}}
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                                    <tr>
                                        <td align="center" bgcolor="{{ $brand }}" style="border-radius:26px;">
                                            <a href="{{ $ctaUrl }}"
                                               style="display:inline-block; padding:15px 32px; font-size:16px; font-weight:600; line-height:20px; color:#ffffff; text-decoration:none; border-radius:26px; background-color:{{ $brand }};">
                                                {{ $ctaLabel }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        {{-- The same destination as plain text. A button that
                             a client refuses to render is a dead end without
                             it. --}}
                        <tr>
                            <td class="sd-pad" style="padding:24px 40px 0 40px;">
                                <p style="margin:0; font-size:13px; line-height:1.6; color:{{ $muted }};">
                                    If the button does not work, copy and paste this link into your browser:<br>
                                    <a href="{{ $ctaUrl }}" style="color:{{ $brand }}; word-break:break-all;">{{ $ctaUrl }}</a>
                                </p>
                            </td>
                        </tr>
                    @endif

                    @if ($supporting)
                        <tr>
                            <td class="sd-pad" style="padding:40px 40px 0 40px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:{{ $muted }};">{{ $supporting }}</p>
                            </td>
                        </tr>
                    @endif

                    @isset($support)
                        <tr>
                            <td class="sd-pad" style="padding:32px 40px 0 40px;">
                                <p style="margin:0 0 4px 0; font-size:14px; font-weight:600; color:{{ $ink }};">Need help?</p>
                                <p style="margin:0; font-size:14px; line-height:1.6; color:{{ $muted }};">{{ $support }}</p>
                            </td>
                        </tr>
                    @endisset

                    {{-- Divider, then the footer. --}}
                    <tr>
                        <td class="sd-pad" style="padding:56px 40px 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr><td style="height:1px; background-color:{{ $line }}; line-height:1px; font-size:0;">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="sd-pad" style="padding:24px 40px 40px 40px;">
                            <p style="margin:0 0 8px 0; font-size:13px; font-weight:600; color:{{ $ink }};">StyleDesk</p>

                            @if ($security)
                                <p style="margin:0 0 12px 0; font-size:12px; line-height:1.6; color:{{ $muted }};">
                                    StyleDesk will never ask you to send your password by email.
                                </p>
                            @endif

                            <p style="margin:0 0 12px 0; font-size:12px; line-height:1.6; color:{{ $muted }};">
                                <a href="{{ url('/privacy') }}" style="color:{{ $muted }};">Privacy Policy</a>
                                &nbsp;·&nbsp;
                                <a href="{{ url('/terms') }}" style="color:{{ $muted }};">Terms of Service</a>
                            </p>

                            <p style="margin:0; font-size:12px; line-height:1.6; color:{{ $muted }};">
                                &copy; {{ date('Y') }} StyleDesk. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
