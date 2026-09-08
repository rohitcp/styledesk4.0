<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application navigation
|--------------------------------------------------------------------------
|
| One source for the desktop icon rail and the mobile drawer. They showed the
| same destinations before but as two hand-written blocks of markup, which is
| the arrangement where a link gets added to one and forgotten in the other —
| and the drawer is the half nobody looks at on a desktop.
|
| `route` is a named route. `pending` marks a screen that is designed but not
| built: it renders as href="#" carrying data-pending-route, so nothing 404s
| and the remaining work stays greppable.
|
| Every entry carries a `key`, including the section headings and the entries
| whose screens are not built yet. The key is what App\Support\Nav resolves a
| translation from — navigation.<key> for a label, navigation.sections.<key>
| for a heading, navigation.aria.<key> for an accessible name — and an entry
| without one is an entry that stays English in every language. The English
| beside it is the fallback for a key that has not been written yet, not the
| string the menu prints.
|
*/

return [

    'primary' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid-2', 'route' => 'dashboard'],
        ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'pending' => 'calendar.html'],

        /*
        | Bookings — the diary. Two things anyone opens this menu for: the
        | appointments already taken and the screen that takes another. Leads
        | are enquiries that have not become appointments yet, which is a
        | different list and not built.
        */
        [
            'key' => 'bookings',
            'label' => 'Booking',
            'icon' => 'calendar-check',
            'route' => 'bookings.index',
            'children' => [
                ['section' => 'Management', 'key' => 'management'],
                ['key' => 'all_bookings', 'label' => 'All Bookings', 'route' => 'bookings.index'],
                ['key' => 'booking_leads', 'label' => 'Booking Leads', 'route' => 'bookings.leads'],

                ['section' => 'Quick Actions', 'key' => 'quick_actions'],
                ['key' => 'add_booking', 'label' => '+ Add Booking', 'route' => 'bookings.create'],
                ['key' => 'add_walkin', 'label' => '+ Add Walk-in', 'route' => 'bookings.create', 'params' => ['walk-in' => 1]],
            ],
        ],

        [
            'key' => 'clients',
            'label' => 'Clients',
            'icon' => 'user',
            'route' => 'clients.index',
            'children' => [
                ['key' => 'all_clients', 'label' => 'All Clients', 'route' => 'clients.index'],
                // Straight to the form, the same pair Services offers: the
                // list and the way to add to it are the two things anyone
                // opens this menu for.
                ['key' => 'add_client', 'label' => 'Add Client', 'route' => 'clients.create'],
                ['key' => 'coupons_offers', 'label' => 'Coupons & Offers', 'route' => 'promotions.index'],
                ['key' => 'gift_cards', 'label' => 'Gift Cards'],
                ['key' => 'loyalty', 'label' => 'Loyalty'],
                ['key' => 'groups', 'label' => 'Groups'],
                ['key' => 'forms_waivers', 'label' => 'Forms & Waivers'],
                ['key' => 'memberships_packages', 'label' => 'Memberships & Packages'],
            ],
        ],

        [
            'key' => 'services',
            'label' => 'Services & resources',
            // The tooltip wording carries an ampersand; the accessible name
            // spells it, because a screen reader reading "and" is clearer
            // than one reading the symbol.
            'aria' => 'Services and resources',
            'icon' => 'tag',
            'route' => 'services.index',
            // Two groups under their own headings: what the business sells,
            // and what it needs to deliver it. The headings are labels rather
            // than links — "Services" as a clickable row above "All Services"
            // is two ways to the same page and a reader wondering how they
            // differ.
            //
            // Categories and add-ons used to sit here and have gone to App
            // Settings: they are decided once and revisited rarely, which is
            // the line this module is drawn on.
            'children' => [
                ['section' => 'Services', 'key' => 'services'],
                ['key' => 'all_services', 'label' => 'All Services', 'route' => 'services.index'],
                ['key' => 'add_service', 'label' => 'Add Service', 'route' => 'services.create'],

                ['section' => 'Resources', 'key' => 'resources'],
                ['key' => 'all_resources', 'label' => 'All Resources', 'route' => 'resources.index'],
                // The resources list adds through a dialog rather than a page
                // of its own, so this opens the list with it already up.
                ['key' => 'add_resource', 'label' => 'Add Resource', 'route' => 'resources.index', 'params' => ['add' => 1]],
                ['key' => 'resource_availability', 'label' => 'Resource Availability', 'route' => 'resources.availability'],
                ['key' => 'resource_utilization', 'label' => 'Resource Utilization', 'route' => 'resources.utilization'],
            ],
        ],

        /*
        | Staff — §1 and §2. Two groups under their own headings, the same
        | shape Services uses: what you open to look something up, and what
        | you open to add something.
        |
        | `count` names an entry in Nav::counts(), so the label reads
        | "Staff · 12" and the 12 is counted on the way out rather than stored
        | anywhere that could fall behind.
        |
        | App Settings keeps its own Staff screens. These are the same
        | records, the same form and the same controller reached from the
        | module instead — see App\Support\StaffSection.
        */
        [
            'key' => 'staff',
            'label' => 'Staff',
            'icon' => 'users',
            'route' => 'staff.index',
            'count' => 'staff',
            'children' => [
                ['section' => 'Management', 'key' => 'management'],
                ['key' => 'all_staff', 'label' => 'All Staff', 'route' => 'staff.index'],
                ['key' => 'staff_schedule', 'label' => 'Staff Schedule', 'route' => 'staff.schedules'],
                ['key' => 'shifts', 'label' => 'Shifts', 'route' => 'shifts.index'],
                ['key' => 'staff_utilization', 'label' => 'Staff Utilization', 'route' => 'staff.utilization'],

                ['section' => 'Quick Actions', 'key' => 'quick_actions'],
                ['key' => 'add_staff', 'label' => '+ Add Staff', 'route' => 'staff.create'],
            ],
        ],
        [
            'key' => 'sales',
            'label' => 'Sales',
            'icon' => 'credit-card',
            'route' => 'sales.index',
        ],
        /*
        | Marketing — four channels, one built.
        |
        | The three that are not carry `pending` rather than being left out:
        | a menu that grows an entry the week it is built tells nobody what is
        | coming, and an owner who cannot find SMS assumes it does not exist
        | rather than that it is not ready.
        */
        [
            'key' => 'marketing',
            'label' => 'Marketing',
            'icon' => 'bullhorn',
            'route' => 'marketing.email.index',
            'children' => [
                ['section' => 'Channels', 'key' => 'channels'],
                ['key' => 'email_marketing', 'label' => 'Email Marketing', 'route' => 'marketing.email.index'],
                ['key' => 'sms_marketing', 'label' => 'SMS Marketing', 'pending' => 'sms-marketing.html'],
                ['key' => 'social_marketing', 'label' => 'Social Media Marketing', 'pending' => 'social-marketing.html'],
                ['key' => 'review_marketing', 'label' => 'Google Review Marketing', 'pending' => 'review-marketing.html'],
            ],
        ],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-simple'],
    ],

    /*
    | Reachable from the app bar on a wide screen, but hidden below it. The
    | drawer is where they go when there is no room, otherwise shrinking the
    | window quietly removes them from the product.
    */
    'utility' => [
        ['key' => 'mentions', 'label' => 'Mentions', 'icon' => 'at'],
        ['key' => 'activity', 'label' => 'Activity', 'icon' => 'wifi'],
        ['key' => 'design-system', 'label' => 'Design system', 'icon' => 'circle-question', 'pending' => 'designsystem.html'],
    ],
];
