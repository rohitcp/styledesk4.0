<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookingPaymentLink;
use App\Support\BookingTotals;
use App\Support\Money;
use Illuminate\Contracts\View\View;

/**
 * The page a client lands on from a payment link.
 *
 * Outside auth, and deliberately thin. The token is the whole credential, so
 * the page shows one appointment and the money owed on it — no client record,
 * no history, no way to reach anything the token does not name. Somebody who
 * finds this link in a forwarded email learns that an appointment exists and
 * what it costs, and nothing further.
 *
 * It takes no money. StyleDesk charges nothing anywhere and this is no
 * exception: what it shows is where to send it — the business's own accounts,
 * the same ones the payment panel reads out at the desk. The link is marked
 * paid when somebody records the money against the booking.
 */
class PaymentLinkController extends Controller
{
    public function show(string $token): View
    {
        $link = BookingPaymentLink::withoutGlobalScopes()
            ->with(['booking.services', 'booking.location', 'booking.staff', 'booking.payments'])
            ->where('token', $token)
            ->first();

        abort_if($link === null, 404);

        /* Read before it is stamped, so a link opened for the first time
           still renders as the live page it is rather than as "opened". */
        $status = $link->currentStatus();

        $link->settleAgainst($link->booking);
        $link->markOpened();

        $tenant = $link->booking->tenant;
        $totals = BookingTotals::for($link->booking);

        return view('bookings.pay-link', [
            'link' => $link,
            'booking' => $link->booking,
            'businessName' => $tenant?->name ?? config('app.name'),
            'status' => $status,
            'expired' => $link->hasExpired(),
            'settled' => $link->booking->dueMinor() <= 0,
            'amount' => $totals->money((int) $link->amount_minor),
            'due' => $totals->money($link->booking->dueMinor()),
            /* Where to send it. Only the methods this business has actually
               set up — a page offering Venmo to a salon with no Venmo is a
               page that wastes somebody's afternoon. */
            'handles' => collect(config('bookings.methods'))
                ->filter(fn (array $method) => $method['handle'] !== null)
                ->map(fn (array $method, string $key) => [
                    'name' => __('bookings.methods.'.$key.'.name'),
                    'handle' => (string) ($tenant?->{$method['handle']} ?? ''),
                ])
                ->filter(fn (array $row) => $row['handle'] !== '')
                ->values()
                ->all(),
            'currency' => Money::symbol($link->booking->currency_code),
        ]);
    }
}
