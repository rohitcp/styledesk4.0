<?php

declare(strict_types=1);
use App\Payments\ManualGateway;
use App\Payments\StripeGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Who processes the money
    |--------------------------------------------------------------------------
    |
    | StyleDesk owns the payment experience; a processor handles card data,
    | merchant verification, processing and payouts. Nothing outside
    | App\Payments names one of these — the booking, checkout, sales and client
    | modules talk to the PaymentGateway interface, so a new processor is a new
    | class rather than a change to four modules.
    |
    | `manual` is not the absence of a gateway. It is money that arrived
    | without StyleDesk moving it — cash, a transfer, a card taken on the
    | terminal beside the till — and every business has it, including those
    | with a processor connected.
    |
    */

    'default' => 'manual',

    'gateways' => [
        'manual' => [
            'driver' => ManualGateway::class,
            'available' => true,
        ],

        /*
         * Stripe Connect — the brief's primary recommendation, and not built.
         *
         * Listed so the settings screen can say "not connected yet" rather
         * than pretend the choice does not exist. Turning this on needs three
         * things StyleDesk does not have: the stripe/stripe-php package, a
         * Connect platform account, and its keys. See the driver's absence as
         * the honest state rather than a gap to paper over — a settings card
         * offering Connect that lands on an error is worse than one saying
         * "coming soon".
         */
        'stripe' => [
            'driver' => StripeGateway::class,
            'available' => true,
        ],

        /* Square — Phase 2. Many salons already own the readers. */
        'square' => [
            'driver' => null,
            'available' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway is not method
    |--------------------------------------------------------------------------
    |
    | Two different questions, and conflating them is how "Cash" ends up
    | needing Stripe. A method is how the client paid; a gateway is who moved
    | the money, if anybody did.
    |
    | `gateway_only` marks a method that cannot simply be recorded — nobody
    | walks in holding an Apple Pay, so offering it without a processor is
    | offering something the desk cannot complete.
    |
    */

    'methods' => [
        'card' => ['gateway_only' => false],
        'cash' => ['gateway_only' => false],
        'apple_pay' => ['gateway_only' => true],
        'google_pay' => ['gateway_only' => true],
        'gift_card' => ['gateway_only' => false],
        'store_credit' => ['gateway_only' => false],
        'paypal' => ['gateway_only' => false],
        'zelle' => ['gateway_only' => false],
        'venmo' => ['gateway_only' => false],
        'cash-app' => ['gateway_only' => false],
        'external' => ['gateway_only' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | What a movement of money is
    |--------------------------------------------------------------------------
    |
    | Every one creates a transaction. The type is what it was for; the status
    | is where it got to. Keeping them apart is what lets "deposit" and
    | "refund" be counted separately from "did it go through".
    |
    */

    'transaction_types' => [
        'payment', 'deposit', 'balance', 'tip', 'gift_card_purchase',
        'gift_card_redemption', 'refund', 'partial_refund', 'cancellation_fee',
        'no_show_fee', 'adjustment', 'store_credit', 'external',
    ],

    'transaction_statuses' => [
        'pending', 'authorized', 'paid', 'partially_paid', 'failed',
        'cancelled', 'refunded', 'partially_refunded', 'disputed',
    ],

    /*
    |--------------------------------------------------------------------------
    | The platform's cut
    |--------------------------------------------------------------------------
    |
    | Nothing is taken. The shape exists so that enabling it later is a config
    | change rather than a migration and a re-reconciliation of every
    | transaction ever recorded — which is the whole reason the brief asks for
    | it now rather than when it is wanted.
    |
    */

    'platform_fee' => [
        'enabled' => false,
        'fixed_minor' => 0,
        'percent' => 0.0,
    ],
];
