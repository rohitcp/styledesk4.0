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
    | Every one carries a `key` in config/navigation.php so Nav::label() can
    | resolve it here — including the entries whose screens are not built yet.
    | They are in the menu and a reader sees them, so a menu that reads half
    | in Spanish is the same bug whether or not the link goes anywhere.
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
    'sms_marketing' => 'SMS Marketing',
    'social_marketing' => 'Social Media Marketing',
    'review_marketing' => 'Google Review Marketing',
    'gift_cards' => 'Gift Cards',
    'loyalty' => 'Loyalty',
    'groups' => 'Groups',
    'forms_waivers' => 'Forms & Waivers',
    'memberships_packages' => 'Memberships & Packages',

    /* The headings above a group of entries inside an open menu. */
    'sections' => [
        'management' => 'Management',
        'quick_actions' => 'Quick Actions',
        'services' => 'Services',
        'resources' => 'Resources',
        'channels' => 'Channels',
    ],

    /* Accessible names, where they differ from the label beside them. */
    'aria' => [
        'services' => 'Services and resources',
    ],

    /* Headings only a screen reader reaches. */
    'drawer_main' => 'Main',
    'rail_primary' => 'Primary',
];
