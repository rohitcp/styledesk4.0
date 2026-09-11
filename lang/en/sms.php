<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| StyleDesk SMS
|--------------------------------------------------------------------------
|
| What the desk reads about a text message: where it got to, and what it was
| about.
|
*/

return [
    'title' => 'SMS',

    'statuses' => [
        'queued' => ['label' => 'Queued'],
        'sending' => ['label' => 'Sending'],
        'sent' => ['label' => 'Sent'],
        'delivered' => ['label' => 'Delivered'],
        'failed' => ['label' => 'Failed'],
        'rejected' => ['label' => 'Rejected'],
        'expired' => ['label' => 'Expired'],
        'opted_out' => ['label' => 'Opted out'],
    ],

    'types' => [
        'reply' => 'Client reply',
        'test' => 'Test message',
        'booking_confirmation' => 'Booking confirmation',
        'appointment_reminder' => 'Appointment reminder',
        'booking_rescheduled' => 'Booking rescheduled',
        'booking_cancelled' => 'Booking cancelled',
        'birthday' => 'Birthday wishes',
        'membership' => 'Membership notifications',
    ],

    'registration' => [
        'not_started' => 'Not started',
        'submitted' => 'Submitted',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
    ],

    'settings' => [
        'sending_from' => 'Sending from :number',
        'test' => 'Send a test message',
        'test_hint' => 'Sends one text through exactly the path a booking confirmation takes, and writes it to the SMS log like any other.',
        'test_to' => 'Send to',
        'send_test' => 'Send SMS',
        'test_body' => 'Test message from :business. StyleDesk SMS is working.',
        'test_sent' => 'Test message sent to :number via :provider.',
        'test_failed' => 'The test message could not be sent. :reason',
        'test_unknown' => 'The provider gave no reason.',
        'test_bad_number' => 'That does not look like a phone number.',
        'test_live' => 'Telnyx is connected, so this will reach a real phone and be charged for.',
        'test_local' => 'No carrier is connected, so nothing will reach a phone — the message is written to the log and to the SMS log.',
        'title' => 'SMS Settings',
        'intro' => 'What StyleDesk texts your clients, from which number, and what you are willing to spend on it.',
        'sender' => 'SMS number',
        'sender_hint' => 'StyleDesk arranges the number and its carrier registration on your behalf. Nothing can be sent to a US mobile until the registration is approved.',
        'no_number' => 'Not assigned yet',
        'registration' => 'Registration',
        'saved' => 'SMS settings saved.',
        'enable' => 'Enable StyleDesk SMS',
        'enable_hint' => 'Text messages are only sent while this is on.',
        'disabled_note' => 'Text messages are off. Nothing is sent, and nothing below is asked.',
        'messages' => 'Transactional messages',
        'messages_hint' => 'Which texts go out. Each one is sent only to clients who have agreed to hear from you.',
        'not_yet' => 'Not available yet.',
        'reminders' => 'Appointment reminders',
        'reminders_hint' => 'How far ahead of an appointment a reminder goes. Choose more than one to send more than one.',
        'hours_before' => '{1} 1 hour before|[2,*] :count hours before',
        'birthday_at' => 'Send birthday texts at',
        'birthday_at_hint' => 'In the location’s own time.',
        'spend' => 'Usage and limits',
        'spend_hint' => 'A ceiling, so an import or a mistake cannot text your whole client list.',
        'monthly_limit' => 'Monthly message limit',
        'no_limit' => 'No limit',
        'alert_at' => 'Warn me at',
        'used_this_month' => 'Messages this month',
        'segments_this_month' => 'Segments this month',
    ],

    'errors' => [
        'disabled' => 'StyleDesk SMS is switched off for this installation.',
        'no_sender' => 'No sending number is set. Add one in App Settings → SMS, or set TELNYX_FROM_NUMBER.',
    ],

    'confirmation' => [
        'not_requested' => 'Not requested',
        'pending' => 'Awaiting client',
        'confirmed' => 'Confirmed by client',
        'cancellation_requested' => 'Cancellation requested',
        'needs_review' => 'Needs review',
    ],
];
