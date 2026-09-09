<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | One engine, two ways in
    |--------------------------------------------------------------------------
    |
    | A transactional template is started by the system; a standard one is
    | chosen by a person. That is the only difference between them. Both are
    | the same row, rendered by the same engine into the same shell, so a fix
    | to how a booking date is formatted lands on every email at once.
    |
    */

    'types' => ['transactional', 'standard'],

    /*
    |--------------------------------------------------------------------------
    | The system events a transactional template can answer
    |--------------------------------------------------------------------------
    |
    | Grouped the way the settings screen lists them. A trigger is the thing
    | that happened, not the email — `booking.confirmed` fires once and finds
    | whichever template is pointed at it, which is what lets a business
    | rewrite the email without touching the code that sends it.
    |
    | Triggers whose module does not exist yet are listed with `phase => 2`:
    | the catalogue is the specification, and a screen that offers a trigger
    | nothing will ever fire is worse than one that says "later".
    |
    */

    'triggers' => [
        'booking' => [
            'booking.confirmed',
            'booking.reminder',
            'booking.updated',
            'booking.rescheduled',
            'booking.cancelled',
            'booking.declined',
            'booking.checked_in',
            'booking.no_show',
        ],

        'payment' => [
            'payment.receipt',
            'payment.deposit_received',
            'payment.received',
            'payment.partial_received',
            'payment.balance_reminder',
            'payment.link',
            'payment.refunded',
            'payment.adjusted',
        ],

        'client' => [
            'client.created',
            'client.updated',
            'client.form_request',
            'client.waiver_request',
            'client.form_completed',
        ],

        'gift_card' => [
            'gift_card.purchased',
            'gift_card.sent',
            'gift_card.redeemed',
        ],
    ],

    /** Triggers whose module has not been built. Offered, and marked. */
    'unbuilt_triggers' => [
        'client.form_request', 'client.waiver_request', 'client.form_completed',
        'gift_card.purchased', 'gift_card.sent', 'gift_card.redeemed',
    ],

    /*
    |--------------------------------------------------------------------------
    | The standard templates a person picks from
    |--------------------------------------------------------------------------
    |
    | Never triggered. A staff member chooses one when writing to a client, so
    | these are keys rather than events.
    |
    */

    'standard' => [
        'general_message',
        'appointment_follow_up',
        'request_information',
        'request_payment',
        'send_payment_link',
        'preparation_instructions',
        'aftercare_instructions',
        'thank_you',
        'please_contact_us',
        'booking_follow_up',
        'service_information',
    ],

    /*
    |--------------------------------------------------------------------------
    | The blocks the shell can draw
    |--------------------------------------------------------------------------
    |
    | The owner says which appear; StyleDesk says what they look like. That
    | division is the whole point of the module — a salon owner is not
    | responsible for email HTML, and a template they cannot break is a
    | template that cannot arrive broken.
    |
    | Order is the order they render in. It is not configurable, because a
    | contact block above the heading is not a layout anybody wants.
    |
    */

    'blocks' => [
        'logo' => ['default' => true],
        'heading' => ['default' => true, 'always' => true],
        'intro' => ['default' => true],
        'booking_details' => ['default' => true],
        'payment_summary' => ['default' => false],
        'coupon' => ['default' => false],
        'cta' => ['default' => true],
        'supporting_message' => ['default' => false],
        'contact' => ['default' => true],
        'footer' => ['default' => true, 'always' => true],
    ],

    /*
    | Which rows the booking details card shows. Per §26: the owner chooses
    | what is relevant, StyleDesk lays it out.
    */
    'detail_fields' => [
        'service' => true,
        'date' => true,
        'time' => true,
        'staff' => true,
        'location' => true,
        'reference' => true,
        'price' => false,
        'amount_paid' => false,
        'balance_due' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Where a button may point
    |--------------------------------------------------------------------------
    |
    | StyleDesk owns the destinations. An owner writes the words on the button
    | and chooses from actions the product can actually perform — a free-text
    | URL field is how a transactional email ends up linking to a page that no
    | longer exists.
    |
    */

    'cta_actions' => [
        'view_booking',
        'manage_booking',
        'make_payment',
        'view_receipt',
        'complete_form',
        'contact_business',
    ],

    /*
    |--------------------------------------------------------------------------
    | The variables a template may use
    |--------------------------------------------------------------------------
    |
    | Grouped for the Insert Variable menu, and the same list the renderer
    | resolves. One catalogue, so a variable offered is a variable that works.
    |
    */

    'variables' => [
        'client' => ['first_name', 'last_name', 'full_name', 'email', 'phone'],
        'business' => ['name', 'phone', 'email', 'website'],
        'booking' => ['reference', 'date', 'start_time', 'end_time', 'status'],
        'service' => ['name', 'duration', 'price'],
        'staff' => ['first_name', 'full_name'],
        'location' => ['name', 'address', 'phone'],
        'payment' => ['amount', 'amount_paid', 'balance_due', 'method', 'payment_link'],
        'coupon' => ['code', 'name', 'discount', 'expires'],
    ],

    /*
    |--------------------------------------------------------------------------
    | The shell
    |--------------------------------------------------------------------------
    |
    | One theme for MVP. One highly polished responsive design beats five with
    | inconsistent quality, and every extra theme is another set of email
    | clients to test against.
    |
    */

    'theme' => [
        'name' => 'StyleDesk Standard',
        'background' => '#F5F5F5',
        'width' => 620,
        'logo_max_height' => 56,
    ],

    'limits' => [
        'name' => 120,
        'subject' => 200,
        'heading' => 120,
        'body' => 20000,
    ],
];
