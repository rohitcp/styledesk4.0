<?php

declare(strict_types=1);

/*
| The Business Hours module: the overview and the per-location editor.
|
| The weekly-hours card itself is shared with Locations and reads its strings
| from the locations file, so the same editor is worded identically wherever
| it is opened from. (Written out rather than as a path: a glob ending in a
| star-slash closes the comment it is written inside.)
*/

return [
    'title' => 'Business hours',
    'intro' => "Opening hours for each location, plus the holidays, closures and special hours that override them. Times are shown in each location's own time zone.",
    'timezone_note' => "Times are in this location's own time zone, :name (:identifier).",

    'edit_hours' => 'Edit hours',
    'save' => 'Save hours',
    'saved' => 'Business hours saved successfully.',
    'saved_from' => 'Hours saved, starting :date.',
    'also_applied' => '{1} Also applied to :count other location.|[2,*] Also applied to :count other locations.',
    'schedule_discarded' => 'Upcoming hours discarded.',
    'correct_fields' => 'Please correct the highlighted fields and try again.',

    'no_locations' => 'No locations yet.',
    'no_locations_hint' => 'Opening hours belong to a location, so add one first.',
    'add_location' => 'Add a location',

    'upcoming' => [
        'title' => 'Coming up',
        'hint' => 'Holidays, closures and special hours in the next 12 months.',
        'in_progress' => 'In progress',
        'summary' => '{1} :count upcoming exception|[2,*] :count upcoming exceptions',
        'and_more' => 'and :count more',
    ],

    'future' => [
        'starts' => 'New hours start :date.',
        'review' => 'Review them',
        'editing' => 'You are editing hours that start on :date. Today’s hours are unchanged.',
        'edit_today' => "Edit today's hours instead",
        'pending' => 'A different set of hours starts on :date. Changes here apply until then.',
        'edit_those' => 'Edit those instead',
        'discard' => 'Discard',
        'discard_confirm' => 'Discard these upcoming hours? The current hours will keep applying.',
    ],

    'effective' => [
        'title' => 'When these hours start',
        'hint' => 'Leave blank to change the hours in force now. Choose a date to plan a change ahead of time — the current hours keep applying until then.',
        'label' => 'Effective from',
    ],

    'apply' => [
        'title' => 'Apply to other locations',
        'hint' => 'Copies this week to the branches you tick. Each keeps its own copy afterwards, so you can still change one without changing the rest.',
        'warning' => "This replaces the ticked locations' hours for the same period.",
    ],

    'exceptions' => [
        'title' => 'Holidays, closures & special hours',
        'hint' => 'Dates that override the weekly hours above.',
        'add' => 'Add date',
        'empty' => 'Nothing scheduled. Add a public holiday, a closure or a day with different hours.',
        'add_title' => 'Add a date',
        'edit_title' => 'Edit this date',
        'save' => 'Save date',
        'added' => 'Added to the calendar.',
        'updated' => 'Calendar entry updated.',
        'removed' => 'Removed from the calendar.',
        'delete_confirm' => 'Remove “:name” from the calendar?',

        'type' => 'What is it',
        'name' => 'Name',
        'name_placeholder' => 'Christmas Day',
        'from' => 'From',
        'to' => 'To',
        'to_hint' => 'Leave blank for a single day.',
        'closed_all_day' => 'Closed all day',
        'closed_all_day_hint' => 'Turn this off to open with different hours instead.',
        'opens' => 'Opens',
        'closes' => 'Closes',
        'notes' => 'Internal note',
        'notes_placeholder' => 'Only your team sees this.',
    ],

    'validation' => [
        'name_required' => 'Give this a name, so the team knows what it is.',
        'date_required' => 'Choose a date.',
        'end_before_start' => 'The end date cannot be before the start date.',
        'opens_required' => 'Enter the opening time, or mark the day as closed.',
        'closes_required' => 'Enter the closing time, or mark the day as closed.',
        'closes_after_opens' => 'Closing time must be after the opening time.',
        'effective_after' => 'A future schedule must start on a later date. Leave this blank to change today’s hours.',
        'schedule_not_future' => 'Only a schedule that has not started yet can be discarded.',
        'clash' => '“:name” already covers :dates. Edit that entry instead, or choose different dates.',
    ],

    /*
     * The exception types.
     *
     * Keyed by the value stored in location_closures, so the dropdown and the
     * validation rule keep reading one list.
     */
    'types' => [
        'public_holiday' => 'Public holiday',
        'closure' => 'Location closure',
        'special_hours' => 'Special opening hours',
        'training' => 'Staff training day',
        'maintenance' => 'Maintenance closure',
        'private_event' => 'Private event',
        'emergency' => 'Emergency closure',
    ],
];
