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
    'search_placeholder' => 'Search bookings, clients, booking ID…',
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
        'arrival' => 'Arrival',
        'checkin' => 'Check-in',
        'location' => 'Location',
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
        'all_locations' => 'All locations',
        'all_services' => 'All services',
        'all_payments' => 'All payment statuses',
        'all_staff' => 'All team members',
        'date' => 'Date',
        'reset' => 'Reset',
    ],

    'statuses' => [
        'pending' => ['label' => 'Pending'],
        'draft' => ['label' => 'Draft'],
        'confirmed' => ['label' => 'Confirmed'],
        'arrived' => ['label' => 'Checked in'],
        'completed' => ['label' => 'Completed'],
        'no-show' => ['label' => 'No show'],
        'declined' => ['label' => 'Declined'],
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
        'purchase' => 'Select type',
        'client' => 'Client',
        'service' => 'Service',
        'when' => 'Staff & time',
        'details' => 'Booking details',
        'payment' => 'Deposit / payment',
        'comms' => 'Communication',
        'summary' => 'Booking summary',
    ],

    /* The Select Type accordion above the services.
     *
     * A type that cannot be sold is shown and disabled with the reason
     * beside it: a missing option reads as a product StyleDesk does not
     * have, and a disabled one names the switch somebody can go and turn on.
     */
    'purchase' => [
        'title' => 'Select type',
        'question' => 'What would you like to sell?',
        'services' => 'Services',
        'services_hint' => 'An appointment: services, staff, a time and a bill.',
        'membership' => 'Membership',
        'membership_hint' => 'A recurring membership or a package of services.',
        'gift_card' => 'Gift card',
        'gift_card_hint' => 'A stored value the client can spend later.',
        'coming_soon' => 'Coming soon',
        'membership_off' => 'Switch Membership on in App Settings first.',
        'membership_empty' => 'Publish a membership under Clients → Membership first.',
    ],

    /* The membership branch of the booking screen: choosing one, when it
     * starts, and what the till is about to ask for.
     */
    'membership' => [
        'select' => 'Select membership',
        'plans' => 'Membership plans',
        'packages' => 'Membership packages',
        'none' => 'Nothing published to sell yet.',
        'none_hint' => 'Publish a membership under Clients → Membership and it appears here.',
        'includes' => 'Includes',
        'select_this' => 'Select membership',
        'selected' => 'Selected',
        'change' => 'Change',
        'saving' => 'Client saves :amount',
        'trial' => ':days-day trial',
        'joining_fee' => 'Joining fee :amount',
        'setup_fee' => 'Setup fee :amount',
        'start' => 'Start date',
        'start_today' => 'Start today',
        'start_later' => 'Choose a start date',
        'start_locked' => 'Memberships start the day they are sold.',
        'scheduled_note' => 'This membership will be Scheduled until :date.',
        'purchase_summary' => 'Purchase summary',
        'billing' => 'Billing',
        'next_billing' => 'Next billing',
        'one_off' => 'One-off purchase',
        'due_today' => 'Amount due today',
        'client_required' => 'Choose a client first — a membership is always attached to one.',
        'plan_required' => 'Choose a membership.',
        'method_note' => 'A recurring membership needs a payment method that can be charged again.',
        'complete' => 'Complete purchase',
    ],

    /* Spending what the client already bought.
     *
     * Offered beside the line it would pay for, never applied on its own:
     * whether to spend a credit today is the client's decision, and the desk
     * asks it out loud.
     */
    'credits' => [
        'available' => 'Membership benefit available',
        'from' => 'From :name',
        'remaining' => ':count available',
        'apply' => 'Apply membership credit',
        'apply_many' => 'Use membership · :count credits',
        'applied_many' => 'Membership · :count credits applied',
        'applied' => 'Membership credit applied',
        'remove' => 'Remove',
        'line' => 'Membership credit',
        'covered' => 'Covered by membership',
        'used_line' => 'Credits used',
        'benefits_section' => 'Membership benefits',
        'col_service' => 'Service',
        'col_included' => 'Included',
        'col_used' => 'Used',
        'col_remaining' => 'Remaining',
        'covered_by' => 'Covered by :name · :count left',
        'used_up' => 'Allowance used. Bookable at the normal price.',
        'each' => ':count credits each',
        'renews' => 'Renews :date',
        'balance_line' => 'Service balance',
        'deposit_covered' => 'No deposit needed',
        'deposit_covered_hint' => 'The membership covers every service on this booking, so there is nothing to take up front. A tip is still collected in full.',
        'expires' => 'Expires :date',
        'membership_id' => 'Membership ID',
        'period' => 'Benefit period',
        'plan_details' => 'Plan details',
        'col_reserved' => 'Reserved',
        'col_status' => 'Status',
        'status_available' => 'Available',
        'status_reserved' => 'Reserved',
        'status_used' => 'Used',
        'reserved_for' => 'Reserved for :date',
        'reserved_title' => 'Membership benefit already reserved',
        'reserved_body' => 'This membership benefit is reserved for the :date appointment. To use it here, cancel or change that booking first.',
        'reserved_view' => 'View :date booking',
        'reserved_cancel' => 'Cancel :date booking',
        'reserved_pay' => 'Pay normally',
        'reserved_close' => 'Close',
    ],

    /* Recurring membership billing and the card it runs on. StyleDesk holds
       the gateway's reference, never the card. */
    'cards' => [
        'recurring' => 'Recurring payment',
        'recurring_hint' => 'Renews automatically until it is cancelled.',
        'recurring_locked' => 'This membership is sold as a subscription and always renews.',
        'renews' => 'Renews :price',
        'one_off' => 'Collect each renewal at the desk',
        'title' => 'Card on file',
        'why' => 'Required for automatic membership renewal.',
        'existing' => 'Use an existing card',
        'add' => '+ Add new card',
        'default' => 'Default',
        'expires' => 'Expires :date',
        'expired' => 'Expired',
        'expiring' => 'Expires this month',
        'save' => 'Save this card on file',
        'save_required' => 'Required for automatic membership renewal.',
        'none' => 'No card on file for this client.',
        'unavailable' => 'Card on file is unavailable',
        'unavailable_hint' => 'Connect a payment processor in App Settings → Payments to save cards for automatic renewal.',
        'client_first' => 'Choose a client before adding a card.',
        'required' => 'Choose a card for the renewal, or turn recurring payment off.',
        'adding' => 'Adding card…',
        'failed' => 'That card could not be saved.',
        'cancel' => 'Cancel',
        'summary_recurring' => 'Recurring',
        'summary_payment_method' => 'Payment method',
        'summary_next_billing' => 'Next billing',
        'yes' => 'Yes',
        'no' => 'No',
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

    'duplicate' => [
        'title' => 'Possible duplicate booking',
        'title_exact' => 'This looks like the same booking twice',
        'message' => ':client already has :service booked on :date.',
        'message_overlap' => 'This overlaps an existing booking for the same client and the same service.',
        'message_exact' => 'This appears to be an exact duplicate of an existing booking — same client, same service, same branch, same time.',
        'existing' => 'Already booked',
        'view' => 'View existing booking',
        'ask' => 'A client can genuinely want the same service twice in a day. Carry on if that is what this is.',
        'continue' => 'Continue anyway',
        'create_anyway' => 'Create anyway',
        'cancel' => 'Cancel this booking',
        'discard_title' => 'Cancel this booking?',
        'discard_body' => 'This removes the booking being taken now, along with the booking lead it has been saving itself as.',
        'discard_keeps' => 'The appointment this client already has is not touched.',
        'keep' => 'Keep booking',
        'discard_confirm' => 'Cancel & delete',
        'blocked' => 'This client already has one of these services booked that day. Check the warning on the booking screen before taking it.',
    ],

    'payment' => [
        'deposit_percent' => 'Deposit',
        'deposit_now' => 'Deposit due now',
        'deposit_now_percent' => ':percent% deposit due now',
        'remaining' => 'Remaining balance',
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
        /* A deposit the service itself insists on: the desk is not being
           asked how much, it is being told. */
        'too_little' => 'These services need a deposit of at least :amount.',
        'deposit_required' => 'Deposit required',
        'deposit_required_error' => 'These services need a deposit, so a booking cannot be taken with nothing collected.',
        'deposit_required_hint' => 'These services require a deposit, so one has to be collected to complete this booking. It can be raised, not removed.',
        'deposit_required_percent' => ':percent% of the booking total',
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
        'coupon' => 'Coupon',
        'tip_due' => 'Tip agreed',
        'amount_due' => 'Amount due',
        'tip_paid' => 'Tip already paid',
        'due_now' => 'Balance due',
        'additional_tip' => 'Additional tip',
        'collect_today' => 'Payment today',
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
    /*
    | What can be done to a booking after it has been taken.
    |
    | Four acts, and each one asks the same two questions: why, and is there
    | anything else. The reasons themselves are the business's own — set in
    | App settings → Reasons — so nothing here names one.
    */
    /*
    | The views a desk works in.
    |
    | Not saved searches: each one is a question somebody at the front desk
    | actually asks between nine and six, which is also why Today is where
    | the page opens.
    */
    'tabs' => [
        'today' => 'Today',
        'next-3' => 'Next 3 days',
        'month' => 'Month',
        'check-in' => 'Check-in pending',
        'more' => 'More',
        'all' => 'All bookings',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no-shows' => 'No shows',
        'declined' => 'Declined',

        'summary' => [
            'total' => "Today's bookings",
            'checked_in' => 'Checked in',
            'pending' => 'Check-in pending',
            'completed' => 'Completed',
            'no_show' => 'No show',
        ],

        /* Where they have got to, and — while they are due — how far off the
           time they are. "12 min late" is what the desk acts on; the
           scheduled time alone makes somebody read a clock and work it out
           themselves, forty times a morning. */
        'arrival' => [
            'checked_in' => 'Checked in',
            'waiting' => 'Not yet arrived',
            'not_due' => 'Not due today',
            'done' => 'Seen',
            'absent' => 'Did not arrive',
            'due' => 'Due now',
            'later' => 'Later today',
            'early' => ':count min early',
            'late' => ':count min late',
        ],

        'previous_month' => 'Previous month',
        'next_month' => 'Next month',
        'empty' => [
            'today' => 'Nothing booked for today.',
            'next-3' => 'Nothing booked over the next three days.',
            'check-in' => 'Nobody is waiting to check in.',
        ],
    ],

    'resources' => [
        'none_available' => 'No resource available for this service at the selected time.',
        'auto' => 'Auto-assigned',
        'manual' => 'Manually selected',
        'change' => 'Change resource',
        'choose' => 'Select resource',
        'unavailable' => 'Unavailable',
        'currently' => 'Currently assigned',
        'updated' => 'Resource updated: :name',
    ],

    'status' => [
        'check-in' => [
            'action' => 'Check in',
            'title' => 'Check in client',
            'intro' => 'The client is here. Their appointment moves to checked in and the time is recorded.',
            'confirm' => 'Check in',
            'note' => 'Check-in note',
            'note_hint' => 'Optional. "Arrived ten minutes early", and anything else the team should know.',
            'done_at' => 'Checked in at :time by :name',
            'already' => 'Checked in',
        ],
        /* No reason list and no dialogue to speak of: finishing an
           appointment is the ordinary outcome, and a required dropdown in
           front of it would be answered the same way every time. */
        'complete' => [
            'action' => 'Complete',
            'title' => 'Complete appointment',
            'intro' => 'The work is done. The appointment is recorded as delivered, and the client is asked how it went if review requests are switched on.',
            'confirm' => 'Complete appointment',
            'note' => 'Completion note',
            'note_hint' => 'Optional. For the team, not for the client.',
        ],
        'no-show' => [
            'action' => 'No show',
            'title' => 'Mark booking as no show',
            'intro' => 'The appointment stays on the record; the client is marked as not having arrived.',
            'reason' => 'Reason',
            'confirm' => 'Save',
        ],
        'cancelled' => [
            'action' => 'Cancel booking',
            'title' => 'Cancel booking',
            'intro' => 'The appointment is called off and the time it held is given back.',
            'reason' => 'Cancellation reason',
            /* "Keep booking" rather than "Cancel": in a dialogue about
               cancelling, a button that says Cancel is the one nobody can
               read twice the same way. */
            'dismiss' => 'Keep booking',
            'confirm' => 'Cancel booking',
        ],
        'declined' => [
            'action' => 'Decline booking',
            'title' => 'Decline booking',
            'intro' => 'The request is turned down. Nothing is booked and the client can be told why.',
            'reason' => 'Decline reason',
            'confirm' => 'Decline booking',
        ],
        'reschedule' => [
            'action' => 'Reschedule booking',
            'title' => 'Reschedule booking',
            'intro' => 'The same booking, at a different time. The reference, the client and the bill all stay as they are.',
            'reason' => 'Reschedule reason',
            'confirm' => 'Save reschedule',
            'current' => 'Currently',
            'new_date' => 'New date',
            'new_time' => 'New time',
            'staff' => 'Team member',
            'location' => 'Location',
            'pick_date' => 'Choose a date to see what is free.',
            'no_slots' => 'Nothing is free on that day.',
            'loading' => 'Checking what is free…',
        ],

        'choose_reason' => 'Choose a reason',
        'note' => 'Note',
        'note_hint' => 'Optional. For the team, not for the client.',
        'details' => 'Additional details',
        'details_hint' => 'Required for this reason.',
        'details_required' => 'This reason asks for an explanation.',
        'reason_unavailable' => 'That reason is no longer available. Choose another.',
        'slot_taken' => 'That time is not free. Choose another.',
        'dismiss' => 'Cancel',

        'done' => [
            'check-in' => 'Client checked in.',
            'complete' => 'Appointment completed.',
            'no-show' => 'Marked as no show.',
            'cancelled' => 'Booking cancelled.',
            'declined' => 'Booking declined.',
            'reschedule' => 'Booking rescheduled.',
        ],
    ],

    /*
    | The booking's own history: everything that was done to it, in the words
    | the reasons had on the day rather than the words they have now.
    */
    'activity' => [
        'title' => 'Booking activity',
        'none' => 'Nothing has happened to this booking yet.',
        'system' => 'StyleDesk',
        'by' => 'by :name',
        'reason' => 'Reason',
        'note' => 'Note',
        'previous' => 'Previous',
        'new' => 'New',
        'events' => [
            'no-show' => 'Booking marked as no show',
            'cancelled' => 'Booking cancelled',
            'declined' => 'Booking declined',
            'confirmed' => 'Booking rescheduled',
            'pending' => 'Booking rescheduled',
            'arrived' => 'Client checked in',
        ],
    ],

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
        'coupon' => 'Coupon code',
        'coupon_placeholder' => 'Enter coupon code',
        'apply' => 'Apply',
        'remove_coupon' => 'Remove',
        'add_tip' => 'Add tip',
        'no_tip' => 'No tip',
        'custom_tip' => 'Custom',
        'discount' => 'Discount',
        'tip' => 'Tip',
        'total_due' => 'Total due',
        'paying_by' => 'Paying by',
        'paying_by_hint' => 'Some services cost a different amount in cash. The total follows this.',
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
        'nothing_to_take' => 'Enter an amount or a tip — a payment of nothing is not a payment.',
        'no_card_provider' => 'No card provider is connected, so StyleDesk cannot take the card itself. Take it on the terminal and record it below.',
        'terminal' => 'Taken on the terminal',
        'not_ready' => 'Not set up yet. Add the account in Business settings.',
        'cash_only' => 'This booking is priced in cash, so it is settled in cash.',
        'cash_only_row' => 'Not available — this booking is priced in cash.',
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
        'authorized' => ['label' => 'Authorised'],
        'cancelled' => ['label' => 'Cancelled'],
        'disputed' => ['label' => 'Disputed'],
        'chargeback' => ['label' => 'Chargeback'],
        'unpaid' => ['label' => 'Unpaid'],
        'partial' => ['label' => 'Partially paid'],
        'paid' => ['label' => 'Paid'],
        'pending' => ['label' => 'Payment pending'],
        'failed' => ['label' => 'Failed'],
        'refunded' => ['label' => 'Refunded'],
        'partially-refunded' => ['label' => 'Partially refunded'],
    ],

    /* The confirmation as a text message. One segment where it can be:
       a text is charged by the 160 characters, and a template that
       quietly became three is a bill nobody agreed to. */
    'sms' => [
        'confirmation' => ':business via StyleDesk: Hi :name, your appointment is confirmed for :date at :time with :staff. Reply YES to confirm, CANCEL to request a change, STOP to opt out. Ref :reference',
    ],

    'confirmation' => [
        'title' => 'Booking confirmed',
        'made' => 'The appointment is in the diary.',
        'payment' => 'Payment',
        'view' => 'View booking',
        'another' => 'Create another booking',
        'to_list' => 'Back to bookings',
        'print' => 'Print confirmation',
        'receipt' => 'Download receipt',
        'receipt_title' => 'Receipt',
        'send' => 'Send confirmation',
        'sending' => 'Sending…',
        'sent' => 'Confirmation sent to :to.',
        'no_email' => 'This client has no email address on file.',
        'no_mobile' => 'There is no mobile number on this booking. Add one to the client, or send it by email.',
        'no_sms_consent' => 'This client has turned text messages off. Send it by email instead.',
        'link_failed' => 'The booking is made, but the payment link could not be emailed. Ring the client instead.',
        'due_notice' => 'Payment due :amount',
        'amount_due' => 'Amount due',
    ],

    'email' => [
        'subject' => ':business — your appointment on :date',
        'headline' => 'Your appointment is confirmed',
        'intro' => 'Thanks, :name. Here are the details.',
        'footer_note' => 'Need to change or cancel? Reply to this email or give us a ring.',
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
