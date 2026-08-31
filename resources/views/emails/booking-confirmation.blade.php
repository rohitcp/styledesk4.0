{{--
    The client's copy of an appointment.

    Inline styles and a table layout, for the same reason as every other mail
    in the app: a class-based layout arrives unstyled in most inboxes, and
    this one is read on a phone on the way out of the salon.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('bookings.email.headline') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f7f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#0f0f10;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $booking->date->translatedFormat('l j F') }} · {{ $booking->timeLabel() }}
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
                                {{ __('bookings.email.headline') }}
                            </h1>
                            <p style="margin:8px 0 0 0; font-size:14px; color:#4b5563;">
                                {{ __('bookings.email.intro', ['name' => $booking->clientName()]) }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px 0 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; font-size:14px;">
                                @foreach ([
                                    __('bookings.summary.reference') => $booking->reference,
                                    __('bookings.summary.services') => $booking->services->pluck('name')->implode(', '),
                                    __('bookings.summary.staff') => $booking->staff?->displayName() ?? __('bookings.any_staff'),
                                    __('bookings.summary.when') => $booking->date->translatedFormat('l j F Y').' · '.$booking->timeLabel(),
                                    __('bookings.summary.location') => $booking->location?->name,
                                ] as $label => $value)
                                    @if ($value)
                                        <tr>
                                            <td style="padding:6px 0; color:#6b7280; width:40%;">{{ $label }}</td>
                                            <td style="padding:6px 0; color:#0f0f10; font-weight:600;">{{ $value }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px 24px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="width:100%; font-size:14px; border-top:1px solid #e5e7eb; margin-top:8px;">
                                @foreach ($totals->lines() as $line)
                                    <tr>
                                        <td style="padding:6px 0; color:{{ ($line['strong'] ?? false) ? '#0f0f10' : '#6b7280' }};">{{ $line['label'] }}</td>
                                        <td align="right" style="padding:6px 0; color:#0f0f10; font-weight:{{ ($line['strong'] ?? false) ? '700' : '600' }};">{{ $line['value'] }}</td>
                                    </tr>
                                @endforeach

                                {{-- What is settled and what is not. A confirmation that
                                     says nothing about money leaves the client to guess
                                     whether they still owe it. --}}
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280;">
                                        {{ $booking->dueMinor() > 0 ? __('bookings.summary.due') : __('bookings.summary.paid') }}
                                    </td>
                                    <td align="right" style="padding:6px 0; color:{{ $booking->dueMinor() > 0 ? '#b45309' : '#15803d' }}; font-weight:700;">
                                        {{ $booking->dueMinor() > 0 ? $due : $paid }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0 0; font-size:12px; color:#9ca3af;">{{ $businessName }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
