{{--
    StyleDesk Standard — the one email shell.

    Every email the product sends wears this: booking, payment, form, gift
    card, and a message a receptionist typed by hand. The content blocks
    change; the shell does not. That is deliberate — one polished responsive
    design beats five with inconsistent quality, and it means a fix to how
    Outlook renders a button reaches every business at once.

    The business owns the content and the branding. StyleDesk owns the width,
    the spacing, the typography, the button, the responsive behaviour and the
    email-client compatibility, so a salon owner cannot ship a broken email.

    Hand-written tables with inline styles, not the app stylesheet: mail
    clients strip <link>, most ignore <style>, and Outlook renders through
    Word. Nothing here depends on flexbox, CSS variables or classes. Every
    measurement is stated twice on purpose — once as an attribute and once in
    the style — because Outlook honours the attribute and everything else
    honours the style.
--}}
@props([
    /** From App\Support\EmailBrand: name, logo, primary, ink. */
    'brand',
    'heading',
    'preheader' => null,
    'ctaLabel' => null,
    'ctaUrl' => null,
    /** Address, phone, email, website — whatever the business has. */
    'contactLines' => [],
])

@php
    $theme = config('email_templates.theme');

    $ink = '#0f0f10';
    $body = '#3f4451';
    $muted = '#6b7280';
    $line = '#e5e7eb';
    $width = (int) $theme['width'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $heading }}</title>

    <style>
        @media only screen and (max-width: 620px) {
            .sd-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .sd-heading { font-size: 24px !important; line-height: 1.25 !important; }
            .sd-cta a { display: block !important; }

            /* The details card goes from two columns to one. Each cell is
               already a label above its value, so stacking is the whole
               change — nothing has to re-pair. */
            .sd-cell {
                display: block !important;
                width: 100% !important;
                padding-right: 0 !important;
            }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{{ $theme['background'] }}; -webkit-font-smoothing:antialiased; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:{{ $ink }};">

    {{-- The grey line an inbox shows beside the subject. Hidden in the body
         itself, or it reads as a stray duplicate sentence. --}}
    @if ($preheader)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">{{ $preheader }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="width:100%; background-color:{{ $theme['background'] }};">
        <tr>
            <td align="center" style="padding:32px 12px;">

                <table role="presentation" width="{{ $width }}" cellpadding="0" cellspacing="0" border="0"
                       style="width:100%; max-width:{{ $width }}px; background-color:#ffffff; border-radius:12px;">

                    {{-- ------------------------------------------- header --}}
                    {{-- The logo, or the name in words. A broken image icon
                         where a logo should be is worse than no logo. --}}
                    <tr>
                        <td align="center" class="sd-pad" style="padding:32px 40px 8px 40px;">
                            @if ($brand['logo'])
                                <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}"
                                     height="{{ $theme['logo_max_height'] }}"
                                     style="display:block; border:0; max-height:{{ $theme['logo_max_height'] }}px; width:auto;">
                            @else
                                <p style="margin:0; font-size:18px; font-weight:700; letter-spacing:-0.01em; color:{{ $ink }};">
                                    {{ $brand['name'] }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    {{-- ------------------------------------------ heading --}}
                    <tr>
                        <td class="sd-pad" style="padding:20px 40px 0 40px;">
                            <h1 class="sd-heading" style="margin:0; font-size:27px; line-height:1.25; font-weight:700; letter-spacing:-0.02em; color:{{ $ink }};">
                                {{ $heading }}
                            </h1>
                        </td>
                    </tr>

                    {{-- ------------------------------------------- body --}}
                    <tr>
                        <td class="sd-pad" style="padding:16px 40px 0 40px; font-size:15px; line-height:1.65; color:{{ $body }};">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- --------------------------------------------- CTA --}}
                    @if ($ctaLabel && $ctaUrl)
                        <tr>
                            <td align="center" class="sd-pad sd-cta" style="padding:28px 40px 0 40px;">
                                {{-- The brand colour, with an ink computed to
                                     stay legible on it — which is what stops a
                                     pale brand shipping white on white. --}}
                                <a href="{{ $ctaUrl }}"
                                   style="display:inline-block; background-color:{{ $brand['primary'] }}; color:{{ $brand['ink'] }}; text-decoration:none; font-size:15px; font-weight:600; padding:14px 32px; border-radius:8px;">
                                    {{ $ctaLabel }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    {{-- --------------------------------- supporting slot --}}
                    @isset($supporting)
                        <tr>
                            <td class="sd-pad" style="padding:28px 40px 0 40px; font-size:14px; line-height:1.65; color:{{ $body }};">
                                {{ $supporting }}
                            </td>
                        </tr>
                    @endisset

                    {{-- ------------------------------------------ contact --}}
                    @if (count(array_filter($contactLines)))
                        <tr>
                            <td class="sd-pad" style="padding:32px 40px 0 40px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                                    <tr><td style="border-top:1px solid {{ $line }}; font-size:0; line-height:0;">&nbsp;</td></tr>
                                </table>

                                <p style="margin:20px 0 0 0; font-size:14px; font-weight:600; color:{{ $ink }};">{{ $brand['name'] }}</p>

                                @foreach (array_filter($contactLines) as $contactLine)
                                    <p style="margin:2px 0 0 0; font-size:13px; line-height:1.6; color:{{ $muted }};">{{ $contactLine }}</p>
                                @endforeach
                            </td>
                        </tr>
                    @endif

                    {{-- ------------------------------------------- footer --}}
                    {{-- Deliberately plain. This is transactional mail about
                         somebody's appointment or account, and marketing-heavy
                         content in it is both wrong and, in some places,
                         against the rules that let it be sent at all. --}}
                    <tr>
                        <td class="sd-pad" style="padding:28px 40px 32px 40px;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:{{ $muted }};">
                                {{ __('email_templates.footer.sent_by', ['name' => $brand['name']]) }}
                            </p>
                            <p style="margin:6px 0 0 0; font-size:12px; line-height:1.6; color:{{ $muted }};">
                                {{ __('email_templates.footer.transactional') }}
                            </p>
                            <p style="margin:6px 0 0 0; font-size:12px; line-height:1.6; color:{{ $muted }};">
                                &copy; {{ now()->year }} {{ $brand['name'] }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
