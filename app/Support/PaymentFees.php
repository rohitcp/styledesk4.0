<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BookingPayment;

/**
 * What a payment cost, and what was left of it.
 *
 * The two fees are kept apart because a salon owner asks about them
 * separately and gets angry about them separately: "what did Stripe cost me"
 * and "what did StyleDesk charge me" are different questions, and one column
 * holding their sum answers neither.
 *
 * Both are nullable and null is the truth, not zero. A card charge's fee is
 * settled by Stripe minutes or hours after the payment on a balance
 * transaction; until that arrives nobody knows what it was, and writing zero
 * would be a claim the receipt cannot support. Cash has no fee at all, which
 * is a different kind of nothing and reads as zero once recorded.
 */
class PaymentFees
{
    /**
     * Record what a processor took, and work out what landed.
     *
     * Called when the settlement figure arrives — from the charge itself
     * where Stripe expands it, or from a webhook later. Safe to call twice:
     * the same numbers written again are the same numbers.
     */
    public static function record(
        BookingPayment $payment,
        ?int $processorFeeMinor,
        ?int $platformFeeMinor = null,
        ?string $balanceTransactionId = null,
    ): BookingPayment {
        $processor = $processorFeeMinor ?? $payment->processor_fee_minor;
        $platform = $platformFeeMinor ?? $payment->platform_fee_minor;

        $payment->forceFill([
            'processor_fee_minor' => $processor,
            'platform_fee_minor' => $platform,
            /* Only once something is known. Net of no fees is the gross, and
               a column claiming that before Stripe has settled would be
               wrong in the one direction a salon notices. */
            'net_minor' => $processor === null && $platform === null
                ? null
                : self::netMinor($payment, $processor, $platform),
            'balance_transaction_id' => $balanceTransactionId ?? $payment->balance_transaction_id,
        ])->save();

        return $payment;
    }

    /**
     * What actually reaches the salon.
     *
     * The tip is part of it: it was collected on the same card and is paid
     * out in the same settlement, and a net figure that excluded it would not
     * reconcile against the bank.
     */
    public static function netMinor(BookingPayment $payment, ?int $processor, ?int $platform): int
    {
        $gross = (int) $payment->amount_minor + (int) $payment->tip_minor;

        return $gross - (int) $processor - (int) $platform;
    }

    /**
     * The fee breakdown a screen shows, or null where nothing is known yet.
     *
     * Null rather than a row of dashes: a payment whose fees have not settled
     * has nothing to say, and a table of blanks reads as a bug.
     *
     * @return array<string, string>|null
     */
    public static function breakdown(BookingPayment $payment, ?string $currency = null): ?array
    {
        if ($payment->processor_fee_minor === null && $payment->platform_fee_minor === null) {
            return null;
        }

        $currency ??= $payment->currency_code;

        return [
            'processor' => Money::format(((int) $payment->processor_fee_minor) / 100, $currency),
            'platform' => Money::format(((int) $payment->platform_fee_minor) / 100, $currency),
            'net' => Money::format(((int) $payment->net_minor) / 100, $currency),
        ];
    }
}
