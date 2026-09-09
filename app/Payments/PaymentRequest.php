<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * What the till is asking for.
 *
 * A value object rather than a bag of arguments, because these travel together
 * through the checkout, the gateway and the transaction row — and because a
 * tip is not the salon's money in the same way the bill is, so it must stay
 * beside the amount rather than folded into it. A total that has absorbed a
 * tip can never be taken apart again.
 *
 * Minor units throughout. Money in this application is integers; a float that
 * has been through a form and a JSON encoder is not the amount anybody typed.
 */
class PaymentRequest
{
    public function __construct(
        /** What is being paid towards the bill. */
        public readonly int $amountMinor,

        /** A key from config('payments.methods'). */
        public readonly string $method,

        /** Owed to whoever did the work, kept apart from the bill. */
        public readonly int $tipMinor = 0,

        /** Cash handed over, where more than the bill was handed over. */
        public readonly ?int $receivedMinor = null,

        /** The processor's own id, or a note somebody typed. */
        public readonly ?string $reference = null,

        public readonly ?string $note = null,

        /**
         * A saved card to charge, where the gateway holds one.
         *
         * A gateway token, never a card number — StyleDesk stores references
         * and nothing else. See the brief: never a raw number, never a CVV.
         */
        public readonly ?string $paymentMethodToken = null,

        /** Who pressed the button. Null means StyleDesk itself. */
        public readonly ?int $userId = null,
    ) {}

    /** The whole handover: what the bill takes and what the tip adds. */
    public function totalMinor(): int
    {
        return $this->amountMinor + $this->tipMinor;
    }

    /** What goes back, when more was handed over than was owed. */
    public function changeMinor(): ?int
    {
        return $this->receivedMinor === null
            ? null
            : max(0, $this->receivedMinor - $this->totalMinor());
    }
}
