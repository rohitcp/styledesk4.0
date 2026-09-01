<?php

declare(strict_types=1);

/*
| Appointments.
|
| The wording keeps two things apart that are easy to run together: the
| appointment note is about this booking, and the client note is about the
| person. One is read once on the day; the other is read by everybody who
| books them, for as long as they are a client.
*/

return [

    'title' => 'Bookings',
    'intro' => 'Every appointment taken, and who it is with.',
    'new' => 'New Booking',
    'new_intro' => 'Find the client, or take a walk-in.',
    'walk_in_intro' => 'Take the details as they stand at the desk.',

    'add' => [
        'client' => 'Add Client',
        'booking' => 'Add Booking',
        'walk_in' => 'Add Booking · Walk-in',
        'leave' => 'Add Leave',
    ],

    'modes' => [
        'booking' => 'Booking',
        'walkin' => 'Walk-in',
    ],

    'any_staff' => 'Any available',
    'walk_in_guest' => 'Walk-in',

    'columns' => [
        'reference' => 'Booking ID',
        'booked_by' => 'Booked by',
        'client' => 'Client',
        'date' => 'Date',
        'time' => 'Time',
        'services' => 'Services',
        'staff' => 'With',
        'total' => 'Total',
        'status' => 'Status',
    ],

    'filters' => [
        'all_statuses' => 'All statuses',
        'all_staff' => 'All team members',
        'date' => 'Date',
        'reset' => 'Reset',
    ],

    'statuses' => [
        'draft' => ['label' => 'Draft'],
        'confirmed' => ['label' => 'Confirmed'],
        'arrived' => ['label' => 'Arrived'],
        'completed' => ['label' => 'Completed'],
        'no-show' => ['label' => 'No show'],
        'cancelled' => ['label' => 'Cancelled'],
    ],

    'sources' => [
        'front-desk' => 'Front desk',
        'phone' => 'Phone',
        'online' => 'Online',
        'walk-in' => 'Walk-in',
        'social' => 'Social',
        'referral' => 'Referral',
        'other' => 'Other',
    ],

    /*
    | The five decisions the booking screen holds, in the order they are
    | usually said out loud rather than the order a database would want them.
    */
    'sections' => [
        'client' => 'Client',
        'service' => 'Service',
        'when' => 'Staff & time',
        'details' => 'Booking details',
        'payment' => 'Deposit / payment',
        'comms' => 'Communication',
        'summary' => 'Booking summary',
    ],

    'client' => [
        'search' => 'Search by name, phone or email…',
        'search_label' => 'Search client by name, phone or email',
        'or' => 'OR',
        'add' => 'Add Client',
        'guest' => 'Book an anonymous walk-in',
        'none' => 'No client matches that.',
        'change' => 'Change',
        'guest_name' => 'Name',
        'guest_phone' => 'Mobile',
        'guest_email' => 'Email',
        'guest_hint' => 'A walk-in is booked without a client record. Add the client instead if they are coming back.',
        'guest_save' => 'Save walk-in details',
        'guest_checking' => 'Checking…',
        'guest_saved' => 'Saved',
        'guest_known' => 'This may already be a client.',
        'guest_known_hint' => 'Use their record instead and the booking keeps their history. Carry on as a walk-in if it is somebody else.',
    ],

    'service' => [
        'search' => 'Search services…',
        'category' => 'Service category',
        'all_categories' => 'All categories',
        'search_categories' => 'Search categories…',
        'chosen' => 'Chosen',
        'none' => 'No services match that.',
        'empty' => 'No services yet. Add one first and the booking screen will offer it.',
        'minutes' => ':count min',
        'remove' => 'Remove :name',

        /*
        | The full-page selector.
        |
        | A salon with a hundred services cannot choose one from a list
        | inside a form field, so choosing is its own screen: the categories
        | down one side, the services down the other, and one Save at the
        | bottom rather than a commit per service.
        */
        'add' => 'Add / Assign Service',
        'change' => 'Add or change services',
        'card_empty' => 'No services chosen yet.',
        'select_title' => 'Select Services',
        'close' => 'Back to the booking',
        'categories' => 'Service Categories',
        'all_services' => 'All Services',
        'client_favorites' => 'Client favourites',
        'selected' => ':count selected',
        'selected_one' => '1 selected',
        'selected_none' => 'Nothing selected',
        'save_close' => 'Save & Close',
        'nothing_here' => 'Nothing in this category.',
        'hours' => ':count hr',
        'hours_minutes' => ':hours hr :minutes min',
        'count' => ':count services',
        'count_one' => '1 service',
        'needs' => 'Needs :names',
    ],

    'when' => [
        'none_in_period' => 'Nothing free at this time of day.',
        'change_location' => 'Change location',
        'search_locations' => 'Search locations…',
        'no_locations' => 'No location matches that search.',
        'closed_date' => 'No booking times available — this location is closed on this date.',
        'too_long' => 'These services do not fit inside this location’s opening hours on this date.',
        'nothing_free' => 'Nothing is free on this date — the times are taken, or nobody is on shift for them.',
        'loading_times' => 'Checking what is free…',
        'staff' => 'Team member',
        'any' => 'Any available',
        'date' => 'Date',
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'next_3' => 'Next 3 days',
        'next_7' => 'Next 7 days',
        'custom' => 'Custom date',
        'done' => 'Done',
        'previous_month' => 'Previous month',
        'next_month' => 'Next month',
        'time' => 'Start time',
        'ends' => 'Ends at :time',
        'location' => 'Location',
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'evening' => 'Evening',
    ],

    'details' => [
        'source' => 'Booking source',
        'note' => 'Appointment note',
        'note_hint' => 'This booking only. It never becomes a permanent client note.',
        'note_placeholder' => 'Client wants the same haircut but slightly shorter today.',
        'client_note' => 'Client note',
        'client_note_aside' => '— kept on the client profile',
        'client_note_hint' => 'Saved to the client when the booking is confirmed. Everyone booking them sees it.',
        'client_note_placeholder' => 'Sensitive scalp — no heat directly on the roots.',
        'choose_source' => 'Choose a source',
        'search_sources' => 'Search sources…',
    ],

    'payment' => [
        'type' => 'Payment option',
        'none' => 'No Payment Now',
        'none_hint' => 'Do not collect payment during booking.',
        'deposit' => 'Take Deposit',
        'deposit_hint' => 'Collect part of the booking total now.',
        'full' => 'Full Payment',
        'full_hint' => 'Collect the entire booking total now.',
        'amount' => 'Deposit amount',
        /* The two ways a desk says a deposit out loud. A percentage is what
           a policy is written in; a number is what gets typed. */
        'preset' => ':percent%',
        'preset_custom' => 'Custom',
        'too_much' => 'A deposit cannot be more than the booking total.',
        'balance' => 'Balance due',
        'collecting' => 'Collecting now',
        'nothing_collected' => 'No payment will be collected for this booking.',
        'action' => 'Payment collection method',
        'choose_action' => 'Choose how it is collected',
        'search_actions' => 'Search…',
        'action_hint' => 'Only asked once there is something to collect.',
        'actions' => [
            'collect-now' => 'Collect Now',
            'desk' => 'Taken at the Desk',
            'link' => 'Send Payment Link',
            'later' => 'Ask on Arrival',
            'waive' => 'Waived',
        ],
        'action_hints' => [
            'collect-now' => 'Charge it here, before the booking is finished.',
            'desk' => 'The desk takes it in person. The balance stays owing until it does.',
            'link' => 'The client pays in their own time. Sent by email once the booking is taken.',
            'later' => 'Collected when the client arrives. The balance stays owing.',
            'waive' => 'Nothing is collected, on purpose. Recorded against your name.',
        ],
        'waiver_reason' => 'Why it is being waived',
        'waiver_placeholder' => 'A regular whose colour went wrong last time.',
        'waiver_hint' => 'Kept with your name and the date, so the decision can be answered for later.',
        'waiver_needed' => 'Say why the payment is being waived.',
        'no_waive_permission' => 'You cannot waive a payment. Ask a manager.',
        'collect_now_hint' => 'The payment card opens as soon as the booking is taken.',
        'link_sent' => 'Payment link sent to :to.',
        'link_status' => 'Payment link',
        'link_statuses' => [
            'sent' => 'Sent',
            'opened' => 'Opened',
            'paid' => 'Paid',
            'expired' => 'Expired',
        ],
        'link_no_email' => 'This client has no email address, so the link has nowhere to go.',
    ],

    'comms' => [
        'send' => 'Send confirmation',
        'both' => 'SMS + Email',
        'sms' => 'SMS',
        'email' => 'Email',
        'none' => 'Nothing',
        'to_sms' => 'SMS to',
        'to_email' => 'Email to',
        'missing' => 'No number or address on file, so nothing can be sent.',
    ],

    'summary' => [
        'client' => 'Client',
        'services' => 'Services',
        'staff' => 'With',
        'when' => 'When',
        'duration' => 'Duration',
        'total' => 'Total',
        'deposit' => 'Deposit',
        'due' => 'Due on the day',
        'nothing' => 'Nothing chosen yet.',
        'minutes' => '{1} :count minute|[2,*] :count minutes',
        'reference' => 'Booking reference',
        'location' => 'Location',
        'resource' => 'Resource',
        'starts' => 'Start',
        'ends' => 'Estimated end',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'tax' => 'Tax (:rate)',
        'tax_included' => 'Tax included (:rate)',
        'paid' => 'Paid',
        'estimate' => 'Prices are confirmed when the booking is taken.',
        'status' => 'Status',
        'date' => 'Date',
        'source' => 'Booked via',
    ],

    /*
    | The panel that opens beside the booking once a client is chosen.
    |
    | Two kinds of fact sit side by side and are worded apart on purpose:
    | "Asked for by name" is something the client said, "Booked most often"
    | is something the diary noticed. A panel that ran the two together would
    | have the desk quoting the software back to people as though they had
    | asked for it.
    */
    'new_client' => [
        'title' => 'Add Client',
        'intro' => 'Just enough to take the booking. The rest can be filled in later from their profile.',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email address',
        'mobile' => 'Mobile number',
        'contact_hint' => 'A mobile or an email — one of the two, so the confirmation has somewhere to go.',
        'needs_contact' => 'A mobile or an email — the confirmation needs somewhere to go.',
        'duplicate' => 'This may already be a client.',
        'add' => 'Add Client',
        'add_anyway' => 'Add anyway',
        'full_form' => 'Full client form',
    ],

    'context' => [
        'preferred' => 'Preferred staff',
        'also_seen' => 'Also seen',
        'last' => 'Last booking',
        'again' => 'Book the same again',
        'recent' => 'Recent visits',
        'average' => ':rating average rating',
        'preferences' => 'Booking preferences',
        'none' => 'No history yet — this is their first booking.',
        'visits' => '{0} No previous visits|{1} :count previous visit|[2,*] :count previous visits',
        'from_diary' => 'From booking history',
        'kinds' => [
            'asked_for' => 'Asked for by name',
            'most_booked' => 'Booked most often',
            'also_seen' => 'Seen before',
        ],
        'cadence' => '{1} Books about every week|[2,*] Books about every :count weeks',
        'windows' => [
            'morning' => 'Prefers morning appointments',
            'afternoon' => 'Prefers afternoon appointments',
            'evening' => 'Prefers evening appointments',
        ],
        'walk_in' => 'Walk-in guest',
        'walk_in_hint' => 'No record is kept beyond this appointment.',
        'remove' => 'Remove this client from the booking',
    ],

    'confirm' => 'Confirm Booking',
    'draft' => 'Save as draft',
    'cancel' => 'Cancel',

    /*
    | What is still missing, said one thing at a time. A list of every
    | unanswered question is a wall; the next one is an instruction.
    */
    'lead' => [
        'created' => 'Booking reference :reference created. No action is needed right now.',
    ],

    /*
    | The booking saving itself as it is filled in.
    |
    | Said quietly and beside the reference, because it is not news: the
    | receptionist is talking to somebody, and a save that announced itself
    | every few seconds would be the loudest thing on the screen.
    */
    'autosave' => [
        'reference' => 'Booking Ref: :reference',
        'saving' => 'Saving…',
        'saved' => 'Saved',
        'failed' => 'Not saved',
        'draft' => 'Draft',
    ],

    'steps' => [
        'save' => 'Save & continue',
        'edit' => 'Edit',
    ],

    'blockers' => [
        'client' => 'Choose a client, or take this as a walk-in.',
        'guest' => 'Give the walk-in a name.',
        'service' => 'Choose at least one service.',
        'time' => 'Choose a start time.',
        'ready' => 'Ready to book.',
    ],

    'booked' => 'Booking confirmed for :name.',
    'no_time_yet' => 'No time yet',
    'saved_draft' => 'Booking saved as a draft.',

    'empty' => 'No bookings match these filters.',
    'empty_hint' => 'Clear the filters to see every appointment.',
    'none_yet' => 'No bookings yet',
    'none_yet_hint' => 'Take the first one and it will appear here.',

    'showing' => 'Showing :from–:to of :total bookings',
    'results' => [
        'zero' => 'No bookings',
        'one' => '1 booking',
        'many' => ':count bookings',
        'clear' => 'Clear filters',
    ],
    'actions_for' => 'Actions for :name',

    /*
    | Money against a booking.
    |
    | Nearly every payment a salon takes happens somewhere else — in the
    | drawer, on the terminal by the till, in somebody's banking app — so the
    | wording is about writing down what happened, not about charging
    | anybody. "Mark as paid" is a person's word for it, and says so.
    */
    /*
    | The booking's own detail page.
    |
    | Read the way the client profile is read: the person on the left, the
    | work in the middle, what to do about them on the right.
    */
    'detail' => [
        'payment_status' => 'Payment',
        'book_again' => 'Book again',
        'cancel_booking' => 'Cancel booking',
        'reschedule' => 'Reschedule',
        'soon_hint' => 'Not built yet — cancelling and moving an appointment both change the diary.',
        'services' => 'Service details',
        'notes' => 'Booking notes',
        'no_notes' => 'Nothing was noted about this appointment.',
        'payment_summary' => 'Payment summary',
        'take_payment' => 'Take Payment',
        'paid_in_full' => 'Paid in full',
        'transactions' => 'Payment transactions',
        'no_transactions' => 'No money has been taken against this booking yet.',
        'recorded_by' => 'recorded by :name',
        'taken_by' => 'Taken by :name on :when',
        'someone' => 'a team member',
        'updated' => 'last updated :when',
        'walk_in' => 'A walk-in, booked without a client record.',
        /* Said out loud, because the profile and this page will disagree as
           the client changes — and that is the point of it. */
        'snapshot' => 'As at :when, when this booking was taken.',
    ],

    'pay' => [
        'title' => 'Payment',
        'due' => 'Amount due',
        'collect_now' => 'Amount to collect now',
        'booking_total' => 'Booking total',
        'remaining' => 'Remaining balance',
        'method' => 'How are they paying?',
        'change_method' => 'Change',
        'back' => 'Back to booking summary',
        'again' => 'Save changes & continue',
        'skip' => 'Confirm without payment',
        'skip_hint' => 'The booking is made and the balance stays owing.',
        'pay_amount' => 'Pay :amount',
        'record' => 'Record cash payment',
        'mark_paid' => 'Mark as paid',
        'marking' => 'Recording…',
        'amount' => 'Amount',
        'received' => 'Amount received',
        'change' => 'Change due',
        'reference' => 'Reference',
        'reference_hint' => 'Whatever the payment shows on their side — optional.',
        'cardholder' => 'Cardholder name',
        'card_number' => 'Card number',
        'expiry' => 'Expiry date',
        'cvv' => 'CVV',
        'zip' => 'Billing ZIP code',
        'card_safe' => 'Card details go straight to the payment provider. StyleDesk never stores them.',
        'no_card_provider' => 'No card provider is connected, so StyleDesk cannot take the card itself. Take it on the terminal and record it below.',
        'terminal' => 'Taken on the terminal',
        'not_ready' => 'Not set up yet. Add the account in Business settings.',
        'handle_hint' => 'Read this out, then mark the payment as received.',
        'short_cash' => 'That is less than the amount being paid.',
        'failed' => 'That payment could not be recorded. Nothing has been charged — try again.',
        'partial' => ':paid of :total paid · :due still owing',
    ],

    'methods' => [
        'card' => ['name' => 'Credit card', 'hint' => 'Charged now, or taken on the terminal'],
        'cash' => ['name' => 'Cash', 'hint' => 'Counted at the desk'],
        'paypal' => ['name' => 'PayPal', 'hint' => 'Sent to the business PayPal'],
        'zelle' => ['name' => 'Zelle', 'hint' => 'Sent to the business Zelle'],
        'cash-app' => ['name' => 'Cash App', 'hint' => 'Sent to the business Cash App'],
        'venmo' => ['name' => 'Venmo', 'hint' => 'Sent to the business Venmo'],
    ],

    'payment_statuses' => [
        'unpaid' => ['label' => 'Unpaid'],
        'partial' => ['label' => 'Partially paid'],
        'paid' => ['label' => 'Paid'],
        'pending' => ['label' => 'Payment pending'],
        'failed' => ['label' => 'Failed'],
        'refunded' => ['label' => 'Refunded'],
        'partially-refunded' => ['label' => 'Partially refunded'],
    ],

    'confirmation' => [
        'title' => 'Booking confirmed',
        'made' => 'The appointment is in the diary.',
        'payment' => 'Payment',
        'view' => 'View booking',
        'another' => 'Create another booking',
        'print' => 'Print confirmation',
        'receipt' => 'Download receipt',
        'receipt_title' => 'Receipt',
        'send' => 'Send confirmation',
        'sending' => 'Sending…',
        'sent' => 'Confirmation sent to :to.',
        'no_email' => 'This client has no email address on file.',
        'no_sms' => 'Text messages are not connected yet. Send it by email, or add an SMS account first.',
        'link_failed' => 'The booking is made, but the payment link could not be emailed. Ring the client instead.',
        'due_notice' => 'Payment due :amount',
        'amount_due' => 'Amount due',
    ],

    'email' => [
        'subject' => ':business — your appointment on :date',
        'headline' => 'Your appointment is confirmed',
        'intro' => 'Thanks, :name. Here are the details.',
        'link_subject' => ':business — paying for your appointment on :date',
        'link_headline' => 'How to pay for your appointment',
        'link_intro' => 'Thanks, :name. Here is what is outstanding, and how to settle it.',
        'link_amount' => 'Amount to pay',
        'link_cta' => 'View payment details',
        'link_expiry' => 'This link works until :when.',
    ],

    /*
    | The page a client lands on from a payment link.
    |
    | Written for somebody who is not a StyleDesk user and never will be, so
    | it says what is owed, where to send it, and nothing about the business's
    | own workings.
    */
    'pay_link' => [
        'title' => 'Paying for your appointment',
        'amount' => 'Amount requested',
        'balance' => 'Balance on this booking: :amount',
        'how' => 'How to pay',
        'reference_hint' => 'Please quote :reference so we can match it to your appointment.',
        'no_handles' => 'Give us a call and we will take it over the phone.',
        'expired' => 'This payment link has expired.',
        'expired_hint' => 'Get in touch and we will send you a new one.',
        'settled' => 'This booking is paid in full.',
        'settled_hint' => 'Nothing further is owed. Thank you.',
        'footer' => 'This link is for one appointment and does not sign you in to anything.',
    ],

    'validation' => [
        'who' => 'Choose a client, or give the walk-in a name.',
    ],
];
