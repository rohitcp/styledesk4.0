<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shift rules
|--------------------------------------------------------------------------
|
| A shift rule is a reusable working pattern — "Standard Full-Time",
| "Weekend Shift" — that a staff schedule applies to a person. This file
| decides which options exist; the labels come from the shift_rules language
| file, so a business running in Spanish gets Spanish without a second list to
| keep in step.
|
*/

return [

    'statuses' => [
        'active' => ['label' => 'Active', 'class' => 'styledesk_badge--active'],
        /*
         * Kept, not deleted. An inactive rule stays readable on the schedules
         * that already used it — a pattern that vanished would leave last
         * summer's rota explained by nothing — but is not offered for new
         * assignments.
         */
        'inactive' => ['label' => 'Inactive', 'class' => 'styledesk_badge--soon'],
    ],

    'location_scopes' => [
        'all' => 'All locations',
        'specific' => 'Specific locations',
    ],

    'break_types' => [
        'none' => 'No break',
        /* A named hour — twelve until one. */
        'fixed' => 'Fixed break',
        /* So many minutes, placed inside the shift when it is scheduled. */
        'duration' => 'Duration only',
    ],

    /*
    | Offered as a list rather than a free number: every business uses the
    | same handful, and a text box invites "45 mins", "0.75" and "three
    | quarters of an hour" into one column. "Custom" is the escape hatch, and
    | is why the column is a number rather than an enum.
    */
    'break_durations' => [15, 30, 45, 60],

    /*
    | Sanity bounds for the hour limits, not policy. A rota with a 30-hour day
    | in it is a typo, and refusing it here is cheaper than explaining the
    | schedule it would generate.
    */
    'limits' => [
        'max_hours_per_day' => 24,
        'max_hours_per_week' => 168,
        'max_consecutive_days' => 31,
        'max_rest_hours' => 48,
    ],
];
