<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Tenant;
use App\Models\TenantStripeAccount;
use Stripe\StripeClient;

/**
 * Whose key authorises a call, and on whose account.
 *
 * Two arrangements, and getting them the wrong way round is the expensive
 * mistake in this whole feature:
 *
 *   PLATFORM  StyleDesk's own secret key, with `stripe_account` naming the
 *             salon's connected account. The platform is acting on their
 *             behalf.
 *
 *   OWN       The salon's own secret key, and NO `stripe_account` — the key
 *             already is the account. Sending `stripe_account` here would ask
 *             their account to act on behalf of another, which Stripe refuses.
 *
 * Both are answered here so no caller has to remember which it is holding.
 */
class StripeClientFactory
{
    /** @var array<string, StripeClient> */
    private array $clients = [];

    /**
     * The client to use for this business, or null if it cannot pay at all.
     *
     * Null is a real answer: a deployment with no platform key, serving a
     * business that has supplied none of its own, has no way to reach Stripe
     * and should not pretend otherwise.
     */
    public function for(?Tenant $tenant): ?StripeClient
    {
        $account = StripeGateway::accountFor($tenant);

        if ($account?->usesOwnKeys()) {
            return $this->make((string) $account->api_key);
        }

        return $this->platform();
    }

    /** StyleDesk's own client, for Connect onboarding and platform charges. */
    public function platform(): ?StripeClient
    {
        return $this->make((string) config('services.stripe.secret'));
    }

    /**
     * The options every call needs, given how this business is connected.
     *
     * @return array<string, string>
     */
    public function options(?TenantStripeAccount $account): array
    {
        /* On their own key there is nothing to impersonate — the key is the
           account. Naming one would be asking their account to act for
           somebody else. */
        if ($account === null || $account->usesOwnKeys()) {
            return [];
        }

        return ['stripe_account' => (string) $account->stripe_account_id];
    }

    /** A client for one key, or null when there is no key to build it from. */
    public function make(string $key): ?StripeClient
    {
        if ($key === '') {
            return null;
        }

        return $this->clients[$key] ??= new StripeClient([
            'api_key' => $key,
            'stripe_version' => '2024-06-20',
        ]);
    }
}
