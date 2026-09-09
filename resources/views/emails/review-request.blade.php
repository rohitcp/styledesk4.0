{{--
    "How was your visit?", sent to the client.

    Inline styles and a table layout, like every other mail in the app: a
    class-based layout arrives unstyled in most inboxes, and this one is read
    on a phone within a minute of arriving.

    The five stars are five links. The first tap is the answer — the page it
    opens has already recorded it and asks only whether they want to add
    anything. A client who would not have opened a form still leaves a rating.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('reviews.email.headline') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f7f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#0f0f10;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ __('reviews.email.preview') }}
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
                                {{ __('reviews.email.headline') }}
                            </h1>
                            <p style="margin:10px 0 0 0; font-size:14px; line-height:1.6; color:#374151;">
                                {{ __('reviews.email.intro', ['name' => $clientName, 'business' => $businessName]) }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:8px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="width:100%; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0; font-size:13px; color:#374151;">
                                            {{ $booking->date->translatedFormat('l j F Y') }} · {{ $booking->timeLabel() }}
                                        </p>
                                        <p style="margin:4px 0 0 0; font-size:13px; color:#6b7280;">
                                            {{ $booking->services->pluck('name')->implode(', ') }}
                                            @if ($booking->staff)
                                                · {{ $booking->staff->first_name }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:22px 24px 4px 24px;">
                            <p style="margin:0 0 12px 0; font-size:14px; font-weight:600; color:#0f0f10;">
                                {{ __('reviews.email.rate') }}
                            </p>
                            @foreach ($starUrls as $rating => $starUrl)
                                <a href="{{ $starUrl }}"
                                   aria-label="{{ trans_choice('reviews.stars', $rating, ['count' => $rating]) }}"
                                   style="display:inline-block; padding:6px 8px; font-size:30px; line-height:1;
                                          text-decoration:none; color:#f5a623;">★</a>
                            @endforeach
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:12px 24px 4px 24px;">
                            <a href="{{ $url }}"
                               style="display:inline-block; padding:12px 22px; background-color:#3d348b; color:#ffffff;
                                      font-size:14px; font-weight:600; text-decoration:none; border-radius:8px;">
                                {{ __('reviews.email.cta') }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 24px 24px 24px;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#9ca3af; word-break:break-all;">
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
