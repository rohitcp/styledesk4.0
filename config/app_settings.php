<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| App Settings modules
|--------------------------------------------------------------------------
|
| The App Settings landing page is generated entirely from this file: the
| groups, the cards, their icons and what the search box matches. Adding a
| settings module means adding an entry here, not editing a view.
|
| `route` is the module's own page. A module with a null route has not been
| built yet; its card renders with its status badge but is not clickable,
| which is the honest version of a menu entry that leads nowhere.
|
| `keywords` widen the search beyond the visible name and description, so
| "vat" finds Taxes and "logo" finds Branding.
|
*/

return [

    /*
    | Statuses a module card can carry.
    |
    |   active          built and configured
    |   setup-required  built, but the business has not finished it
    |   coming-soon     not built yet
    */
    'statuses' => [
        'active' => ['label' => 'Active', 'class' => 'styledesk_badge--active'],
        'setup-required' => ['label' => 'Setup required', 'class' => 'styledesk_badge--setup'],
        'coming-soon' => ['label' => 'Coming soon', 'class' => 'styledesk_badge--soon'],
    ],

    'groups' => [

        [
            'name' => 'Business Setup',
            'description' => 'Who you are, where you work and how you present the business.',
            'modules' => [
                [
                    'key' => 'business',
                    'name' => 'Business',
                    'description' => 'Business name, type, contact details and operating configuration.',
                    'icon' => 'building',
                    'route' => 'settings.business.show',
                    'status' => 'active',
                    'keywords' => ['company', 'name', 'details', 'profile', 'about'],
                ],
                [
                    'key' => 'locations',
                    'name' => 'Locations',
                    'description' => 'Branches, addresses, location contact details and per-site settings.',
                    'icon' => 'location-dot',
                    'keywords' => ['branch', 'address', 'site', 'salon', 'shop'],
                ],
                [
                    'key' => 'business-hours',
                    'name' => 'Business Hours',
                    'description' => 'Opening and closing times, split shifts, holidays and temporary closures.',
                    'icon' => 'clock',
                    'keywords' => ['opening', 'closing', 'schedule', 'holiday', 'shift', 'closure'],
                ],
                [
                    'key' => 'branding',
                    'name' => 'Branding',
                    'description' => 'Logo, favicon and brand colours across the app, emails and receipts.',
                    'icon' => 'palette',
                    'keywords' => ['logo', 'colour', 'color', 'theme', 'favicon', 'brand'],
                ],
                [
                    'key' => 'languages',
                    'name' => 'Languages',
                    'description' => 'Your primary language and the secondary languages you support.',
                    'icon' => 'language',
                    'keywords' => ['locale', 'translation', 'english', 'arabic', 'spanish'],
                ],
                [
                    'key' => 'currency',
                    'name' => 'Currency',
                    'description' => 'Your primary currency and the secondary currencies you price in.',
                    'icon' => 'coins',
                    'keywords' => ['money', 'price', 'usd', 'gbp', 'eur', 'exchange'],
                ],
            ],
        ],

        [
            'name' => 'Booking & Operations',
            'description' => 'The rules that decide what can be booked, by whom and when.',
            'modules' => [
                [
                    'key' => 'booking-rules',
                    'name' => 'Booking Rules',
                    'description' => 'Intervals, notice periods, booking windows and appointment rules.',
                    'icon' => 'calendar-check',
                    'keywords' => ['interval', 'notice', 'window', 'guest', 'appointment', 'lead time'],
                ],
                [
                    'key' => 'services',
                    'name' => 'Services',
                    'description' => 'Service defaults, categories and how services behave when booked.',
                    'icon' => 'tag',
                    'keywords' => ['treatment', 'category', 'duration', 'price'],
                ],
                [
                    'key' => 'resources',
                    'name' => 'Resources',
                    'description' => 'Chairs, rooms, treatment and massage rooms, equipment and other bookables.',
                    'icon' => 'chair',
                    'keywords' => ['chair', 'room', 'equipment', 'bed', 'station', 'bookable'],
                ],
                [
                    'key' => 'staff',
                    'name' => 'Staff Members',
                    'description' => 'Team members, locations, working hours, service access and employment status.',
                    'icon' => 'users',
                    'route' => 'settings.staff.index',
                    'status' => 'active',
                    'keywords' => ['team', 'employee', 'stylist', 'therapist', 'provider', 'staff', 'hours', 'availability'],
                    // Counts are resolved at render time by the controller;
                    // the config says which to show, not what they are.
                    'counts' => ['active_staff', 'pending_invites'],
                ],
                [
                    'key' => 'calendar-scheduling',
                    'name' => 'Calendar & Scheduling',
                    'description' => 'Calendar behaviour, scheduling defaults and how appointments display.',
                    'icon' => 'calendar-days',
                    'keywords' => ['diary', 'agenda', 'view', 'slot', 'timetable'],
                ],
                [
                    'key' => 'cancellation-no-show',
                    'name' => 'Cancellation & No-Show',
                    'description' => 'Cancellation policies and windows, fees and no-show handling.',
                    'icon' => 'calendar-xmark',
                    'keywords' => ['cancel', 'no show', 'fee', 'policy', 'refund'],
                ],
            ],
        ],

        [
            'name' => 'Clients & Experience',
            'description' => 'What your clients see, fill in and buy.',
            'modules' => [
                [
                    'key' => 'clients',
                    'name' => 'Clients',
                    'description' => 'Client defaults, preferences and how client records are configured.',
                    'icon' => 'address-book',
                    'keywords' => ['customer', 'contact', 'guest', 'record'],
                ],
                [
                    'key' => 'client-booking',
                    'name' => 'Client Booking',
                    'description' => 'The client-facing booking experience and what clients can do themselves.',
                    'icon' => 'user-clock',
                    'keywords' => ['self service', 'reschedule', 'customer booking'],
                ],
                [
                    'key' => 'online-booking',
                    'name' => 'Online Booking',
                    'description' => 'Public booking availability, page behaviour and online booking rules.',
                    'icon' => 'globe',
                    'keywords' => ['public', 'website', 'widget', 'link', 'booking page'],
                ],
                [
                    'key' => 'forms',
                    'name' => 'Forms',
                    'description' => 'Intake, consent and consultation forms, and when clients are asked to fill them.',
                    'icon' => 'clipboard-list',
                    'keywords' => ['intake', 'consent', 'consultation', 'waiver', 'questionnaire'],
                ],
                [
                    'key' => 'memberships',
                    'name' => 'Memberships',
                    'description' => 'Membership tiers, recurring benefits and how memberships are applied.',
                    'icon' => 'id-card',
                    'keywords' => ['subscription', 'recurring', 'tier', 'plan'],
                ],
                [
                    'key' => 'packages',
                    'name' => 'Packages',
                    'description' => 'Bundled services, how packages are sold and how sessions are drawn down.',
                    'icon' => 'box',
                    'keywords' => ['bundle', 'course', 'session', 'block'],
                ],
                [
                    'key' => 'gift-cards',
                    'name' => 'Gift Cards',
                    'description' => 'Gift card values, expiry, redemption rules and defaults.',
                    'icon' => 'gift',
                    'keywords' => ['voucher', 'gift', 'redeem', 'certificate'],
                ],
                [
                    'key' => 'loyalty-rewards',
                    'name' => 'Loyalty & Rewards',
                    'description' => 'Points, rewards, earning rules and how clients redeem them.',
                    'icon' => 'star',
                    'keywords' => ['points', 'reward', 'redeem', 'referral', 'stamp'],
                ],
            ],
        ],

        [
            'name' => 'Communication',
            'description' => 'What StyleDesk sends, to whom, and how it reads.',
            'modules' => [
                [
                    'key' => 'notifications',
                    'name' => 'Notifications',
                    'description' => 'Email and in-app notification behaviour, and which events notify who.',
                    'icon' => 'bell',
                    'keywords' => ['alert', 'reminder', 'push', 'in app'],
                ],
                [
                    'key' => 'email-settings',
                    'name' => 'Email Settings',
                    'description' => 'Sender name and address, reply-to and email defaults.',
                    'icon' => 'envelope',
                    'keywords' => ['smtp', 'sender', 'from', 'reply to', 'mail'],
                ],
                [
                    'key' => 'email-templates',
                    'name' => 'Email Templates',
                    'description' => 'Confirmation, reminder, cancellation, reschedule, invitation and welcome emails.',
                    'icon' => 'envelope-open-text',
                    'keywords' => ['template', 'confirmation', 'reminder', 'welcome', 'invitation', 'wording'],
                ],
                [
                    'key' => 'sms-settings',
                    'name' => 'SMS Settings',
                    'description' => 'SMS sender, message defaults and when text messages are sent.',
                    'icon' => 'comment-sms',
                    'keywords' => ['text', 'message', 'mobile', 'sendivo', 'phone'],
                ],
            ],
        ],

        [
            'name' => 'Finance',
            'description' => 'Taking money, and everything that follows it.',
            'modules' => [
                [
                    'key' => 'payments',
                    'name' => 'Payments',
                    'description' => 'Accepted payment methods, deposits, payment behaviour and defaults.',
                    'icon' => 'credit-card',
                    'keywords' => ['card', 'deposit', 'stripe', 'cash', 'checkout', 'pay'],
                ],
                [
                    'key' => 'taxes',
                    'name' => 'Taxes',
                    'description' => 'Tax rates, what they apply to and tax-related defaults.',
                    'icon' => 'percent',
                    'keywords' => ['vat', 'gst', 'rate', 'sales tax'],
                ],
                [
                    'key' => 'tips',
                    'name' => 'Tips',
                    'description' => 'Tipping options, suggested percentages and how tips are shared.',
                    'icon' => 'hand-holding-dollar',
                    'keywords' => ['gratuity', 'tip', 'service charge'],
                ],
                [
                    'key' => 'receipts-invoices',
                    'name' => 'Receipts & Invoices',
                    'description' => 'Numbering, formatting and what appears on receipts and invoices.',
                    'icon' => 'receipt',
                    'keywords' => ['invoice', 'receipt', 'number', 'vat receipt', 'print'],
                ],
                [
                    'key' => 'inventory',
                    'name' => 'Inventory',
                    'description' => 'Stock defaults, low-stock behaviour and retail product settings.',
                    'icon' => 'boxes-stacked',
                    'keywords' => ['stock', 'product', 'retail', 'supplier', 'reorder'],
                ],
            ],
        ],

        [
            'name' => 'Administration',
            'description' => 'Who can do what, and what happens to your data.',
            'modules' => [
                [
                    'key' => 'roles-permissions',
                    'name' => 'Roles & Permissions',
                    'description' => 'Control what Owners, Admins, Managers, Receptionists, Service Providers and custom roles can access.',
                    'icon' => 'user-shield',
                    'keywords' => ['role', 'permission', 'access', 'admin', 'manager', 'receptionist', 'custom role', 'matrix'],
                    'counts' => ['roles'],
                ],
                [
                    'key' => 'security',
                    'name' => 'Security',
                    'description' => 'Session behaviour, sign-in policies and application security settings.',
                    'icon' => 'shield-halved',
                    'keywords' => ['password', '2fa', 'session', 'login', 'mfa', 'passkey'],
                ],
                [
                    'key' => 'data-privacy',
                    'name' => 'Data & Privacy',
                    'description' => 'Data retention, client consent and privacy configuration.',
                    'icon' => 'lock',
                    'keywords' => ['gdpr', 'retention', 'consent', 'delete', 'privacy'],
                ],
                [
                    'key' => 'import-export',
                    'name' => 'Import & Export',
                    'description' => 'Bring data in, take data out and run migrations.',
                    'icon' => 'arrow-right-arrow-left',
                    'keywords' => ['csv', 'migrate', 'backup', 'download', 'upload'],
                ],
                [
                    'key' => 'system-preferences',
                    'name' => 'System Preferences',
                    'description' => 'General StyleDesk behaviour and application-wide defaults.',
                    'icon' => 'sliders',
                    'keywords' => ['default', 'general', 'preference', 'format', 'timezone'],
                ],
            ],
        ],

        [
            'name' => 'Developer & Integrations',
            'description' => 'Connecting StyleDesk to everything else.',
            'modules' => [
                [
                    'key' => 'integrations',
                    'name' => 'Integrations',
                    'description' => 'Third-party integrations and connected services.',
                    'icon' => 'plug',
                    'keywords' => ['connect', 'google', 'calendar sync', 'zapier', 'third party'],
                ],
                [
                    'key' => 'api-webhooks',
                    'name' => 'API & Webhooks',
                    'description' => 'API access, keys, webhook endpoints and developer integrations.',
                    'icon' => 'code',
                    'keywords' => ['api', 'key', 'token', 'webhook', 'endpoint', 'developer'],
                ],
            ],
        ],
    ],
];
