<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bookings
|--------------------------------------------------------------------------
|
| An appointment: a client, a service or several, somebody to do them and a
| time to do them in. This file decides which options exist; the labels come
| from the bookings language file, so a business running in Spanish gets
| Spanish without a second list to keep in step.
|
*/

return [

    /*
    | Where the appointment came from. Kept because "how did they find us"
    | is a question the reports will ask of every booking, and a free text
    | box would answer it in forty spellings.
    */
    'sources' => ['front-desk', 'phone', 'online', 'walk-in', 'social', 'referral', 'other'],

    /*
    | Where the appointment has got to.
    |
    | Draft is one still being written — saved from the booking screen so a
    | half-taken appointment survives a phone call — and is the only status
    | that holds no promise to anybody.
    */
    'statuses' => [
        'draft' => ['class' => 'styledesk_badge--setup'],
        'confirmed' => ['class' => 'styledesk_badge--active'],
        'arrived' => ['class' => 'styledesk_badge--active'],
        'completed' => ['class' => 'styledesk_badge--soon'],
        'no-show' => ['class' => 'styledesk_badge--soon'],
        'cancelled' => ['class' => 'styledesk_badge--soon'],
    ],

    /*
    | What is collected while the booking is being taken.
    |
    | Three answers, because they are three different acts: nothing now and
    | the whole bill is owed later; part of it now against a booking worked
    | later; or all of it now. A deposit is not a discount — the rest is
    | still owed, and the balance is what the desk chases.
    */
    'payment_types' => ['none', 'deposit', 'full'],

    /*
    | How the money is collected, once something is being collected.
    |
    | Five different acts, not five wordings of one. Collect Now charges it in
    | the booking screen before the booking is finished; the desk takes it in
    | person afterwards; a link asks the client to pay in their own time; on
    | arrival is the answer a salon gives most often; and waiving is a
    | decision somebody has to be accountable for, which is why it is the one
    | that records a name, a date and a reason.
    |
    | Only meaningful when something is being collected at all. With
    | `payment_type` of `none` there is nothing to collect and no method.
    */
    'collection_methods' => ['collect-now', 'desk', 'link', 'later', 'waive'],

    /*
    | How long a payment link stands before it stops working. Long enough to
    | survive a weekend, short enough that a link found in an old inbox is
    | not a way into somebody's appointment.
    */
    'payment_link_hours' => (int) env('BOOKING_PAYMENT_LINK_HOURS', 72),

    /*
    | Where a payment link can get to. Sent and opened are the two this side
    | can know; paid is settled from the money against the booking, because
    | StyleDesk takes none itself; expired is the clock.
    */
    'payment_link_statuses' => [
        'sent' => ['class' => 'styledesk_badge--info'],
        'opened' => ['class' => 'styledesk_badge--setup'],
        'paid' => ['class' => 'styledesk_badge--active'],
        'expired' => ['class' => 'styledesk_badge--soon'],
    ],

    /*
    | How the client is told. None is a real answer: a walk-in standing at
    | the desk has already been told.
    */
    'confirmations' => ['both', 'sms', 'email', 'none'],

    /*
    | How a booking gets paid for.
    |
    | Only `card` is a payment StyleDesk could ever take itself, and only
    | then with a provider connected; the rest are money that changes hands
    | somewhere else and is written down here. That is the honest shape of a
    | salon's till, and it is why every one of them ends in a person saying
    | the money arrived rather than in a webhook saying so.
    |
    | `handle` names the business's own account setting, where the method
    | needs one read out to the client. `manual` means nothing can verify it,
    | so the panel asks for Mark as Paid.
    */
    'methods' => [
        'card' => ['manual' => false, 'handle' => null],
        'cash' => ['manual' => true, 'handle' => null],
        'paypal' => ['manual' => true, 'handle' => 'paypal_handle'],
        'zelle' => ['manual' => true, 'handle' => 'zelle_handle'],
        'cash-app' => ['manual' => true, 'handle' => 'cash_app_handle'],
        'venmo' => ['manual' => true, 'handle' => 'venmo_handle'],
    ],

    /*
    | Who takes the card, if anybody.
    |
    | Null means no provider is connected, which is the default and the state
    | every business starts in. The panel will not pretend to charge a card
    | in that state: it offers the terminal beside the till instead, and the
    | payment is recorded the way cash is. Card details are never stored or
    | logged by StyleDesk in either case.
    */
    'card_provider' => env('BOOKING_CARD_PROVIDER'),

    /*
    | Where a lead has got to — what happened to it, not how far it got.
    |
    | `mvp` is whether the status is offered in the leads screen's own filter.
    | The two that are not are set by the system rather than chosen by a
    | person: a payment waiting on a provider, and a held time that ran out.
    | They are still valid values, and a lead in one still shows its badge.
    |
    | The colours are the app's semantic ones rather than the brand: a queue
    | read at a glance needs amber to mean "waiting" everywhere it appears,
    | and a brand-coloured status list means nothing at all.
    */
    'lead_statuses' => [
        /* A booking still being written on the New Booking screen, saving
           itself as it goes. It becomes 'in-progress' the moment the client
           moves past the first card, and 'converted' when it is confirmed. */
        'draft' => ['class' => 'styledesk_badge--setup', 'mvp' => true],
        'new' => ['class' => 'styledesk_badge--info', 'mvp' => true],
        'in-progress' => ['class' => 'styledesk_badge--info', 'mvp' => true],
        'awaiting-confirmation' => ['class' => 'styledesk_badge--setup', 'mvp' => true],
        'awaiting-deposit' => ['class' => 'styledesk_badge--setup', 'mvp' => true],
        'payment-pending' => ['class' => 'styledesk_badge--setup', 'mvp' => true],
        'follow-up' => ['class' => 'styledesk_badge--attention', 'mvp' => true],
        'contacted' => ['class' => 'styledesk_badge--note', 'mvp' => true],
        /* The one status that is not a lead any more. Kept for the reporting
           — how many calls became bookings is the question the whole table
           exists to answer — but out of the queue, because a booking that
           was taken is not work still to do. */
        'converted' => ['class' => 'styledesk_badge--active', 'mvp' => false],
        'abandoned' => ['class' => 'styledesk_badge--soon', 'mvp' => true],
        'cancelled' => ['class' => 'styledesk_badge--soon', 'mvp' => true],
        'lost' => ['class' => 'styledesk_badge--danger', 'mvp' => true],
        'expired' => ['class' => 'styledesk_badge--soon', 'mvp' => true],
    ],

    /*
    | How far through the booking screen the client got. The five cards, and
    | the state of having finished them.
    */
    'lead_steps' => ['service', 'when', 'details', 'payment', 'comms', 'completed'],

    /*
    | Why a lead ended where it did. Codes rather than free text, because
    | "why do we lose bookings" is a question of counting, and forty
    | spellings of "too expensive" answer nothing.
    */
    'lead_cancel_reasons' => [
        'changed-mind', 'no-suitable-time', 'staff-unavailable', 'price', 'duplicate', 'other',
    ],

    'lead_lost_reasons' => [
        'declined', 'unreachable', 'booked-elsewhere', 'price', 'no-availability', 'other',
    ],

    'lead_contact_methods' => ['phone', 'sms', 'email', 'whatsapp', 'in-person'],

    /*
    | How long a lead sits before the desk is told to chase it, and how long
    | before it stops being a live call. Hours, and configurable because a
    | salon open three days a week wants longer than one open seven.
    */
    'lead_follow_up_hours' => (int) env('LEAD_FOLLOW_UP_HOURS', 24),
    'lead_abandon_hours' => (int) env('LEAD_ABANDON_HOURS', 72),

    /*
    | Where the bill has got to, which is not where the appointment has got
    | to. A booking can be finished and unpaid, or paid for and months away.
    */
    'payment_statuses' => [
        'unpaid' => ['class' => 'styledesk_badge--setup'],
        'partial' => ['class' => 'styledesk_badge--soon'],
        'paid' => ['class' => 'styledesk_badge--active'],
        'pending' => ['class' => 'styledesk_badge--soon'],
        'failed' => ['class' => 'styledesk_badge--soon'],
        'refunded' => ['class' => 'styledesk_badge--soon'],
        'partially-refunded' => ['class' => 'styledesk_badge--soon'],
    ],
];
