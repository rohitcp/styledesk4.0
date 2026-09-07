<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Loyalty & rewards
|--------------------------------------------------------------------------
|
| One source for the vocabulary of the loyalty module: what a purchase can be,
| how long points may live, why somebody adjusted a balance by hand, and what
| each line of a client's rewards history is. The settings screen builds its
| lists from these, the controller validates against them and the client's
| activity table reads its icons from them — three readings of one list rather
| than three lists that drift.
|
*/

return [

    /*
    | What a business may decide to give points for.
    |
    | `available` false renders the option disabled and labelled "Coming
    | soon", the same way config/reviews.php treats a channel nothing can
    | deliver yet. Products, memberships, packages and gift cards are not
    | things StyleDesk sells yet — present so the screen tells the truth about
    | what is planned, inert so nobody switches on an earning rule that would
    | quietly award nothing.
    |
    | `default` is the MVP recommendation: services and products earn, the
    | rest do not. Tips and taxes are off because points on money that was
    | never the business's to keep is a rounding error a salon has to explain.
    */
    'purchases' => [
        'services' => ['available' => true, 'default' => true],
        'products' => ['available' => false, 'default' => true],
        'memberships' => ['available' => false, 'default' => false],
        'packages' => ['available' => false, 'default' => false],
        'gift_cards' => ['available' => false, 'default' => false],
        'tips' => ['available' => true, 'default' => false],
        'taxes' => ['available' => true, 'default' => false],
    ],

    /*
    | How long a point lives.
    |
    | Months, and null for never — which is the default, because a balance
    | that evaporates is the one thing a loyalty scheme can do that loses more
    | goodwill than having no scheme at all. A business that wants a deadline
    | can set one; StyleDesk does not set one for them.
    */
    'expiry' => [
        'never' => ['months' => null],
        '6m' => ['months' => 6],
        '12m' => ['months' => 12],
        '24m' => ['months' => 24],
    ],

    /*
    | Every line a rewards history can hold, and the icon it is read by.
    |
    | One list, because the history is one list. `sign` says which direction
    | the type may move a balance — it is what the manual adjustment form
    | posts, and what stops "remove points" arriving as a positive number.
    |
    | `pending` is not here. Points a client has not earned yet are worked out
    | from the diary rather than written down — see App\Support\LoyaltyPoints.
    */
    'activities' => [
        'earned' => ['icon' => 'star', 'sign' => 'positive'],
        'redeemed' => ['icon' => 'gift', 'sign' => 'negative'],
        'expired' => ['icon' => 'clock', 'sign' => 'negative'],
        'refund_adjustment' => ['icon' => 'arrow-right-arrow-left', 'sign' => 'negative'],
        'cancellation_adjustment' => ['icon' => 'calendar-xmark', 'sign' => 'negative'],
        'manual_add' => ['icon' => 'plus', 'sign' => 'positive'],
        'manual_deduct' => ['icon' => 'sliders', 'sign' => 'negative'],
    ],

    /*
    | The filters above that history, and which activity types each one holds.
    | The tab's buttons are built from this, so a seventh activity type is one
    | entry here rather than a view edit.
    */
    'filters' => [
        'all' => [],
        'earned' => ['earned'],
        'redeemed' => ['redeemed'],
        'adjustments' => ['manual_add', 'manual_deduct'],
        'expired' => ['expired'],
        'refunds' => ['refund_adjustment', 'cancellation_adjustment'],
    ],

    /*
    | Why somebody moved a balance by hand.
    |
    | A list rather than a free-text box, for the same reason config/reasons.php
    | exists: "goodwill" typed four ways is four things nobody can count. The
    | internal note beside it is where the particulars go.
    */
    'adjustment_reasons' => [
        'customer_service',
        'promotion',
        'correction',
        'duplicate',
        'refund',
        'other',
    ],

    /*
    | Which of the four loyalty messages StyleDesk can actually send.
    |
    | All four are storable and none is deliverable yet: the points engine
    | lands before the messages do, and a toggle that promised a client an
    | email nobody wrote would be worse than one that says "coming soon".
    */
    'notifications' => [
        'earned_email' => ['available' => false],
        'earned_sms' => ['available' => false],
        'reward_email' => ['available' => false],
        'reward_sms' => ['available' => false],
    ],

    /*
    | The default earning and redemption rule, used for a business that has
    | never opened the screen. A dollar a point, five dollars back for five
    | hundred — the shape every client already understands.
    */
    'defaults' => [
        'program_name' => 'Rewards',
        'spend_amount' => 1,
        'points_earned' => 1,
        'points_required' => 500,
        'reward_value' => 5,
        'minimum_redemption' => 500,
    ],

];
