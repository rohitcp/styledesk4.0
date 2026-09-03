<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Booking;
use App\Models\BookingPayment;

/**
 * How StyleDesk takes money, whoever is processing it.
 *
 * The seam the payment brief calls its key architectural decision: StyleDesk
 * owns the payment experience, and a processor handles card data, merchant
 * verification, processing and payouts. Booking, Checkout, Sales and Client
 * talk to this and never to a vendor SDK, so adding Square — or Adyen, or
 * whatever a business already uses — is a new class here rather than a change
 * to four modules.
 *
 * Two kinds of implementation are expected and both are first-class:
 *
 *   - A PROCESSOR (Stripe, Square) actually moves the money, and its methods
 *     talk to an API that can refuse, retry or take a moment.
 *   - A RECORDER (cash, Zelle, a card taken on the terminal beside the till)
 *     does not move anything. The money has already arrived; StyleDesk is
 *     writing down that it did. Those methods always succeed, because there
 *     is nothing to fail.
 *
 * Modelling the second as a degenerate case of the first is what keeps the
 * till honest: a salon that never connects a processor still has a complete
 * transaction history, and one that connects a processor later does not have
 * to have its old payments rewritten.
 */
interface PaymentGateway
{
    /** The key this gateway is configured and stored under. */
    public function key(): string;

    /** Its name, as a salon owner would say it. */
    public function label(): string;

    /**
     * Whether this gateway can be used right now.
     *
     * A processor that has not been connected is not usable, and saying so is
     * how the checkout offers a method it can actually complete rather than
     * failing at the last step.
     */
    public function isReady(): bool;

    /**
     * Whether money moves through it.
     *
     * False for a recorder. The difference decides whether the checkout asks
     * for card details, whether a refund can be issued from StyleDesk, and
     * whether the figure belongs in a payout reconciliation.
     */
    public function processes(): bool;

    /**
     * The payment methods this gateway can take.
     *
     * @return array<int, string> Keys from config('payments.methods')
     */
    public function methods(): array;

    /**
     * Take money against a booking.
     *
     * Returns the stored transaction. An implementation that talks to an API
     * must record the attempt whatever the answer — a payment that failed on
     * the wire is one the desk has to know about, and a row written only on
     * success leaves them believing a client was charged when they were not.
     *
     * @throws PaymentFailed when the money did not move and nothing was recorded
     */
    public function charge(Booking $booking, PaymentRequest $request): BookingPayment;

    /**
     * Give some of it back.
     *
     * Partial by default: a full refund is a partial one for the whole
     * amount, and one method rather than two means the reason, the note and
     * the audit trail cannot diverge between them.
     *
     * @throws PaymentFailed when the refund was refused
     */
    public function refund(BookingPayment $payment, int $amountMinor, ?string $reason = null, ?string $note = null): BookingPayment;

    /**
     * A link the client can pay from, or null where the gateway cannot make
     * one — a recorder cannot, because there is nothing on the other end of
     * the link to take the money.
     */
    public function paymentLink(Booking $booking, int $amountMinor): ?string;
}
