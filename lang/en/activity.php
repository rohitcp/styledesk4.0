<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Business activity
|--------------------------------------------------------------------------
|
| The panel behind the app bar's Activity icon: who did what, when, and a way
| through to the record it happened to.
|
| The one-word types are the point of the wording here. "Booking", "Cancel",
| "Payment" — a reader scanning a column of them should be able to find the
| kind of thing they are looking for without reading a sentence, which is
| what the sentence underneath is then free to be about.
|
*/

return [

    'title' => 'Business activity',
    'intro' => 'What has been happening across the business.',
    'open' => 'Activity',
    'close' => 'Close activity',
    'mark_read' => 'Mark all as read',
    'unread' => ':count new',
    'unread_capped' => '99+ new',
    'loading' => 'Loading…',
    'more' => 'Show more',
    'empty' => 'Nothing has happened yet.',
    'empty_hint' => 'Bookings, payments and changes across the business appear here as they happen.',
    'empty_filtered' => 'Nothing of that kind yet.',

    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'earlier' => 'Earlier',

    'by' => 'By :name',
    'system' => 'StyleDesk',

    /*
    | One word each, and never two. The type is a label a reader scans past,
    | not a sentence they read.
    */
    'kinds' => [
        'booking' => 'Booking',
        'reschedule' => 'Reschedule',
        'cancel' => 'Cancel',
        'checkin' => 'Checkin',
        'checkout' => 'Checkout',
        'noshow' => 'Noshow',
        'client' => 'Client',
        'note' => 'Note',
        'file' => 'File',
        'payment' => 'Payment',
        'deposit' => 'Deposit',
        'refund' => 'Refund',
        'staff' => 'Staff',
        'schedule' => 'Schedule',
        'service' => 'Service',
        'resource' => 'Resource',
        'email' => 'Email',
        'sms' => 'SMS',
        'review' => 'Review',
        'coupon' => 'Coupon',
        'giftcard' => 'Giftcard',
        'login' => 'Login',
        'settings' => 'Settings',
    ],

    /* The filter chips across the top. */
    'groups' => [
        'all' => 'All',
        'bookings' => 'Bookings',
        'clients' => 'Clients',
        'payments' => 'Payments',
        'staff' => 'Staff',
        'scheduling' => 'Scheduling',
        'communication' => 'Communication',
        'system' => 'System',
    ],

    /* Where the row goes when it is clicked. */
    'links' => [
        'booking' => 'View booking',
        'client' => 'View client',
        'staff' => 'View staff',
        'schedule' => 'View schedule',
        'service' => 'View service',
        'resource' => 'View resource',
        'payment' => 'View payment',
    ],

    /*
    | Sentences this screen writes itself, for sources that store facts rather
    | than prose. Everything from the client timeline arrives already written
    | and is shown as it was stored.
    */
    'sentences' => [
        'status' => 'Booking :reference was marked :status.',
        'reason' => 'Reason: :reason.',
        'schedule' => 'Schedule published for :name — :count shifts.',
    ],

    /*
    | Administrative changes, keyed by the action recorded in the audit log.
    | Anything without an entry falls back to the action's own words rather
    | than to nothing.
    */
    'audit' => [
        'fallback' => ':action — :name',
        'staff.created' => ':name was added to the team.',
        'staff.edited' => ':name’s details were updated.',
        'staff.role_changed' => ':name’s role was changed.',
        'staff.deleted' => ':name was removed from the team.',
        'staff.invitation_sent' => ':name was invited to join.',
        'auth.login' => ':name signed in.',
        'auth.logout' => ':name signed out.',
        'auth.password_reset' => ':name’s password was reset.',
    ],

];
