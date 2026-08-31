<?php

declare(strict_types=1);

/*
| Assigning a working schedule to one member of staff.
|
| The wording keeps three things apart, because they are easy to confuse and
| the copy is where that distinction is actually made: business working hours
| say when the business is open, a shift rule is the pattern somebody is on,
| and the schedule below is the dated reality that pattern generated.
*/

return [

    'year' => 'Year',
    'monthly_summary' => 'Monthly summary',
    'month_hours' => 'Hours',
    'month_shifts' => 'Shifts',
    'month_days' => 'Working days',
    'bookings_pending' => 'Booking counts arrive with the booking module.',

    'working_schedule' => 'Working schedule',
    'assign' => 'Assign Schedule',
    'assign_title' => 'Assign Schedule',
    'assign_intro' => 'Assign a working schedule for the selected staff member and date range.',
    'assign_for' => 'Staff member',
    'period' => 'Schedule period',
    'assigning' => 'Assigning…',

    'add_shift' => 'Add Shift',
    'add_for_day' => 'Add schedule',
    'delete_day' => 'Delete Schedule',
    'delete_day_title' => 'Delete Schedule?',
    'delete_day_confirm' => "This will remove :name's working schedule for :date.",
    'day_deleted' => 'Schedule for :date deleted.',
    'mark_on_leave' => 'Mark as On Leave — Coming Soon',
    'showing' => 'Showing :from–:to of :total days',
    'actions_for' => 'Actions for :name',

    'filters' => [
        'period' => 'Period',
        'month' => 'Month',
        'year' => 'Year',
        'apply' => 'Apply',
        'edit_period' => 'Change month',
        'reset' => 'Reset',
    ],

    'periods' => [
        'month' => 'Month',
        '3-months' => '3 Months',
        '6-months' => '6 Months',
        '9-months' => '9 Months',
        'year' => 'Year',
    ],

    'columns' => [
        'date' => 'Date',
        'day' => 'Day',
        'working' => 'Working Status',
        'time' => 'Time',
        'status' => 'Status',
        'published_on' => 'Published On',
        'published_by' => 'Published By',
        'hours' => 'Total Hours',
        'bookings' => 'Bookings',
    ],

    'empty' => 'No schedule found',
    'empty_hint' => 'There are no staff schedule records for the selected period.',

    'duration_intro' => 'The whole month is planned at once. You can change any day once the schedule opens.',
    'continue_label' => 'Continue',
    'back' => 'Back',
    'close' => 'Close',
    'save' => 'Save',
    'save_and_publish' => 'Save & Publish',
    'update_and_publish' => 'Update & Publish',
    'rule_change_title' => 'Rebuild the days from this rule?',
    'rule_change_confirm' => 'The days below will be filled in again from the rule you have chosen, and the changes you have made here will be lost.',
    'rule_change_label' => 'Rebuild the days',
    'leave_title' => 'Leave without saving?',
    'leave_confirm' => 'This schedule has changes that have not been saved. Leaving now discards them.',
    'leave_confirm_label' => 'Discard changes',
    'needs_javascript' => 'Building a schedule needs JavaScript, which is switched off in this browser.',

    'summary' => '{0} Nothing scheduled|{1} :hours scheduled hours · :count working day|[2,*] :hours scheduled hours · :count working days',
    'hours_short' => ':count hrs',

    'working' => 'Working',
    'off' => 'Off',
    'not_working' => 'Not working',
    'week_number' => 'Week :number',
    'add_period' => '+ Add Working Period',
    'remove_period' => 'Remove this period',
    'starts_at' => 'Start',
    'ends_at' => 'End',
    'break' => 'Break',
    'no_break' => 'No break',

    'assigned' => '{1} :count shift assigned.|[2,*] :count shifts assigned.',
    'nothing_to_assign' => 'No working days were chosen, so nothing was assigned.',
    'replaced_note' => 'Assigning replaces the shifts already in this range.',

    'shift_rule' => 'Shift Rule',
    'no_shift_rule' => 'No Shift Rule',
    'change_rule' => 'Change',
    'prefill_hint' => 'Choosing a rule fills the days below from the business working hours and the rule\'s own shift periods. Changing a day here changes only this schedule, never the rule.',

    'refused' => '{1} The schedule was not saved: one day breaks the Shift Rule.|[2,*] The schedule was not saved: :count days break the Shift Rule.',
    'refused_title' => 'This schedule was not saved.',

    'publish_statuses' => [
        'draft' => 'Draft',
        'published' => 'Published',
    ],

    /*
    | Publishing.
    |
    | Assigning a schedule and telling somebody about it are separate acts,
    | and the copy is where that separation is actually made: a draft is the
    | manager still thinking, published is the staff member's working week.
    */
    'draft_badge' => 'Draft Schedule',
    'published_badge' => 'Published',
    'published_notice_title' => 'This schedule has already been published.',
    'published_notice' => 'Any changes you make here update :name\'s published schedule. When you publish it again, they will be emailed to say their schedule has changed. Saving as a draft tells them nothing.',
    'locked' => 'Published — the staff member has been told about this day.',
    'published_on' => 'Published :date',
    'published_by' => 'by :name',
    'changes_badge' => 'Changes not published',
    'changes_hint' => 'This schedule was published on :date and has been edited since. :name has not been told about the changes.',
    'draft_hint' => 'Nothing has been sent yet. :name will only be emailed when you publish.',
    'published_hint' => ':name was emailed this schedule.',

    'save_draft' => 'Save Draft',
    'publish' => 'Publish Schedule',
    'publish_changes' => 'Publish Changes',
    'publishing' => 'Publishing…',

    'draft_saved' => 'Schedule saved as draft.',
    'published' => 'Schedule published successfully. :name has been notified by email.',
    'republished' => 'Schedule updated and published successfully. :name has been notified.',
    'published_without_email' => 'Schedule published. :name has no email address on file, so no notification was sent.',
    'published_email_failed' => 'Schedule published, but the email to :name could not be sent. The failure has been logged.',
    'nothing_to_save' => 'There is nothing scheduled in this period to save.',
    'nothing_to_publish' => 'There is nothing scheduled in this period to publish.',

    'confirm' => [
        'title' => 'Publish Staff Schedule?',
        'intro' => 'Review the schedule before publishing. Once published, the staff member will be notified by email.',
        'staff_member' => 'Staff Member',
        'period' => 'Schedule Period',
        'duration' => 'Duration',
        'working_days' => 'Working Days',
        'total_hours' => 'Total Scheduled Hours',
        'weeks' => '{1} 1 Week|[2,*] :count Weeks',
        'hours' => '{1} :count Hour|[2,*] :count Hours',
        'days' => ':count',
        'days_long' => '{1} 1 Day|[2,*] :count Days',
        'will_notify' => 'The schedule will be published and the staff member will be notified by email.',
        'consequence' => "Once published, this schedule becomes the staff member's official working schedule and the staff member will be notified by email.",
    ],

    'confirm_draft' => [
        'title' => 'Save Schedule as Draft?',
        'intro' => 'The schedule will be saved but will not be sent to the staff member until it is published.',
    ],

    'day_total' => '{1} :count Hour|[2,*] :count Hours',

    'email' => [
        'subject' => 'Your work schedule has been published',
        'subject_updated' => 'Your work schedule has been updated',
        'headline_updated' => 'Your work schedule has been updated',
        'intro_updated' => 'This replaces the schedule you were sent before. Here it is in full.',
        'preheader' => 'Your schedule for :from – :until.',
        'headline' => 'Your work schedule has been published',
        'greeting' => 'Hi :name,',
        'intro' => ':business has published your working schedule. Here it is in full.',
        'period' => 'Schedule period',
        'location' => 'Location',
        'working_days' => 'Working days',
        'total_hours' => 'Total scheduled hours',
        'published_on' => 'Published',
        'published_by' => 'Published by',
        'hours' => '{1} :count Hour|[2,*] :count Hours',
        'daily_schedule' => 'Daily schedule',
        'not_working' => 'Not Working',
        'day_total' => '{1} Total: :count Hour|[2,*] Total: :count Hours',
        'cta' => 'View My Schedule',
        'questions' => 'If anything here looks wrong, speak to your manager at :business.',
    ],

    'validation' => [
        'staff_not_active' => ':name is not active, so no schedule can be assigned.',
        'ends_after_starts' => 'The end time must be later than the start time.',
        'periods_overlap' => 'The working periods on this day overlap.',
        'split_not_allowed' => 'The assigned Shift Rule does not allow split shifts, so this day may only have one period.',
        'one_period_only' => 'The assigned Shift Rule does not allow the same person to work more than one period in a day.',
        'too_many_periods' => 'The assigned Shift Rule allows at most :count periods a day.',
        'gap_too_short' => 'The assigned Shift Rule requires :hours hours between split shifts.',
        'business_closed' => 'The business is closed on this day.',
        'outside_business_hours' => 'Outside the business working hours (:hours).',
        'over_daily_hours' => 'More than the :count hours a day the Shift Rule allows.',
        'over_weekly_hours' => 'More than the :count hours a week the Shift Rule allows.',
        'not_enough_rest' => 'Less than the :count hours rest the Shift Rule requires since the previous shift.',
        'too_many_consecutive' => 'More than :count consecutive working days, which the Shift Rule does not allow.',
        'already_working' => 'Already working :hours on this day.',
    ],
];
