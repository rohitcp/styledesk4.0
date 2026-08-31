{{--
    The published-schedule email.

    Hand-written table layout with inline styles rather than the app
    stylesheet: mail clients strip <link> and most ignore <style>, so a
    class-based layout arrives unstyled. Nothing here depends on flexbox or
    CSS variables, because this has to survive being rendered by Outlook.

    Every measurement is stated twice on purpose — once as an attribute and
    once in the style — because Outlook honours the attribute and everything
    else honours the style.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f7f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#0f0f10;">

    {{-- The grey line an inbox shows beside the subject. Hidden in the body
         itself, or it reads as a stray duplicate sentence. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ __('schedule.email.preheader', ['from' => $startsOn, 'until' => $endsOn]) }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="width:100%; background-color:#f6f7f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                       style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden;">

                    <tr>
                        <td style="padding:32px 40px 8px 40px;">
                            <p style="margin:0 0 4px 0; font-size:13px; color:#6b7280;">{{ $businessName }}</p>
                            <h1 style="margin:0; font-size:24px; line-height:1.25; font-weight:700; color:#0f0f10;">
                                {{ $headline }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 40px 0 40px; font-size:15px; line-height:1.6; color:#3f4451;">
                            <p style="margin:0 0 16px 0;">{{ __('schedule.email.greeting', ['name' => $staffName]) }}</p>
                            <p style="margin:0;">{{ $intro }}</p>
                        </td>
                    </tr>

                    {{-- The period at a glance, before the day-by-day list: the
                         first question asked of a rota is how many hours it
                         comes to, and counting them down a list is the reader
                         doing the arithmetic. --}}
                    <tr>
                        <td style="padding:24px 40px 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="width:100%; border:1px solid #e5e7eb; border-radius:10px;">
                                @php
                                    $facts = array_filter([
                                        __('schedule.email.period') => $startsOn.' – '.$endsOn,
                                        __('schedule.email.location') => $locationName,
                                        __('schedule.email.working_days') => (string) $workingDays,
                                        /* trans_choice, not __: the string is
                                           a plural set, and __ hands the
                                           reader the whole "{1} :count
                                           Hour|[2,*] :count Hours" line.
                                           Chosen on a whole number and
                                           printed with the exact one, the way
                                           the publish dialog does it — half
                                           an hour matches neither branch. */
                                        __('schedule.email.total_hours') => trans_choice(
                                            'schedule.email.hours', (int) ceil($totalHours), ['count' => $totalHours],
                                        ),
                                        /* When it was sent and who sent it: a
                                           rota that has been through two
                                           versions is read by its date, and
                                           the name is who to ask about it. */
                                        __('schedule.email.published_on') => $publishedOn,
                                        __('schedule.email.published_by') => $publishedBy,
                                    ]);
                                @endphp

                                @foreach ($facts as $term => $value)
                                    <tr>
                                        <td style="padding:12px 16px; font-size:13px; color:#6b7280; @if (! $loop->last) border-bottom:1px solid #e5e7eb; @endif">
                                            {{ $term }}
                                        </td>
                                        <td align="right" style="padding:12px 16px; font-size:14px; font-weight:600; color:#0f0f10; @if (! $loop->last) border-bottom:1px solid #e5e7eb; @endif">
                                            {{ $value }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Days off are listed too. A schedule showing only the
                         working days leaves the reader counting backwards to
                         find out whether Saturday is theirs. --}}
                    <tr>
                        <td style="padding:28px 40px 0 40px;">
                            <h2 style="margin:0 0 12px 0; font-size:15px; font-weight:700; color:#0f0f10;">
                                {{ __('schedule.email.daily_schedule') }}
                            </h2>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="width:100%; border-top:1px solid #e5e7eb;">
                                @foreach ($days as $day)
                                    <tr>
                                        <td valign="top"
                                            style="padding:10px 0; font-size:14px; color:#0f0f10; border-bottom:1px solid #e5e7eb;">
                                            {{ $day['label'] }}
                                        </td>
                                        <td valign="top" align="right"
                                            style="padding:10px 0; font-size:14px; color:#3f4451; border-bottom:1px solid #e5e7eb;">
                                            @if ($day['periods'] === [])
                                                <span style="color:#9ca3af;">{{ __('schedule.email.not_working') }}</span>
                                            @else
                                                @foreach ($day['periods'] as $period)
                                                    <span style="display:block; font-weight:600; color:#0f0f10;">{{ $period }}</span>
                                                @endforeach

                                                {{-- The total only where it adds something: on a
                                                     single block it is the same sum twice, on a
                                                     split day it is the answer. --}}
                                                @if (count($day['periods']) > 1)
                                                    <span style="display:block; font-size:12px; color:#6b7280;">
                                                        {{ trans_choice('schedule.email.day_total', (int) ceil($day['hours']), ['count' => $day['hours']]) }}
                                                    </span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    @if ($scheduleUrl)
                        <tr>
                            <td align="center" style="padding:28px 40px 0 40px;">
                                <a href="{{ $scheduleUrl }}"
                                   style="display:inline-block; padding:13px 28px; background-color:#3d348b; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px;">
                                    {{ __('schedule.email.cta') }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:28px 40px 32px 40px; font-size:13px; line-height:1.6; color:#6b7280;">
                            <p style="margin:0;">{{ __('schedule.email.questions', ['business' => $businessName]) }}</p>
                        </td>
                    </tr>
                </table>

                @if ($supportEmail)
                    <p style="margin:20px 0 0 0; font-size:12px; color:#9ca3af;">
                        {{ $supportEmail }}
                    </p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
