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
];
