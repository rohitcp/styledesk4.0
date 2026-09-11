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
| permission with no `scopes` key is a plain on/off toggle and is stored with
| the scope `all`. Storing a scope on every row regardless keeps the check one
| shape rather than two.
|
| Phase 1 shows this read-only. Nothing about the storage assumes that: a role
| is a row, a grant is a row, and an editable matrix in Phase 2 writes the same
| rows this screen reads.
|
*/

$readScopes = ['own', 'assigned', 'location', 'all'];

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
                'dashboard.business_summary' => ['label' => 'View business summary'],
                'dashboard.todays_appointments' => ['label' => "View today's appointments", 'scopes' => ['own', 'location', 'all']],
                'dashboard.revenue_summary' => ['label' => 'View revenue summary'],
                'dashboard.staff_performance' => ['label' => 'View staff performance', 'scopes' => ['own', 'location', 'all']],

                /*
                | One permission per panel, so the dashboard is assembled
                | from what somebody may see rather than from what their role
                | is called. A business that gives its senior therapist the
                | tips panel and nothing else gets exactly that, without
                | anybody inventing a role for it.
                */
                'dashboard.view_revenue' => ['label' => 'Dashboard: revenue', 'scopes' => ['location', 'all']],
                'dashboard.view_bookings' => ['label' => 'Dashboard: booking overview', 'scopes' => ['own', 'location', 'all']],
                'dashboard.view_checkin' => ['label' => 'Dashboard: check-in queue', 'scopes' => ['own', 'location', 'all']],
                'dashboard.view_clients' => ['label' => 'Dashboard: client overview', 'scopes' => ['location', 'all']],
                'dashboard.view_staff' => ['label' => 'Dashboard: staff overview', 'scopes' => ['location', 'all']],
                'dashboard.view_schedule' => ['label' => 'Dashboard: staff schedule', 'scopes' => ['location', 'all']],
                'dashboard.view_payments' => ['label' => 'Dashboard: payments', 'scopes' => ['location', 'all']],
                'dashboard.view_reports' => ['label' => 'Dashboard: reports'],
                /* The provider's own day. Deliberately unscoped: "own" is
                   the whole of what these mean. */
                'dashboard.view_own_schedule' => ['label' => 'Dashboard: my schedule'],
                'dashboard.view_own_clients' => ['label' => 'Dashboard: my clients'],
                'dashboard.view_own_performance' => ['label' => 'Dashboard: my performance'],
                'dashboard.view_tips' => ['label' => 'Dashboard: tips'],
            ],
        ],

        'calendar' => [
            'label' => 'Calendar & appointments',
            'icon' => 'calendar',
            'permissions' => [
                'calendar.view' => ['label' => 'View calendar', 'scopes' => $readScopes],
                'calendar.view_all_staff' => ['label' => 'View all staff calendars'],
                'appointments.create' => ['label' => 'Create appointment'],
                'appointments.edit' => ['label' => 'Edit appointment', 'scopes' => $readScopes],
                'appointments.cancel' => ['label' => 'Cancel appointment', 'scopes' => $readScopes],
                /* Turning a request down is not cancelling a booking that was
                   accepted, and marking somebody absent is not either. Both
                   are their own permission because both are their own act,
                   and a salon that lets the desk cancel may not want the desk
                   deciding who did not turn up. */
                'appointments.decline' => ['label' => 'Decline booking request', 'scopes' => $readScopes],
                'appointments.no_show' => ['label' => 'Mark appointment as no show', 'scopes' => $readScopes],
                'appointments.reschedule' => ['label' => 'Reschedule appointment', 'scopes' => $readScopes],
                'appointments.check_in' => ['label' => 'Check in client'],
                /* Saying the appointment was delivered. Its own permission
                   rather than check-in's: it is the status every revenue and
                   visit report counts, and it is what asks the client for a
                   review — a business may well want the desk letting people
                   in without the desk deciding whose work was finished. */
                'appointments.complete' => ['label' => 'Complete appointment', 'scopes' => $readScopes],
                'appointments.check_out' => ['label' => 'Check out client'],
                'calendar.block_time' => ['label' => 'Block calendar time', 'scopes' => ['own', 'location', 'all']],
                'appointments.override_rules' => ['label' => 'Override booking restrictions'],
            ],
        ],

        'clients' => [
            'label' => 'Clients',
            'icon' => 'address-book',
            'permissions' => [
                'clients.view' => ['label' => 'View clients', 'scopes' => $readScopes],
                'clients.create' => ['label' => 'Add client'],
                'clients.edit' => ['label' => 'Edit client', 'scopes' => $readScopes],
                'clients.view_contact' => ['label' => 'View client contact information'],
                'clients.view_history' => ['label' => 'View client appointment history'],
                'clients.view_notes' => ['label' => 'View client notes'],
                'clients.add_notes' => ['label' => 'Add client notes'],
                'clients.view_preferences' => ['label' => 'View client preferences'],
                'clients.edit_preferences' => ['label' => 'Edit client preferences'],
                /* Files are their own three permissions for the same reason
                   notes are: a client's consent form and treatment
                   photographs are more sensitive than their phone number,
                   and a business must be able to let the desk file a
                   document without letting them remove one. Reading is
                   scoped, so a stylist can be given the clients they work on
                   and no others. */
                'clients.view_files' => ['label' => 'View client files', 'scopes' => $readScopes],
                'clients.upload_files' => ['label' => 'Upload client files'],
                'clients.manage_files' => ['label' => 'Edit and delete client files'],
                'clients.merge' => ['label' => 'Merge clients'],
                'clients.archive' => ['label' => 'Archive client'],
                'clients.delete' => ['label' => 'Delete client'],
                // Medical history, allergies, incident notes. Off for every
                // role but Owner and Admin.
                'clients.view_sensitive_notes' => ['label' => 'View sensitive notes'],
            ],
        ],

        /*
        | Reviews & feedback.
        |
        | Its own group rather than a row inside Clients, because reading what
        | clients said and handling the ones who were unhappy are different
        | jobs held by different people: a stylist should be able to see their
        | own ratings without being able to see the complaint another branch
        | is still working through, and the person who assigns and resolves
        | that complaint is not necessarily the person who configures how the
        | asking works.
        */
        'reviews' => [
            'label' => 'Reviews & Feedback',
            'icon' => 'star',
            'permissions' => [
                /* Scoped, so a business can hand a stylist their own reviews
                   and no others. `own` reads as "reviews of my work" — the
                   review carries its own staff_id for exactly this. */
                'reviews.view' => ['label' => 'View reviews', 'scopes' => $readScopes],
                'reviews.send_request' => ['label' => 'Send review request'],
                'reviews.assign' => ['label' => 'Assign feedback'],
                'reviews.add_note' => ['label' => 'Add internal note to feedback'],
                'reviews.change_status' => ['label' => 'Change feedback status'],
                'reviews.manage_settings' => ['label' => 'Manage review settings'],
                'reviews.view_reports' => ['label' => 'View review reports', 'scopes' => ['location', 'all']],
            ],
        ],

        /*
        | Loyalty & rewards.
        |
        | Its own group rather than rows inside Clients, because configuring
        | the scheme and touching one client's balance are different jobs held
        | by different people. Adjusting points by hand is issuing the business
        | money, which is why it is separate from reading a balance and
        | separate again from spending one at the till — a receptionist should
        | be able to redeem what a client has earned without being able to
        | invent a thousand points on a Tuesday.
        */
        'loyalty' => [
            'label' => 'Loyalty & Rewards',
            'icon' => 'gift',
            'permissions' => [
                'loyalty.view_settings' => ['label' => 'View loyalty settings'],
                'loyalty.manage_settings' => ['label' => 'Manage loyalty settings'],
                /* Scoped, so a stylist can be given the balances of the
                   clients they work on and no others. */
                'loyalty.view_rewards' => ['label' => 'View client rewards', 'scopes' => $readScopes],
                'loyalty.adjust_points' => ['label' => 'Adjust client points'],
                'loyalty.redeem_points' => ['label' => 'Redeem client points'],
            ],
        ],

        /*
        | Membership.
        |
        | Its own group rather than rows inside Clients, for the same reason
        | Loyalty is: deciding the terms the business sells memberships on and
        | selling one to the person at the desk are different jobs held by
        | different people.
        */
        'membership' => [
            'label' => 'Membership',
            'icon' => 'id-card',
            'permissions' => [
                'membership.view_settings' => ['label' => 'View membership settings'],
                'membership.manage_settings' => ['label' => 'Manage membership settings'],
                'membership.view_members' => ['label' => 'View client memberships'],
                /* Separate from editing a client: correcting a phone number
                   and stopping a subscription somebody is paying for are not
                   the same authority. */
                'membership.manage_members' => ['label' => 'Cancel and pause memberships'],
            ],
        ],

        'email' => [
            'label' => 'Email',
            'icon' => 'envelope',
            'permissions' => [
                'email.view_history' => ['label' => 'View email history'],
                'email.send' => ['label' => 'Send client email'],
                'email.manage_templates' => ['label' => 'Manage email templates'],
                'email.manage_settings' => ['label' => 'Manage email settings'],
                /* Connecting and disconnecting are separate from managing the
                   settings: handing somebody the sender name is not handing
                   them the keys to the business's mailbox. Listed now,
                   granted when Gmail lands in 1.1. */
                'email.connect_gmail' => ['label' => 'Connect Gmail'],
                'email.disconnect_gmail' => ['label' => 'Disconnect Gmail'],
            ],
        ],

        'sms' => [
            'label' => 'SMS',
            'icon' => 'comment-sms',
            'permissions' => [
                'sms.view_log' => ['label' => 'View SMS log', 'scopes' => ['own', 'location', 'all']],
                'sms.send' => ['label' => 'Send client SMS'],
                /* Configuring what a business texts its clients is its own
                   authority: somebody who may set up the salon is not
                   automatically somebody who decides what it says to a
                   client's phone, or what it spends doing so. */
                'sms.manage_settings' => ['label' => 'Manage SMS settings'],
            ],
        ],

        'services' => [
            'label' => 'Services',
            'icon' => 'tag',
            'permissions' => [
                'services.view' => ['label' => 'View services', 'scopes' => ['assigned', 'all']],
                'services.create' => ['label' => 'Add service'],
                'services.edit' => ['label' => 'Edit service'],
                'services.toggle_active' => ['label' => 'Activate / deactivate service'],
                'services.delete' => ['label' => 'Delete service'],
                'services.manage_categories' => ['label' => 'Manage service categories'],
                'services.manage_pricing' => ['label' => 'Manage service pricing'],
                'services.manage_duration' => ['label' => 'Manage service duration'],
                'services.assign_staff' => ['label' => 'Assign services to staff'],
                'services.assign_resources' => ['label' => 'Assign resources to services'],
            ],
        ],

        'staff' => [
            'label' => 'Staff members',
            'icon' => 'users',
            'permissions' => [
                'staff.view' => ['label' => 'View staff directory', 'scopes' => ['own', 'location', 'all']],
                'staff.view_profile' => ['label' => 'View staff profile', 'scopes' => ['own', 'location', 'all']],
                'staff.create' => ['label' => 'Add staff member'],
                'staff.edit' => ['label' => 'Edit staff member', 'scopes' => ['own', 'location', 'all']],
                'staff.deactivate' => ['label' => 'Activate / deactivate staff'],
                'staff.archive' => ['label' => 'Archive staff member'],
                'staff.delete' => ['label' => 'Delete staff member'],
                'staff.invite' => ['label' => 'Invite staff member'],
                'staff.resend_invitation' => ['label' => 'Resend staff invitation'],
                'staff.assign_location' => ['label' => 'Manage assigned locations'],
                'staff.manage_working_hours' => ['label' => 'Manage working hours', 'scopes' => ['own', 'location', 'all']],
                'staff.assign_services' => ['label' => 'Manage staff services'],
                'staff.manage_availability' => ['label' => 'Manage calendar availability', 'scopes' => ['own', 'location', 'all']],
                'staff.manage_booking_settings' => ['label' => 'Manage default booking settings'],
                'staff.assign_role' => ['label' => 'Assign roles to staff'],
                'staff.view_employment' => ['label' => 'View employment details'],
            ],
        ],

        'roles' => [
            'label' => 'Roles & permissions',
            'icon' => 'user-shield',
            'permissions' => [
                'roles.view' => ['label' => 'View roles'],
                'roles.view_permissions' => ['label' => 'View permissions'],
                // Listed to define the model. Phase 1 grants none of them.
                'roles.create' => ['label' => 'Create role', 'phase' => 2],
                'roles.edit' => ['label' => 'Edit role', 'phase' => 2],
                'roles.delete' => ['label' => 'Delete role', 'phase' => 2],
                'roles.manage_matrix' => ['label' => 'Assign permissions', 'phase' => 2],
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
                'locations.manage_hours' => ['label' => 'Manage location hours', 'scopes' => ['location', 'all']],
                'locations.assign_staff' => ['label' => 'Assign staff to location'],
                'locations.assign_services' => ['label' => 'Assign services to location'],
                'locations.manage_resources' => ['label' => 'Manage location resources'],
            ],
        ],

        'business_hours' => [
            'label' => 'Business hours',
            'icon' => 'clock',
            'permissions' => [
                'business_hours.view' => ['label' => 'View business hours'],
                'business_hours.edit' => ['label' => 'Edit business hours'],
                'business_hours.manage_closures' => ['label' => 'Manage closures'],
                'business_hours.manage_holidays' => ['label' => 'Manage holidays'],
                'business_hours.manage_special' => ['label' => 'Manage special hours'],
                'business_hours.manage_split_shifts' => ['label' => 'Manage split shifts'],
            ],
        ],

        'resources' => [
            'label' => 'Resources & rooms',
            'icon' => 'chair',
            'permissions' => [
                'resources.view' => ['label' => 'View resources', 'scopes' => ['location', 'all']],
                'resources.create' => ['label' => 'Add resource'],
                'resources.edit' => ['label' => 'Edit resource', 'scopes' => ['location', 'all']],
                'resources.delete' => ['label' => 'Delete resource'],
                'resources.manage_availability' => ['label' => 'Manage resource availability'],
                'resources.assign_services' => ['label' => 'Assign resource to service'],
                'resources.assign_location' => ['label' => 'Assign resource to location'],
            ],
        ],

        'payments' => [
            'label' => 'Payments & checkout',
            'icon' => 'credit-card',
            'permissions' => [
                'payments.view_checkout' => ['label' => 'View checkout'],
                'payments.process' => ['label' => 'Process payment'],
                'payments.refund' => ['label' => 'Process refund'],
                'payments.apply_discount' => ['label' => 'Apply discount'],
                'payments.manual_adjustment' => ['label' => 'Apply manual price adjustment'],
                'payments.accept_tips' => ['label' => 'Accept tips'],
                'payments.void' => ['label' => 'Void transaction'],
                'payments.view_transactions' => ['label' => 'View transactions', 'scopes' => ['own', 'location', 'all']],
                'payments.view_details' => ['label' => 'View payment details'],
            ],
        ],

        'sales' => [
            'label' => 'Sales & financial',
            'icon' => 'chart-simple',
            'permissions' => [
                'sales.view' => ['label' => 'View sales', 'scopes' => ['own', 'location', 'all']],
                'sales.view_revenue' => ['label' => 'View revenue', 'scopes' => ['own', 'location', 'all']],
                'sales.view_financial_reports' => ['label' => 'View financial reports'],
                'sales.view_staff_revenue' => ['label' => 'View staff revenue', 'scopes' => ['own', 'location', 'all']],
                'sales.view_tips' => ['label' => 'View tips', 'scopes' => ['own', 'location', 'all']],
                'sales.view_taxes' => ['label' => 'View taxes'],
                'sales.view_discounts' => ['label' => 'View discounts'],
                'sales.view_refunds' => ['label' => 'View refunds'],
            ],
        ],

        /*
        | Marketing — writing to every client the business has.
        |
        | Split from `email`, which is a conversation with one client: sending
        | one person a receipt and sending twelve hundred people a promotion
        | are different acts with different consequences, and a business can
        | perfectly well want its receptionist to do the first and not the
        | second.
        |
        | Sending is its own permission and deliberately not implied by
        | creating: a campaign is drafted, read over, and then sent, and those
        | are two decisions.
        */
        'marketing' => [
            'label' => 'Marketing',
            'icon' => 'bullhorn',
            'permissions' => [
                'marketing.view' => ['label' => 'View campaigns', 'scopes' => $readScopes],
                'marketing.create' => ['label' => 'Create campaign'],
                'marketing.edit' => ['label' => 'Edit campaign'],
                'marketing.send' => ['label' => 'Send campaign'],
                'marketing.schedule' => ['label' => 'Schedule campaign'],
                'marketing.delete' => ['label' => 'Delete campaign'],
                'marketing.view_reports' => ['label' => 'View campaign reports'],
            ],
        ],

        'reports' => [
            'label' => 'Reports',
            'icon' => 'chart-simple',
            'permissions' => [
                'reports.view' => ['label' => 'View reports', 'scopes' => ['own', 'location', 'all']],
                'reports.appointments' => ['label' => 'View appointment reports', 'scopes' => ['own', 'location', 'all']],
                'reports.clients' => ['label' => 'View client reports'],
                'reports.staff' => ['label' => 'View staff reports', 'scopes' => ['own', 'location', 'all']],
                'reports.services' => ['label' => 'View service reports'],
                'reports.sales' => ['label' => 'View sales reports', 'scopes' => ['location', 'all']],
                'reports.financial' => ['label' => 'View financial reports'],
                'reports.export' => ['label' => 'Export reports'],
            ],
        ],

        'inventory' => [
            'label' => 'Inventory & products',
            'icon' => 'boxes-stacked',
            'permissions' => [
                'inventory.view_products' => ['label' => 'View products'],
                'inventory.create_product' => ['label' => 'Add product'],
                'inventory.edit_product' => ['label' => 'Edit product'],
                'inventory.delete_product' => ['label' => 'Delete product'],
                'inventory.adjust' => ['label' => 'Adjust inventory'],
                'inventory.view_levels' => ['label' => 'View inventory levels'],
                // What stock costs the business, as opposed to what it sells
                // for. Withheld from anyone who does not set prices.
                'inventory.view_cost' => ['label' => 'View inventory cost'],
                'inventory.manage_suppliers' => ['label' => 'Manage suppliers'],
                'inventory.manage_purchase_orders' => ['label' => 'Manage purchase orders'],
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'icon' => 'bell',
            'permissions' => [
                'notifications.view' => ['label' => 'View notifications'],
                'notifications.manage_own' => ['label' => 'Manage own notifications'],
                'notifications.manage_business' => ['label' => 'Manage business notifications'],
                'notifications.manage_client_settings' => ['label' => 'Manage client notification settings'],
                'notifications.manage_staff_settings' => ['label' => 'Manage staff notification settings'],
                'notifications.manage_templates' => ['label' => 'Manage email and SMS templates'],
            ],
        ],

        'app_settings' => [
            'label' => 'App settings',
            'icon' => 'gear',
            'permissions' => [
                'settings.view' => ['label' => 'View app settings'],
                'settings.manage_business' => ['label' => 'Manage business information'],
                'settings.manage_branding' => ['label' => 'Manage branding'],
                'settings.manage_booking_rules' => ['label' => 'Manage booking rules'],
                'settings.manage_hours' => ['label' => 'Manage business hours'],
                'settings.manage_locations' => ['label' => 'Manage locations'],
                'settings.manage_staff' => ['label' => 'Manage staff'],
                'settings.manage_services' => ['label' => 'Manage services'],
                'settings.manage_payments' => ['label' => 'Manage payments'],
                'settings.manage_notifications' => ['label' => 'Manage notifications'],
                'settings.manage_integrations' => ['label' => 'Manage integrations'],
            ],
        ],

        'integrations' => [
            'label' => 'Integrations',
            'icon' => 'plug',
            'permissions' => [
                'integrations.view' => ['label' => 'View integrations'],
                'integrations.connect' => ['label' => 'Connect integration'],
                'integrations.configure' => ['label' => 'Configure integration'],
                'integrations.disconnect' => ['label' => 'Disconnect integration'],
            ],
        ],

        'data' => [
            'label' => 'Data & export',
            'icon' => 'arrow-right-arrow-left',
            'permissions' => [
                'data.export_clients' => ['label' => 'Export client data'],
                'data.export_appointments' => ['label' => 'Export appointment data'],
                'data.export_financial' => ['label' => 'Export financial data'],
                'data.import' => ['label' => 'Import data'],
                'data.export_business' => ['label' => 'Access business data export'],
            ],
        ],

        'billing' => [
            'label' => 'Account & billing',
            'icon' => 'receipt',
            'permissions' => [
                'billing.view_subscription' => ['label' => 'View subscription'],
                'billing.view' => ['label' => 'View billing'],
                'billing.manage_subscription' => ['label' => 'Manage subscription', 'owner_only' => true],
                'billing.manage_payment_method' => ['label' => 'Manage payment method', 'owner_only' => true],
                'billing.view_invoices' => ['label' => 'View invoices'],
                'billing.download_invoices' => ['label' => 'Download invoices'],
                'billing.cancel_subscription' => ['label' => 'Cancel subscription', 'owner_only' => true],
            ],
        ],

        'security' => [
            'label' => 'Security',
            'icon' => 'shield-halved',
            'permissions' => [
                'security.view' => ['label' => 'View security settings'],
                'security.view_login_activity' => ['label' => 'View login activity'],
                'security.manage' => ['label' => 'Manage security settings', 'owner_only' => true],
                'security.manage_sessions' => ['label' => 'Manage session settings', 'owner_only' => true],
                'security.manage_authentication' => ['label' => 'Manage authentication settings', 'owner_only' => true],
            ],
        ],

        'business' => [
            'label' => 'Business',
            'icon' => 'building',
            'permissions' => [
                /**
                 * No role but Owner may hold these, and the escalation guard
                 * treats them as owner-only whatever a matrix says. Listed so
                 * the screen can show them as reserved rather than pretend
                 * they do not exist.
                 */
                'business.delete' => ['label' => 'Delete business', 'owner_only' => true],
                'business.transfer_ownership' => ['label' => 'Transfer ownership', 'owner_only' => true],
            ],
        ],
    ],
];
