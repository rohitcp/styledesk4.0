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
];
