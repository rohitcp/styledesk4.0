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
    /** The accessible name for a card's Edit control; :section is the card title. */
    'edit_section' => 'Edit :section',
    'delete' => 'Delete',
    'remove' => 'Remove',
    'add' => 'Add',
    'custom_color' => 'Custom colour',
    'back' => 'Back',
    'close' => 'Close',
    'dismiss' => 'Dismiss',
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
        'progress' => 'Upload progress',
        'cancel' => 'Cancel upload',
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

    /*
    | The live-validation messages the browser writes while a form is being
    | filled in. Worded to match what the server says when it refuses the same
    | value, so correcting a field before submitting and correcting it after
    | read identically. :field is the field's own label.
    */
    'validation' => [
        'required' => ':field is required.',
        'email' => 'Enter a valid email address.',
        'url' => 'Enter a valid website address, starting with https://',
        'phone' => 'Enter a valid phone number.',
        'date' => 'Enter a valid date.',
        'numeric' => ':field must be a number.',
        'integer' => ':field must be a whole number.',
        'min' => ':field must be at least :min characters.',
        'max' => ':field must be :max characters or fewer.',
        'min_value' => ':field must be :min or more.',
        'max_value' => ':field must be :max or less.',
        'taken' => 'That value is already in use.',
    ],
    'type_a_time' => 'Type a time, e.g. 2:30 PM',

    /*
    | The chrome every layout draws: the announcement bar above the app bar,
    | the footer beneath it, and the banner that appears when a page's assets
    | have gone stale.
    |
    | Here rather than in a layout because four layouts draw the same footer,
    | and a string typed into each of them is a string that gets translated in
    | three of them.
    */
    'stale_assets' => 'This page is out of date, so parts of it will not work. ',
    'reload' => 'Reload',
    'legal' => 'Legal',
    'terms' => 'Terms',
    'privacy' => 'Privacy',
    'support' => 'Support',
    'all_rights_reserved' => '© :year StyleDesk. All rights reserved.',

    'banner' => [
        'watch_now' => 'Watch Now: Getting started with StyleDesk',
        /* trans_choice, not Str::plural(): the helper only knows English, so
           it would have written "2 day" in every other language. Each language
           states its own plural here, and the whole sentence is one string so
           word order can differ too. */
        'trial_remaining' => '{0} Your free trial ends today|{1} :count day remaining in your free trial|[2,*] :count days remaining in your free trial',
        'trial_ending' => 'Your trial ends soon',
        'subscribe' => 'Subscribe now',
    ],

    'session' => [
        'expiring' => 'Your session is about to expire',
        'expiring_body' => 'For your security, you will be signed out because there has been no activity.',
        /* :time is the live countdown, wrapped in its own element by the view. */
        'countdown' => 'Session expires in :time',
        'sign_out' => 'Sign out',
        'stay' => 'Stay signed in',
        'expired' => 'Your session has expired',
        'expired_body' => 'Your session ended because there was no activity. Sign in again to continue.',
        'sign_in' => 'Sign in',
    ],
];
