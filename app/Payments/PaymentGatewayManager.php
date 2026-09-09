<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Tenant;

/**
 * Which gateway a business is using.
 *
 * Resolved per tenant, because a salon that has connected Stripe and one that
 * has not are the same application with different answers here — not two code
 * paths. Everything that takes money asks this rather than naming a processor.
 *
 * A business always has a gateway. Where none is connected the answer is the
 * manual one, which records money that arrived by other means; there is no
 * "no gateway" state for the checkout to branch on.
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    /**
     * The gateway this business takes card payments through.
     *
     * Falls back to recording rather than to nothing: a processor that has
     * been chosen but not finished connecting must not stop the desk taking
     * cash.
     */
    public function for(?Tenant $tenant): PaymentGateway
    {
        $chosen = $tenant?->payment_gateway ?: config('payments.default');
        $gateway = $this->make($chosen);

        return $gateway !== null && $gateway->isReady() ? $gateway : $this->make('manual');
    }

    /**
     * The vault this business can save cards in, or null where it has none.
     *
     * Null is the honest answer for a salon taking cash and card at the
     * terminal: there is nowhere to keep a card that could be charged again
     * next month, and every screen that offers Card on File has to be able to
     * find that out rather than failing at the last step.
     *
     * A gateway that is chosen but not finished connecting is not a vault
     * either — `for()` has already fallen back to recording by then.
     */
    public function vault(?Tenant $tenant): ?VaultsCards
    {
        $gateway = $this->for($tenant);

        return $gateway instanceof VaultsCards && $gateway->isReady() ? $gateway : null;
    }

    /**
     * Every gateway a business could use, whether or not it is connected.
     *
     * The settings screen lists them all: one that cannot be used yet says so,
     * where hiding it leaves an owner hunting for Square.
     *
     * @return array<int, PaymentGateway>
     */
    public function all(): array
    {
        return collect(array_keys(config('payments.gateways')))
            ->map(fn (string $key) => $this->make($key))
            ->filter()
            ->values()
            ->all();
    }

    public function make(?string $key): ?PaymentGateway
    {
        if ($key === null) {
            return null;
        }

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $class = config('payments.gateways.'.$key.'.driver');

        if (! $class || ! class_exists($class)) {
            return $this->resolved[$key] = null;
        }

        /*
         * A driver whose deployment-level prerequisites are missing is not
         * built at all.
         *
         * Constructing it would mean constructing its SDK client, and those
         * refuse an empty key by throwing — so a deployment with no Stripe
         * secret could not even render the settings screen that explains it
         * has no Stripe secret.
         */
        if (method_exists($class, 'isConfigured') && ! $class::isConfigured()) {
            return $this->resolved[$key] = null;
        }

        return $this->resolved[$key] = app($class);
    }
}
