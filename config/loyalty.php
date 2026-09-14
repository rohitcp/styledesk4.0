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
    | deliver yet.
    |
    | `default` is the MVP recommendation: services earn and nothing else
    | does, so a business that never opens this screen gives points on the
    | thing a booking is made of and on nothing it has not thought about.
    | Tips and taxes are off because points on money that was never the
    | business's to keep is a rounding error a salon has to explain.
    */
    'purchases' => [
        'services' => ['available' => true, 'default' => true],

        /* A membership is money the business took, so it can earn — but
           which kind matters enough to be two switches. A package is bought
           once and a subscription bills again, and a salon that is happy to
           give points on a one-off purchase is not necessarily happy to give
           them every month for the life of a subscription.

           Both earn on a payment that actually happened. StyleDesk does not
           bill renewals yet, so in practice `membership_recurring` awards on
           the first payment of a subscription; when renewals arrive they
           come through the same door. */
        'membership_package' => ['available' => true, 'default' => false],
        'membership_recurring' => ['available' => true, 'default' => false],

        'taxes' => ['available' => true, 'default' => false],
        'tips' => ['available' => true, 'default' => false],

        /* Nothing StyleDesk sells yet. Present so the screen tells the truth
           about what is planned, inert so nobody switches on an earning rule
           that would quietly award nothing. */
        'products' => ['available' => false, 'default' => false],
        'gift_cards' => ['available' => false, 'default' => false],
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
    | How far ahead the client's profile warns about points running out.
    |
    | Thirty days: long enough that somebody can book an appointment to spend
    | them, short enough that the warning still reads as urgent. Only ever
    | shown to a business that set an expiry at all — the default is never,
    | and a business with no deadline has nothing to warn about.
    */
    'expiring_soon_days' => 30,

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
        /* Given for joining rather than for spending. Its own type because
           the history should say what it was for, and "manual_add" would say
           a person did it. */
        'welcome' => ['icon' => 'star', 'sign' => 'positive'],
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
        'earned' => ['earned', 'welcome'],
        'redeemed' => ['redeemed'],
        'adjustments' => ['manual_add', 'manual_deduct'],
        'expired' => ['expired'],
        'refunds' => ['refund_adjustment', 'cancellation_adjustment'],
    ],

    /*
    | What a reward can be.
    |
    | The catalogue is the difference between a scheme that gives money back
    | and one that gives something worth having: "$10 off" and "a free
    | aromatherapy upgrade" cost the business differently and read to the
    | client completely differently, and a business should be able to offer
    | either.
    |
    | `available` false renders the type disabled and labelled the same way
    | the purchase list does. A free product needs products, which StyleDesk
    | does not sell yet; offering the type would let a business build a
    | reward nothing could ever hand over.
    |
    | `needs` says which field the type is configured by, because the form
    | asks a different question for each: an amount off, a percentage off, or
    | which service is being given. A type that needs nothing is worth
    | whatever the note beside it says — that is what `custom` is for, and it
    | is why it is the only one the till cannot price on its own.
    */
    'reward_types' => [
        'fixed_discount' => ['available' => true, 'needs' => 'amount'],
        'percentage_discount' => ['available' => true, 'needs' => 'percent'],
        'free_service' => ['available' => true, 'needs' => 'service'],
        'free_add_on' => ['available' => true, 'needs' => 'service'],
        'service_upgrade' => ['available' => true, 'needs' => 'service'],
        'free_product' => ['available' => false, 'needs' => 'amount'],
        'custom' => ['available' => true, 'needs' => 'none'],
    ],

    /*
    | What a reward may be spent on.
    |
    | Three answers, not a matrix. "Any service", "these services" and "these
    | categories" is the whole question a salon actually asks, and a reward
    | restricted by location as well is a reward the desk cannot explain to
    | the client standing in front of it.
    |
    | Locations are deliberately absent: §15 of the loyalty brief makes the
    | balance one balance for the whole business, and a reward earnable
    | everywhere but spendable at one branch is the same promise broken at
    | the counter.
    */
    'reward_scopes' => [
        'all_services',
        'services',
        'categories',
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
    | The number a member quotes.
    |
    | Separate from client_ref because a member number is the thing printed on
    | a card and read out over a counter: it outlives the record's internal
    | identifier and should not change if the client list is ever renumbered.
    */
    'member_id' => [
        'prefix' => 'RW-',
        'padding' => 6,
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
