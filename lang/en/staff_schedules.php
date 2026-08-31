<?php

declare(strict_types=1);

/*
| The team's rota board: a row per person, a column per month.
|
| The wording keeps three states apart, because the whole screen exists to
| tell them apart at a glance: Published is a month the person has been
| emailed, Draft is one planned but not sent, and Not Scheduled is a month
| nobody has thought about yet.
*/

return [

    'title' => 'Staff Schedule',
    'intro' => 'Which months are covered, which are still drafts, and who has nothing planned.',
    'summary' => '{0} No staff members|{1} :count staff member|[2,*] :count staff members',

    'assign' => 'Assign Schedule',
    'add_shift' => 'Add Shift',

    'search_label' => 'Search staff',
    'search_placeholder' => 'Search by name, email or job title',

    'filters' => [
        'month' => 'Month',
        'year' => 'Year',
        'status' => 'Schedule status',
        'coverage' => 'Schedule coverage',
        'all_statuses' => 'All statuses',
        'all_coverage' => 'All months',
        'apply' => 'Apply',
        'reset' => 'Reset',
    ],

    'statuses' => [
        'scheduled' => 'Scheduled',
        'not-scheduled' => 'Not scheduled',
        'draft' => 'Draft',
        'published' => 'Published',
    ],

    'coverage' => [
        'completed' => 'Completed months',
        'current' => 'Current month',
        'future' => 'Future months',
    ],

    /*
    | What one cell says. "Changes" is the fourth state the per-person screen
    | already keeps: sent once and edited since, which is a draft as far as
    | the staff member is concerned and not one as far as their inbox is.
    */
    'states' => [
        'published' => 'Published',
        'draft' => 'Draft',
        'changes' => 'Changes pending',
        'not-scheduled' => 'Not Scheduled',
    ],

    'columns' => [
        'staff' => 'Staff Member',
    ],

    'summary_line' => 'Month :month · Year :year',
    'shifts_count' => '{1} :count shift|[2,*] :count shifts',
    'scroll_hint' => 'Scroll left and right to see every month.',
    'this_month' => 'This month',
    'cell_hint' => ':name · :month',
    'open_schedule' => 'Open :name’s schedule for :month',
    'start_schedule' => 'Assign :name a schedule for :month',

    'menu' => [
        'view' => 'View :month schedule',
        'assign' => 'Assign :month schedule',
    ],

    'actions_for' => 'Actions for :name',
    'showing' => 'Showing :from–:to of :total staff',
    'results' => [
        'zero' => 'No staff members',
        'one' => '1 staff member',
        'many' => ':count staff members',
        'clear' => 'Clear filters',
    ],
    'empty' => 'No staff members match these filters.',
    'empty_hint' => 'Clear the filters to see the whole team.',
    'no_months' => 'No months match this coverage filter.',

    'start' => [
        'title' => 'Assign Schedule',
        'intro' => 'Choose who the schedule is for and which month it covers. The month is planned whole.',
        'staff' => 'Staff member',
        'choose_staff' => 'Search or select a staff member',
        'continue' => 'Continue to Schedule',
    ],

    'month' => [
        'date' => 'Date',
        'day' => 'Day',
        'shift' => 'Shift',
        'start' => 'Start',
        'end' => 'End',
        'break' => 'Break',
        'hours' => 'Total hours',
        'status' => 'Status',
        'split' => 'Shift :index',
        'minutes' => ':count min',
        'edit' => 'Edit / Reassign Schedule',
        'loading' => 'Loading…',
        'failed' => 'That schedule could not be loaded.',
    ],

    'legend' => [
        'title' => 'Key',
        'published' => 'Everyone has been emailed this month.',
        'draft' => 'Planned, but nobody has been told yet.',
        'not_scheduled' => 'Nothing planned — needs attention.',
    ],
];
