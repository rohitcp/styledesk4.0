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
*/

return [

    'primary' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid-2', 'route' => 'dashboard'],
        ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'pending' => 'calendar.html'],

        [
            'key' => 'clients',
            'label' => 'Clients',
            'icon' => 'user',
            'route' => 'clients.index',
            'children' => [
                ['label' => 'All Clients', 'route' => 'clients.index'],
                ['label' => 'Groups'],
                ['label' => 'Forms & Waivers'],
                ['label' => 'Memberships & Packages'],
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
            'pending' => 'services.html',
            'children' => [
                ['label' => 'All Services', 'pending' => 'services.html'],
                ['label' => 'Categories', 'pending' => 'service-categories.html'],
                ['label' => 'Add-ons', 'pending' => 'service-addons.html'],
                ['separator' => true],
                ['label' => 'All Resources', 'route' => 'resources.index'],
                ['label' => 'Resource Types', 'pending' => 'resource-types.html'],
                ['label' => 'Availability', 'pending' => 'resource-availability.html'],
                ['label' => 'Maintenance', 'pending' => 'resource-maintenance.html'],
            ],
        ],

        ['key' => 'staff', 'label' => 'Staff', 'icon' => 'users'],
        ['key' => 'sales', 'label' => 'Sales', 'icon' => 'credit-card'],
        ['key' => 'marketing', 'label' => 'Marketing', 'icon' => 'bullhorn'],
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
