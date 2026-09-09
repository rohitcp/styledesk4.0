<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | How a business sends email to its clients
    |--------------------------------------------------------------------------
    |
    | Two ways, and exactly one of them is in use at a time. A business with
    | both configured still has one default, because "which address did that
    | go out from" must have a single answer.
    |
    | The labels are the reader's, not the plumbing's: "Connect Gmail", never
    | "Gmail SMTP". A salon owner is connecting their email account, not
    | configuring a mail server, and naming it after the protocol makes a
    | simple thing sound like a job for their nephew.
    |
    */

    'providers' => [
        'styledesk' => [
            /* Needs nothing connected, so it is the one a business can use on
               the day they sign up — and the one MVP 1.0 ships. */
            'available' => true,
        ],

        'gmail' => [
            /*
             * Available only where the platform holds Google credentials.
             *
             * They are StyleDesk's, not the salon's, so this is a deployment
             * question rather than a per-business one. Without them the card
             * reads "coming soon" instead of offering a Connect button that
             * lands on a Google error page — which is the honest answer, since
             * on that deployment it genuinely is not available yet.
             *
             * Being available is not the same as being connected: a business
             * still has to connect its own mailbox, and ClientEmailSender
             * checks that separately.
             */
            'available' => (bool) env('GOOGLE_CLIENT_ID')
                && (bool) env('GOOGLE_CLIENT_SECRET')
                && (bool) env('GOOGLE_REDIRECT_URI'),
        ],
    ],

    'default_provider' => 'styledesk',

    /*
    |--------------------------------------------------------------------------
    | Where a message got to
    |--------------------------------------------------------------------------
    |
    | Queued → Sent → Delivered, or Failed. Delivered is only honest once a
    | provider tells us so; without webhooks nothing may claim it, which is why
    | a message stops at Sent rather than quietly promoting itself.
    |
    | Opened, clicked, replied and bounced come with the provider that can
    | report them.
    |
    */

    'statuses' => [
        'queued' => ['tone' => 'soon'],
        'sent' => ['tone' => 'active'],
        'delivered' => ['tone' => 'active'],
        'failed' => ['tone' => 'setup'],
    ],

    /*
    |--------------------------------------------------------------------------
    | What may be written into a template
    |--------------------------------------------------------------------------
    |
    | The whole list, so the drawer can offer them and the renderer can refuse
    | anything else. A variable nobody defined is left standing in the text
    | rather than blanked: "{{ balance_due }}" reaching a client is a bug
    | somebody reports, and an empty space is one nobody notices.
    |
    */

    'variables' => [
        'client_first_name',
        'client_last_name',
        'business_name',
        'booking_date',
        'booking_time',
        'booking_reference',
        'service_name',
        'staff_name',
        'balance_due',
    ],

    /*
    |--------------------------------------------------------------------------
    | The templates a business starts with
    |--------------------------------------------------------------------------
    |
    | Keys only. Subject and body live in the language files so they translate
    | — a Spanish salon should not be sent English scaffolding to edit.
    |
    | These are starting points the sender edits in the drawer before sending.
    | Editable stored templates are their own screen and their own phase.
    |
    */

    'templates' => [
        'appointment_follow_up',
        'appointment_information',
        'payment_reminder',
        'outstanding_balance',
        'thank_you',
        'service_follow_up',
        'membership_information',
        'general_message',
    ],

    /** Guard rails on the drawer's two required fields. */
    'limits' => [
        'subject' => 200,
        'message' => 10000,
    ],
];
