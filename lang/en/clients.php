<?php

declare(strict_types=1);

/*
| App Settings → Clients.
|
| The catalogue's own labels — field names, statuses, panels — live in
| config/clients.php next to the values they describe, and are surfaced through
| this file only where a language needs its own wording.
*/

return [
    'title' => 'Clients',
    'intro' => 'What a client record holds, how it behaves, and what your team sees when they open one.',
    'saved' => 'Client settings updated successfully.',
    'scope_note' => 'These are global settings. Client profiles, history and day-to-day management belong to the Clients module.',
    'pending_note' => 'Settings that describe booking screens and client profiles are stored now and take effect as those screens arrive.',

    'cards' => [
        'records' => 'Client records',
        'records_hint' => 'Profile fields, what is required, how names are written, and the Client ID.',
        'lists' => 'Preferences & tags',
        'lists_hint' => 'The structured preferences and labels your team can put on a client.',
        'notes' => 'Notes',
        'notes_hint' => 'Client notes, who may write them and where they appear.',
        'booking' => 'Booking behaviour',
        'booking_hint' => 'What the booking screen shows about the client it has selected.',
        'duplicates' => 'Duplicate detection',
        'duplicates_hint' => 'How a possible duplicate is spotted, and what happens when one is.',
        'communication' => 'Communication',
        'communication_hint' => 'The ways a client can be contacted, and what they can consent to.',
        'status' => 'Status & archiving',
        'status_hint' => 'Active, inactive and archived clients, and where each one appears.',
        'privacy' => 'Privacy & consent',
        'privacy_hint' => 'What is recorded when a client gives or withdraws marketing consent.',
    ],

    'defaults' => [
        'title' => 'Defaults for a new client',
        'status' => 'Status',
        'location' => 'Preferred location',
        'staff' => 'Preferred staff member',
        'communication' => 'Communication method',
        'marketing' => 'Marketing consent',
        'none' => 'None',
    ],

    'fields' => [
        'title' => 'Profile fields',
        'hint' => 'Which fields a client record carries, and which of them must be filled in. Drag to reorder.',
        'field' => 'Field',
        'enabled' => 'On',
        'required' => 'Required',
        'locked' => 'Always on',
        'locked_hint' => 'A client record with no name is not a record anyone can use.',
        'required_disabled' => 'A field that is switched off cannot be required.',
        'contact_advice' => 'A mobile number or an email address is what makes a duplicate findable. Requiring at least one is worth considering.',
    ],

    'name_format' => 'How client names are written',
    'name_format_hint' => 'Used everywhere a client is named, so one client cannot read as two people on two screens.',
    'name_preview' => 'For example',

    'client_id' => 'Client ID',
    'client_id_hint' => 'Given automatically, unique to your business, and searchable. It cannot be edited.',
    'client_id_example' => 'For example :example',

    'preferences' => [
        'title' => 'Client preferences',
        'enabled' => 'Enable client preferences',
        'multiple' => 'Allow more than one preference per client',
        'add' => 'Add preference',
        'placeholder' => 'Sensitive scalp',
        'empty' => 'No preferences yet.',
        'deactivate_note' => 'Deactivating keeps it on the clients who already have it; it just stops being offered.',
    ],

    'tags' => [
        'title' => 'Client tags',
        'enabled' => 'Enable client tags',
        'add' => 'Add tag',
        'placeholder' => 'VIP',
        'colour' => 'Colour',
        'empty' => 'No tags yet.',
        'multiple_note' => 'A client can carry several tags.',
    ],

    'notes' => [
        'enabled' => 'Enable client notes',
        'multiple' => 'Allow more than one note per client',
        'in_booking' => 'Show notes while booking',
        'important_on_profile' => 'Show important notes at the top of the profile',
        'allow_important' => 'Let staff mark a note as important',
        'staff_can_edit' => 'Let staff edit their own notes',
        'admin_can_delete' => 'Let the owner and admins delete notes',
    ],

    'duplicates' => [
        'rules' => 'Treat a client as a possible duplicate when they share',
        'warning' => 'Warn before creating a possible duplicate',
        'show_matches' => 'Show the matching clients, so the existing one can be opened',
        'no_merge' => 'Records are never merged automatically. Two people can share a phone, and a wrongly merged history is not something a receptionist can unpick.',
    ],

    'search' => 'Find a client by',
    'creation' => 'Clients can be created from',
    'creation_unavailable' => 'Not available yet',
    'booking_panels' => 'Show on the booking screen',
    'history_panels' => 'Show on the client profile',

    'status' => [
        'allow_booking_inactive' => 'Allow inactive clients to be booked',
        'archived_in_search' => 'Include archived clients in booking search',
        'explainer' => 'Active clients appear normally. Inactive clients appear with a badge. Archived clients stay out of booking search but keep their appointments, transactions and history.',
    ],

    'communication' => [
        'methods' => 'Ways a client can be contacted',
        'marketing' => 'Marketing a client can consent to',
        'stored_separately' => 'Each is stored on its own, so a client who agrees to reminders has not agreed to marketing.',
    ],

    'consent' => [
        'record' => 'Record marketing consent',
        'record_date' => 'Record when it was given',
        'record_captured_by' => 'Record who captured it',
        'client_can_opt_out' => 'Let clients opt out',
        'show_on_profile' => 'Show consent status on the client profile',
        'later' => 'Digital consent forms, treatment and photo consent, privacy acknowledgements and electronic signatures arrive in a later release.',
    ],

    'deactivate' => 'Deactivate',
    'activate' => 'Activate',

    'preference_added' => 'Preference added.',
    'preference_activated' => 'Preference activated.',
    'preference_deactivated' => 'Preference deactivated. Clients who already have it keep it.',
    'tag_added' => 'Tag added.',
    'tag_saved' => 'Tag updated.',
    'tag_activated' => 'Tag activated.',
    'tag_deactivated' => 'Tag deactivated. Clients who already have it keep it.',
    'order_saved' => 'Order saved.',

    'validation' => [
        'status_required' => 'Choose a default status.',
        'name_format_required' => 'Choose how client names are written.',
        'location_invalid' => 'Choose one of your own locations.',
        'staff_invalid' => 'Choose one of your own staff members.',
        'preference_exists' => 'You already have a preference with that name.',
        'tag_exists' => 'You already have a tag with that name.',
    ],
];
