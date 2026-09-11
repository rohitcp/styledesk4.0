<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| StyleDesk SMS
|--------------------------------------------------------------------------
|
| What the product is called to a business, whatever carrier is under it. The
| carrier's own credentials live in config/services.php with every other
| third party; what is here is how StyleDesk behaves.
|
*/

return [

    /*
    | Where a message can get to, and how the badge is painted.
    |
    | Three failures rather than one, because only one of them is worth
    | trying again: `failed` may be a moment's trouble, while `rejected` and
    | `opted_out` are answers that will not change on a retry.
    */
    'statuses' => [
        'queued' => ['class' => 'styledesk_badge--setup'],
        'sending' => ['class' => 'styledesk_badge--setup'],
        'sent' => ['class' => 'styledesk_badge--info'],
        'delivered' => ['class' => 'styledesk_badge--active'],
        'failed' => ['class' => 'styledesk_badge--danger'],
        'rejected' => ['class' => 'styledesk_badge--danger'],
        'expired' => ['class' => 'styledesk_badge--attention'],
        'opted_out' => ['class' => 'styledesk_badge--soon'],
    ],

    /*
    | What a client can text back, and what it means.
    |
    | The stop words are the ones the US carriers require every campaign to
    | honour. Matched on the whole message, case-insensitively: "stop" is an
    | opt-out and "stop by at 4" is not.
    */
    /*
    | The words a client can reply with, and what each one does.
    |
    | `stop` and `start` are the carriers' requirement and are honoured
    | everywhere at once. `yes` and `cancel` are about one appointment, so
    | they only act where StyleDesk is sure which — see SmsReplies.
    */
    'keywords' => [
        /* CANCEL is deliberately not here. On this shared number it means
           "call off my appointment", which is a request for the desk — not
           "stop texting me", which is what the carriers reserve STOP for. */
        'stop' => ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'END', 'QUIT'],
        /* YES is deliberately not here. On this shared number it answers a
           booking — "yes, I'll be there" — and treating it as an opt-in would
           quietly re-subscribe somebody who had asked to be left alone. */
        'start' => ['START', 'UNSTOP'],
        'help' => ['HELP', 'INFO'],
        'yes' => ['YES', 'Y', 'CONFIRM', 'OK'],
        /* Not in the stop list: a client asking to call off one appointment
           has not asked to stop hearing from the salon. */
        'cancel' => ['CANCEL', 'CANCELBOOKING', 'CANCELAPPOINTMENT'],
    ],

    /*
    | Where a client's answer to a booking can get to.
    |
    | Its own state, separate from the booking's status: `status` is what the
    | salon has decided about the appointment, and this is whether the person
    | coming has acknowledged it. A booking confirmed by the desk and
    | unanswered by the client is exactly the list a receptionist rings.
    */
    'confirmation' => [
        'not_requested' => ['class' => 'styledesk_badge--soon'],
        'pending' => ['class' => 'styledesk_badge--setup'],
        'confirmed' => ['class' => 'styledesk_badge--active'],
        'cancellation_requested' => ['class' => 'styledesk_badge--attention'],
        'needs_review' => ['class' => 'styledesk_badge--danger'],
    ],

    /*
    | The messages a business can switch on, in the order the screen lists
    | them. `available` is whether StyleDesk can actually send it yet — a
    | switch for a message nothing produces is a promise the app does not
    | keep, so it is drawn and disabled rather than hidden.
    */
    'messages' => [
        'booking_confirmation' => ['available' => true, 'default' => true],
        'appointment_reminder' => ['available' => false, 'default' => true],
        'booking_rescheduled' => ['available' => false, 'default' => true],
        'booking_cancelled' => ['available' => false, 'default' => true],
        'birthday' => ['available' => false, 'default' => false],
        'membership' => ['available' => false, 'default' => false],
    ],

    /*
    | How far ahead a reminder may go. Hours rather than a free number: every
    | business uses the same handful, and a text box invites "1 day",
    | "24hrs" and "tomorrow" into one column.
    */
    'reminder_hours' => [1, 2, 6, 12, 24, 48, 72],

    /*
    | Where a 10DLC registration can be, and how the badge reads. Nothing may
    | be sent to a US number until this reaches `approved` — the carriers
    | filter unregistered traffic rather than delivering it.
    */
    'registration' => [
        'not_started' => ['class' => 'styledesk_badge--soon'],
        'submitted' => ['class' => 'styledesk_badge--setup'],
        'pending' => ['class' => 'styledesk_badge--setup'],
        'approved' => ['class' => 'styledesk_badge--active'],
        'rejected' => ['class' => 'styledesk_badge--danger'],
        'suspended' => ['class' => 'styledesk_badge--danger'],
    ],

    /*
    | Write every incoming webhook to the log, while somebody is wiring one
    | up. Never in production, whatever this says: the payload carries a
    | client's phone number and what was said to them.
    |
    |   SMS_DEBUG_WEBHOOKS=true
    */
    'debug_webhooks' => env('SMS_DEBUG_WEBHOOKS', false),

    /*
    | Which carrier carries a message: clicksend, local, or disabled.
    |
    | Read only where the installation is allowed to reach one at all — see
    | App\Messaging\SmsProviders. On a developer's machine this is ignored,
    | deliberately.
    */
    'provider' => env('SMS_PROVIDER', 'disabled'),

    /*
    | The safety catch.
    |
    | Development never reaches a real carrier. Not by convention, not by
    | remembering to set something — by refusing. A configured provider, a
    | back-office choice, a production database copied down for debugging:
    | none of them can make a local machine text a client.
    |
    | The failure this prevents is silent and expensive. A seeded database
    | holds real-looking phone numbers, and nobody finds out until the
    | clients do.
    |
    | Set it deliberately, for one afternoon, and set it back. Never commit
    | it as true.
    */
    'allow_live_in_local' => env('SMS_ALLOW_LIVE_IN_LOCAL', false),
];
