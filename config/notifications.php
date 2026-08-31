<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notification catalogue
|--------------------------------------------------------------------------
|
| Every message StyleDesk can send a member of staff, grouped the way the
| Notifications screen groups them. Config rather than a database table
| because what a notification *means* is decided by the code that sends it —
| a row nobody can send is a switch that does nothing, and a send with no row
| is a message nobody can turn off.
|
| Each type carries:
|   channels  the delivery channels it supports
|   default   the channels that are on before anyone chooses
|   critical  true when it may never be switched off entirely
|
| Critical types are the account's own security: somebody who can silence
| "your password was changed" is somebody a stolen session can hide behind.
| They are rendered locked rather than absent, so the screen still says the
| message will be sent.
|
*/

return [

    /*
    | The channels themselves, in the order they are shown as columns.
    | `available` false renders the column as "Coming soon" — present, so the
    | screen tells the truth about what is planned, and inert, so nobody
    | switches on a message that cannot be delivered.
    */
    'channels' => [
        'in_app' => ['available' => true],
        'email' => ['available' => true],
        'sms' => ['available' => false],
        'push' => ['available' => false],
    ],

    /*
    | Groups, in screen order. Keys are stored in
    | user_notification_preferences.type_key and must not be renamed casually:
    | a renamed key silently restores that notification's default for
    | everybody who had turned it off.
    */
    'groups' => [

        'appointments' => [
            'booking.created' => ['channels' => ['in_app', 'email'], 'default' => ['in_app']],
            'booking.assigned' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
            'booking.updated' => ['channels' => ['in_app', 'email'], 'default' => ['in_app']],
            'booking.rescheduled' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
            'booking.cancelled' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
            'booking.completed' => ['channels' => ['in_app', 'email'], 'default' => []],
            'booking.no_show' => ['channels' => ['in_app', 'email'], 'default' => ['in_app']],
            'booking.reminder' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
        ],

        'clients' => [
            'client.assigned' => ['channels' => ['in_app', 'email'], 'default' => ['in_app']],
            'client.updated' => ['channels' => ['in_app', 'email'], 'default' => []],
            'client.note_added' => ['channels' => ['in_app', 'email'], 'default' => ['in_app']],
            'client.file_uploaded' => ['channels' => ['in_app', 'email'], 'default' => []],
            'client.mentioned' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
        ],

        'team' => [
            'team.invitation' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
            'team.location_assigned' => ['channels' => ['in_app', 'email'], 'default' => ['in_app']],
            'team.hours_changed' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
            'team.schedule_changed' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
            'team.mentioned' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
            'team.role_changed' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
        ],

        'resources' => [
            'resource.assigned' => ['channels' => ['in_app', 'email'], 'default' => ['in_app']],
            'resource.changed' => ['channels' => ['in_app', 'email'], 'default' => []],
            'resource.unavailable' => ['channels' => ['in_app', 'email'], 'default' => ['in_app']],
            'resource.conflict' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email']],
        ],

        /*
        | The account's own security. Every one of these is critical: they are
        | how somebody finds out that their account was taken, and the person
        | doing the taking is exactly who would switch them off.
        */
        'system' => [
            'security.alert' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email'], 'critical' => true],
            'security.password_changed' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email'], 'critical' => true],
            'security.email_changed' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email'], 'critical' => true],
            'security.new_login' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email'], 'critical' => true],
            'security.account_alert' => ['channels' => ['in_app', 'email'], 'default' => ['in_app', 'email'], 'critical' => true],
        ],
    ],
];
