<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | What an administrator may do
    |--------------------------------------------------------------------------
    |
    | The whole catalogue, grouped the way the navigation is. Every Backoffice
    | route names one of these, and a route that names none is a route nobody
    | thought about — so the middleware refuses rather than defaulting to
    | allow.
    |
    | Adding a module means adding its permissions here and listing them in the
    | roles below. Nothing else in the application has to change.
    |
    */

    'permissions' => [
        'dashboard' => ['dashboard.view'],
        'clients' => ['clients.view', 'clients.manage'],
        'plans' => ['plans.view', 'plans.manage'],
        'billing' => ['billing.view', 'billing.manage'],
        'settings' => ['settings.view', 'settings.manage'],
        'admins' => ['admins.view', 'admins.manage'],
        'audit' => ['audit.view'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Two are in use for MVP 1.0; the other three are the ones the brief names
    | as coming later, defined now because defining them is what makes them
    | cheap to turn on. A role is a name and a list — there is no role table,
    | because the list is deployed with the code and a second copy in the
    | database is a second answer to the same question.
    |
    | `*` is every permission there is, including ones added after this file
    | was written. Only the Super Owner has it, and only the Super Owner may
    | act on another Super Owner — that rule is in the model rather than here,
    | because it is about who a person is and not about what they may reach.
    |
    */

    'roles' => [
        'super-owner' => [
            'permissions' => ['*'],
        ],

        'admin' => [
            'permissions' => [
                'dashboard.view',
                'clients.view', 'clients.manage',
                'plans.view', 'plans.manage',
                'billing.view', 'billing.manage',
                'settings.view', 'settings.manage',
                /* May see who the administrators are, and may not change
                   them: managing colleagues is the Super Owner's. */
                'admins.view',
                'audit.view',
            ],
        ],

        'billing-admin' => [
            'permissions' => ['dashboard.view', 'clients.view', 'plans.view', 'billing.view', 'billing.manage'],
        ],

        'support-admin' => [
            'permissions' => ['dashboard.view', 'clients.view', 'clients.manage', 'plans.view', 'billing.view'],
        ],

        'read-only' => [
            'permissions' => ['dashboard.view', 'clients.view', 'plans.view', 'billing.view', 'audit.view'],
        ],
    ],

    /** The roles offered when an administrator is created, in this order. */
    'assignable_roles' => ['admin', 'billing-admin', 'support-admin', 'read-only'],

    /*
    |--------------------------------------------------------------------------
    | Switching a client off
    |--------------------------------------------------------------------------
    |
    | Why a business was disabled, as a fixed list rather than free text. Two
    | reasons: "non-payment" spelled eleven ways cannot be counted, and a
    | reason chosen from a list is one the next administrator reads the same
    | way the last one meant it. The note beside it carries the particulars.
    |
    | `other` is last and is the only one that makes the note mandatory — a
    | reason of "Other" with nothing beside it says nothing at all.
    |
    | Keys are stored; the labels live in the backoffice lang files so they
    | translate.
    | Adding a reason means adding a key here and a line there.
    |
    */

    'disable_reasons' => [
        'non_payment',
        'payment_failed',
        'subscription_cancelled',
        'trial_expired',
        'chargeback',
        'tos_violation',
        'fraud',
        'client_requested',
        'business_closed',
        'duplicate_account',
        'compliance',
        'administrative',
        'other',
    ],

    /** The reason that must be explained in the note rather than by itself. */
    'disable_reason_requiring_note' => 'other',

    /*
    |--------------------------------------------------------------------------
    | The one-time code in front of the sign-in
    |--------------------------------------------------------------------------
    */

    'verification' => [
        /* Six digits. Long enough that guessing is hopeless against the
           attempt limit below, short enough to be read off a phone. */
        'code_length' => 6,

        'ttl_minutes' => (int) env('BACKOFFICE_CODE_TTL_MINUTES', 10),

        /* Wrong guesses before the code itself is burnt. The connection is
           throttled as well, but a limit that only counts requests lets an
           attacker spread guesses across addresses. */
        'max_attempts' => 5,

        /* How long a verified email stays verified before the second step is
           asked again. Long enough to survive a mistyped password, short
           enough that a shared machine is not left standing open. */
        'window_minutes' => (int) env('BACKOFFICE_VERIFY_WINDOW_MINUTES', 30),

        /* Requests per minute, per address and per IP. Both, because either
           alone is trivially worked around. */
        'throttle' => '5,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    |
    | Shorter than the salon application's. A console that can suspend a
    | customer's account should not be left signed in on an unattended desk
    | all afternoon.
    |
    */

    'session_timeout_minutes' => (int) env('BACKOFFICE_SESSION_TIMEOUT_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | The Super Owner
    |--------------------------------------------------------------------------
    |
    | Seeded so a fresh install has somebody who can sign in. The password is
    | never written here or anywhere else in source: the seeder reads it from
    | the environment, and generates a single-use one it prints once when the
    | environment does not set it.
    |
    */

    'super_owner' => [
        'name' => env('BACKOFFICE_SUPER_OWNER_NAME', 'Rohit Philip'),
        'email' => env('BACKOFFICE_SUPER_OWNER_EMAIL', 'rohitcphilip@gmail.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | The navigation
    |--------------------------------------------------------------------------
    |
    | One list, read by the layout. A module hidden from somebody who cannot
    | reach it — rather than shown and then refused, which teaches a reader
    | that the product is broken instead of that the door is closed.
    |
    */

    'navigation' => [
        ['key' => 'dashboard', 'route' => 'backoffice.dashboard', 'permission' => 'dashboard.view', 'icon' => 'grid'],
        ['key' => 'clients', 'route' => 'backoffice.clients.index', 'permission' => 'clients.view', 'icon' => 'building'],
        ['key' => 'plans', 'route' => 'backoffice.plans.index', 'permission' => 'plans.view', 'icon' => 'layers'],
        ['key' => 'billing', 'route' => 'backoffice.billing.index', 'permission' => 'billing.view', 'icon' => 'card'],
        ['key' => 'settings', 'route' => 'backoffice.settings.index', 'permission' => 'settings.view', 'icon' => 'cog'],
    ],
];
