<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tips
|--------------------------------------------------------------------------
|
| Two questions, and they are different ones: whether this business takes
| tips, and which of its services are tipped. A salon that tips its stylists
| does not tip the shelf a bottle of shampoo came off.
|
*/

return [

    'title' => 'Tips',
    'intro' => 'Whether clients are asked to tip, what they are offered, and which services it applies to.',
    'back' => 'App settings',

    'enable' => 'Take tips',
    'enable_hint' => 'Adds a tip step at the till. Switching it off hides the question and keeps everything below exactly as you left it.',
    'disabled_note' => 'Tips are switched off. What is set here is kept, and comes back the moment you switch them on again.',

    /* Two tabs once tipping is on: what to suggest, and which services it
       applies to. */
    'tabs' => [
        'suggest' => 'What to suggest',
        'services' => 'Services',
    ],

    'defaults' => 'What to suggest',
    'defaults_hint' => 'What a service uses when it has not said otherwise.',
    'tip_type' => 'Tip type',
    'types' => [
        'percent' => 'Percentage',
        'fixed' => 'Fixed amount',
    ],
    'default_tip' => 'Default tip',
    'default_tip_hint' => 'A percentage of the tipped part of the bill, or a flat sum.',

    'percentages' => 'Offered at the till',
    'percentages_hint' => 'Up to six. The client also gets a box to type their own.',

    'require_selection' => 'Ask the client to choose',
    'require_selection_hint' => 'They have to answer before the payment goes through. Answering with “No tip” still counts as answering — it is a prompt, not a charge.',
    'allow_no_tip' => 'Offer “No tip”',
    'allow_no_tip_hint' => 'Recommended. Without it the client has no way to decline.',

    /* ------------------------------------------------------------ services */

    'services' => 'Services',
    'services_hint' => 'Which services are tipped, and what each one suggests. Blank follows the setting above.',
    'columns' => [
        'service' => 'Service',
        'category' => 'Category',
        'price' => 'Price',
        'tips' => 'Tips',
        'default' => 'Default tip',
        'type' => 'Type',
        'required' => 'Must choose',
        'no_tip' => 'Allows “No tip”',
        'status' => 'Status',
    ],
    'follows_default' => 'Follows the default',
    'accepted' => 'Accepted',
    'not_accepted' => 'Not tipped',
    'no_services' => 'No active services to configure yet.',

    'edit_service' => 'Tip settings',
    'edit_service_for' => 'Tips — :name',
    'service_card_hint' => 'How tipping works for this service. It starts on your App settings → Tips defaults and can be changed here without touching them.',
    'accepts' => 'Accept tips for this service',
    'accepts_hint' => 'Off for anything nobody worked on — retail, for instance.',

    /* ------------------------------------------------------------- the till */

    'panel' => [
        'title' => 'Tip',
        'eligible' => 'Tipped on',
        'custom' => 'Custom',
        'none' => 'No tip',
        'selected' => 'Tip',
        'required' => 'Choose a tip option before taking payment.',
        'not_eligible' => 'Nothing on this booking is tipped.',
    ],

    'saved' => 'Tip settings saved.',
    'enabled' => 'Tips are on.',
    'disabled' => 'Tips are off. Your settings are kept.',
    'service_saved' => 'Service tip settings saved.',
];
