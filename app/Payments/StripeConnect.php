<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Tenant;
use App\Models\TenantStripeAccount;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Connecting a business's own Stripe account under the StyleDesk platform.
 *
 * Stripe hosts the onboarding. That is not laziness: it is identity
 * verification, bank details and tax information, and building forms for those
 * would make StyleDesk responsible for collecting and storing exactly the data
 * Connect exists to keep out of the platform.
 *
 * The owner presses Connect, answers Stripe, and comes back. What StyleDesk
 * keeps is an account id and Stripe's own answers about what that account can
 * do.
 */
class StripeConnect
{
    public function __construct(private readonly StripeClientFactory $clients) {}

    /**
     * The platform's own client.
     *
     * Onboarding is always platform work: creating an account under StyleDesk,
     * and linking an owner off to Stripe to verify it. A business on its own
     * key never comes through here — it already has an account.
     */
    private function stripe(): StripeClient
    {
        $client = $this->clients->platform();

        if ($client === null) {
            throw new PaymentFailed(__('payments.stripe.platform_not_configured'));
        }

        return $client;
    }

    /**
     * The account for this business, made if it has none.
     *
     * `express` rather than `standard`: the salon gets a Stripe-hosted
     * dashboard and StyleDesk stays the place they run their business from,
     * which is the arrangement the brief describes.
     *
     * @throws PaymentFailed
     */
    public function account(Tenant $tenant, ?int $userId = null): TenantStripeAccount
    {
        if (($existing = StripeGateway::accountFor($tenant)) !== null) {
            return $existing;
        }

        try {
            $account = $this->stripe()->accounts->create([
                'type' => 'express',
                'email' => $tenant->business_email ?: null,
                'business_profile' => array_filter([
                    'name' => $tenant->name,
                    'url' => $tenant->website,
                ]),
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'metadata' => ['tenant_id' => (string) $tenant->getTenantKey()],
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        return TenantStripeAccount::create([
            'tenant_id' => $tenant->getTenantKey(),
            'stripe_account_id' => $account->id,
            'business_name' => $tenant->name,
            'connected_by' => $userId,
            'connected_at' => now(),
        ]);
    }

    /**
     * Where to send the owner to finish.
     *
     * Single-use and short-lived by Stripe's design, so it is made fresh each
     * time rather than stored — a link kept on the row would be one somebody
     * follows next week and finds expired.
     *
     * @throws PaymentFailed
     */
    public function onboardingUrl(TenantStripeAccount $account, string $returnUrl, string $refreshUrl): string
    {
        try {
            return $this->stripe()->accountLinks->create([
                'account' => $account->stripe_account_id,
                'return_url' => $returnUrl,
                'refresh_url' => $refreshUrl,
                'type' => 'account_onboarding',
            ])->url;
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }
    }

    /**
     * Where the owner manages the account afterwards.
     *
     * Stripe's own dashboard: payouts, bank details, disputes. StyleDesk does
     * not rebuild any of it.
     *
     * @throws PaymentFailed
     */
    public function dashboardUrl(TenantStripeAccount $account): string
    {
        try {
            return $this->stripe()->accounts->createLoginLink($account->stripe_account_id)->url;
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }
    }

    /**
     * Ask Stripe what this account can do now, and write it down.
     *
     * Copied locally so the checkout asks a database a local question rather
     * than making an API call on every page load — and refreshed by the
     * account.updated webhook, because a business that fails verification a
     * week later must stop being offered as ready.
     */
    public function sync(TenantStripeAccount $account): TenantStripeAccount
    {
        try {
            $remote = $this->stripe()->accounts->retrieve($account->stripe_account_id);
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        return $this->apply($account, $remote->toArray());
    }

    /**
     * Stripe's answer, applied to the row.
     *
     * Shared by the sync and the webhook so a change arriving either way is
     * stored identically — two writers with two shapes is how a connected
     * account ends up disagreeing with itself.
     *
     * @param  array<string, mixed>  $remote
     */
    public function apply(TenantStripeAccount $account, array $remote): TenantStripeAccount
    {
        $bank = data_get($remote, 'external_accounts.data.0');

        $account->forceFill([
            'business_name' => data_get($remote, 'business_profile.name') ?: $account->business_name,
            'country' => data_get($remote, 'country'),
            'default_currency' => data_get($remote, 'default_currency'),
            'payout_last4' => data_get($bank, 'last4'),
            'charges_enabled' => (bool) data_get($remote, 'charges_enabled'),
            'payouts_enabled' => (bool) data_get($remote, 'payouts_enabled'),
            'details_submitted' => (bool) data_get($remote, 'details_submitted'),
            'requirements' => data_get($remote, 'requirements'),
            'synced_at' => now(),
        ])->save();

        return $account;
    }
}
