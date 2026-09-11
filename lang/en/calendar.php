<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Calendar
|--------------------------------------------------------------------------
|
| The diary drawn against the clock: who is working, who is booked, and where
| the gaps are.
|
*/

return [
    'title' => 'Calendar',
    'subtitle' => 'Who is working, who is booked, and where the gaps are.',

    'nav' => [
        'previous' => 'Previous day',
        'next' => 'Next day',
        'today' => 'Today',
        'pick' => 'Choose a date',
    ],

    'views' => [
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
    ],

    'filters' => [
        'location' => 'Location',
        'staff' => 'Staff',
        'resource' => 'Resource',
        'all_staff' => 'All staff',
        'all_resources' => 'All resources',
        'all_locations' => 'All locations',
        'interval' => 'Interval',
        'minutes' => ':count min',
        'service' => 'Service',
        'all_services' => 'All services',
        'search' => 'Search…',
    ],

    'summary' => [
        'total' => 'Bookings',
        'arrived' => 'Checked in',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
        'owing' => 'Balance due',
        'revenue' => 'Scheduled revenue',
    ],

    'card' => [
        'balance' => 'Balance due · :amount',
        'membership' => 'Membership',
        /* Reserved until the client walks in — the rule the redemption engine
           keeps. A calendar that called a future appointment "used" would be
           telling the client they had already had it. */
        'credits_reserved' => 'Membership · :count credit reserved|Membership · :count credits reserved',
        'credits_used' => 'Membership · :count credit used|Membership · :count credits used',
        'note' => 'Has a note',
    ],

    'more' => '+:count more',
    'preview' => [
        'status' => 'Status',
        'payment' => 'Payment',
    ],

    'now' => 'Now · :time',
    'break' => ':minutes min break',
    'off' => 'Not working',
    'closed' => 'Closed on this day.',
    'no_staff' => 'Nobody is set up to perform services at this location yet.',
    'empty' => 'Nothing booked on this day.',
    'loading' => 'Loading the day…',
    'new_booking' => '+ New booking',
    'free_slot' => 'Book :staff at :time',
];
