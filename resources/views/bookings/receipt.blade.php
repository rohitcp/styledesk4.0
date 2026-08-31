{{--
    The printable copy.

    Its own page rather than the app shell: what is being printed is a
    confirmation to hand over or a receipt to keep, and neither wants a
    navigation rail down the side of it. One template for both, because they
    are the same facts under a different heading and two would drift apart the
    first time a line was added to either.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $booking->reference }} — {{ $isReceipt ? __('bookings.confirmation.receipt_title') : __('bookings.confirmation.title') }}</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            padding: 32px 20px;
            background: #f6f7f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0f0f10;
        }
        .sheet {
            max-width: 520px;
            margin: 0 auto;
            padding: 28px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }
        h1 { margin: 6px 0 0; font-size: 20px; }
        .muted { color: #6b7280; font-size: 13px; margin: 0; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; margin-top: 18px; }
        td { padding: 6px 0; vertical-align: top; }
        td.label { color: #6b7280; width: 45%; }
        td.value { text-align: right; font-weight: 600; }
        .rule td { border-top: 1px solid #e5e7eb; padding-top: 12px; }
        .total td { font-size: 15px; font-weight: 700; }
        .actions { max-width: 520px; margin: 16px auto 0; text-align: right; }
        button {
            font: inherit; font-size: 13px; font-weight: 600; color: #fff; background: #4338ca;
            border: 0; border-radius: 8px; padding: 8px 14px; cursor: pointer;
        }
        /* The button is for the screen; paper does not need it. */
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: 0; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <p class="muted">{{ tenant()?->name ?? config('app.name') }}</p>
        <h1>{{ $isReceipt ? __('bookings.confirmation.receipt_title') : __('bookings.confirmation.title') }}</h1>
        <p class="muted" style="margin-top:6px">{{ $booking->reference }}</p>

        <table>
            @foreach ([
                'client' => $booking->clientName(),
                'services' => $booking->services->pluck('name')->implode(', '),
                'staff' => $booking->staff?->displayName() ?? __('bookings.any_staff'),
                'when' => $booking->date->translatedFormat('l j F Y').' · '.$booking->timeLabel(),
                'duration' => trans_choice('bookings.summary.minutes', (int) $booking->minutes, ['count' => (int) $booking->minutes]),
                'location' => $booking->location?->name,
            ] as $key => $value)
                @if ($value)
                    <tr>
                        <td class="label">{{ __('bookings.summary.'.$key) }}</td>
                        <td class="value">{{ $value }}</td>
                    </tr>
                @endif
            @endforeach

            @foreach ($totals->lines() as $index => $line)
                <tr class="{{ $index === 0 ? 'rule' : '' }} {{ ($line['strong'] ?? false) ? 'total' : '' }}">
                    <td class="label">{{ $line['label'] }}</td>
                    <td class="value">{{ $line['value'] }}</td>
                </tr>
            @endforeach

            @foreach ($booking->payments as $payment)
                <tr class="{{ $loop->first ? 'rule' : '' }}">
                    <td class="label">{{ $payment->methodLabel() }}</td>
                    <td class="value">{{ $payment->amountLabel() }}</td>
                </tr>
            @endforeach

            <tr class="{{ $booking->payments->isEmpty() ? 'rule' : '' }}">
                <td class="label">{{ $booking->dueMinor() > 0 ? __('bookings.summary.due') : __('bookings.summary.paid') }}</td>
                <td class="value">
                    {{ $totals->money($booking->dueMinor() > 0 ? $booking->dueMinor() : $booking->paidMinor()) }}
                </td>
            </tr>
        </table>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('bookings.confirmation.print') }}</button>
    </div>
</body>
</html>
