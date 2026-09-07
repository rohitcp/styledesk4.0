<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
|
| The dashboard answers a different question depending on who logs in, so the
| wording is written for the person reading it rather than for the data
| underneath. A receptionist is asking who is at the desk; an owner is asking
| how the month is going.
|
*/

return [

    'title' => 'Dashboard',
    'greeting' => [
        'morning' => 'Good morning',
        'afternoon' => 'Good afternoon',
        'evening' => 'Good evening',
    ],

    'location' => 'Location',
    'all_locations' => 'All locations',

    'performance' => [
        'title' => 'Business performance',
        'today' => 'Taken today',
        'month' => 'Taken this month',
        'change' => 'vs. same days last month',
        'outstanding' => 'Outstanding',
        'bookings' => 'Bookings this month',
        'average' => 'Average booking',
        /* Said rather than shown as a nought: a first month has nothing to
           compare against, and "0%" would read as a business standing
           still. */
        'no_comparison' => 'No figures for last month yet',
    ],

    'bookings_today' => [
        'title' => "Today's bookings",
        'total' => 'Total',
        'pending_checkin' => 'Pending check-in',
        'checked_in' => 'Checked in',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
    ],

    'checkin' => [
        'title' => 'Check-in',
        'none' => 'Nobody is waiting to check in.',
        'none_hint' => 'Appointments appear here as the day goes on.',
        'view_all' => 'Open the queue',
        'action' => 'Check in',
    ],

    'arriving' => [
        'title' => 'Arriving soon',
        'none' => 'Nobody is due in the next hour.',
    ],

    'waiting' => [
        'title' => 'Waiting',
        'none' => 'Nobody is waiting.',
        'for' => 'Waiting :count min',
        'since' => 'Checked in at :time',
        'too_long' => 'Waiting a while',
    ],

    'staff_today' => [
        'title' => 'Working today',
        'none' => 'Nobody is on the rota today.',
        'shift' => 'Shift',
        'now' => 'With a client',
        'next' => 'Next',
        'remaining' => ':count left',
        'free' => 'Free',
    ],

    'schedule_issues' => [
        'title' => 'Schedule issues',
        'none' => 'No operational issues require your attention.',
        'working_without_a_shift' => 'Working without a shift on the rota',
        'bookings_without_staff' => 'Appointments with nobody assigned',
    ],

    'clients' => [
        'title' => 'Clients',
        'new_today' => 'New today',
        'booked_today' => 'In today',
        'returning_today' => 'Returning',
        'active' => 'Active clients',
    ],

    'services' => [
        'title' => 'Most booked this month',
        'none' => 'Nothing has been booked this month yet.',
        'bookings' => ':count booked',
    ],

    'payments' => [
        'title' => 'Payments',
        'collected' => 'Taken today',
        'outstanding' => 'Outstanding',
        'partial' => 'Part paid',
        'due_today' => 'Seen today and unpaid',
        'none' => 'Nothing is owed from today.',
    ],

    'alerts' => [
        'title' => 'Needs attention',
        'none' => 'No operational issues require your attention.',
        'late' => ':count client waiting to arrive is past their time|:count clients waiting to arrive are past their time',
        'waiting_too_long' => ':count client has been waiting a while|:count clients have been waiting a while',
        'unpaid' => ':count appointment seen today has not been paid for|:count appointments seen today have not been paid for',
        'unstaffed' => ':count appointment today has nobody assigned|:count appointments today have nobody assigned',
    ],

    'my_next_client' => [
        'title' => 'Next client',
        'none' => "You don't have another appointment scheduled today.",
        'none_hint' => 'Anything booked in later today will appear here.',
        'here' => 'Here now',
        'view_client' => 'View client',
        'view_booking' => 'View booking',
    ],

    'my_day' => [
        'title' => 'My day',
        'none' => 'Nothing is booked in for you today.',
    ],

    'my_schedule' => [
        'title' => 'My shift',
        'none' => 'You are not on the rota today.',
        'from' => 'From',
        'to' => 'Until',
        'break' => 'Break',
        'remaining' => 'Still to come',
    ],

    'my_performance' => [
        'title' => 'My day so far',
        'clients' => 'Clients',
        'completed' => 'Completed',
        'average' => 'Average service',
        'tips' => 'Tips',
        'minutes' => ':count min',
    ],

    'quick_actions' => [
        'title' => 'Quick actions',
        'booking' => 'Create booking',
        'client' => 'Add client',
        'checkin' => 'Check in a client',
        'staff' => 'Add staff',
        'service' => 'Add service',
        'schedule' => 'Manage schedule',
        'calendar' => 'View calendar',
    ],

    /* The setup checklist, shown until the last item is done or dismissed. */
    'getting_started' => [
        'title' => 'Getting started',
        'intro' => 'A few things left to set up. You can come back to these any time.',
    ],
];
