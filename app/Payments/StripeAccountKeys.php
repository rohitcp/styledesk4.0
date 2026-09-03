<?php

declare(strict_types=1);

namespace App\Payments;

use Stripe\Exception\ApiErrorException;

/**
 * Checking a key a business has pasted in.
 *
 * A key is trusted only after Stripe has said what it is. Storing an unchecked
 * one leaves a salon believing it can take payments until the first client
 * tries — which is the worst possible moment to find out.
 *
 * The answer doubles as the account's details, so the settings card can show
 * the business name and payout account without a second call.
 */
class StripeAccountKeys
{
    public function __construct(private readonly StripeClientFactory $clients) {}

    /**
     * What this key belongs to.
     *
     * @return array<string, mixed> Stripe's account object
     *
     * @throws PaymentFailed when the key is refused or cannot charge
     */
    public function verify(string $apiKey): array
    {
        $client = $this->clients->make($apiKey);

        if ($client === null) {
            throw new PaymentFailed(__('payments.stripe.key_empty'));
        }

        try {
            /* `retrieve` with no id asks "who am I", which is the only call
               that works on every key and tells us everything we need. */
            $account = $client->accounts->retrieve();
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        return $account->toArray();
    }
}
