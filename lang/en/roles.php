<?php

declare(strict_types=1);

/*
| The system roles.
|
| StyleDesk's own vocabulary, keyed by the role key rather than by its stored
| name — so a role a business created and named itself keeps its own words,
| exactly as a service name or a client note does.
|
| Every English value is the name config/role_defaults.php already carried.
*/

return [
    'owner' => [
        'name' => 'Owner',
        'description' => 'Full access to the business, staff, settings, billing and operational data.',
    ],
    'administrator' => [
        'name' => 'Admin',
        'description' => 'Full operational and administrative access, except protected Owner-only actions.',
    ],
    'manager' => [
        'name' => 'Manager',
        'description' => 'Day-to-day operations, staff, services, clients and reporting for their locations.',
    ],
    'front-desk' => [
        'name' => 'Receptionist',
        'description' => 'Appointments, clients, bookings, check-in and check-out, and front-desk activities.',
    ],
    'service-provider' => [
        'name' => 'Service Provider',
        'description' => 'Their own calendar, appointments, assigned clients and services.',
    ],

    /*
    | The counts under a role's name.
    |
    | Whole phrases pluralised by the language file rather than by
    | Str::plural(), which only knows English and would have written
    | "2 miembro del personals".
    */
    'permissions_summary' => ':granted of :total permissions',
    'staff_summary' => '{0} No staff|{1} :count staff member|[2,*] :count staff members',
];
