<?php

declare(strict_types=1);

/*
| Bookings that were started and not finished.
|
| The wording treats a lead as a call to return rather than as a failure. Most
| of them are somebody who rang off to check a date, and a screen that called
| them "abandoned" would have the desk apologising for its own diary.
*/

return [

    'title' => 'Booking leads',
    'intro' => 'Bookings that were started but never finished. Each one is a call worth returning.',

    'search' => 'Search by name, reference or number…',
    'search_label' => 'Search leads',
    'all_statuses' => 'All statuses',

    'actions' => [
        'complete' => 'Complete booking',
        'view_booking' => 'View booking',
        'view_client' => 'View client',
    ],

    'columns' => [
        'who' => 'Client',
        'services' => 'Asked for',
        'expected' => 'Wanted for',
        'total' => 'Value',
        'started' => 'Started',
        'step' => 'Stopped at',
        'status' => 'Status',
    ],

    /*
    | What happened to a lead. Not how far it got — that is the step below,
    | and the two are kept apart on purpose: "follow-up required" says to
    | ring them, "deposit & payment" says what to ring about.
    */
    'statuses' => [
        'new' => ['label' => 'New'],
        'in-progress' => ['label' => 'In progress'],
        'awaiting-confirmation' => ['label' => 'Awaiting client'],
        'awaiting-deposit' => ['label' => 'Awaiting deposit'],
        'payment-pending' => ['label' => 'Payment pending'],
        'follow-up' => ['label' => 'Follow-up required'],
        'contacted' => ['label' => 'Contacted'],
        'converted' => ['label' => 'Converted'],
        'abandoned' => ['label' => 'Abandoned'],
        'cancelled' => ['label' => 'Cancelled'],
        'lost' => ['label' => 'Lost'],
        'expired' => ['label' => 'Expired'],
    ],

    /* How far through the booking screen the client got. */
    'steps' => [
        'service' => 'Service',
        'when' => 'Who with + when',
        'details' => 'Booking details',
        'payment' => 'Deposit & payment',
        'comms' => 'Communication',
        'completed' => 'Finished',
    ],

    /* Why it ended where it did. */
    'reasons' => [
        'changed-mind' => 'Client changed their mind',
        'no-suitable-time' => 'No suitable appointment',
        'staff-unavailable' => 'Preferred staff unavailable',
        'price' => 'Price',
        'duplicate' => 'Duplicate request',
        'declined' => 'Client declined',
        'unreachable' => 'Unable to contact',
        'booked-elsewhere' => 'Booked elsewhere',
        'no-availability' => 'No suitable availability',
        'other' => 'Other',
    ],

    'contact_methods' => [
        'phone' => 'Phone',
        'sms' => 'Text message',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'in-person' => 'In person',
    ],

    /*
    | The drawer the listing opens over itself.
    |
    | A field nobody has filled in yet says "Not selected" rather than coming
    | back blank: a blank line reads as broken, and what is missing is
    | usually what the call is about.
    */
    'drawer' => [
        'not_selected' => 'Not selected',
        'summary' => 'Lead',
        'created' => 'Created',
        'created_by' => 'Created by',
        'last_activity' => 'Last activity',
        'taken_by' => 'Last contacted by',
        'client' => 'Client',
        'name' => 'Name',
        'phone' => 'Phone',
        'email' => 'Email',
        'preferences' => 'Booking preferences',
        'booking' => 'Booking details',
        'services' => 'Services',
        'duration' => 'Duration',
        'price' => 'Price',
        'date' => 'Date',
        'time' => 'Time',
        'staff' => 'With',
        'notes' => 'Notes',
        'payment' => 'Payment',
        'estimated_total' => 'Estimated total',
        'deposit_required' => 'Deposit required',
        'deposit_paid' => 'Deposit paid',
        'outstanding' => 'Outstanding',
        'payment_status' => 'Payment status',
        'journey' => 'Booking progress',
        'activity' => 'Activity',
        'no_activity' => 'Nothing recorded yet.',
        'stopped_at' => 'Stopped at',
        'view_client' => 'Open client record',
        'close' => 'Close',
        'send_email' => 'Send email',
        'send_sms' => 'Send SMS',
        'soon' => 'Soon',
    ],

    'events' => [
        'created' => 'Booking lead created',
        'step' => ':step completed',
        'cancelled' => 'Cancelled — :reason',
        'converted' => 'Converted into a booking',
        'contacted' => 'Client contacted',
    ],

    'notes' => [
        'button' => 'Notes',
        'title' => 'Notes',
        'write' => 'Add a note',
        'placeholder' => 'Rang at 4pm, no answer — will try tomorrow.',
        'hint' => 'Saved to the client record and tagged with this lead, so the next person sees it either way.',
        'save' => 'Save note',
        'saved' => 'Note saved to the client record.',
        'empty' => 'No notes about this lead yet.',
        'needs_client' => 'A walk-in has no client record to keep a note on.',
    ],

    'cancel' => [
        'title' => 'Cancel this booking lead?',
        'body' => 'The lead is marked cancelled and kept in the history, with who cancelled it and when.',
        'reason' => 'Reason',
        'choose_reason' => 'Choose a reason',
        'note' => 'Note',
        'note_placeholder' => 'Anything the next person should know — optional.',
        'keep' => 'Keep lead',
        'confirm' => 'Cancel booking lead',
        'cancelled' => 'Booking lead cancelled.',
        'failed' => 'That could not be saved. Try again.',
    ],

    'empty' => 'No leads match these filters.',
    'none_yet' => 'No booking leads yet',
    'none_yet_hint' => 'A lead is written when somebody starts a booking and does not finish it, so you have the reference and the services to call back about.',

    'showing' => 'Showing :from–:to of :total leads',
    'results' => [
        'zero' => 'No leads',
        'one' => '1 lead',
        'many' => ':count leads',
    ],
];
