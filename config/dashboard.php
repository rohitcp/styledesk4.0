<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
|
| The dashboard answers a different question depending on who logs in.
|
|   Owner        — how is my business performing?
|   Admin        — is today operating correctly?
|   Manager      — is my location and team running correctly?
|   Receptionist — who is arriving, waiting, or needs attention?
|   Provider     — who is my next client, and what do I do next?
|
| So this is not one page with panels hidden by role. Each role has its own
| running order, and the same panel sits in a different place — check-in is
| the top of a receptionist's screen and the sixth thing an owner scrolls to,
| because it is urgent to one of them and merely interesting to the other.
|
| Two rules decide what somebody actually sees:
|
|   1. the order below, for their role;
|   2. the permission on each panel, which is what really governs it.
|
| The second is why a custom role needs no entry here — it falls through to
| `default`, which lists every panel, and the permissions sort it out. A
| business that grants its senior therapist the tips panel and nothing else
| gets exactly that without anybody inventing a role for it.
|
*/

return [

    /*
    | Every panel, and the permission that governs it.
    |
    | `scope` is the widest reading a panel supports. A panel marked
    | `location` is narrowed to the locations somebody is assigned to when
    | they hold the permission at location scope rather than all.
    */
    'widgets' => [
        'performance' => ['permission' => 'dashboard.view_revenue'],
        'bookings_today' => ['permission' => 'dashboard.view_bookings'],
        'checkin' => ['permission' => 'dashboard.view_checkin'],
        'arriving_soon' => ['permission' => 'dashboard.view_checkin'],
        'waiting' => ['permission' => 'dashboard.view_checkin'],
        'staff_today' => ['permission' => 'dashboard.view_staff'],
        'schedule_issues' => ['permission' => 'dashboard.view_schedule'],
        'clients' => ['permission' => 'dashboard.view_clients'],
        'services' => ['permission' => 'dashboard.view_reports'],
        'payments' => ['permission' => 'dashboard.view_payments'],
        'alerts' => ['permission' => 'dashboard.view'],

        /* One person's own day. Nothing here reads another member of
           staff's work, which is what makes them safe to give away. */
        'my_next_client' => ['permission' => 'dashboard.view_own_clients'],
        'my_day' => ['permission' => 'dashboard.view_own_schedule'],
        'my_schedule' => ['permission' => 'dashboard.view_own_schedule'],
        'my_performance' => ['permission' => 'dashboard.view_own_performance'],

        'quick_actions' => ['permission' => 'dashboard.view'],
    ],

    /*
    | The running order per role.
    |
    | Read top to bottom as "what this person needs to know first". A
    | receptionist opens the page to find out who is at the desk; an owner
    | opens it to find out how the month is going.
    */
    'layouts' => [
        'owner' => [
            /* Today first, then the month. An owner walking in wants to know
               what the day looks like before they want to know what the
               month came to. */
            'bookings_today', 'performance', 'staff_today', 'clients',
            'checkin', 'services', 'alerts', 'quick_actions',
        ],

        'administrator' => [
            'bookings_today', 'checkin', 'waiting', 'staff_today',
            'payments', 'clients', 'alerts', 'quick_actions',
        ],

        'manager' => [
            'bookings_today', 'staff_today', 'checkin', 'waiting',
            'schedule_issues', 'performance', 'alerts', 'quick_actions',
        ],

        /* The most operational screen in StyleDesk. Who is coming, who has
           arrived, who is still waiting — and only then anything else. */
        'front-desk' => [
            'checkin', 'arriving_soon', 'waiting', 'bookings_today',
            'payments', 'alerts', 'quick_actions',
        ],

        'service-provider' => [
            'my_next_client', 'my_day', 'my_schedule', 'my_performance',
            'alerts', 'quick_actions',
        ],

        /* A custom role has no fixed dashboard: it is offered everything and
           its permissions decide. Ordered business-first, then operational,
           then personal — which is the order somebody with a wide role
           would read them in. */
        'default' => [
            'performance', 'bookings_today', 'checkin', 'arriving_soon',
            'waiting', 'staff_today', 'schedule_issues', 'clients',
            'payments', 'services', 'my_next_client', 'my_day',
            'my_schedule', 'my_performance', 'alerts', 'quick_actions',
        ],
    ],

    /*
    | The panels that live in the side column, as tabs.
    |
    | These three are the ones somebody glances at repeatedly while working
    | on something else — who is at the desk, who is on the floor, what is
    | going wrong. Stacked down the page they would be scrolled past; beside
    | the page they stay in view, and tabbed because only one of them is
    | urgent at a time.
    |
    | Listed in tab order. A role that holds none of them simply gets one
    | column, which is what a service provider should have: their dashboard
    | is one thing at a time.
    */
    'side' => ['checkin', 'staff_today', 'alerts'],

    /*
    | How near an appointment has to be to count as arriving soon.
    |
    | Long enough that the desk can get the room ready, short enough that the
    | list is not simply "the rest of the day".
    */
    'arriving_within_minutes' => 60,

    /*
    | How long somebody can sit in reception before it is worth saying so.
    |
    | Fifteen minutes: past that, a client who checked in on time has been
    | forgotten by somebody, and the desk should find out from the screen
    | rather than from the client.
    */
    'waiting_too_long_minutes' => 15,
];
