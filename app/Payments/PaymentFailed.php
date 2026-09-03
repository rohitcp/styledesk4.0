<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * The money did not move.
 *
 * Carries the processor's own words rather than a friendly replacement,
 * because this is what gets written against the transaction for whoever has
 * to work out why a salon could not take a payment — and "something went
 * wrong" has never helped anybody do that. The screen decides what the client
 * sees; this decides what the record says.
 */
class PaymentFailed extends \RuntimeException
{
    public function __construct(string $message, public readonly ?string $gatewayCode = null)
    {
        parent::__construct($message);
    }
}
