<?php

declare(strict_types=1);

/*
| The icon rail, the drawer and the account menu.
|
| Every English value here is the label the app already showed. This is a
| translation layer, not a rename: a value that differed would change the
| product's copy under cover of adding a language, and the first sign would be
| a test asserting a label that no longer exists.
|
| Keys are hyphen-safe because they mirror the `key` in config/navigation.php —
| Nav::label() resolves navigation.<key>, so the two cannot drift.
*/

return [
    'bookings' => 'Booking',
    'menu_for' => ':name menu',
    'dashboard' => 'Dashboard',
    'calendar' => 'Calendar',
    'clients' => 'Clients',
    'services' => 'Services & resources',
    'staff' => 'Staff',
    'sales' => 'Sales',
    'marketing' => 'Marketing',
    'reports' => 'Reports',
    'mentions' => 'Mentions',
    'activity' => 'Activity',
    'design-system' => 'Design system',
    'team' => 'Team',
    'app_settings' => 'App settings',
    'main_menu' => 'Main menu',
    'search_placeholder' => 'Type for search and recent items…',
    'my_profile' => 'My profile',
    'sign_out' => 'Sign out',
    'invite_team_members' => 'Invite team members',
    'add' => 'Add',

    'active_staff' => '{0} No active staff|{1} :count active staff member|[2,*] :count active staff members',
    'coming_soon' => 'Soon',

    /*
    | The dropdown entries under the rail's top-level items.
    |
    | These carry a `key` in config/navigation.php so Nav::label() can resolve
    | them here. The label-only entries beside them — Gift Cards, Loyalty,
    | Groups — are deliberately left untranslated: they name screens that do
    | not exist yet, and translating a label for a page nobody can open is
    | work spent ahead of the work it describes.
    */
    'all_bookings' => 'All Bookings',
    'booking_leads' => 'Booking Leads',
    'add_booking' => '+ Add Booking',
    'add_walkin' => '+ Add Walk-in',
    'all_clients' => 'All Clients',
    'add_client' => 'Add Client',
    'coupons_offers' => 'Coupons & Offers',
    'all_services' => 'All Services',
    'add_service' => 'Add Service',
    'all_resources' => 'All Resources',
    'add_resource' => 'Add Resource',
    'resource_availability' => 'Resource Availability',
    'resource_utilization' => 'Resource Utilization',
    'all_staff' => 'All Staff',
    'staff_schedule' => 'Staff Schedule',
    'shifts' => 'Shifts',
    'staff_utilization' => 'Staff Utilization',
    'add_staff' => '+ Add Staff',
    'email_marketing' => 'Email Marketing',

    /* Headings only a screen reader reaches. */
    'drawer_main' => 'Main',
    'rail_primary' => 'Primary',
];
