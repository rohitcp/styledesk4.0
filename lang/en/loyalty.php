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
        'products' => 'Products',
        'memberships' => 'Memberships',
        'packages' => 'Packages',
        'gift_cards' => 'Gift cards',
        'tips' => 'Tips',
        'taxes' => 'Taxes',
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
    ],

    'activity' => [
        'system' => 'StyleDesk',
    ],

];
