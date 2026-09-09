<?php

declare(strict_types=1);

/*
| The Shifts screen: working hours on a named date.
|
| Deliberately worded against the staff schedule it sits beside — a schedule
| is the recurring pattern, a shift is one dated block — because the two are
| easy to confuse and the copy is where that distinction is actually made.
*/

return [

    'title' => 'Shifts',
    'intro' => 'Working hours on a named date. A shift overrides the recurring schedule for that day.',
    'add' => 'Add Shift',
    'add_title' => 'Add a shift',
    'edit_title' => 'Edit shift',
    'adding' => 'Adding…',
    'saving' => 'Saving…',
    'save' => 'Save shift',
    'save_and_add_another' => 'Save & Add Another',

    'created' => 'Shift added for :name.',
    'updated' => 'Shift updated.',
    'deleted' => 'Shift removed.',
    'delete_confirm' => 'Remove this shift for :name on :date?',
    'correct_fields' => 'Check the highlighted fields and try again.',

    'fields' => [
        'staff' => 'Staff member',
        'staff_placeholder' => 'Search or select staff',
        'location' => 'Location',
        'location_placeholder' => 'Search or select a location',
        'date' => 'Date',
        'starts_at' => 'Start time',
        'ends_at' => 'End time',
        'break' => 'Break',
        'break_hint' => 'Unpaid time inside the shift.',
        'type' => 'Shift type',
        'status' => 'Status',
        'notes' => 'Notes',
        'notes_placeholder' => 'Anything whoever reads the rota should know.',
    ],

    'columns' => [
        'staff' => 'Staff',
        'date' => 'Date',
        'hours' => 'Hours',
        'break' => 'Break',
        'location' => 'Location',
        'type' => 'Type',
        'status' => 'Status',
    ],

    'filters' => [
        'all_staff' => 'All staff',
        'all_locations' => 'All locations',
        'all_types' => 'All types',
        'all_statuses' => 'All statuses',
        'from' => 'From',
        'until' => 'Until',
    ],

    'types' => [
        'regular' => 'Regular',
        'overtime' => 'Overtime',
        'cover' => 'Cover',
        'training' => 'Training',
        'on-call' => 'On call',
        'custom' => 'Custom',
    ],

    'statuses' => [
        'scheduled' => 'Scheduled',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'minutes' => ':count min',
    'no_break' => 'None',

    'view' => 'View shift',
    'duplicate' => 'Duplicate',
    'cancel_shift' => 'Cancel shift',
    'cancel_confirm' => 'Cancel this shift? It stays on the rota marked cancelled, so everyone can see it was dropped.',
    'cancelled_toast' => 'Shift cancelled.',

    'validation' => [
        'business_closed' => 'The business is closed on :day, so nobody can be rostered that day. Change the day, or open it in App Settings → Business → Working Hours.',
        'outside_business_hours' => 'Those hours fall outside when the business is open (:hours).',
        'ends_after_start' => 'The end time must be later than the start time.',
        'break_too_long' => 'The break is longer than the shift.',
        'clash' => ':name already has a shift that overlaps these hours on that day.',
        'staff_required' => 'Choose who is working this shift.',
        'date_required' => 'Choose the day this shift is on.',
    ],

    'results' => [
        'zero' => 'No shifts found',
        'one' => '1 shift found',
        'many' => ':count shifts found',
        'empty' => 'No shifts match your search or filters.',
        'clear' => 'Clear filters',
    ],
    'showing' => 'Showing :from–:to of :total shifts',
    'none_yet' => 'No shifts yet',
    'none_yet_hint' => 'Add a shift when somebody works hours their recurring schedule does not already cover — a Saturday, a cover, an evening of training.',
];
