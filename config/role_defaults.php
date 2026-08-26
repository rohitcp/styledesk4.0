<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| System roles and their default permissions
|--------------------------------------------------------------------------
|
| The matrices from §20-24. Each tenant is seeded its own copy of these five
| roles, so a business can change what Manager means without changing it for
| every other business.
|
| `permissions` maps a catalogue key to its scope. `*` grants every permission
| in the catalogue at `all` scope and is used only by Owner — writing Owner's
| 135 rows out by hand would be a list to keep in step with the catalogue
| forever, and the one role that must never be short a permission is the one
| that can fix everything else.
|
| A permission absent from a role's list is not granted. There is no "deny"
| entry, because a missing grant and an explicit denial would be two ways to
| say the same thing and would eventually disagree.
|
*/

return [

    'owner' => [
        'name' => 'Owner',
        'description' => 'Full access to everything, including billing, security and ownership.',
        'display_order' => 0,
        'permissions' => '*',
    ],

    'administrator' => [
        'name' => 'Admin',
        'description' => 'Business administration, staff and operations. Billing and security are restricted.',
        'display_order' => 1,
        'permissions' => [
            'dashboard.view' => 'all',
            'dashboard.metrics' => 'all',
            'dashboard.financial_summary' => 'all',

            'calendar.view' => 'all',
            'calendar.manage_availability' => 'all',
            'calendar.block_time' => 'all',
            'calendar.override_availability' => 'all',
            'calendar.double_book' => 'all',

            'bookings.view' => 'all',
            'bookings.create' => 'all',
            'bookings.edit' => 'all',
            'bookings.reschedule' => 'all',
            'bookings.cancel' => 'all',
            'bookings.override_rules' => 'all',
            'bookings.override_pricing' => 'all',
            'bookings.apply_discount' => 'all',
            'bookings.mark_no_show' => 'all',
            'bookings.check_in' => 'all',
            'bookings.complete' => 'all',
            'bookings.reassign_provider' => 'all',

            'clients.view' => 'all',
            'clients.create' => 'all',
            'clients.edit' => 'all',
            'clients.view_contact' => 'all',
            'clients.view_notes' => 'all',
            'clients.add_notes' => 'all',
            'clients.edit_notes' => 'all',
            'clients.view_sensitive_notes' => 'all',
            'clients.view_history' => 'all',
            'clients.view_spending' => 'all',
            'clients.view_preferences' => 'all',
            'clients.export' => 'all',

            'staff.view' => 'all',
            'staff.create' => 'all',
            'staff.edit' => 'all',
            'staff.activate' => 'all',
            'staff.deactivate' => 'all',
            'staff.archive' => 'all',
            'staff.invite' => 'all',
            'staff.assign_role' => 'all',
            'staff.assign_location' => 'all',
            'staff.assign_services' => 'all',
            'staff.manage_working_hours' => 'all',
            'staff.manage_availability' => 'all',
            'staff.manage_booking_settings' => 'all',
            'staff.view_employment' => 'all',

            'services.view' => 'all',
            'services.create' => 'all',
            'services.edit' => 'all',
            'services.delete' => 'all',
            'services.change_pricing' => 'all',
            'services.manage_duration' => 'all',
            'services.manage_categories' => 'all',
            'services.assign_staff' => 'all',
            'services.assign_locations' => 'all',
            'services.assign_resources' => 'all',

            'locations.view' => 'all',
            'locations.create' => 'all',
            'locations.edit' => 'all',
            'locations.manage_hours' => 'all',
            'locations.manage_closures' => 'all',
            'locations.manage_staff' => 'all',

            'resources.view' => 'all',
            'resources.create' => 'all',
            'resources.edit' => 'all',
            'resources.delete' => 'all',
            'resources.assign_services' => 'all',
            'resources.block_availability' => 'all',

            'payments.view_prices' => 'all',
            'payments.checkout' => 'all',
            'payments.take_payment' => 'all',
            'payments.apply_discount' => 'all',
            'payments.manual_adjustment' => 'all',
            'payments.refund' => 'all',
            'payments.void' => 'all',
            'payments.view_transactions' => 'all',
            'payments.view_tips' => 'all',
            'payments.manage_register' => 'all',

            'reports.view' => 'all',
            'reports.revenue' => 'all',
            'reports.staff_performance' => 'all',
            'reports.clients' => 'all',
            'reports.services' => 'all',
            'reports.appointments' => 'all',
            'reports.export' => 'all',

            'settings.view' => 'all',
            'settings.edit_business' => 'all',
            'settings.manage_hours' => 'all',
            'settings.manage_booking_rules' => 'all',
            'settings.manage_branding' => 'all',
            'settings.manage_notifications' => 'all',
            'settings.manage_languages' => 'all',
            'settings.manage_currencies' => 'all',
            'settings.manage_tax' => 'all',
            'settings.manage_locations' => 'all',

            'integrations.view' => 'all',
            'integrations.connect' => 'all',
            'integrations.disconnect' => 'all',
            'integrations.configure' => 'all',

            // View only by default, per §21. Changing the plan and cancelling
            // the subscription stay with the Owner.
            'billing.view_plan' => 'all',
            'billing.view' => 'all',
            'billing.view_invoices' => 'all',

            // "Limited" in §21: they can see the settings and the history,
            // but not change the policy or force anyone out.
            'security.view' => 'all',
            'security.view_login_history' => 'all',
            'security.view_audit_log' => 'all',

            'data.import' => 'all',
            'data.export' => 'all',

        /**
         * roles.* is deliberately absent.
         *
         * §21 makes role administration "optional, Owner-controlled", so
         * the default is off and the Owner grants it deliberately. A
         * default that can edit the permission matrix is a default that
         * can grant itself everything else.
         */
        ],
    ],

    'manager' => [
        'name' => 'Manager',
        'description' => 'Day-to-day operations for their assigned locations.',
        'display_order' => 2,
        'permissions' => [
            'dashboard.view' => 'all',
            'dashboard.metrics' => 'location',

            'calendar.view' => 'location',
            'calendar.manage_availability' => 'location',
            'calendar.block_time' => 'all',

            'bookings.view' => 'location',
            'bookings.create' => 'all',
            'bookings.edit' => 'location',
            'bookings.reschedule' => 'location',
            'bookings.cancel' => 'location',
            'bookings.mark_no_show' => 'all',
            'bookings.check_in' => 'all',
            'bookings.complete' => 'all',
            'bookings.reassign_provider' => 'all',

            'clients.view' => 'location',
            'clients.create' => 'all',
            'clients.edit' => 'location',
            'clients.view_contact' => 'all',
            'clients.view_notes' => 'all',
            'clients.add_notes' => 'all',
            'clients.edit_notes' => 'all',
            'clients.view_history' => 'all',

            // View and schedule their locations' staff, but not add them.
            'staff.view' => 'location',
            'staff.manage_working_hours' => 'location',
            'staff.manage_availability' => 'location',

            'services.view' => 'all',
            /**
             * Kept from the earlier Service Categories spec, which gave
             * Manager add-but-not-delete rights over categories. §22 lists
             * only "Services — View", but its list is a summary of defaults
             * rather than an exhaustive matrix, and silently removing a
             * capability a business already relies on is the worse reading.
             */
            'services.manage_categories' => 'all',

            'locations.view' => 'assigned',

            'resources.view' => 'location',
            'resources.create' => 'all',
            'resources.edit' => 'location',
            'resources.block_availability' => 'all',

            'payments.view_prices' => 'all',
            'payments.checkout' => 'all',
            'payments.take_payment' => 'all',
            'payments.view_transactions' => 'location',
            'payments.manage_register' => 'all',

            // Location-level reporting only; revenue is withheld by default.
            'reports.view' => 'location',
            'reports.appointments' => 'all',
            'reports.staff_performance' => 'location',
        ],
    ],

    'front-desk' => [
        'name' => 'Receptionist',
        'description' => 'Front-desk operations: clients, bookings and the day\'s calendar.',
        'display_order' => 3,
        'permissions' => [
            'dashboard.view' => 'all',

            'calendar.view' => 'location',

            'bookings.view' => 'location',
            'bookings.create' => 'all',
            'bookings.reschedule' => 'location',
            'bookings.cancel' => 'location',
            'bookings.check_in' => 'all',
            'bookings.complete' => 'all',

            'clients.view' => 'location',
            'clients.create' => 'all',
            'clients.edit' => 'location',
            'clients.view_contact' => 'all',
            'clients.view_notes' => 'all',
            'clients.add_notes' => 'all',
            'clients.view_history' => 'all',
            // Sensitive notes are explicitly withheld by §23.

            'staff.view' => 'location',

            'services.view' => 'all',
            'locations.view' => 'assigned',
            'resources.view' => 'location',

            'payments.view_prices' => 'all',
            'payments.checkout' => 'all',
            'payments.take_payment' => 'all',
        ],
    ],

    'service-provider' => [
        'name' => 'Service Provider',
        'description' => 'Their own calendar, appointments, clients and availability.',
        'display_order' => 4,
        'is_default' => true,
        'permissions' => [
            'dashboard.view' => 'all',
            'dashboard.metrics' => 'own',

            'calendar.view' => 'own',
            'calendar.manage_availability' => 'own',

            'bookings.view' => 'own',

            // Assigned rather than location: the clients they can see are the
            // ones they have appointments with, not everyone at the site.
            'clients.view' => 'assigned',
            'clients.view_contact' => 'all',
            'clients.view_notes' => 'all',
            'clients.add_notes' => 'all',
            'clients.view_history' => 'all',

            'staff.view' => 'own',
            'staff.manage_working_hours' => 'own',
            'staff.manage_availability' => 'own',

            'services.view' => 'assigned',
            'locations.view' => 'assigned',

            'payments.view_prices' => 'all',

            'reports.view' => 'own',
            'reports.staff_performance' => 'own',
            'payments.view_tips' => 'own',
        ],
    ],
];
