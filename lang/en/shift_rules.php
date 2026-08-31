<?php

declare(strict_types=1);

/*
| App Settings → Staff → Shift Rules.
|
| A shift rule is a reusable working pattern; a staff schedule is what applies
| one to a person. The copy is deliberately worded against that distinction,
| because it is the thing readers get wrong.
*/

return [

    'title' => 'Shift Rules',
    'intro' => 'Reusable working patterns. A rule is a template — assigning one to somebody, and changing a day they actually work, is done from Staff → Staff Schedule.',
    'add' => 'Add Shift Rule',
    'add_title' => 'Add a shift rule',
    'edit_title' => 'Edit shift rule',
    'saving' => 'Saving…',
    'save' => 'Save rule',
    'save_and_add_another' => 'Save & Add Another',

    'created' => ':name saved.',
    'updated' => ':name updated.',
    'deleted' => 'Shift rule deleted.',
    'duplicated' => 'Copied :name. Give the copy its own name and save it.',
    'copy_of' => ':name (copy)',
    'made_active' => ':name is active.',
    'made_inactive' => ':name is no longer offered for new schedules.',

    'sections' => [
        'basics' => 'Basic information',
        'days' => 'Working days & hours',
        'break' => 'Break',
        'limits' => 'Hour rules',
        'flexibility' => 'Flexibility',
        'dates' => 'Effective dates',
        'split' => 'Split Shift Settings',
    ],

    'fields' => [
        'name' => 'Rule name',
        'name_placeholder' => 'Standard Full-Time',
        'description' => 'Description',
        'description_placeholder' => 'When or why this rule should be used.',
        'location_scope' => 'Applies to',
        'locations' => 'Locations',
        'locations_placeholder' => 'Search or select locations',
        'status' => 'Status',

        'break_type' => 'Break',
        'break_minutes' => 'Break length',
        'break_starts_at' => 'Break starts',
        'break_ends_at' => 'Break ends',

        'max_hours_per_day' => 'Maximum hours per day',
        'max_hours_per_week' => 'Maximum hours per week',
        'min_hours_per_shift' => 'Minimum hours per shift',
        'max_hours_per_shift' => 'Maximum hours per shift',
        'min_rest_hours' => 'Minimum rest between shifts',
        'min_rest_hours_hint' => 'Stops a rota that finishes at 11pm and starts again at 6am.',
        'max_consecutive_days' => 'Maximum consecutive working days',

        'allow_overtime' => 'Allow overtime',
        'overtime_after_hours' => 'Overtime after',
        'max_overtime_hours' => 'Maximum overtime',
        'allow_adjustment' => 'Allow schedule adjustment',
        'allow_adjustment_hint' => 'A manager may move one generated shift without changing this rule.',
        'allow_split_shift' => 'Allow Employee Split Shift',
        'allow_split_shift_hint' => 'Allow an employee to work more than one separate shift period on the same day.',
        'allow_same_employee_multiple_periods' => 'Allow same employee on multiple periods',
        'allow_same_employee_multiple_periods_hint' => 'The same person may work more than one period in a day, provided the periods do not overlap.',
        'max_periods_per_employee_per_day' => 'Maximum shift periods per employee per day',
        'min_gap_minutes' => 'Minimum gap between split shifts',
        'min_gap_hint' => 'Non-working time required between two periods the same person works.',
        'minutes_unit' => 'minutes',

        'effective_from' => 'Effective from',
        'effective_until' => 'Effective until',
        'effective_hint' => 'Leave both empty for a rule that is always in force.',

        'hours_unit' => 'hours',
        'hours_per_week' => 'hours / week',
        'days_unit' => 'days',
    ],

    'columns' => [
        'day' => 'Day',
        'business_hours' => 'Business hours',
        'name' => 'Rule',
        'location' => 'Location',
        'days' => 'Working days',
        'hours' => 'Default hours',
        'weekly' => 'Weekly hours',
        'overtime' => 'Overtime',
        'status' => 'Status',
        'assigned' => 'Staff',
        'updated' => 'Last updated',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'location_scopes' => [
        'all' => 'All locations',
        'specific' => 'Specific locations',
    ],

    'break_types' => [
        'none' => 'No break',
        'fixed' => 'Fixed break',
        'duration' => 'Duration only',
    ],

    'break_custom' => 'Custom',
    'minutes' => ':count min',
    'hours_vary' => 'Varies by day',
    'no_working_days' => 'No working days',
    'all_locations' => 'All locations',
    'overtime_allowed' => 'Allowed',
    'overtime_not_allowed' => 'Not allowed',
    'not_set' => 'Not set',

    'assigned_staff' => 'Assigned staff',
    'assigned_count' => '{0} No staff on this rule yet|{1} :count staff member|[2,*] :count staff members',
    'assigned_where' => 'Staff are put on a rule from the Add or Edit Staff form. Generating their actual dated schedule is Staff → Staff Schedule.',

    'duplicate' => 'Duplicate',
    'activate' => 'Activate',
    'deactivate' => 'Deactivate',
    'deactivate_confirm' => 'Deactivate :name? It stays readable on the schedules that already use it, but is not offered for new ones.',
    'delete_confirm' => 'Delete :name? This rule will be permanently removed.',
    'delete_blocked' => ':name is in use by :count staff, so it cannot be deleted. Deactivate it instead — the schedules that use it need it to stay readable.',

    'validation' => [
        'period_name_required' => 'Give every shift period a name.',
        'period_times_required' => 'Give every shift period a start and an end time.',
        'period_ends_after_starts' => 'A shift period must end later than it starts.',
        'period_outside_business_hours' => 'Shift period must fall within the business working hours.',
        'periods_required' => 'Add at least one shift period, or switch split shifts off.',
        'period_break_too_long' => 'The break is longer than the period.',
        'name_required' => 'Give the rule a name.',
        'name_taken' => 'A rule with that name already exists.',
        'days_required' => 'Choose at least one working day.',
        'ends_after_starts' => 'The end time must be later than the start time.',
        'periods_overlap' => 'The working periods on :day overlap.',
        'split_not_allowed' => 'This rule does not allow split shifts, so :day may only have one period.',
        'break_too_long' => 'The break is longer than the shortest working day.',
        'break_minutes_required' => 'Choose how long the break is.',
        'break_times_required' => 'Give the fixed break a start and an end.',
        'weekly_hours_positive' => 'Maximum hours per week must be more than zero.',
        'min_shift_over_max' => 'Minimum hours per shift cannot be more than the maximum.',
        'until_before_from' => 'Effective until cannot be earlier than effective from.',
        'locations_required' => 'Choose at least one location, or set the rule to apply everywhere.',
    ],

    'results' => [
        'zero' => 'No shift rules found',
        'one' => '1 shift rule found',
        'many' => ':count shift rules found',
        'empty' => 'No shift rules match your search or filters.',
        'clear' => 'Clear filters',
    ],
    'showing' => 'Showing :from–:to of :total shift rules',
    'none_yet' => 'No shift rules yet',
    'none_yet_hint' => 'A shift rule is a working pattern you write once and apply to as many people as you like — "Standard Full-Time", "Weekend Shift", "Part-Time Morning".',

    'weekdays_short' => [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ],

    'filters' => [
        'all_statuses' => 'All statuses',
        'all_locations' => 'All locations',
    ],

    /* The week editor is the same island the location-hours screen uses, so
       only the words that differ are overridden: a pattern has working days
       and days off, where a branch is open or closed. */
    'hours_editor' => [
        'open' => 'Working',
        'closed' => 'Off',
        'closed_all_day' => 'Not working',
        'add_period' => 'Add another period',
        'opening_time' => ':day start time',
        'closing_time' => ':day end time',
    ],

    'hours_are_global' => 'Business working hours are used across scheduling and booking. Changing them here changes them everywhere.',
    'edit_business_hours' => 'Edit Business Working Hours',
    'no_location_yet' => 'Add a location before writing a rule — a working week belongs to a branch.',
    'closed' => 'Closed',

    'sections_split' => 'Split Shift Settings',
    'split_intro' => 'Divide the business day into named periods for coverage. Every period must fall within the business working hours.',

    'periods' => [
        'name' => 'Shift name',
        'name_placeholder' => 'Morning Shift',
        'starts_at' => 'Start time',
        'ends_at' => 'End time',
        'break' => 'Break',
        'no_break' => 'No break',
        'status' => 'Status',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'add' => '+ Add Shift Period',
        'remove' => 'Remove',
        'minutes' => ':count min',
        'empty' => 'No shift periods yet. Add the first one to divide the business day.',
    ],

    'enable' => 'Enable Shift Rules',
    'enable_hint' => 'Enable reusable staff scheduling rules for this business.',
    'feature_on' => 'Shift rules are on.',
    'feature_off' => 'Shift rules are off. Nothing has been deleted.',
    'feature_off_title' => 'Shift rules are switched off',
    'feature_off_kept' => '{0} Turn them on to write a working pattern you can apply to as many people as you like.|{1} Your :count rule is still here — turn shift rules on to see it.|[2,*] Your :count rules are still here — turn shift rules on to see them.',
    'rule_count' => '{0} Shift rules|{1} :count shift rule|[2,*] :count shift rules',
    'back_to_list' => 'Back to rules',
    'delete_title' => 'Delete Shift Rule?',
];
