<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Coupons & offers
|--------------------------------------------------------------------------
|
| One module for both, because they are one thing with one difference: a
| coupon is typed in and an offer applies itself.
|
*/

return [

    'title' => 'Coupons & offers',
    'intro' => 'Discounts a client types in, and ones that apply themselves.',
    'new' => 'Create coupon / offer',
    'none_yet' => 'No coupons or offers yet.',
    'none_yet_hint' => 'A first-visit discount is the one most salons start with.',
    'automatic' => 'Automatic',
    'no_expiry_short' => 'No expiry',
    'copy_of' => ':name (copy)',

    'summary' => [
        'active' => 'Active offers',
        'scheduled' => 'Scheduled',
        'expired' => 'Expired',
        'redemptions' => 'Total redemptions',
    ],

    'columns' => [
        'name' => 'Name',
        'code' => 'Code',
        'type' => 'Type',
        'discount' => 'Discount',
        'applies' => 'Applies to',
        'starts' => 'Start',
        'ends' => 'End',
        'used' => 'Used',
        'status' => 'Status',
    ],

    'types' => [
        'coupon' => 'Coupon',
        'offer' => 'Offer',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'active' => 'Active',
        'expired' => 'Expired',
        'disabled' => 'Disabled',
    ],

    'applies' => [
        'booking' => 'Entire booking',
        'all_services' => 'All services',
        'services' => 'Selected services',
        'categories' => 'Selected categories',
    ],

    'filters' => [
        'all_statuses' => 'All statuses',
        'all_types' => 'All types',
        'all_locations' => 'All locations',
        'reset' => 'Reset',
    ],

    'search' => 'Search by name, code or service…',
    'results' => [
        'zero' => 'No coupons or offers match',
        'one' => '1 coupon or offer',
        'many' => ':count coupons and offers',
        'clear' => 'Clear filters',
    ],
    'showing' => 'Showing :from–:to of :total',
    'empty' => 'Nothing matches those filters.',
    'actions_for' => 'Actions for :name',

    /* -------------------------------------------------------------- form */

    'form' => [
        'create_title' => 'Create coupon or offer',
        'edit_title' => 'Edit coupon or offer',

        'templates' => 'Start from a template',
        'templates_hint' => 'A template only fills the form in. Everything it sets can be changed before you save.',
        'scratch' => 'Start from scratch',

        'basics' => 'Basic information',
        'name' => 'Promotion name',
        'name_placeholder' => 'New client 20% off',
        'description' => 'Internal description',
        'description_hint' => 'For the team, not the client.',
        'type' => 'Promotion type',
        'type_coupon' => 'Coupon code',
        'type_coupon_hint' => 'The client or the desk types it in.',
        'type_offer' => 'Automatic offer',
        'type_offer_hint' => 'Applies itself to any booking that qualifies.',
        'code' => 'Coupon code',
        'code_hint' => 'Letters, numbers and hyphens. Saved in capitals.',
        'generate' => 'Generate',

        'discount' => 'Discount',
        'discount_type' => 'Discount type',
        'percent' => 'Percentage',
        'fixed' => 'Fixed amount',
        'amount' => 'Amount',
        'percent_hint' => 'A percentage of the part it applies to. Up to 100.',
        'fixed_hint' => 'A flat sum off, never more than the part it applies to.',

        'applies' => 'Applies to',
        'applies_hint' => 'A percentage is taken off the part it applies to, not the whole bill.',
        'services' => 'Services',
        'categories' => 'Categories',

        'locations' => 'Locations',
        'all_locations' => 'All locations',
        'selected_locations' => 'Selected locations',

        'validity' => 'Validity',
        'starts' => 'Start date',
        'ends' => 'End date',
        'no_expiry' => 'No expiry date',
        'days' => 'Valid days',
        'days_hint' => 'Leave every day ticked unless the promotion is for particular days — an empty Tuesday cannot be sold again on Wednesday.',

        'eligibility' => 'Who it is for',
        'eligibility_all' => 'All clients',
        'eligibility_new' => 'New clients only',
        'eligibility_new_hint' => 'Nobody has finished an appointment for them yet.',
        'eligibility_existing' => 'Existing clients only',
        'eligibility_selected' => 'Selected clients',
        'clients' => 'Clients',

        'redemption' => 'Redemption rules',
        'min_spend' => 'Minimum booking amount',
        'min_spend_hint' => 'Optional. Stops a fixed discount being used against a very small booking.',
        'total_limit' => 'Total redemptions',
        'total_limit_hint' => 'Leave blank for unlimited.',
        'per_client_limit' => 'Per client',
        'per_client_limit_hint' => 'Leave blank for unlimited. Once each is the usual answer.',

        'availability' => 'Where it can be used',
        'allow_online' => 'Allow clients to use this during online booking',
        'combinable' => 'Can be combined with another promotion',
        'combinable_hint' => 'Off is the safe answer: one promotion per booking.',
        'draft' => 'Save as a draft',
        'draft_hint' => 'A draft is not offered to anybody until you turn it off.',

        'save' => 'Save',
        'cancel' => 'Cancel',
    ],

    'weekdays' => [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ],

    /* ------------------------------------------------------------ details */

    'details' => [
        'title' => 'Offer details',
        'discount' => 'Discount',
        'for' => 'Available for',
        'services' => 'Services',
        'locations' => 'Locations',
        'valid' => 'Valid',
        'usage' => 'Usage',
        'online' => 'Online booking',
        'online_yes' => 'Clients can use this themselves',
        'online_no' => 'Staff only',
        'created_by' => 'Created by :name',
        'no_expiry' => 'No expiry',
        'every_day' => 'Every day',
    ],

    'report' => [
        'title' => 'How it is doing',
        'redemptions' => 'Redemptions',
        'clients' => 'Clients',
        'discount' => 'Discount given',
        'revenue' => 'Revenue on those bookings',
        'revenue_hint' => 'What the bookings it was used on came to — not a claim that the promotion caused them.',
        'none' => 'Nobody has used this yet.',
    ],

    'actions' => [
        'view' => 'View',
        'edit' => 'Edit',
        'duplicate' => 'Duplicate',
        'disable' => 'Disable',
        'enable' => 'Enable',
        'back' => 'Coupons & offers',
    ],

    /* --------------------------------------------------------- the answer */

    'refused' => [
        'not_running' => 'That promotion is not running.',
        'not_started' => 'That promotion has not started yet.',
        'expired' => 'That promotion has expired.',
        'wrong_day' => 'That promotion does not run on this day.',
        'wrong_location' => 'That promotion is not available at this location.',
        'needs_a_client' => 'That promotion is only for particular clients, so the booking needs one.',
        'new_only' => 'That promotion is for new clients only.',
        'existing_only' => 'That promotion is for existing clients only.',
        'not_for_this_client' => 'That promotion is not available for this client.',
        'no_eligible_services' => 'Nothing on this booking qualifies for that promotion.',
        'under_minimum' => 'That promotion needs a booking of at least :amount.',
        'fully_redeemed' => 'That promotion has been fully redeemed.',
        'client_limit' => 'This client has already used that promotion.',
        'unknown_code' => 'No promotion with that code.',
    ],

    'created' => 'Coupon or offer created.',
    'saved' => 'Coupon or offer saved.',
    'duplicated' => 'Copied. It is saved as a draft.',
    'disabled' => 'Promotion disabled.',
    'enabled' => 'Promotion enabled.',
];
