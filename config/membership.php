<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Membership
|--------------------------------------------------------------------------
|
| One source for the vocabulary of the membership module: what a membership
| can be, how often a recurring one bills, when it starts, how long a credit
| lives and what cancelling one means. The settings screen builds its lists
| from these, the controller validates against them and the plan builder reads
| the same keys — three readings of one list rather than three lists that
| drift.
|
| Everything here is a word, not a rule. What a particular plan costs and what
| it includes is the business's decision and lives in its own row; this file
| only says which answers exist.
|
*/

return [

    /*
    | The two things a business can sell.
    |
    | A recurring membership renews on a cycle and its credits come back with
    | it. A package is bought once, holds a fixed number of services and is
    | finished when they are used. They are not two settings of one thing —
    | one has a next billing date and the other never will — so they are named
    | separately everywhere rather than distinguished by a nullable column.
    */
    'types' => [
        'recurring' => ['recurs' => true],
        'package' => ['recurs' => false],
    ],

    /*
    | How often a recurring membership bills.
    |
    | Months, because every cycle a salon actually sells is a whole number of
    | them, and "quarterly" stated as 3 keeps the next-billing-date arithmetic
    | to one addition. Weekly is deliberately absent: a weekly membership is a
    | class pass, and StyleDesk does not sell one yet.
    */
    'billing_frequencies' => [
        'monthly' => ['months' => 1],
        'quarterly' => ['months' => 3],
        'yearly' => ['months' => 12],
    ],

    /*
    | When a membership bought today starts working.
    |
    | The default the purchase screen offers. Staff may still choose the other
    | one per sale where the business allows a start date to be picked at all
    | — this is the answer nobody has to think about, not the only answer.
    */
    'activation' => [
        'immediately',
        'start_date',
    ],

    /*
    | How long an unused credit lives once it has been granted.
    |
    | Months, and null for never. `cycle` is the recurring default and is not
    | a duration at all — the credit dies when the cycle it belongs to ends,
    | whenever that happens to be — which is why it cannot be expressed as a
    | number of months here.
    */
    'credit_expiry' => [
        'cycle' => ['months' => null],
        'never' => ['months' => null],
        '1m' => ['months' => 1],
        '3m' => ['months' => 3],
        '6m' => ['months' => 6],
        '12m' => ['months' => 12],
    ],

    /*
    | What cancelling does to the current cycle.
    |
    | End of cycle by default: the client has paid for the month they are in,
    | and a cancellation that takes their remaining credits away the moment
    | they ask is the one refund conversation no salon wants.
    */
    'cancellation' => [
        'end_of_cycle',
        'immediately',
    ],

    /*
    | Where a membership can be sold.
    |
    | `available` false renders the channel disabled and labelled "Coming
    | soon", the same way config/loyalty.php treats a purchase nothing can
    | earn on yet. Online booking cannot sell one until the public booking
    | pages exist — present so the screen tells the truth about what is
    | planned, inert so nobody switches on a channel that would sell nothing.
    */
    'channels' => [
        'in_store' => ['available' => true, 'default' => true],
        'online' => ['available' => false, 'default' => false],
    ],

    /*
    | Payment methods that can be charged again without the client present.
    |
    | A recurring membership needs one: cash buys a package, it does not renew
    | a subscription. Named here rather than inferred from config/bookings.php,
    | because "can be taken at the till" and "can be taken again next month"
    | are different questions and the methods list answers only the first.
    */
    'repeatable_methods' => ['card', 'paypal'],

    /*
    | What a business gets before it has chosen anything.
    |
    | Off, sold at the desk, starting today, credits resetting each cycle and
    | cancellable at the end of the month with no commitment. Every one of
    | these is the answer that takes the least from the client, because a
    | default that quietly locks somebody into six months is a default nobody
    | agreed to.
    */
    'defaults' => [
        'billing_frequency' => 'monthly',
        'default_activation' => 'immediately',
        'credit_expiry' => 'cycle',
        'cancellation_effective' => 'end_of_cycle',
        'minimum_commitment_months' => 0,
        'cancellation_notice_days' => 0,
    ],
];
