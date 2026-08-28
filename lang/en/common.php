<?php

declare(strict_types=1);

/*
| Words the whole app uses. Anything that appears on more than one screen
| belongs here rather than in that screen's own file, so "Save" is translated
| once and cannot come out two ways.
*/

return [
    'retry' => 'Retry',
    'save' => 'Save',
    'save_changes' => 'Save changes',
    'done' => 'Done',
    'cancel' => 'Cancel',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'remove' => 'Remove',
    'add' => 'Add',
    'custom_color' => 'Custom colour',
    'back' => 'Back',
    'close' => 'Close',
    'search' => 'Search',
    'filter' => 'Filter',
    'clear' => 'Clear',
    'clear_all' => 'Clear all',
    'apply' => 'Apply',
    'show_more' => 'Show more',
    'show_less' => 'Show less',
    'view' => 'View',
    'yes' => 'Yes',
    'no' => 'No',
    'optional' => '(optional)',
    'not_set' => 'Not set',
    'on' => 'On',
    'off' => 'Off',
    'none' => 'None',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'saving' => 'Saving…',
    'loading' => 'Loading…',

    /**
     * The shared calendar picker (x-date-field).
     *
     * Month and weekday names are not listed here — Carbon already knows them
     * in every locale the app offers, and a hand-maintained list of twelve
     * would only be a second place for them to disagree.
     */
    'choose_a_date' => 'Choose a date',
    'today' => 'Today',
    'month' => 'Month',
    'year' => 'Year',
    'previous_month' => 'Previous month',
    'next_month' => 'Next month',
    'language' => 'Language',
    'coming_soon' => 'Coming soon',
    'setup_required' => 'Setup required',
    'view_only' => 'View only',

    /*
     * The shared image uploader. Used by the staff avatar today and by any
     * future image field, so its words live here rather than in a module.
     */
    'upload' => [
        'choose' => 'Choose image',
        'too_large' => 'That image is larger than 2 MB.',
        'failed' => 'That image could not be uploaded. Please try again.',
    ],
    /** Whether someone agreed to be contacted. Rendered by <x-consent-status>. */
    'consent' => [
        'opted_in' => 'Opted in',
        'opted_out' => 'Opted out',
    ],
    /** Asked before anything is taken off a record. */
    'confirm' => [
        'deactivate' => 'Deactivate',
        'activate' => 'Activate',
        'remove_tag_title' => 'Remove client tag?',
        'remove_tag' => 'Remove “:label” from this client?',
        'remove_behavioral_title' => 'Remove behavioural tag?',
        'remove_behavioral' => 'Remove “:label” from this client?',
    ],
];
