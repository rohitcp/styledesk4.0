<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Support\Facades\DB;

/**
 * Money that arrived without StyleDesk moving it.
 *
 * Cash in the drawer, a bank transfer, a card taken on the terminal beside the
 * till. StyleDesk is not charging anybody here — it is writing down that money
 * arrived, which is why nothing in this class can fail on the wire and why it
 * needs no credentials.
 *
 * This is what every StyleDesk business has before it connects a processor,
 * and what it keeps afterwards: a salon with Stripe connected still takes cash.
 * Treating it as a real gateway rather than as "the absence of one" is what
 * lets the checkout, the Sales table and the transaction history be written
 * once instead of branching on whether a processor exists.
 */
class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return __('payments.gateways.manual.name');
    }

    /** Always. Recording money needs nothing set up. */
    public function isReady(): bool
    {
        return true;
    }

    public function processes(): bool
    {
        return false;
    }

    /** @return array<int, string> */
    public function methods(): array
    {
        return collect(config('payments.methods'))
            ->reject(fn (array $method) => $method['gateway_only'] ?? false)
            ->keys()
            ->all();
    }

    public function charge(Booking $booking, PaymentRequest $request): BookingPayment
    {
        return DB::transaction(function () use ($booking, $request) {
            $payment = $booking->payments()->create([
                'tenant_id' => $booking->tenant_id,
                'method' => $request->method,
                /* Paid the moment it is written, because it was paid before
                   it was written — there is no pending state for money
                   already in the drawer. */
                'status' => 'paid',
                'amount_minor' => $request->amountMinor,
                'tip_minor' => $request->tipMinor,
                'currency_code' => $booking->currency_code,
                'received_minor' => $request->receivedMinor,
                'change_minor' => $request->changeMinor(),
                'reference' => $request->reference,
                'note' => $request->note,
                'paid_at' => now(),
                'recorded_by' => $request->userId,
            ]);

            /* Written down as part of the same transaction: a payment that
               lands without the booking's status moving is a bill the desk
               will chase twice. */
            $booking->load('payments');
            $booking->settlePaymentStatus();

            return $payment;
        });
    }

    /**
     * Money handed back the way it came.
     *
     * A second row rather than an edit to the first: the original payment
     * happened, and a history that rewrites it cannot answer "what did we
     * actually take in March".
     */
    public function refund(BookingPayment $payment, int $amountMinor, ?string $reason = null, ?string $note = null): BookingPayment
    {
        return DB::transaction(function () use ($payment, $amountMinor, $reason, $note) {
            $refund = $payment->booking->payments()->create([
                'tenant_id' => $payment->tenant_id,
                'method' => $payment->method,
                'status' => 'refunded',
                'amount_minor' => $amountMinor,
                'currency_code' => $payment->currency_code,
                'note' => trim(($reason ? $reason.' — ' : '').(string) $note) ?: null,
                'paid_at' => now(),
                'recorded_by' => auth()->id(),
            ]);

            $payment->booking->load('payments');
            $payment->booking->settlePaymentStatus();

            return $refund;
        });
    }

    /**
     * Nothing to link to.
     *
     * A payment link is a page that takes money, and this gateway does not
     * take any — offering one would send the client somewhere that cannot
     * help them.
     */
    public function paymentLink(Booking $booking, int $amountMinor): ?string
    {
        return null;
    }
}
