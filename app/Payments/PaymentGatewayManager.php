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

        return $this->resolved[$key] = $class && class_exists($class) ? app($class) : null;
    }
}
