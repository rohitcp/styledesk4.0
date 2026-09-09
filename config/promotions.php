<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Coupons & offers
|--------------------------------------------------------------------------
|
| The templates a business can start from. A template is not a kind of
| promotion — it only fills the form in, and every value it sets is a field
| the reader can then change. That is what makes them safe to offer: nothing
| here decides anything, it only saves somebody typing.
|
| The eight below are the promotions salons actually run, which is why they
| are worth pre-writing. A blank form is a fair amount of thinking for
| somebody who just wants twenty per cent off a first visit.
|
*/

return [

    'templates' => [
        'new-client' => [
            'name' => 'New Client Offer',
            'type' => 'coupon',
            'code' => 'WELCOME20',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'eligibility' => 'new',
            'applies_to' => 'all_services',
        ],

        'birthday' => [
            'name' => 'Birthday Offer',
            'type' => 'coupon',
            'code' => 'BIRTHDAY15',
            'discount_type' => 'percent',
            'discount_value' => 15,
            'eligibility' => 'existing',
            'applies_to' => 'all_services',
        ],

        'referral' => [
            'name' => 'Referral Offer',
            'type' => 'coupon',
            'code' => 'REFER20',
            'discount_type' => 'fixed',
            'discount_value' => 20,
            'eligibility' => 'new',
            'applies_to' => 'all_services',
        ],

        'rebooking' => [
            'name' => 'Rebooking Offer',
            'type' => 'coupon',
            'code' => 'REBOOK10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'eligibility' => 'existing',
            'applies_to' => 'all_services',
        ],

        'seasonal' => [
            'name' => 'Seasonal Promotion',
            'type' => 'offer',
            'discount_type' => 'percent',
            'discount_value' => 15,
            'eligibility' => 'all',
            'applies_to' => 'all_services',
        ],

        /* The one that pays for itself: an empty Tuesday cannot be sold
           again on Wednesday. */
        'slow-day' => [
            'name' => 'Slow Day Offer',
            'type' => 'offer',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'eligibility' => 'all',
            'applies_to' => 'all_services',
            'days' => [2],
        ],

        'service' => [
            'name' => 'Service Promotion',
            'type' => 'offer',
            'discount_type' => 'percent',
            'discount_value' => 15,
            'eligibility' => 'all',
            'applies_to' => 'services',
        ],

        'win-back' => [
            'name' => 'Win Back Client',
            'type' => 'coupon',
            'code' => 'WE-MISS-YOU-20',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'eligibility' => 'existing',
            'applies_to' => 'all_services',
        ],
    ],
];
