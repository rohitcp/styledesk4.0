<?php

declare(strict_types=1);

/*
| Membership.
|
| Two audiences, kept apart by their top-level keys: `settings` is read by the
| person deciding the terms the business sells on, everything above it by the
| person standing at the desk with somebody in front of them.
*/

return [

    'title' => 'Membership',

    /* The two things a business can sell. Named as what the client buys
       rather than as a data type — nobody signs up for a "recurring". */
    'types' => [
        'recurring' => 'Recurring Membership',
        'recurring_hint' => 'Billed automatically on a cycle. Its benefits renew every time it bills.',
        'package' => 'Membership Package',
        'package_hint' => 'Bought once, holds a set number of services and is finished when they are used.',
    ],

    'billing_frequencies' => [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'yearly' => 'Yearly',
    ],

    /* Read as an answer to "when does it start?" */
    'activation' => [
        'immediately' => 'Immediately',
        'start_date' => 'On a selected start date',
    ],

    'credit_expiry' => [
        'cycle' => 'At the end of each billing cycle',
        'never' => 'Never',
        '1m' => 'After 1 month',
        '3m' => 'After 3 months',
        '6m' => 'After 6 months',
        '12m' => 'After 12 months',
    ],

    'cancellation' => [
        'end_of_cycle' => 'At the end of the billing cycle',
        'immediately' => 'Immediately',
    ],

    'channels' => [
        'in_store' => 'In store',
        'in_store_hint' => 'Sold at the front desk through the booking screen.',
        'online' => 'Online',
        'online_hint' => 'Bought by clients themselves on your booking page.',
    ],

    /* ------------------------------------------------------ the module -- */

    'intro' => 'The memberships and packages you sell. Clients buy them from the booking screen.',
    'new' => 'Create Membership',
    'none_yet' => 'No memberships yet',
    'none_yet_hint' => 'Create one and it becomes available in the booking screen the moment you publish it.',
    'all_locations' => 'All locations',

    /* "$79 / month". The period is the singular noun, not the frequency —
       "$79 / monthly" is not something anybody writes. */
    'price_per' => ':price / :period',
    'periods' => [
        'monthly' => 'month',
        'quarterly' => 'quarter',
        'yearly' => 'year',
    ],

    'discount_off' => ':amount off additional services',
    'saving' => 'Customer saving',
    'regular_value' => 'Regular value',

    'statuses' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'disabled' => 'Disabled',
    ],

    'created' => 'Membership created.',
    'saved' => 'Membership saved.',
    'duplicated' => 'Copied. This is a draft until you publish it.',
    'disabled' => 'Membership taken off sale.',
    'enabled' => 'Membership back on sale.',
    'copy_of' => 'Copy of :name',

    'tabs' => [
        'overview' => 'Overview',
        'plans' => 'Membership Plans',
        'packages' => 'Membership Packages',
        'members' => 'Members',
    ],

    'summary' => [
        'plans' => 'Plans on sale',
        'packages' => 'Packages on sale',
        'drafts' => 'Drafts',
        'members' => 'Members',
    ],

    'search' => 'Search by name, code or included service',
    'filters' => [
        'all_statuses' => 'All statuses',
        'all_locations' => 'All locations',
        'reset' => 'Reset',
    ],

    'columns' => [
        'name' => 'Name',
        'code' => 'Code',
        'price' => 'Price',
        'includes' => 'Includes',
        'benefit' => 'Member benefit',
        'saving' => 'Saving',
        'locations' => 'Locations',
        'status' => 'Status',
    ],

    'results' => [
        'zero' => 'No memberships match',
        'one' => ':count membership',
        'many' => ':count memberships',
        'clear' => 'Clear filters',
    ],
    'empty' => 'Nothing matches those filters.',
    'showing' => 'Showing :from–:to of :total',
    'actions_for' => 'Actions for :name',

    'actions' => [
        'view' => 'View',
        'edit' => 'Edit',
        'duplicate' => 'Duplicate',
        'disable' => 'Take off sale',
        'enable' => 'Put back on sale',
    ],

    'overview' => [
        'title' => 'Overview',
        'intro' => 'What you sell, and where it is sold.',
        'recent' => 'Recently updated',
        'recent_empty' => 'Nothing built yet.',
        'terms' => 'Selling terms',
        'terms_hint' => 'These apply to every membership you sell. They are set in App Settings.',
        'terms_link' => 'Open Membership settings',
        'term_channels' => 'Sold',
        'term_activation' => 'Starts',
        'term_credits' => 'Unused credits',
        'term_credits_rollover' => 'Roll over',
        'term_credits_reset' => 'Reset each cycle',
        'term_cancellation' => 'Cancellation',
        'term_cancellation_off' => 'Not allowed',
        'nothing_sellable' => 'Nothing is on sale',
        'nothing_sellable_hint' => 'Every membership you have built is a draft or has been taken off sale, so the booking screen has nothing to offer.',
    ],

    'members' => [
        'title' => 'Members',
        'intro' => 'Everybody holding a membership, and what they have left.',
        'none' => 'Nobody holds a membership yet',
        'none_hint' => 'Memberships are sold from the booking screen. The people who buy one appear here.',
        'columns' => [
            'client' => 'Client',
            'membership' => 'Membership',
            'status' => 'Status',
            'started' => 'Started',
            'next_billing' => 'Next billing',
            'credits' => 'Credits left',
        ],
        'no_billing' => '—',
        'credits_none' => 'None left',
    ],

    /* Uploading the picture on a membership. Beside the field, never in a
       dialog: a person can act on "use a JPG" and cannot act on an alert
       they have already dismissed. */
    'images' => [
        'uploading' => 'Uploading…',
        'failed' => 'That picture could not be uploaded.',
        'too_large' => 'That picture is too large. The limit is 5 MB.',
        'wrong_type' => 'Use a JPG, PNG or WebP.',
    ],

    /* What a member's own membership can be. Stored rather than derived —
       cancelling and pausing are events with a date attached — except that a
       scheduled one becomes active when its day arrives. */
    'member_statuses' => [
        'scheduled' => 'Scheduled',
        'active' => 'Active',
        'paused' => 'Paused',
        'cancelled' => 'Cancelled',
        'ended' => 'Ended',
    ],

    /* Selling one, and the reasons a sale is refused. Each names the thing
       that would make it work rather than saying only that it did not. */
    'sale' => [
        'not_on_sale' => 'That membership is not on sale. It is a draft, or it has been taken off sale.',
        'channel_closed' => 'Memberships cannot be sold at the desk. Open the in-store channel in App Settings.',
        'no_future_start' => 'This business does not allow a membership to be dated forward.',
        'method_not_repeatable' => 'A recurring membership needs a payment method that can be charged again. Cash can buy a package; it cannot renew a subscription.',
    ],

    /* One client's memberships, on their profile: what they hold, what they
       have left, and what has happened to it. */
    'member' => [
        'title' => 'Membership',
        'none' => 'Not a member',
        'none_hint' => 'Memberships are sold from the booking screen — choose Membership under Select type.',
        'off' => 'Membership is switched off',
        'off_hint' => 'Nothing new can be sold. What this client already holds is kept exactly as it is.',
        'active' => 'Active membership',
        'past' => 'Past memberships',
        'started' => 'Started',
        'ends' => 'Ends',
        'ended' => 'Ended',
        'next_billing' => 'Next billing',
        'no_billing' => 'No further billing',
        'price' => 'Price',
        'sold_at' => 'Sold at',
        'credits' => 'Available benefits',
        'credits_none' => 'No credits available.',
        'credit_count' => ':count available',
        'credit_expires' => 'Expires :date',
        'credits_paused' => 'Credits cannot be spent while the membership is paused.',
        'history_title' => 'History',
        'history_none' => 'Nothing has happened yet.',

        /* One line per kind of thing that can happen. Written as what
           happened rather than as a category, because this is read in a list
           beside a date. */
        'history' => [
            'started' => 'Membership started',
            'payment' => 'Payment taken',
            'redeemed' => 'Credit used',
            'released' => 'Credit returned',
            'paused' => 'Membership paused',
            'cancelled' => 'Membership cancelled',
            'ends_on' => 'Ends :date',
        ],

        /* Acting on one. Each refusal names the thing that would make it
           work rather than saying only that it did not. */
        'cancel' => 'Cancel membership',
        'cancel_confirm' => 'Cancel this membership? Nothing already paid for is taken away.',
        'pause' => 'Pause membership',
        'pause_confirm' => 'Pause this membership? Billing stops and credits cannot be spent until it restarts.',
        'resume' => 'Restart membership',
        'cancelled_now' => 'Membership cancelled.',
        'cancelled_on' => 'Membership will end on :date. It stays usable until then.',
        'paused' => 'Membership paused.',
        'resumed' => 'Membership restarted.',
        'already_cancelled' => 'That membership has already been cancelled.',
        'cannot_pause' => 'Only a running membership can be paused.',
        'not_paused' => 'That membership is not paused.',
        'in_commitment' => 'This membership cannot be cancelled until :date — the client agreed to a minimum commitment.',
        'cancel_not_allowed' => 'This business does not allow memberships to be cancelled here.',
        'notice_note' => 'Cancelling now takes effect on :date.',
    ],

    /* Membership Activated — what the client now holds. */
    'sold' => [
        'title' => 'Membership activated',
        'scheduled_title' => 'Membership scheduled',
        'intro' => ':client now holds this membership.',
        'scheduled_intro' => ':client holds this membership from :date. Its credits do not exist until then.',
        'client' => 'Client',
        'membership' => 'Membership',
        'type' => 'Type',
        'status' => 'Status',
        'start' => 'Start date',
        'paid' => 'Amount paid',
        'billing' => 'Billing',
        'one_off' => 'One-off purchase',
        'next_billing' => 'Next billing date',
        'benefits' => 'Included benefits',
        'credits' => 'Available credits',
        'credits_available' => ':count available',
        'credits_none' => 'Nothing available yet.',
        'view_client' => 'View client',
        'view_membership' => 'View membership',
        'another' => 'Create another purchase',
    ],

    /* ----------------------------------------------------- create step 1 -- */

    'choose' => [
        'title' => 'Create Membership',
        'question' => 'What type of Membership would you like to create?',
        'intro' => 'This is the one answer you cannot change afterwards — the two are different products, not two settings of one.',
        'example' => 'For example',
        'recurring_example' => '$79 a month, including one massage every month.',
        'package_example' => 'Four massages for $150, bought once.',
        'select' => 'Continue',
    ],

    /* ------------------------------------------------------------- form -- */

    'form' => [
        'create_title' => 'Create Membership',
        'edit_title' => 'Edit Membership',

        'step' => 'Step :number',

        'basics' => 'Basic information',
        'basics_hint' => 'What this is called and how it is described to the client.',
        'name' => 'Membership name',
        'name_placeholder' => 'Monthly Massage Membership',
        'description' => 'Description',
        'description_hint' => 'One or two lines. Clients read this on the card in the booking screen.',
        'image' => 'Membership image',
        'image_upload' => 'Upload image',
        'image_replace' => 'Replace image',
        'image_hint' => 'JPG, PNG or WebP, up to 5 MB. Shown on the card in the booking screen.',
        'internal_code' => 'Internal code',
        'internal_code_hint' => 'Your own reference. Letters, digits and hyphens.',

        'pricing' => 'Pricing',
        'pricing_hint_recurring' => 'What the client pays each cycle, and how often.',
        'pricing_hint_package' => 'What the client pays once, and what it would have cost separately.',
        'price' => 'Membership price',
        'package_price' => 'Package price',
        'billing_frequency' => 'Billing frequency',
        'joining_fee' => 'Joining fee',
        'setup_fee' => 'Setup fee',
        'trial_days' => 'Trial period',
        'trial_days_hint' => 'Days before the first charge. Leave empty for no trial.',
        'extras_hint' => 'Charged once, at the start, on top of the first cycle. Leave empty for none.',
        'regular_value' => 'Regular value',
        'regular_value_hint' => 'What the included services would cost bought separately. Leave empty to make no saving claim.',
        'currency_optional_hint' => 'Optional. Leave blank if this membership is not sold in this currency.',
        'value_below_price' => 'The regular value has to be at least the package price, or there is no saving to show.',
        'saving_preview' => 'Customer saving: :amount',

        'services' => 'Benefits & services',
        'services_hint_recurring' => 'Benefits and credits are issued again after each successful billing cycle.',
        'services_hint_package' => 'What the package contains in total. When they are used, it is finished.',
        'add_service' => '+ Add service',
        'service' => 'Service',
        'service_search' => 'Search services…',
        'quantity' => 'Quantity',
        'credits_column' => 'Credits',
        'count_placeholder' => 'e.g. 4',
        'services_intro' => 'What the member gets with this membership.',
        'credits_recurring' => 'Credits are issued again after every successful billing cycle — never while a payment has failed, or the membership is paused or cancelled.',
        'credits_package' => 'Credits are issued once when the package is purchased, and are not replenished.',
        'remove' => 'Remove',
        'duplicate_service' => 'Each service can only be listed once. Change its quantity instead of adding it twice.',
        'no_services' => 'Add at least one service — a membership that includes nothing is a subscription to nothing.',

        'benefits' => 'Additional member benefits',
        'benefits_hint' => 'What a member gets off everything else they buy. Optional.',
        'discount_type' => 'Discount',
        'discount_none' => 'No discount',
        'percent' => 'Percentage off',
        'fixed' => 'Fixed amount off',
        'discount_value' => 'Amount',
        'discount_percentage' => 'Percentage',
        'priority_booking' => 'Priority booking',
        'priority_booking_hint' => 'Members are flagged at the desk so they can be fitted in first.',

        'credits' => 'Credit rules',
        'credits_hint' => 'Leave these as they are to follow the business settings. Change one only where this membership is genuinely different.',
        'follow_business' => 'Follow business setting (:value)',
        'yes' => 'Yes',
        'no' => 'No',
        'credit_expiry' => 'Credit expiration',
        'rollover' => 'Unused credits roll over',
        'maximum_rollover' => 'Maximum rollover credits',
        'substitution' => 'Credits can pay for another service',

        'availability' => 'Availability',
        'availability_hint' => 'Where this membership can be sold and used, and through which channels.',
        'locations' => 'Locations',
        'all_locations' => 'All locations',
        'selected_locations' => 'Selected locations',
        'channels' => 'Purchase channels',
        'channels_hint' => 'Your business channels are the ceiling: a membership offered online in a business that does not sell online is not for sale.',
        'channel_closed' => 'Closed in App Settings',

        'review' => 'Review & publish',
        'review_hint' => 'A draft can be edited freely and cannot be sold. Publishing puts it in the booking screen.',
        'summary_includes' => 'Includes',
        'summary_benefit' => 'Member benefit',
        'summary_locations' => 'Locations',
        'summary_sold' => 'Sold',
        'save_draft' => 'Save as draft',
        'publish' => 'Publish membership',
        'save' => 'Save changes',
    ],

    /* ------------------------------------------------------------- show -- */

    'show' => [
        'includes' => 'What it includes',
        'benefits' => 'Member benefits',
        'no_benefits' => 'No additional benefits.',
        'credits' => 'Credit rules',
        'availability' => 'Availability',
        'sold_in_store' => 'At the desk',
        'sold_online' => 'Online',
        'sold_nowhere' => 'Nowhere — every channel is closed.',
        'draft_note' => 'This is a draft. It cannot be sold until it is published.',
        'disabled_note' => 'This membership has been taken off sale. Nobody new can buy it; everybody who already has one keeps it.',
        'publish' => 'Publish',
        'from_business' => 'From business settings',
        'per_cycle' => 'each cycle',
        'in_total' => 'in total',
        'created_by' => 'Created by',
        'joining_fee' => 'Joining fee',
        'setup_fee' => 'Setup fee',
        'trial' => 'Trial',
        'trial_days' => ':days days',
    ],

    /* ------------------------------------------------------- app settings -- */

    'settings' => [
        'title' => 'Membership',
        'intro' => 'Whether you sell memberships, where they can be bought, what happens to credits nobody used and what cancelling one means.',

        'enable' => 'Enable Membership',
        'enable_hint' => 'Adds Membership under Clients, and makes it a purchase type in the booking screen.',
        'disabled_note' => 'Membership is off. Nothing new can be sold — every existing member keeps their plan, their credits and their history exactly as they are.',

        'saved' => 'Membership settings saved.',

        /* --- selling --- */
        'selling' => 'Selling',
        'selling_hint' => 'Where a membership can be bought and who can complete the sale.',
        'channels' => 'Purchase channels',
        'channels_hint' => 'Turn every channel off and nothing can be sold, whether or not membership is enabled.',
        'coming_soon' => 'Coming soon',
        'allow_staff_to_sell' => 'Allow staff to sell Membership',
        'allow_staff_to_sell_hint' => 'Anybody with the permission can complete a membership sale at the desk. Off, and only a manager can.',

        /* --- starting --- */
        'starting' => 'Start date',
        'starting_hint' => 'When a membership bought today begins.',
        'allow_start_date_selection' => 'Allow Membership start date selection',
        'allow_start_date_selection_hint' => 'Staff can date a membership forward. Off, and every membership starts the day it is sold.',
        'default_activation' => 'Default activation',
        'default_activation_hint' => 'What the purchase screen offers before anybody chooses.',

        /* --- credits --- */
        'credits' => 'Credits',
        'credits_hint' => 'What happens to an included service the client did not use.',
        'credits_enabled' => 'Include services in memberships',
        'credits_enabled_hint' => 'Memberships grant services the client can draw down. Off, and a membership is its discount and its perks — nothing to redeem.',
        'allow_rollover' => 'Allow unused credits to roll over',
        'allow_rollover_hint' => 'Unused credits carry into the next cycle. Off, and each cycle starts fresh.',
        'maximum_rollover' => 'Maximum rollover credits',
        'maximum_rollover_hint' => 'The most a client can bank. Leave empty for no limit.',
        'credit_expiry' => 'Credit expiration',
        'credit_expiry_hint' => 'How long a credit lives once it has been granted.',
        'allow_credits_across_locations' => 'Allow Membership credits across multiple locations',
        'allow_credits_across_locations_hint' => 'A credit earned at one branch can be spent at another.',
        'allow_service_substitution' => 'Allow Membership credit service substitution',
        'allow_service_substitution_hint' => 'A credit for one service can pay for another of the same value.',

        /* --- cancellation --- */
        'cancellation' => 'Cancellation',
        'cancellation_hint' => 'What a member can do when they want to stop, and what happens when they do.',
        'allow_cancellation' => 'Allow Membership cancellation',
        'allow_cancellation_hint' => 'Off, and a membership can only be ended by someone with the permission to do it.',
        'allow_pause' => 'Allow Membership pause',
        'allow_pause_hint' => 'A member can hold their membership without losing it. Billing stops while it is paused.',
        'minimum_commitment_months' => 'Minimum commitment period',
        'minimum_commitment_months_hint' => 'Months a member cannot cancel inside. Zero for none.',
        'cancellation_notice_days' => 'Cancellation notice period',
        'cancellation_notice_days_hint' => 'Days of warning a member must give. Zero for none.',
        'cancellation_effective' => 'Cancel',
        'cancellation_effective_hint' => 'When a cancellation takes effect.',
        'months' => 'months',
        'days' => 'days',

        /* --- what StyleDesk decides --- */
        'rules' => 'How StyleDesk handles memberships',
        'rules_hint' => 'What the app decides, rather than what you do.',
        'rule_recurring' => 'A recurring membership needs a payment method it can charge again',
        'rule_recurring_body' => 'A card on file, or another method that can be billed without the client present. Cash can buy a package; it cannot renew a subscription.',
        'rule_package' => 'A package is finished when its services are used',
        'rule_package_body' => 'No renewal, no next billing date. What the client bought is the list of services, and it is done when the list is empty.',
        'rule_scheduled' => 'A membership dated forward is Scheduled, not Active',
        'rule_scheduled_body' => 'Its credits do not exist and its benefits do not apply until the start date arrives.',
        'rule_off' => 'Switching Membership off stops sales and nothing else',
        'rule_off_body' => 'Existing members keep their plans, their credits and their history. Renewals already agreed are honoured.',
    ],
];
