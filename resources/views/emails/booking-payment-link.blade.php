{{--
    How to pay for an appointment, sent to the client.

    Inline styles and a table layout, for the same reason as every other mail
    in the app: a class-based layout arrives unstyled in most inboxes, and
    this one is read on a phone.

    The amount is the link's own rather than the booking's balance. What was
    asked for on the day it was sent is not rewritten by what changed after.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('bookings.email.link_headline') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f7f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#0f0f10;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $amount }} · {{ $booking->date->translatedFormat('l j F') }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#f6f7f9;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"
                       style="width:560px; max-width:100%; background-color:#ffffff; border:1px solid #e5e7eb; border-radius:10px;">
                    <tr>
                        <td style="padding:24px 24px 8px 24px;">
                            <p style="margin:0; font-size:13px; color:#6b7280;">{{ $businessName }}</p>
                            <h1 style="margin:6px 0 0 0; font-size:20px; line-height:1.3; color:#0f0f10;">
                                {{ __('bookings.email.link_headline') }}
                            </h1>
                            <p style="margin:10px 0 0 0; font-size:14px; line-height:1.6; color:#374151;">
                                {{ __('bookings.email.link_intro', ['name' => $booking->clientName()]) }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="width:100%; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0; font-size:12px; color:#6b7280;">{{ __('bookings.email.link_amount') }}</p>
                                        <p style="margin:4px 0 0 0; font-size:26px; font-weight:700; color:#0f0f10;">{{ $amount }}</p>
                                        <p style="margin:12px 0 0 0; font-size:13px; color:#374151;">
                                            {{ $booking->date->translatedFormat('l j F Y') }} · {{ $booking->timeLabel() }}
                                        </p>
                                        <p style="margin:4px 0 0 0; font-size:13px; color:#6b7280;">
                                            {{ $booking->services->pluck('name')->implode(', ') }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 24px 4px 24px;">
                            <a href="{{ $url }}"
                               style="display:inline-block; padding:12px 22px; background-color:#3d348b; color:#ffffff;
                                      font-size:14px; font-weight:600; text-decoration:none; border-radius:8px;">
                                {{ __('bookings.email.link_cta') }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 24px 24px 24px;">
                            {{-- Said plainly, because a link with no stated life
                                 is one somebody finds in an old inbox in March. --}}
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#6b7280;">
                                {{ __('bookings.email.link_expiry', ['when' => $link->expires_at?->translatedFormat('j M Y') ?? '']) }}
                            </p>
                            <p style="margin:8px 0 0 0; font-size:12px; line-height:1.6; color:#9ca3af; word-break:break-all;">
                                {{ $url }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
