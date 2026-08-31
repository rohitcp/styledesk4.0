<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shifts
|--------------------------------------------------------------------------
|
| A shift is working hours on a named date — Saturday 12 September, 10:00 to
| 16:00. That is what tells it from a staff schedule, which is the recurring
| pattern underneath: Monday to Friday, nine to five. A shift on a date
| overrides the pattern for that date.
|
| This file decides which options exist; the labels come from the shifts
| language file, so a business running in Spanish gets Spanish without a
| second list to keep in step.
|
*/

return [

    /*
    | Why somebody is working this particular day. Not a rota rule — a note
    | to whoever reads the week — but a structured one, so "who covered for
    | whom last month" is a question the data can answer.
    */
    'types' => [
        'regular' => ['label' => 'Regular', 'class' => 'styledesk_badge--soon'],
        'overtime' => ['label' => 'Overtime', 'class' => 'styledesk_badge--setup'],
        'cover' => ['label' => 'Cover', 'class' => 'styledesk_badge--setup'],
        'training' => ['label' => 'Training', 'class' => 'styledesk_badge--soon'],
        'on-call' => ['label' => 'On call', 'class' => 'styledesk_badge--soon'],
        'custom' => ['label' => 'Custom', 'class' => 'styledesk_badge--soon'],
    ],

    /*
    | Where the shift has got to.
    |
    | Cancelled is kept rather than deleted: a shift that was cancelled is a
    | fact about the week, and a row that disappears leaves whoever is looking
    | at the rota wondering whether it was ever there.
    */
    'statuses' => [
        'scheduled' => ['label' => 'Scheduled', 'class' => 'styledesk_badge--setup'],
        'confirmed' => ['label' => 'Confirmed', 'class' => 'styledesk_badge--active'],
        'completed' => ['label' => 'Completed', 'class' => 'styledesk_badge--soon'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'styledesk_badge--soon'],
    ],

    /*
    | The unpaid break inside a shift, offered as a list rather than a free
    | number: every business uses the same handful, and a text box invites
    | "45 mins", "0.75" and "three quarters of an hour" into one column.
    */
    'breaks' => [0, 15, 30, 45, 60, 90],

    /*
    | How the published-schedule email is delivered.
    |
    | Sync by default, which means in the same request that published the
    | schedule: a queued job only leaves the building once a worker picks it
    | up, and an installation without one leaves a manager told the staff
    | member was emailed while the row sits in the jobs table. The staff
    | member being told is the point of publishing, so it does not wait on
    | infrastructure that may not be running.
    |
    | Set SCHEDULE_MAIL_QUEUE to the queue connection once a worker is part
    | of the deployment, and publishing hands the send off again.
    */
    'mail_connection' => env('SCHEDULE_MAIL_QUEUE', 'sync'),
];
