<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| System roles and their default permissions
|--------------------------------------------------------------------------
|
| The access matrix from the Roles & Permissions spec. Each tenant is seeded
| its own copy of these five roles, so a business can change what Manager
| means without changing it for every other business.
|
| `permissions` maps a catalogue key to the scope the role holds it at. '*'
| grants everything at full scope and is used only by Owner — writing its 155
| rows by hand would be a list to keep in step with the catalogue forever, and
| the one role that must never be short a permission is the one that can fix
| everything else.
|
| A permission absent from a role's list is not granted. There is no "deny"
| entry: a missing grant and an explicit denial would be two ways to say the
| same thing, and they would eventually disagree.
|
| The matrix's softer words map onto scopes rather than onto separate ideas:
| "Own" and "Assigned" are scopes, and "Limited" means some of a module's
| permissions and not others — which falls out of the lists below rather than
| needing a concept of its own.
|
*/

return [

    'owner' => [
        'name' => 'Owner',
        'description' => 'Full access to the business, staff, settings, billing and operational data.',
        'display_order' => 0,
        'permissions' => '*',
    ],

    'administrator' => [
        'name' => 'Admin',
        'description' => 'Full operational and administrative access, except protected Owner-only actions.',
        'display_order' => 1,
        'permissions' => [
            /* Marketing. Writing to the whole client list is an
               administrator's job, sending included. */
            'marketing.view' => 'all',
            'marketing.create' => 'all',
            'marketing.edit' => 'all',
            'marketing.send' => 'all',
            'marketing.schedule' => 'all',
            'marketing.delete' => 'all',
            'marketing.view_reports' => 'all',
            /* Email. Admin matches Owner: full access, Gmail included. */
            'email.view_history' => 'all',
            'email.send' => 'all',
            'email.manage_templates' => 'all',
            'email.manage_settings' => 'all',
            'email.connect_gmail' => 'all',
            'email.disconnect_gmail' => 'all',
            /* SMS. Same reading as email: an administrator sets up what the
               business says and what it spends saying it. */
            'sms.view_log' => 'all',
            'sms.send' => 'all',
            'sms.manage_settings' => 'all',
            'dashboard.view' => 'all',
            'dashboard.view_revenue' => 'all',
            'dashboard.view_bookings' => 'all',
            'dashboard.view_checkin' => 'all',
            'dashboard.view_clients' => 'all',
            'dashboard.view_staff' => 'all',
            'dashboard.view_schedule' => 'all',
            'dashboard.view_payments' => 'all',
            'dashboard.view_reports' => 'all',
            'dashboard.business_summary' => 'all',
            'dashboard.todays_appointments' => 'all',
            'dashboard.revenue_summary' => 'all',
            'dashboard.staff_performance' => 'all',
            'calendar.view' => 'all',
            'calendar.view_all_staff' => 'all',
            'appointments.create' => 'all',
            'appointments.edit' => 'all',
            'appointments.cancel' => 'all',
            'appointments.decline' => 'all',
            'appointments.no_show' => 'all',
            'appointments.reschedule' => 'all',
            'appointments.check_in' => 'all',
            'appointments.complete' => 'all',
            'appointments.check_out' => 'all',
            'calendar.block_time' => 'all',
            'appointments.override_rules' => 'all',
            'clients.view' => 'all',
            'clients.create' => 'all',
            'clients.edit' => 'all',
            'clients.view_contact' => 'all',
            'clients.view_history' => 'all',
            'clients.view_notes' => 'all',
            'clients.add_notes' => 'all',
            'clients.view_preferences' => 'all',
            'clients.edit_preferences' => 'all',
            'clients.view_files' => 'all',
            'clients.upload_files' => 'all',
            'clients.manage_files' => 'all',
            'clients.merge' => 'all',
            'clients.archive' => 'all',
            'clients.delete' => 'all',
            'clients.view_sensitive_notes' => 'all',
            'reviews.view' => 'all',
            'reviews.send_request' => 'all',
            'reviews.assign' => 'all',
            'reviews.add_note' => 'all',
            'reviews.change_status' => 'all',
            'reviews.manage_settings' => 'all',
            'reviews.view_reports' => 'all',
            'services.view' => 'all',
            'services.create' => 'all',
            'services.edit' => 'all',
            'services.toggle_active' => 'all',
            'services.delete' => 'all',
            'services.manage_categories' => 'all',
            'services.manage_pricing' => 'all',
            'services.manage_duration' => 'all',
            'services.assign_staff' => 'all',
            'services.assign_resources' => 'all',
            'staff.view' => 'all',
            'staff.view_profile' => 'all',
            'staff.create' => 'all',
            'staff.edit' => 'all',
            'staff.deactivate' => 'all',
            'staff.archive' => 'all',
            'staff.delete' => 'all',
            'staff.invite' => 'all',
            'staff.resend_invitation' => 'all',
            'staff.assign_location' => 'all',
            'staff.manage_working_hours' => 'all',
            'staff.assign_services' => 'all',
            'staff.manage_availability' => 'all',
            'staff.manage_booking_settings' => 'all',
            'staff.assign_role' => 'all',
            'staff.view_employment' => 'all',
            'roles.view' => 'all',
            'roles.view_permissions' => 'all',
            'locations.view' => 'all',
            'locations.create' => 'all',
            'locations.edit' => 'all',
            'locations.delete' => 'all',
            'locations.manage_hours' => 'all',
            'locations.assign_staff' => 'all',
            'locations.assign_services' => 'all',
            'locations.manage_resources' => 'all',
            'business_hours.view' => 'all',
            'business_hours.edit' => 'all',
            'business_hours.manage_closures' => 'all',
            'business_hours.manage_holidays' => 'all',
            'business_hours.manage_special' => 'all',
            'business_hours.manage_split_shifts' => 'all',
            'resources.view' => 'all',
            'resources.create' => 'all',
            'resources.edit' => 'all',
            'resources.delete' => 'all',
            'resources.manage_availability' => 'all',
            'resources.assign_services' => 'all',
            'resources.assign_location' => 'all',
            'payments.view_checkout' => 'all',
            'payments.process' => 'all',
            'payments.refund' => 'all',
            'payments.apply_discount' => 'all',
            'payments.manual_adjustment' => 'all',
            'payments.accept_tips' => 'all',
            'payments.void' => 'all',
            'payments.view_transactions' => 'all',
            'payments.view_details' => 'all',
            'sales.view' => 'all',
            'sales.view_revenue' => 'all',
            'sales.view_financial_reports' => 'all',
            'sales.view_staff_revenue' => 'all',
            'sales.view_tips' => 'all',
            'sales.view_taxes' => 'all',
            'sales.view_discounts' => 'all',
            'sales.view_refunds' => 'all',
            'reports.view' => 'all',
            'reports.appointments' => 'all',
            'reports.clients' => 'all',
            'reports.staff' => 'all',
            'reports.services' => 'all',
            'reports.sales' => 'all',
            'reports.financial' => 'all',
            'reports.export' => 'all',
            'inventory.view_products' => 'all',
            'inventory.create_product' => 'all',
            'inventory.edit_product' => 'all',
            'inventory.delete_product' => 'all',
            'inventory.adjust' => 'all',
            'inventory.view_levels' => 'all',
            'inventory.view_cost' => 'all',
            'inventory.manage_suppliers' => 'all',
            'inventory.manage_purchase_orders' => 'all',
            'notifications.view' => 'all',
            'notifications.manage_own' => 'all',
            'notifications.manage_business' => 'all',
            'notifications.manage_client_settings' => 'all',
            'notifications.manage_staff_settings' => 'all',
            'notifications.manage_templates' => 'all',
            'settings.view' => 'all',
            'settings.manage_business' => 'all',
            'settings.manage_branding' => 'all',
            'settings.manage_booking_rules' => 'all',
            'settings.manage_hours' => 'all',
            'settings.manage_locations' => 'all',
            'settings.manage_staff' => 'all',
            'settings.manage_services' => 'all',
            'settings.manage_payments' => 'all',
            'settings.manage_notifications' => 'all',
            'settings.manage_integrations' => 'all',
            'integrations.view' => 'all',
            'integrations.connect' => 'all',
            'integrations.configure' => 'all',
            'integrations.disconnect' => 'all',
            'data.export_clients' => 'all',
            'data.export_appointments' => 'all',
            'data.export_financial' => 'all',
            'data.import' => 'all',
            'data.export_business' => 'all',
            'billing.view_subscription' => 'all',
            'billing.view' => 'all',
            'billing.view_invoices' => 'all',
            'billing.download_invoices' => 'all',
            'security.view' => 'all',
            'security.view_login_activity' => 'all',

            /* Forms & Waivers. Full access, sensitive answers included: an
               administrator is the person a therapist asks when a
               contraindication needs checking out of hours. */
            'forms.view' => 'all',
            'forms.create' => 'all',
            'forms.edit' => 'all',
            'forms.publish' => 'all',
            'forms.archive' => 'all',
            'forms.view_responses' => 'all',
            'forms.assign' => 'all',
            'forms.send' => 'all',
            'forms.download' => 'all',
            'forms.view_sensitive' => 'all',

            /* Loyalty. Full access, settings included: how the scheme works
               is an administrative decision. */
            'loyalty.view_settings' => 'all',
            'loyalty.manage_settings' => 'all',
            'loyalty.view_rewards' => 'all',
            'loyalty.adjust_points' => 'all',
            'loyalty.redeem_points' => 'all',

            /* Membership. Full access, settings included: the terms the
               business sells on are an administrative decision. */
            'membership.view_settings' => 'all',
            'membership.manage_settings' => 'all',
            'membership.view_members' => 'all',
            'membership.manage_members' => 'all',
        ],
    ],

    'manager' => [
        'name' => 'Manager',
        'description' => 'Day-to-day operations, staff, services, clients and reporting for their locations.',
        'display_order' => 2,
        'permissions' => [
            /* Marketing. A manager writes and schedules campaigns for
               their branches and reads how they did. Sending to the whole
               list is left to an owner or an administrator: it is the one act
               here that cannot be taken back. */
            'marketing.view' => 'location',
            'marketing.create' => 'all',
            'marketing.edit' => 'all',
            'marketing.schedule' => 'all',
            'marketing.view_reports' => 'all',
            /* Email. A manager runs the floor and the messages that go with
               it, and owns the templates the desk sends from — but not who the
               business sends as, which is an owner's decision. */
            'email.view_history' => 'all',
            'email.send' => 'all',
            'email.manage_templates' => 'all',
            'dashboard.view' => 'all',
            'dashboard.view_bookings' => 'location',
            'dashboard.view_checkin' => 'location',
            'dashboard.view_staff' => 'location',
            'dashboard.view_schedule' => 'location',
            'dashboard.view_revenue' => 'location',
            'dashboard.business_summary' => 'all',
            'dashboard.todays_appointments' => 'location',
            'dashboard.staff_performance' => 'location',
            'calendar.view' => 'location',
            'calendar.view_all_staff' => 'all',
            'appointments.create' => 'all',
            'appointments.check_in' => 'all',
            'appointments.complete' => 'location',
            'appointments.check_out' => 'all',
            'appointments.edit' => 'location',
            'appointments.cancel' => 'location',
            'appointments.decline' => 'location',
            'appointments.no_show' => 'location',
            'appointments.reschedule' => 'location',
            'calendar.block_time' => 'location',
            'clients.view' => 'location',
            'clients.edit' => 'location',
            'clients.create' => 'all',
            'clients.view_contact' => 'all',
            'clients.view_history' => 'all',
            'clients.view_notes' => 'all',
            'clients.add_notes' => 'all',
            'clients.view_preferences' => 'all',
            'clients.edit_preferences' => 'all',
            'clients.view_files' => 'location',
            'clients.upload_files' => 'all',
            'clients.manage_files' => 'all',
            'clients.archive' => 'all',
            'reviews.view' => 'location',
            'reviews.send_request' => 'all',
            'reviews.assign' => 'all',
            'reviews.add_note' => 'all',
            'reviews.change_status' => 'all',
            'reviews.view_reports' => 'location',
            'services.view' => 'all',
            'services.create' => 'all',
            'services.edit' => 'all',
            'services.toggle_active' => 'all',
            'services.manage_categories' => 'all',
            'services.manage_duration' => 'all',
            'services.assign_staff' => 'all',
            'services.assign_resources' => 'all',
            'staff.view' => 'location',
            'staff.view_profile' => 'location',
            'staff.edit' => 'location',
            'staff.manage_working_hours' => 'location',
            'staff.manage_availability' => 'location',
            'staff.deactivate' => 'all',
            'staff.assign_services' => 'all',
            'staff.manage_booking_settings' => 'all',
            'staff.resend_invitation' => 'all',
            'roles.view' => 'all',
            'roles.view_permissions' => 'all',
            'locations.view' => 'assigned',
            'locations.edit' => 'location',
            'locations.manage_hours' => 'location',
            'locations.assign_staff' => 'all',
            'locations.assign_services' => 'all',
            'locations.manage_resources' => 'all',
            'business_hours.view' => 'all',
            'business_hours.edit' => 'all',
            'business_hours.manage_closures' => 'all',
            'business_hours.manage_holidays' => 'all',
            'business_hours.manage_special' => 'all',
            'business_hours.manage_split_shifts' => 'all',
            'resources.view' => 'location',
            'resources.edit' => 'location',
            'resources.create' => 'all',
            'resources.manage_availability' => 'all',
            'resources.assign_services' => 'all',
            'resources.assign_location' => 'all',
            'payments.view_checkout' => 'all',
            'payments.process' => 'all',
            'payments.apply_discount' => 'all',
            'payments.accept_tips' => 'all',
            'payments.view_details' => 'all',
            'payments.view_transactions' => 'location',
            'sales.view' => 'location',
            'sales.view_revenue' => 'location',
            'sales.view_staff_revenue' => 'location',
            'sales.view_tips' => 'location',
            'reports.view' => 'location',
            'reports.appointments' => 'location',
            'reports.staff' => 'location',
            'reports.sales' => 'location',
            'reports.clients' => 'all',
            'reports.services' => 'all',
            'inventory.view_products' => 'all',
            'inventory.adjust' => 'all',
            'inventory.view_levels' => 'all',
            'inventory.edit_product' => 'all',
            'inventory.create_product' => 'all',
            'notifications.view' => 'all',
            'notifications.manage_own' => 'all',
            /**
             * settings.view is deliberately absent.
             *
             * This spec's matrix reads "App Settings — Limited" for Manager,
             * while the earlier App Settings spec said only Owner and Admin
             * may see the module at all — and that is what is built and
             * tested. Widening access to a settings area is not something to
             * do as a side effect of reorganising a catalogue, so the
             * narrower of the two rules stands until it is asked for.
             */

            /* Forms & Waivers. They build and send them, and read what came
               back for their own locations. Sensitive answers are withheld
               until the business says otherwise: a manager runs a rota, and
               somebody's medication list is not part of that job. */
            'forms.view' => 'all',
            'forms.create' => 'all',
            'forms.edit' => 'all',
            'forms.publish' => 'all',
            'forms.view_responses' => 'location',
            'forms.assign' => 'all',
            'forms.send' => 'all',
            'forms.download' => 'all',

            /* Loyalty. Their clients' balances, and the authority to correct
               and spend one. Not the rules — what a point is worth is one
               decision for the whole business, not five. */
            'loyalty.view_settings' => 'all',
            'loyalty.view_rewards' => 'location',
            'loyalty.adjust_points' => 'all',
            'loyalty.redeem_points' => 'all',

            /* Membership. They read the terms so they can answer "can I
               cancel?" at the desk, and they can act on the answer. Setting
               the terms everybody is sold on is still not theirs. */
            'membership.view_settings' => 'all',
            'membership.view_members' => 'all',
            'membership.manage_members' => 'all',
        ],
    ],

    'front-desk' => [
        'name' => 'Receptionist',
        'description' => 'Appointments, clients, bookings, check-in and check-out, and front-desk activities.',
        'display_order' => 3,
        'permissions' => [
            /* Marketing. The desk can see what went out — a client ringing
               up about an offer is a call the desk takes — and writes none of
               it. */
            'marketing.view' => 'location',
            /* Email. The desk sends and reads; it does not decide what the
               templates say. */
            'email.view_history' => 'all',
            'email.send' => 'all',
            'dashboard.view' => 'all',
            'dashboard.view_bookings' => 'location',
            'dashboard.view_checkin' => 'location',
            'dashboard.view_clients' => 'location',
            'dashboard.view_payments' => 'location',
            'dashboard.view_staff' => 'location',
            'dashboard.todays_appointments' => 'location',
            'calendar.view' => 'location',
            'calendar.view_all_staff' => 'all',
            'appointments.create' => 'all',
            'appointments.check_in' => 'all',
            'appointments.complete' => 'location',
            'appointments.check_out' => 'all',
            'appointments.edit' => 'location',
            'appointments.cancel' => 'location',
            'appointments.decline' => 'location',
            'appointments.no_show' => 'location',
            'appointments.reschedule' => 'location',
            'clients.view' => 'location',
            'clients.edit' => 'location',
            'clients.create' => 'all',
            'clients.view_contact' => 'all',
            'clients.view_history' => 'all',
            'clients.view_notes' => 'all',
            'clients.add_notes' => 'all',
            'clients.view_preferences' => 'all',
            /* Upload and read, never delete. The desk scans the consent form
               the client just signed; removing one from the record is a
               decision for whoever answers for the record. */
            'clients.view_files' => 'location',
            'clients.upload_files' => 'all',
            'reviews.view' => 'location',
            'reviews.send_request' => 'all',
            'services.view' => 'all',
            'staff.view' => 'location',
            'staff.view_profile' => 'location',
            'locations.view' => 'assigned',
            'business_hours.view' => 'all',
            'resources.view' => 'location',
            'payments.view_checkout' => 'all',
            'payments.process' => 'all',
            'payments.apply_discount' => 'all',
            'payments.accept_tips' => 'all',
            'payments.view_details' => 'all',
            'reports.view' => 'own',
            'inventory.view_products' => 'all',
            'inventory.view_levels' => 'all',
            'notifications.view' => 'all',
            'notifications.manage_own' => 'all',

            /* Forms & Waivers. The desk's job is getting them completed, not
               reading them: assign, send, chase, and see whether a client is
               ready to be taken through. `forms.view` is the forms list, so
               they can tell which form somebody still needs — the answers
               themselves are not theirs. */
            'forms.view' => 'all',
            'forms.assign' => 'all',
            'forms.send' => 'all',

            /* Loyalty. Reads a balance and spends it at the till. Inventing
               points is somebody else's decision. */
            'loyalty.view_rewards' => 'location',
            'loyalty.redeem_points' => 'all',

            /* Reads a client's membership to answer "what have I got left".
               Ending one is somebody else's decision. */
            'membership.view_members' => 'all',
        ],
    ],

    'service-provider' => [
        'name' => 'Service Provider',
        'description' => 'Their own calendar, appointments, assigned clients and services.',
        'display_order' => 4,
        'is_default' => true,
        'permissions' => [
            'dashboard.view' => 'all',
            'dashboard.view_own_schedule' => 'all',
            'dashboard.view_own_clients' => 'all',
            'dashboard.view_own_performance' => 'all',
            'dashboard.view_tips' => 'all',
            'dashboard.view_checkin' => 'own',
            'dashboard.todays_appointments' => 'own',
            'dashboard.staff_performance' => 'own',
            'calendar.view' => 'own',
            'calendar.block_time' => 'own',
            'appointments.edit' => 'own',
            'appointments.cancel' => 'own',
            'appointments.no_show' => 'own',
            'appointments.reschedule' => 'own',
            /* Not check-in. Checking a client in is front-desk work in phase
               one — the provider is with somebody else when it happens — and
               a business that wants it can grant it. Checking out is theirs,
               because that is the end of their own appointment. */
            'appointments.check_out' => 'all',
            /* Their own. Completing an appointment is saying the work they
               did was delivered, which is theirs to say. */
            'appointments.complete' => 'own',
            'clients.view' => 'assigned',
            'clients.edit' => 'assigned',
            'clients.view_contact' => 'all',
            'clients.view_history' => 'all',
            'clients.view_notes' => 'all',
            'clients.add_notes' => 'all',
            'clients.view_preferences' => 'all',
            /* Their own clients' files, and the treatment photographs they
               take themselves. Scoped to assigned like everything else they
               hold on a client: a stylist reads the records of the people
               they actually work on. */
            'clients.view_files' => 'assigned',
            'clients.upload_files' => 'all',
            /* Their own work, and no one else's. A stylist reading the
               complaint another branch is still working through is not what
               this screen is for. */
            'reviews.view' => 'own',
            'services.view' => 'assigned',
            'staff.view' => 'own',
            'staff.view_profile' => 'own',
            'staff.manage_working_hours' => 'own',
            'staff.manage_availability' => 'own',
            'locations.view' => 'assigned',
            'business_hours.view' => 'all',
            'resources.view' => 'location',
            'payments.view_checkout' => 'all',
            'payments.accept_tips' => 'all',
            'sales.view' => 'own',
            'sales.view_revenue' => 'own',
            'sales.view_staff_revenue' => 'own',
            'sales.view_tips' => 'own',
            'reports.view' => 'own',
            'reports.appointments' => 'own',
            'reports.staff' => 'own',
            'inventory.view_products' => 'all',
            'inventory.view_levels' => 'all',
            'notifications.view' => 'all',
            'notifications.manage_own' => 'all',

            /* Loyalty. The balances of the clients they see, and nothing
               else — so "you have enough for a reward" can be said in the
               chair. */
            /* Forms & Waivers. The intake answers for the clients they are
               treating, which is the point of taking an intake form. Not
               sensitive ones by default, and nothing about building or
               sending them. */
            'forms.view_responses' => 'own',

            'loyalty.view_rewards' => 'own',
        ],
    ],
];
