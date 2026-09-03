<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /**
     * Transactional SMS.
     *
     * Sendivo has no first-party Laravel package, so this is consumed by a
     * custom notification channel rather than a driver Laravel knows about.
     */
    'sendivo' => [
        'key' => env('SENDIVO_API_KEY'),
        'sender_id' => env('SENDIVO_SENDER_ID', 'StyleDesk'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Connect Gmail.
    |
    | One set of credentials for the whole platform — a StyleDesk Google Cloud
    | project — not one per salon. A business connects its own mailbox through
    | them; the tokens that come back are the tenant's and live on
    | tenant_gmail_connections.
    |
    | Absent credentials disable the provider rather than breaking it: the
    | settings card says "coming soon" instead of offering a Connect button
    | that lands on a Google error page.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    | Stripe Connect.
    |
    | One StyleDesk platform account; each business connects its own account
    | under it, and money goes to the salon's bank rather than through
    | StyleDesk's. See App\Payments\StripeGateway.
    |
    | Absent keys disable the processor rather than breaking it: the settings
    | card says "not available yet" instead of offering a Connect button that
    | lands on a Stripe error page.
    */
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
