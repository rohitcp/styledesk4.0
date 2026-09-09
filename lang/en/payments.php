<?php

declare(strict_types=1);

return [

    'title' => 'Payments',
    'intro' => 'Whether you take payments, who processes them, and what you accept.',

    'enable' => 'Enable Payments',
    'enable_hint' => 'When this is off, StyleDesk records no money against bookings and the checkout is hidden.',

    'processor' => 'Payment Processor',
    'processor_hint' => 'One processor handles your card payments. You can still record cash and transfers whatever you choose.',
    'active' => 'ACTIVE',
    'coming_soon' => 'Coming soon',
    'use_this' => 'Use this',

    'gateways' => [
        'manual' => [
            'name' => 'Record payments only',
            'description' => 'Money that arrives another way — cash, a bank transfer, or a card taken on your own terminal. StyleDesk writes down that it arrived; it does not charge anybody.',
            'unavailable' => '',
        ],
        'stripe' => [
            'name' => 'Stripe',
            'description' => 'Take card payments, Apple Pay and Google Pay online and at the desk, with payouts to your own bank account.',
            'unavailable' => '',
        ],
        'square' => [
            'name' => 'Square',
            'description' => 'Connect the Square account you already use, including your existing readers and terminals.',
            'unavailable' => 'Not available yet. StyleDesk is building this.',
        ],
    ],

    /* Card brands, as a person writes them rather than as a gateway keys
       them. Anything not listed falls back to its own key, tidied up — a new
       brand should read as itself rather than as nothing. */
    /* A client's saved cards. StyleDesk holds the gateway's reference and
       the four digits a receptionist says out loud — never a card number. */
    'methods_list' => [
        'no_vault' => 'No payment processor is connected, so cards cannot be saved.',
        'default_set' => ':card is now the default payment method.',
        'removed' => ':card has been removed.',
        'title' => 'Payment methods',
        'none' => 'No cards saved',
        'none_hint' => 'A card saved here can be charged for membership renewals without the client present.',
        'default' => 'Default',
        'make_default' => 'Set as default',
        'expires' => 'Expires :date',
        'expired' => 'Expired',
        'expiring' => 'Expires this month',
        'needs_attention' => 'Payment method needs attention',
        'add' => 'Add new card',
        'remove' => 'Remove card',
        'remove_confirm' => 'Remove this card? It can no longer be charged.',
        'in_use' => 'This card renews :name. Choose another payment method before removing it.',
        'used_by' => 'Renews :name',
        'gateway' => 'Processed by :name',
        'statuses' => [
            'active' => 'Active',
            'expired' => 'Expired',
            'removed' => 'Removed',
        ],
    ],

    'brands' => [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
        'discover' => 'Discover',
        'diners' => 'Diners Club',
        'jcb' => 'JCB',
        'unionpay' => 'UnionPay',
    ],

    'stripe' => [
        'not_settled' => 'The card was not charged. The payment did not settle.',
        'connect' => 'Connect Stripe',
        'continue' => 'Continue setup',
        'manage' => 'Manage account',
        'disconnect' => 'Disconnect',
        'not_connected' => 'No Stripe account connected yet.',
        'no_account' => 'There is no Stripe account to open.',
        'payout_account' => 'Payouts to •••• :last4',
        'no_payout_account' => 'No payout bank account yet',
        'connected' => 'Stripe is connected. You can now take card payments.',
        'still_needed' => 'Stripe still needs a few details before you can take payments.',
        'disconnected' => 'Stripe has been disconnected. You can still record cash and transfers.',
        'failed' => 'Stripe could not be reached. :reason',
        'not_ready' => 'This business cannot take card payments yet.',
        'outstanding' => 'Stripe still needs: :fields',
        'refunded_at_stripe' => 'Refunded from the Stripe dashboard.',
        'modes' => [
            'platform' => 'Connected through StyleDesk',
            'own' => 'Your own Stripe account',
        ],
        'use_own' => 'Use my own Stripe account instead',
        'replace_keys' => 'Replace my Stripe keys',
        'use_own_hint' => 'Already have Stripe? Paste your keys and StyleDesk will use your account directly. Payments, payouts and disputes stay entirely between you and Stripe.',
        'secret_key' => 'Secret key',
        'publishable_key' => 'Publishable key',
        'save_keys' => 'Save and verify',
        'keys_saved' => 'Your Stripe keys have been saved and verified.',
        'key_rejected' => 'Stripe would not accept that key. :reason',
        'key_empty' => 'No key was given.',
        'key_warning' => 'A secret key can charge, refund and read everything on your Stripe account. StyleDesk encrypts it and never shows it again — treat it like a password and use a restricted key if you would rather limit what StyleDesk can do.',
        'platform_not_configured' => 'StyleDesk is not set up to onboard Stripe accounts.',
        'platform_unavailable' => 'Connecting through StyleDesk is not available on this installation. You can still use your own Stripe account below.',
        'statuses' => [
            'connected' => 'Connected',
            'needs_attention' => 'Needs attention',
            'incomplete' => 'Setup incomplete',
        ],
        /* Said once, because it is the thing an owner most wants to know and
           the reason Connect was chosen over one shared merchant account. */
        'money_note' => 'Payments go straight to your own Stripe account and your own bank. StyleDesk never holds your money.',
    ],

    'methods' => 'Accepted Payment Methods',
    'methods_hint' => 'What your team can choose at the till. Some are processed by your card processor; the rest are collected another way and recorded here.',
    'needs_processor' => 'Needs a connected card processor',

    'method_names' => [
        'card' => 'Credit / debit card',
        'cash' => 'Cash',
        'apple_pay' => 'Apple Pay',
        'google_pay' => 'Google Pay',
        'gift_card' => 'Gift card',
        'store_credit' => 'Store credit',
        'paypal' => 'PayPal',
        'zelle' => 'Zelle',
        'venmo' => 'Venmo',
        'cash-app' => 'Cash App',
        'external' => 'Other / external payment',
    ],

    'deposit' => 'Booking Deposit',
    'deposit_hint' => 'What you ask for up front when a booking is taken.',
    'deposit_type' => 'Deposit',
    'deposit_value' => 'Amount',
    'deposit_types' => [
        'none' => 'No deposit',
        'fixed' => 'Fixed amount',
        'percent' => 'Percentage of the booking',
    ],
    /* The three levels, said once. A service that asks for its own deposit and
       a booking that overrides both are the other two, and an owner who does
       not know the order will not understand why a service ignores this. */
    'deposit_levels' => 'This is the default. A service can ask for its own, and an individual booking can override both.',

    'save' => 'Save',
    'saved' => 'Your payment settings have been saved.',
];
