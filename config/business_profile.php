<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Business profile options
|--------------------------------------------------------------------------
|
| The choices offered on the Business settings screen. Kept in config rather
| than as enums in a form, because the view has to turn a stored value back
| into a label and both sides must read from the same list — a select whose
| options drift from the display map shows a stored value as blank.
|
*/

return [

    /*
    | Date formats, keyed by the PHP format string that is actually stored.
    | The label carries a live example, which is the only way most people can
    | tell d/m/Y from m/d/Y.
    */
    'date_formats' => [
        'd/m/Y' => 'DD/MM/YYYY',
        'm/d/Y' => 'MM/DD/YYYY',
        'Y-m-d' => 'YYYY-MM-DD',
        'd M Y' => 'DD Mon YYYY',
        'M j, Y' => 'Mon D, YYYY',
        'j F Y' => 'D Month YYYY',
    ],

    'time_formats' => [
        '12' => '12-hour (1:30 PM)',
        '24' => '24-hour (13:30)',
    ],

    /*
    | 0-6 matching PHP's `w`, so the stored value can be compared directly
    | against date('w') without a translation table.
    */
    'first_day_of_week' => [
        0 => 'Sunday',
        1 => 'Monday',
        6 => 'Saturday',
    ],

    /*
    | Appointment lengths and the grid bookings snap to. Offered as minutes so
    | the stored value is arithmetic-ready rather than a label to parse.
    */
    'booking_durations' => [
        15 => '15 minutes',
        20 => '20 minutes',
        30 => '30 minutes',
        45 => '45 minutes',
        60 => '1 hour',
        90 => '1 hour 30 minutes',
        120 => '2 hours',
    ],

    'appointment_intervals' => [
        5 => '5 minutes',
        10 => '10 minutes',
        15 => '15 minutes',
        20 => '20 minutes',
        30 => '30 minutes',
        60 => '1 hour',
    ],

    'tax_behaviors' => [
        'inclusive' => 'Prices include tax',
        'exclusive' => 'Tax added at checkout',
        'none' => 'No tax applied',
    ],

    'staff_assignment' => [
        'any' => 'Any available staff member',
        'client-chooses' => 'Client chooses a staff member',
        'manual' => 'Assigned manually by the business',
    ],
];
