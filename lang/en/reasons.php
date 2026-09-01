<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reason codes
|--------------------------------------------------------------------------
|
| Why something happened, chosen from a list rather than typed. The lists
| themselves live in config/reasons.php; this is what they are called.
|
*/

return [

    'title' => 'Reasons',
    'intro' => 'Why things happened, as a list rather than a free-text box — so "why do we lose bookings" is a question the reports can actually answer.',
    'back' => 'All reason lists',

    'types' => [
        'booking-cancellation' => ['label' => 'Booking cancellation', 'intro' => 'Why an appointment was called off.'],
        'booking-reschedule' => ['label' => 'Booking reschedule', 'intro' => 'Why an appointment moved.'],
        'no-show' => ['label' => 'No show', 'intro' => 'Why somebody did not arrive.'],
        'refund' => ['label' => 'Refund', 'intro' => 'Why money went back out.'],
        'payment-adjustment' => ['label' => 'Payment adjustment', 'intro' => 'Why a bill was changed after it was written.'],
        'client-status-change' => ['label' => 'Client status change', 'intro' => 'Why a client moved between active, inactive and the rest.'],
        'staff-schedule-change' => ['label' => 'Staff schedule change', 'intro' => 'Why a rota changed — shifts, hours, leave and cover.'],
        'booking-declined' => ['label' => 'Booking declined', 'intro' => 'Why a requested appointment was turned down.'],
        'service-cancellation' => ['label' => 'Service cancellation', 'intro' => 'Why a service stopped being offered.'],
    ],

    'columns' => [
        'reason' => 'Reason',
        'source' => 'Source',
        'details' => 'Asks why',
        'status' => 'Status',
        'action' => 'Action',
    ],

    'system' => 'StyleDesk',
    'custom' => 'Yours',
    'renamed' => 'Renamed',
    'active' => 'On',
    'inactive' => 'Off',
    'activate' => 'Switch on',
    'deactivate' => 'Switch off',
    'edit' => 'Edit',
    'delete' => 'Delete reason',
    'delete_confirm' => 'Delete “:name”? Anything already recorded under it keeps its reason; nothing new can be filed under it.',
    'system_undeletable' => 'StyleDesk supplies this one. It can be renamed or switched off, but not deleted — the records filed under it still have to say why.',

    'add' => 'Add reason',
    'add_title' => 'Add a reason',
    'edit_title' => 'Edit reason',
    'name' => 'Reason',
    'name_placeholder' => 'Client changed their mind',
    'description' => 'Description',
    'description_hint' => 'Optional. What this one means, for whoever picks it next.',
    'requires_details' => 'Ask for an explanation when this is chosen',
    'requires_details_hint' => 'For the reasons that are not an answer on their own — “Other” is the obvious one.',
    'details_label' => 'Additional details',

    'extra_title' => 'Also asked',
    'extra_hint' => 'This list asks a second question alongside the reason. It is part of how :label is recorded and is not configurable.',

    'counts' => ':active of :total switched on',
    'reorder_hint' => 'Drag to reorder',
    'save_order' => 'Save order',
    'order_changed' => 'The order has changed but has not been saved yet.',

    'added' => 'Reason added.',
    'updated' => 'Reason updated.',
    'activated' => 'Reason switched on.',
    'deactivated' => 'Reason switched off. Everything already recorded under it is untouched.',
    'deleted' => 'Reason deleted.',
    'order_saved' => 'Order saved.',
    'none' => 'No reasons in this list yet.',
];
