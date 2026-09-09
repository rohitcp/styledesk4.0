<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use App\Payments\PaymentGatewayManager;

/**
 * What a business's payments are allowed to do.
 *
 * One place, because the answer has three parts and every screen that asks
 * needs all three: is the feature built at all, has this business switched it
 * on, and can the gateway it is connected to actually do it.
 *
 * Getting the last one wrong is what produces a Payment Link button on a
 * salon that takes cash — a button with nothing at the other end of it.
 */
class PaymentCapabilities
{
    /**
     * Whether this business can do one thing right now.
     *
     * Unknown keys are false rather than true. A screen asking about a
     * capability that does not exist has a bug in it, and answering "yes" to
     * a question nobody defined is how that bug reaches a client.
     */
    public static function allows(?Tenant $tenant, string $capability): bool
    {
        $definition = self::definition($capability);

        if ($definition === null || ! $definition['available']) {
            return false;
        }

        /* A recorder cannot take a payment link, hold a card or charge one
           again next month. Nothing the business switches on changes that. */
        if ($definition['needs_processor'] && ! self::hasProcessor($tenant)) {
            return false;
        }

        return in_array($capability, self::enabled($tenant), true);
    }

    /**
     * The capabilities this business has switched on.
     *
     * Null in the column means "whatever is on by default", which is the
     * honest answer for a business that has never opened the screen — the
     * alternative is every existing salon waking up with everything off.
     *
     * @return list<string>
     */
    public static function enabled(?Tenant $tenant): array
    {
        $stored = $tenant?->payment_capabilities;

        return is_array($stored) ? array_values($stored) : self::defaults();
    }

    /** @return list<string> */
    public static function defaults(): array
    {
        return self::all()
            ->filter(fn (array $definition) => $definition['available'] && $definition['default'])
            ->keys()
            ->all();
    }

    /** Every capability a save may actually name. */
    public static function selectable(): array
    {
        return self::all()
            ->filter(fn (array $definition) => $definition['available'])
            ->keys()
            ->all();
    }

    /**
     * Every capability with what a screen needs to draw it.
     *
     * Grouped as the settings screen groups them: in-person needs hardware or
     * somebody at a terminal, online needs a page a client can reach, and the
     * two are switched on at different times.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function grouped(?Tenant $tenant): array
    {
        $enabled = self::enabled($tenant);
        $processor = self::hasProcessor($tenant);

        return collect(config('payments.capabilities'))
            ->map(fn (array $group) => collect($group)
                ->map(fn (array $definition, string $key) => $definition + [
                    'key' => $key,
                    'enabled' => in_array($key, $enabled, true),
                    /* Why it cannot be switched on, where it cannot. Named so
                       the screen can say which thing to go and fix rather
                       than showing a switch that refuses. */
                    'blocked_by' => match (true) {
                        ! $definition['available'] => 'unbuilt',
                        $definition['needs_processor'] && ! $processor => 'no_processor',
                        default => null,
                    },
                ])
                ->all())
            ->all();
    }

    /** @return array<string, mixed>|null */
    private static function definition(string $capability): ?array
    {
        return self::all()->get($capability);
    }

    /** Every capability, flattened out of its group. */
    private static function all()
    {
        return collect(config('payments.capabilities'))
            ->flatMap(fn (array $group) => $group);
    }

    /** Whether money can actually move, as opposed to being written down. */
    private static function hasProcessor(?Tenant $tenant): bool
    {
        return app(PaymentGatewayManager::class)->for($tenant)->processes();
    }
}
