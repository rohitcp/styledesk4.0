<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Permission catalogue
|--------------------------------------------------------------------------
|
| Every permission StyleDesk understands, grouped the way the Roles &
| Permissions screen presents them. This file is the authority: a permission
| that is not listed here cannot be granted, cannot be checked, and will not
| appear in the matrix.
|
| `scopes` is the list a permission accepts, most restrictive first. A
| permission with no `scopes` key is a plain on/off toggle — "can they do this
| at all" — and is stored with the scope `all`. Storing a scope on every row
| regardless keeps the check one shape rather than two.
|
| Scope meanings, from §19 and §27:
|
|   own       only records belonging to this user
|   assigned  records they are attached to (their bookings, their clients)
|   location  everything at the locations they are assigned to
|   all       everything in the business
|
*/

/** Scope shorthand, so the groups below stay readable. */
$readScopes = ['own', 'assigned', 'location', 'all'];
$writeScopes = ['own', 'assigned', 'location', 'all'];

return [

    'scopes' => [
        'own' => 'Own',
        'assigned' => 'Assigned',
        'location' => 'Location',
        'all' => 'All',
    ],

    'groups' => [

        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'grid-2',
            'permissions' => [
                'dashboard.view' => ['label' => 'View dashboard'],
                'dashboard.metrics' => ['label' => 'View metrics', 'scopes' => ['own', 'location', 'all']],
                'dashboard.financial_summary' => ['label' => 'View financial summary'],
            ],
        ],

        'calendar' => [
            'label' => 'Calendar',
            'icon' => 'calendar',
            'permissions' => [
                'calendar.view' => ['label' => 'View calendars', 'scopes' => $readScopes],
                'calendar.manage_availability' => ['label' => 'Manage availability', 'scopes' => ['own', 'location', 'all']],
                'calendar.block_time' => ['label' => 'Block time'],
                'calendar.override_availability' => ['label' => 'Override availability'],
                'calendar.double_book' => ['label' => 'Double book'],
            ],
        ],

        'bookings' => [
            'label' => 'Bookings',
            'icon' => 'calendar-check',
            'permissions' => [
                'bookings.view' => ['label' => 'View bookings', 'scopes' => $readScopes],
                'bookings.create' => ['label' => 'Create bookings'],
                'bookings.edit' => ['label' => 'Edit bookings', 'scopes' => $writeScopes],
                'bookings.reschedule' => ['label' => 'Reschedule', 'scopes' => $writeScopes],
                'bookings.cancel' => ['label' => 'Cancel', 'scopes' => $writeScopes],
                'bookings.delete' => ['label' => 'Delete bookings'],
                'bookings.override_rules' => ['label' => 'Override booking rules'],
                'bookings.override_pricing' => ['label' => 'Override pricing'],
                'bookings.apply_discount' => ['label' => 'Apply discount'],
                'bookings.mark_no_show' => ['label' => 'Mark no-show'],
                'bookings.check_in' => ['label' => 'Check in'],
                'bookings.complete' => ['label' => 'Complete appointment'],
                'bookings.reassign_provider' => ['label' => 'Reassign provider'],
            ],
        ],

        'clients' => [
            'label' => 'Clients',
            'icon' => 'address-book',
            'permissions' => [
                'clients.view' => ['label' => 'View clients', 'scopes' => $readScopes],
                'clients.create' => ['label' => 'Create clients'],
                'clients.edit' => ['label' => 'Edit clients', 'scopes' => $writeScopes],
                'clients.delete' => ['label' => 'Delete clients'],
                'clients.merge' => ['label' => 'Merge clients'],
                'clients.view_contact' => ['label' => 'View contact information'],
                'clients.view_notes' => ['label' => 'View notes'],
                'clients.add_notes' => ['label' => 'Add notes'],
                'clients.edit_notes' => ['label' => 'Edit notes'],
                // Medical history, allergies, incident notes. Off by default
                // for every role except Owner and Admin.
                'clients.view_sensitive_notes' => ['label' => 'View sensitive notes'],
                'clients.view_history' => ['label' => 'View booking history'],
                'clients.view_spending' => ['label' => 'View spending'],
                'clients.view_preferences' => ['label' => 'View preferences'],
                'clients.export' => ['label' => 'Export client data'],
            ],
        ],

        'staff' => [
            'label' => 'Staff',
            'icon' => 'users',
            'permissions' => [
                'staff.view' => ['label' => 'View staff', 'scopes' => ['own', 'location', 'all']],
                'staff.create' => ['label' => 'Add staff'],
                'staff.edit' => ['label' => 'Edit staff', 'scopes' => ['own', 'location', 'all']],
                'staff.activate' => ['label' => 'Activate staff'],
                'staff.deactivate' => ['label' => 'Deactivate staff'],
                'staff.archive' => ['label' => 'Archive staff'],
                'staff.delete' => ['label' => 'Delete staff'],
                'staff.invite' => ['label' => 'Invite staff'],
                'staff.assign_role' => ['label' => 'Assign role'],
                'staff.assign_location' => ['label' => 'Assign location'],
                'staff.assign_services' => ['label' => 'Assign services'],
                'staff.manage_working_hours' => ['label' => 'Manage working hours', 'scopes' => ['own', 'location', 'all']],
                'staff.manage_availability' => ['label' => 'Manage availability', 'scopes' => ['own', 'location', 'all']],
                'staff.manage_booking_settings' => ['label' => 'Manage booking settings'],
                'staff.view_employment' => ['label' => 'View employment details'],
            ],
        ],

        'services' => [
            'label' => 'Services',
            'icon' => 'tag',
            'permissions' => [
                'services.view' => ['label' => 'View services', 'scopes' => ['assigned', 'all']],
                'services.create' => ['label' => 'Add services'],
                'services.edit' => ['label' => 'Edit services'],
                'services.delete' => ['label' => 'Delete services'],
                'services.change_pricing' => ['label' => 'Change pricing'],
                'services.manage_duration' => ['label' => 'Manage duration'],
                'services.manage_categories' => ['label' => 'Manage categories'],
                'services.assign_staff' => ['label' => 'Assign staff'],
                'services.assign_locations' => ['label' => 'Assign locations'],
                'services.assign_resources' => ['label' => 'Assign resources'],
            ],
        ],

        'locations' => [
            'label' => 'Locations',
            'icon' => 'location-dot',
            'permissions' => [
                'locations.view' => ['label' => 'View locations', 'scopes' => ['assigned', 'all']],
                'locations.create' => ['label' => 'Add location'],
                'locations.edit' => ['label' => 'Edit location', 'scopes' => ['location', 'all']],
                'locations.delete' => ['label' => 'Delete location'],
                'locations.manage_hours' => ['label' => 'Manage business hours', 'scopes' => ['location', 'all']],
                'locations.manage_closures' => ['label' => 'Manage closures and holidays'],
                'locations.manage_staff' => ['label' => 'Manage location staff', 'scopes' => ['location', 'all']],
            ],
        ],

        'resources' => [
            'label' => 'Resources',
            'icon' => 'chair',
            'permissions' => [
                'resources.view' => ['label' => 'View resources', 'scopes' => ['location', 'all']],
                'resources.create' => ['label' => 'Create resources'],
                'resources.edit' => ['label' => 'Edit resources', 'scopes' => ['location', 'all']],
                'resources.delete' => ['label' => 'Delete resources'],
                'resources.assign_services' => ['label' => 'Assign services'],
                'resources.block_availability' => ['label' => 'Block availability'],
            ],
        ],

        'payments' => [
            'label' => 'Payments & checkout',
            'icon' => 'credit-card',
            'permissions' => [
                'payments.view_prices' => ['label' => 'View prices'],
                'payments.checkout' => ['label' => 'Checkout'],
                'payments.take_payment' => ['label' => 'Take payment'],
                'payments.apply_discount' => ['label' => 'Apply discount'],
                'payments.manual_adjustment' => ['label' => 'Apply manual price adjustment'],
                'payments.refund' => ['label' => 'Issue refund'],
                'payments.void' => ['label' => 'Void payment'],
                'payments.view_transactions' => ['label' => 'View transaction history', 'scopes' => ['own', 'location', 'all']],
                'payments.view_tips' => ['label' => 'View tips', 'scopes' => ['own', 'location', 'all']],
                'payments.manage_register' => ['label' => 'Open and close register'],
            ],
        ],

        'reports' => [
            'label' => 'Reports',
            'icon' => 'chart-simple',
            'permissions' => [
                'reports.view' => ['label' => 'View reports', 'scopes' => ['own', 'location', 'all']],
                'reports.revenue' => ['label' => 'Revenue reports', 'scopes' => ['location', 'all']],
                'reports.staff_performance' => ['label' => 'Staff performance', 'scopes' => ['own', 'location', 'all']],
                'reports.clients' => ['label' => 'Client reports'],
                'reports.services' => ['label' => 'Service reports'],
                'reports.appointments' => ['label' => 'Appointment reports'],
                'reports.export' => ['label' => 'Export reports'],
            ],
        ],

        'business_settings' => [
            'label' => 'Business settings',
            'icon' => 'building',
            'permissions' => [
                'settings.view' => ['label' => 'View settings'],
                'settings.edit_business' => ['label' => 'Edit business details'],
                'settings.manage_hours' => ['label' => 'Manage business hours'],
                'settings.manage_booking_rules' => ['label' => 'Manage booking rules'],
                'settings.manage_branding' => ['label' => 'Manage branding'],
                'settings.manage_notifications' => ['label' => 'Manage notifications'],
                'settings.manage_languages' => ['label' => 'Manage languages'],
                'settings.manage_currencies' => ['label' => 'Manage currencies'],
                'settings.manage_tax' => ['label' => 'Manage tax'],
                'settings.manage_locations' => ['label' => 'Manage locations'],
            ],
        ],

        'roles' => [
            'label' => 'Roles & permissions',
            'icon' => 'user-shield',
            'permissions' => [
                'roles.view' => ['label' => 'View roles'],
                'roles.create' => ['label' => 'Create custom role'],
                'roles.edit' => ['label' => 'Edit role'],
                'roles.duplicate' => ['label' => 'Duplicate role'],
                'roles.delete' => ['label' => 'Delete custom role'],
                'roles.assign' => ['label' => 'Assign role'],
                'roles.manage_matrix' => ['label' => 'Manage permission matrix'],
            ],
        ],

        'integrations' => [
            'label' => 'Integrations',
            'icon' => 'plug',
            'permissions' => [
                'integrations.view' => ['label' => 'View integrations'],
                'integrations.connect' => ['label' => 'Connect integration'],
                'integrations.disconnect' => ['label' => 'Disconnect integration'],
                'integrations.configure' => ['label' => 'Configure integration'],
                'integrations.view_credentials' => ['label' => 'View API credentials'],
                'integrations.manage_api' => ['label' => 'Manage API and webhooks'],
            ],
        ],

        'billing' => [
            'label' => 'Billing & subscription',
            'icon' => 'receipt',
            'permissions' => [
                'billing.view_plan' => ['label' => 'View plan'],
                'billing.view' => ['label' => 'View billing'],
                'billing.view_invoices' => ['label' => 'View invoices'],
                'billing.change_plan' => ['label' => 'Change plan'],
                'billing.update_payment_method' => ['label' => 'Update payment method'],
                'billing.purchase_addons' => ['label' => 'Purchase add-ons'],
                'billing.cancel_subscription' => ['label' => 'Cancel subscription'],
            ],
        ],

        'security' => [
            'label' => 'Security',
            'icon' => 'shield-halved',
            'permissions' => [
                'security.view' => ['label' => 'View security settings'],
                'security.manage_password_policy' => ['label' => 'Manage password policy'],
                'security.manage_sessions' => ['label' => 'Manage session timeout'],
                'security.manage_mfa' => ['label' => 'Manage MFA'],
                'security.force_logout' => ['label' => 'Force logout a user'],
                'security.reset_access' => ['label' => 'Reset user access'],
                'security.view_login_history' => ['label' => 'View login history'],
                'security.view_audit_log' => ['label' => 'View audit log'],
            ],
        ],

        'data' => [
            'label' => 'Data management',
            'icon' => 'arrow-right-arrow-left',
            'permissions' => [
                'data.import' => ['label' => 'Import data'],
                'data.export' => ['label' => 'Export data'],
                'data.delete' => ['label' => 'Delete data'],
                'data.manage_retention' => ['label' => 'Manage retention'],
                'data.manage_privacy_requests' => ['label' => 'Manage privacy requests'],
            ],
        ],

        'business' => [
            'label' => 'Business',
            'icon' => 'gear',
            'permissions' => [
                /**
                 * Two permissions no role but Owner may hold, and which the
                 * escalation guard treats as owner-only regardless of what a
                 * matrix says. They are here so the UI can show them as
                 * locked rather than pretending they do not exist.
                 */
                'business.delete' => ['label' => 'Delete business', 'owner_only' => true],
                'business.transfer_ownership' => ['label' => 'Transfer ownership', 'owner_only' => true],
            ],
        ],
    ],
];
