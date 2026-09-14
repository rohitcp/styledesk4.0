<?php

declare(strict_types=1);

/*
| Loyalty and rewards.
|
| Two audiences, kept apart by their top-level keys: `settings` is read by the
| person deciding how the scheme works, `client` by the person standing at the
| desk with somebody in front of them. The second is the one that has to be
| short — a receptionist reads it while a client waits.
*/

return [

    'title' => 'Loyalty & Rewards',

    /* One line per kind of thing that can happen to a balance. Written as
       what happened rather than as a category, because this is read in a list
       beside a date and a number. */
    'activities' => [
        'earned' => 'Points earned',
        'redeemed' => 'Reward redeemed',
        'expired' => 'Points expired',
        'refund_adjustment' => 'Refund adjustment',
        'cancellation_adjustment' => 'Cancellation adjustment',
        'welcome' => 'Welcome bonus',
        'manual_add' => 'Manual addition',
        'manual_deduct' => 'Manual deduction',
    ],

    /* Why somebody moved a balance by hand. */
    'reasons' => [
        'customer_service' => 'Customer service credit',
        'promotion' => 'Promotion',
        'correction' => 'Correction',
        'duplicate' => 'Duplicate points',
        'refund' => 'Refund adjustment',
        'other' => 'Other',
    ],

    'purchases' => [
        'services' => 'Services',
        'membership_package' => 'Membership package',
        'membership_recurring' => 'Membership recurring',
        'taxes' => 'Taxes',
        'tips' => 'Tips',
        'products' => 'Product',
        'gift_cards' => 'Gift card',
    ],

    'expiry' => [
        'never' => 'Never',
        '6m' => 'After 6 months',
        '12m' => 'After 12 months',
        '24m' => 'After 24 months',
    ],

    'notifications' => [
        'earned_email' => 'Points earned email',
        'earned_sms' => 'Points earned SMS',
        'reward_email' => 'Reward available email',
        'reward_sms' => 'Reward available SMS',
    ],

    /* ------------------------------------------------------- app settings -- */

    'settings' => [
        'title' => 'Loyalty & Rewards',
        'intro' => 'What a visit earns, what a point is worth back, and who can spend one.',

        'enable' => 'Enable Loyalty & Rewards',
        'enable_hint' => 'Clients earn points on completed, paid appointments and can spend them on future visits.',
        'disabled_note' => 'Rewards are off. Nothing new is earned and nothing can be redeemed — every balance and every line of history is kept exactly as it is.',

        'program' => 'Program',
        'program_hint' => 'What your clients see this called, on their receipt and in their emails.',
        'program_name' => 'Program name',
        'program_name_hint' => 'For example: Glow Rewards, Beauty Points, Wellness Rewards.',
        'description' => 'Description',
        'description_hint' => 'One line explaining the scheme. Optional.',
        'description_placeholder' => 'Earn points every time you visit and redeem them toward future services.',

        'earn' => 'Earn points',
        'earn_hint' => 'How spending turns into points. Points are whole: with $5 = 1 point, a $17 service earns 3.',
        'spend_amount' => 'Spent',
        'points_earned' => 'Points',
        'earn_rule' => ':symbol:amount spent = :points',
        'eligible' => 'Eligible purchases',
        'eligible_hint' => 'What earns points. Services always do — they are what a booking is made of.',
        'always_on' => 'Always on',
        'coming_soon' => 'Coming soon',

        'redeem' => 'Redeem points',
        'redeem_hint' => 'What a point is worth back, and the limits on spending a balance in one go.',
        'points_required' => 'Points required',
        'reward_value' => 'Reward value',
        'minimum_redemption' => 'Minimum points to redeem',
        'minimum_hint' => 'The smallest balance that can be spent at all. Below it, a client is told how far they have to go.',
        'maximum_reward' => 'Maximum reward per transaction',
        'maximum_hint' => 'The most a single visit may be discounted by. Leave empty for no limit.',
        'rule' => ':points points = :value',

        'expiry' => 'Expiration',
        'expiry_hint' => 'How long a point lives after it is earned. Points already earned keep the deadline they were given.',

        'notifications' => 'Notifications',
        'notifications_hint' => 'What a client hears when their balance moves. Nothing sends yet — the messages arrive with the next release.',

        'rules' => 'Business rules',
        'rules_hint' => 'The parts of the scheme StyleDesk decides, so you know what to expect.',
        'rule_locations' => 'One balance for the whole business',
        'rule_locations_body' => 'A client earns at any location and can redeem at any other. There is no separate balance per branch.',
        'rule_awarded' => 'Points land when the visit is finished and paid',
        'rule_awarded_body' => 'Drafts, leads, cancellations, declines, no-shows and unpaid appointments earn nothing. A part-paid appointment earns in proportion to what has been settled.',
        'rule_refunds' => 'Refunds take points back',
        'rule_refunds_body' => 'A full refund reverses everything the visit earned. A partial refund reverses the same proportion, and the client keeps the rest.',
        'rule_calculation' => 'Points are counted on what the client actually paid',
        'rule_calculation_body' => 'Coupons, discounts and rewards already spent come off before points are worked out.',

        'saved' => 'Loyalty settings saved.',
    ],

    /* ----------------------------------------------------- client profile -- */

    'client' => [
        'title' => 'Loyalty & Rewards',
        'off' => 'Rewards are switched off for this business.',
        'off_hint' => 'Balances and history are kept. Nothing new is earned and nothing can be redeemed until the scheme is switched back on.',

        'available' => 'Available points',
        'available_hint' => 'Points that can be redeemed now.',
        'pending' => 'Pending points',
        'pending_hint' => 'Expected from appointments not yet completed.',
        'lifetime_earned' => 'Lifetime earned',
        'lifetime_redeemed' => 'Lifetime redeemed',

        'reward_value' => 'Reward value',
        'worth' => 'Worth :value',
        'worth_nothing' => 'Not enough to redeem yet',

        'next_reward' => 'Next reward',
        'progress' => ':have / :need points',
        'to_go' => ':points more points to unlock :value',
        'unlocked' => ':value ready to redeem',

        'activity' => 'Rewards activity',
        'none' => 'No rewards activity yet.',
        'none_filtered' => 'Nothing in this part of the history.',
        'columns' => [
            'date' => 'Date',
            'activity' => 'Activity',
            'booking' => 'Booking',
            'location' => 'Location',
            'points' => 'Points',
            'balance' => 'Balance',
        ],
        'filters' => [
            'all' => 'All activity',
            'earned' => 'Earned',
            'redeemed' => 'Redeemed',
            'adjustments' => 'Adjustments',
            'expired' => 'Expired',
            'refunds' => 'Refunds',
        ],

        'adjust' => 'Adjust points',
        'adjust_title' => 'Adjust points',
        'adjust_intro' => 'Points added or removed by hand are recorded against your name and cannot be edited afterwards.',
        'direction' => 'Adjustment type',
        'add' => 'Add points',
        'remove' => 'Remove points',
        'points' => 'Points',
        'reason' => 'Reason',
        'note' => 'Internal note',
        'note_hint' => 'Only your team sees this. Optional.',
        'save' => 'Save adjustment',
        'adjusted' => 'Points adjusted.',

        'by' => 'by :name',
        'expires' => 'Expires :date',
        'expiring_soon' => 'Expiring soon',
        'expiring_hint' => 'Within :days days',
        'membership_sale' => 'Membership',
        'source' => 'Joined via',
        'table_date' => 'Date',
        'table_activity' => 'Activity',
        'table_points' => 'Points',
        'table_balance' => 'Balance',
        'table_source' => 'Booking / sale',
        'table_location' => 'Location',
        'table_staff' => 'Staff',
        'table_status' => 'Status',
        'catalogue' => 'Rewards',
        'catalogue_hint' => 'What this balance can be spent on. Rewards are applied during booking or checkout.',
        'catalogue_empty' => 'No rewards have been set up yet. Points come off the bill at the rate above.',
        'affordable' => 'Can be used now',
        'short_by' => ':points more points needed',
        'reward_any' => 'Any service',
    ],

    /*
     * The reward catalogue on App Settings → Loyalty & Rewards.
     *
     * A reward is what a balance actually buys, as opposed to the conversion
     * rule above it, which is what a balance is worth. Both are needed: a
     * business may want nothing more than "points off the bill", and another
     * wants to give away an upgrade it can afford rather than cash it cannot.
     */
    'rewards' => [
        'title' => 'Reward catalogue',
        'intro' => 'What your clients can spend their points on. Leave it empty and points simply come off the bill at the rate above.',
        'add' => '+ Add reward',
        'edit' => 'Edit reward',
        'empty' => 'No rewards yet. Clients can still redeem points against the bill at the rate you set above.',
        'name' => 'Reward name',
        'name_placeholder' => '$10 Off Any Service',
        'description' => 'Description',
        'description_hint' => 'Shown to the client beside the reward. Optional.',
        'type' => 'Reward type',
        'points_required' => 'Points required',
        'value' => 'Amount off',
        'percent' => 'Percentage off',
        'service' => 'Service given',
        'scope' => 'Can be used on',
        'scope_services' => 'Choose services',
        'scope_categories' => 'Choose categories',
        'active' => 'Available to clients',
        'active_hint' => 'Switch off to take it out of the catalogue without losing the rewards already given.',
        'save' => 'Save reward',
        'cancel' => 'Cancel',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove this reward from the catalogue? Rewards clients have already redeemed keep their history.',
        'added' => 'Reward added.',
        'saved' => 'Reward saved.',
        'removed' => 'Reward removed.',
        'retired' => 'Reward taken off the catalogue. Redemptions already made keep their history.',
        'no_service' => 'No service chosen',
        'value_varies' => 'Set at the till',
        'inactive' => 'Not available',
        'points' => ':count points',

        'types' => [
            'fixed_discount' => 'Amount off',
            'percentage_discount' => 'Percentage off',
            'free_service' => 'Free service',
            'free_add_on' => 'Free add-on',
            'service_upgrade' => 'Service upgrade',
            'free_product' => 'Free product',
            'custom' => 'Something else',
        ],

        'scopes' => [
            'all_services' => 'Any service',
            'services' => 'Chosen services only',
            'categories' => 'Chosen categories only',
        ],

        'validation' => [
            'amount_required' => 'Say how much comes off.',
            'percent_required' => 'Say what percentage comes off.',
            'service_required' => 'Choose the service this reward gives.',
            'scope_required' => 'Choose at least one, or make it available on any service.',
        ],
    ],

    /*
     * What became of one line of the history.
     *
     * "Pending" is absent on purpose: pending points are computed from the
     * diary and never written to the ledger, so no line can be in that state.
     * The figure above the table is where they are reported.
     */
    'statuses' => [
        'available' => 'Available',
        'redeemed' => 'Redeemed',
        'expired' => 'Expired',
        'reversed' => 'Reversed',
    ],

    /*
     * Joining the scheme: how it is decided, and what it is worth.
     */
    'enrollment' => [
        'title' => 'Enrolment',
        'hint' => 'Who joins the rewards scheme, and what they get for joining.',
        'mode' => 'Default for new clients',
        'modes' => [
            'auto' => 'Enrol every new client automatically',
            'default_on' => 'Ticked by default, staff can opt out',
            'opt_in' => 'Not ticked, staff must opt in',
        ],
        'mode_hints' => [
            'auto' => 'Nobody is asked. Every client added anywhere joins the scheme.',
            'default_on' => 'The highest enrolment rate that still leaves the client a say.',
            'opt_in' => 'The receptionist has to tick the box for every client.',
        ],
        'welcome_points' => 'Welcome bonus',
        'welcome_hint' => 'Points credited when a client joins. Leave at zero to give nothing.',

        /* On the create-client form. */
        'section' => 'Loyalty & Rewards',
        'enroll' => 'Enrol this client in :program',
        'enroll_hint' => 'The client can earn points, receive rewards and use loyalty benefits.',
        'automatic' => 'Every new client joins :program automatically.',
        'welcome_badge' => 'Welcome bonus: :points points',
        'welcome_note' => 'These points are issued according to the rules of the scheme.',
        'no_contact' => 'This client has no email address or mobile number, so they cannot be told about their points.',

        /* On the client profile. */
        'status' => 'Membership',
        'enrolled' => 'Enrolled',
        'not_enrolled' => 'Not enrolled',
        'not_enrolled_hint' => 'This client earns points but never formally joined the scheme.',
        'member_id' => 'Member ID',
        'member_since' => 'Member since',
        'enrolled_by' => 'Enrolled by :name',

        'sources' => [
            'client_creation' => 'Client creation',
            'booking' => 'Booking',
            'walk_in' => 'Walk-in',
            'import' => 'Import',
        ],
    ],

    /*
     * Clients → Loyalty: the list of everybody in the scheme.
     */
    'members' => [
        'title' => 'Loyalty',
        'intro' => 'Everybody enrolled in your rewards scheme, and what they are sitting on.',
        'search' => 'Search members…',
        'none' => 'Nobody has joined yet. Clients can be enrolled when they are added, or from their profile.',
        'showing' => 'Showing',
        'actions_for' => 'Actions for :name',
        'results' => [
            'zero' => 'No members match',
            'one' => ':count member',
            'many' => ':count members',
            'clear' => 'Clear search',
        ],
        'disabled' => 'Loyalty & Rewards is not switched on',
        'disabled_hint' => 'Turn it on in App Settings to start enrolling clients, awarding points and offering rewards.',
        'enable' => 'Enable Loyalty & Rewards',
        'ask_an_owner' => 'Ask the account owner or an administrator to switch it on.',
        'open_profile' => 'Open client profile',
        'back' => 'Back to Loyalty',
        'columns' => [
            'client' => 'Client',
            'member_id' => 'Member ID',
            'mobile' => 'Phone',
            'email' => 'Email',
            'status' => 'Status',
            'enrolled' => 'Enrolled',
            'balance' => 'Balance',
            'earned' => 'Lifetime',
            'redeemed' => 'Redeemed',
            'tier' => 'Tier',
            'last_activity' => 'Last activity',
        ],
    ],

    /*
     * What state a membership of the scheme is in.
     *
     * Separate from `statuses`, which is what became of one LINE of the
     * ledger. A client is paused; a transaction is redeemed. Reading one list
     * for both would put "Expired" beside somebody's name.
     */
    'member_statuses' => [
        'active' => 'Active',
        'paused' => 'Paused',
        'suspended' => 'Suspended',
        'unenrolled' => 'Unenrolled',
    ],

    'activity' => [
        'system' => 'StyleDesk',
    ],

];
