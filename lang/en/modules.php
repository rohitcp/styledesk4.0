<?php

declare(strict_types=1);

/*
| The App Settings landing page: group headings and module cards.
|
| Every English value here is the string config/app_settings.php already
| carried. This is a translation layer, not a rewrite — a value that differed
| would change the product's copy under cover of adding a language.
|
| The config keeps its own literals as a last-resort fallback, so a module
| added without a translation renders its own name rather than a key.
*/

return [
    'groups' => [
        'business_setup' => [
            'name' => 'Business Setup',
            'description' => 'Who you are, where you work and how you present the business.',
        ],
        'booking_operations' => [
            'name' => 'Booking & Operations',
            'description' => 'The rules that decide what can be booked, by whom and when.',
        ],
        'clients_experience' => [
            'name' => 'Clients & Experience',
            'description' => 'What your clients see, fill in and buy.',
        ],
        'communication' => [
            'name' => 'Communication',
            'description' => 'What StyleDesk sends, to whom, and how it reads.',
        ],
        'finance' => [
            'name' => 'Finance',
            'description' => 'Taking money, and everything that follows it.',
        ],
        'administration' => [
            'name' => 'Administration',
            'description' => 'Who can do what, and what happens to your data.',
        ],
        'developer_integrations' => [
            'name' => 'Developer & Integrations',
            'description' => 'Connecting StyleDesk to everything else.',
        ],
    ],

    'modules' => [
        'business' => [
            'name' => 'Business',
            'description' => 'Business name, type, contact details and operating configuration.',
        ],
        'locations' => [
            'name' => 'Locations',
            'description' => 'Branches, addresses, location managers, operating hours and contact details.',
        ],
        'business-hours' => [
            'name' => 'Business Hours',
            'description' => 'Opening and closing times, split shifts, holidays and temporary closures.',
        ],
        'branding' => [
            'name' => 'Branding',
            'description' => 'Logo, favicon and brand colours across the app, emails and receipts.',
        ],
        'languages' => [
            'name' => 'Languages',
            'description' => 'Set the primary application language and choose the additional languages available to your team.',
        ],
        'currency' => [
            'name' => 'Currency',
            'description' => 'Your primary currency and the secondary currencies you price in.',
        ],
        'booking-rules' => [
            'name' => 'Booking Rules',
            'description' => 'Intervals, notice periods, booking windows and appointment rules.',
        ],
        'services' => [
            'name' => 'Service Categories',
            'description' => 'The categories your price list is organised into, including which are offered and the order they appear in.',
        ],
        'resources' => [
            'name' => 'Resource Categories',
            'description' => 'The categories your bookable assets are grouped into — chairs, rooms, equipment — including which are offered and the order they appear in.',
        ],
        'staff' => [
            'name' => 'Staff Members',
            'description' => 'Team members, locations, working hours, service access and employment status.',
        ],
        'calendar-scheduling' => [
            'name' => 'Calendar & Scheduling',
            'description' => 'Calendar behaviour, scheduling defaults and how appointments display.',
        ],
        'cancellation-no-show' => [
            'name' => 'Cancellation & No-Show',
            'description' => 'Cancellation policies and windows, fees and no-show handling.',
        ],
        'clients' => [
            'name' => 'Clients',
            'description' => 'Client defaults, preferences and how client records are configured.',
        ],
        'client-booking' => [
            'name' => 'Client Booking',
            'description' => 'The client-facing booking experience and what clients can do themselves.',
        ],
        'online-booking' => [
            'name' => 'Online Booking',
            'description' => 'Public booking availability, page behaviour and online booking rules.',
        ],
        'forms' => [
            'name' => 'Forms',
            'description' => 'Intake, consent and consultation forms, and when clients are asked to fill them.',
        ],
        'memberships' => [
            'name' => 'Membership',
            'description' => 'Whether the business sells memberships, where they can be bought, what happens to unused credits and what cancelling one means.',
        ],
        'packages' => [
            'name' => 'Packages',
            'description' => 'Bundled services, how packages are sold and how sessions are drawn down.',
        ],
        'gift-cards' => [
            'name' => 'Gift Cards',
            'description' => 'Gift card values, expiry, redemption rules and defaults.',
        ],
        'loyalty-rewards' => [
            'name' => 'Loyalty & Rewards',
            'description' => 'Points, rewards, earning rules and how clients redeem them.',
        ],
        'notifications' => [
            'name' => 'Notifications',
            'description' => 'Email and in-app notification behaviour, and which events notify who.',
        ],
        'email-settings' => [
            'name' => 'Email Settings',
            'description' => 'Sender name and address, reply-to and email defaults.',
        ],
        'email-templates' => [
            'name' => 'Email Templates',
            'description' => 'Confirmation, reminder, cancellation, reschedule, invitation and welcome emails.',
        ],
        'sms-settings' => [
            'name' => 'SMS Settings',
            'description' => 'SMS sender, message defaults and when text messages are sent.',
        ],
        'payments' => [
            'name' => 'Payments',
            'description' => 'Accepted payment methods, deposits, payment behaviour and defaults.',
        ],
        'taxes' => [
            'name' => 'Taxes',
            'description' => 'Tax rates, what they apply to and tax-related defaults.',
        ],
        'tips' => [
            'name' => 'Tips',
            'description' => 'Tipping options, suggested percentages and how tips are shared.',
        ],
        'receipts-invoices' => [
            'name' => 'Receipts & Invoices',
            'description' => 'Numbering, formatting and what appears on receipts and invoices.',
        ],
        'inventory' => [
            'name' => 'Inventory',
            'description' => 'Stock defaults, low-stock behaviour and retail product settings.',
        ],
        'roles-permissions' => [
            'name' => 'Roles & Permissions',
            'description' => 'Control what Owners, Admins, Managers, Receptionists, Service Providers and custom roles can access.',
        ],
        'security' => [
            'name' => 'Security',
            'description' => 'Session behaviour, sign-in policies and application security settings.',
        ],
        'data-privacy' => [
            'name' => 'Data & Privacy',
            'description' => 'Data retention, client consent and privacy configuration.',
        ],
        'import-export' => [
            'name' => 'Import & Export',
            'description' => 'Bring data in, take data out and run migrations.',
        ],
        'system-preferences' => [
            'name' => 'System Preferences',
            'description' => 'General StyleDesk behaviour and application-wide defaults.',
        ],
        'integrations' => [
            'name' => 'Integrations',
            'description' => 'Third-party integrations and connected services.',
        ],
        'api-webhooks' => [
            'name' => 'API & Webhooks',
            'description' => 'API access, keys, webhook endpoints and developer integrations.',
        ],
    ],

    /*
     * Module status badges.
     *
     * Keyed by the status in config/app_settings.php, so a new status is a
     * key here rather than a branch in a view.
     */
    'statuses' => [
        'active' => 'Active',
        'setup-required' => 'Setup required',
        'coming-soon' => 'Coming soon',
        'view-only' => 'View only',
    ],

    /*
     * The live figures under a card.
     *
     * Pluralised with Laravel's | syntax rather than by Str::plural(), which
     * only knows English. "1 idioma|idiomas" is the same sentence in
     * Spanish and cannot be derived from the English one.
     */
    'counts' => [
        'active_staff' => '{1} active member|[2,*] active members',
        'pending_invites' => '{1} pending invite|[2,*] pending invites',
        'roles' => '{1} role|[2,*] roles',
        'active_locations' => '{1} active location|[2,*] active locations',
        'upcoming_closures' => '{1} upcoming closure|[2,*] upcoming closures',
        'enabled_currencies' => '{1} currency|[2,*] currencies',
        'enabled_languages' => '{1} language|[2,*] languages',
    ],
];
