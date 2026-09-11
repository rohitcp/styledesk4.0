<?php

declare(strict_types=1);

/*
| The Locations module: the card grid, the view screen and the add/edit form.
|
| Field labels are shared across all three deliberately. "Location code"
| naming one thing on a card and another on the form is the drift a single key
| exists to prevent.
*/

return [
    'title' => 'Locations',
    'intro' => 'Open a location for its manager, hours, services, staff and booking settings.',
    'summary' => '{1} :count active location|[2,*] :count active locations',
    'summary_inactive' => ':count inactive',

    'add' => 'Add location',
    'add_first' => 'Add your first location',
    'add_title' => 'Add location',
    'add_intro' => "The branch's address, contact details, manager and opening hours. Services, staff assignment and booking rules are configured once the location exists.",
    'edit' => 'Edit location',
    'edit_title' => 'Edit location',
    'view' => 'View location',
    'deactivate' => 'Deactivate location',
    'activate' => 'Activate location',

    'created' => 'Location created successfully.',
    'saved' => 'Location saved successfully.',
    'save_failed' => "We couldn't save your changes. Please review the information and try again.",
    'correct_fields' => 'Please correct the highlighted fields and try again.',
    'made_inactive' => ':name is now inactive. It takes no new bookings; its history is unchanged.',
    'made_active' => ':name is active again.',

    'search_placeholder' => 'Search by name, code, city or address',
    'search_label' => 'Search locations',
    'all_statuses' => 'All statuses',
    'actions_for' => 'Actions for :name',

    'empty_title' => 'No locations yet.',
    'empty_body' => 'Add the branch your business operates from, so staff, services and bookings have somewhere to belong.',
    'no_matches' => 'No locations match your search.',
    'no_matches_hint' => 'Try a different word, or clear the filters.',
    'clear_search' => 'Clear search',

    'inactive_notice' => 'This location is inactive. It takes no new bookings and is hidden from online booking. Its past appointments, transactions and staff history are unchanged.',

    'cards' => [
        'information' => 'Location information',
        'address' => 'Address',
        'contact' => 'Contact details',
        'contact_hint' => 'Used instead of the main business contact details wherever this branch is named.',
        'manager' => 'Location manager',
        'manager_hint' => 'Who is responsible for this branch. Naming someone here does not change what they can do in StyleDesk — that is decided by their role under Roles & permissions.',
        'manager_hint_short' => 'Who is responsible for this branch. This does not change what they can do in StyleDesk.',
        'hours' => 'Business hours',
        'hours_hint' => "Times are in this location's own time zone, :timezone.",
        'elsewhere' => 'Configured elsewhere',
        'elsewhere_hint' => 'These follow the business-wide settings until per-location overrides arrive.',
    ],

    'fields' => [
        'name' => 'Location name',
        'code' => 'Location code',
        'code_hint' => 'A short name that tells this branch apart on reports and receipts.',
        'type' => 'Location type',
        'primary' => 'Primary location',
        'primary_hint' => "The business's main branch. Setting this here removes it from whichever location holds it now.",
        'status' => 'Status',
        'status_hint' => 'An inactive location takes no new bookings and is hidden from online booking. Its history is kept.',

        'address_line1' => 'Address line 1',
        'address_line2' => 'Address line 2',
        'suite' => 'Suite / unit',
        'city' => 'City',
        'state' => 'State / province',
        'postal_code' => 'ZIP / postal code',
        'country' => 'Country',
        'timezone' => 'Time zone',
        'timezone_hint' => 'Opening hours and bookings for this branch are read in this zone.',

        'manager' => 'Location manager',
        'assistants' => 'Assistant managers',
        'assistants_hint' => 'Anyone also chosen as the location manager above is not listed twice.',

        'phone' => 'Main phone number',
        'phone_short' => 'Main phone',
        'phone_secondary' => 'Secondary phone',
        'email' => 'Location email',
        'booking_email' => 'Booking contact email',
        'support_email' => 'Customer service email',
        'website' => 'Website',
        'extension' => 'Internal extension',
        'contact_person' => 'Contact person',

        'contact' => 'Contact',
        'today' => 'Today',
    ],

    'placeholders' => [
        'name' => 'Downtown Salon',
        'code' => 'DT01',
        'type' => 'Not specified',
        'country' => 'Choose a country',
        'state' => 'Choose a state or region',
        'timezone' => 'Choose a time zone',
        'manager' => 'Not assigned',
    ],

    'not_assigned' => 'Not assigned',
    'no_phone' => 'No phone number',
    'closed' => 'Closed',
    'closed_today' => 'Closed today',
    'no_active_staff' => 'No active staff yet.',
    'no_active_staff_link' => 'Add a staff member',
    'no_active_staff_tail' => 'and you can name a manager here.',

    'elsewhere' => [
        'holidays' => 'Holidays & special hours',
        'holidays_value' => 'Follows business hours',
        'staff' => 'Staff assigned',
        'staff_value' => '{1} :count staff member has this as their primary location|[2,*] :count staff members have this as their primary location',
        'services' => 'Services offered',
        'services_value' => 'All services the business offers',
        'resources' => 'Resources & rooms',
        'resources_value' => 'Not configured yet',
        'booking' => 'Booking settings',
        'currency' => 'Currency & language',
        'uses_business' => 'Uses business settings',
        'link_hours' => 'Business hours →',
        'link_staff' => 'Staff →',
        'link_services' => 'Services →',
        'link_business' => 'Business →',
        'link_permissions' => 'Permissions →',
    ],

    'confirm' => [
        'deactivate' => 'Make :name inactive? It stops taking new bookings and is hidden from online booking. Its past appointments, transactions and staff history are kept.',
        'activate' => 'Make :name active again? It can take bookings and will appear in online booking.',
    ],

    'validation' => [
        'name_required' => 'Location name is required.',
        'code_unique' => 'Another location already uses this code.',
        'status_required' => 'Choose whether this location is active.',
        'address_required' => 'Address line 1 is required.',
        'city_required' => 'City is required.',
        'state_required' => 'State or province is required.',
        'postal_required' => 'ZIP or postal code is required.',
        'country_required' => 'Choose a country.',
        'country_in' => 'Choose a country from the list.',
        'timezone_required' => 'Choose a time zone.',
        'timezone_in' => 'Choose a time zone from the list.',
        'phone_required' => 'Main phone number is required.',
        'email_required' => 'Location email is required.',
        'email_invalid' => 'Enter a valid email address.',
        'url_invalid' => 'Enter a valid website URL, including https://',
        'closes_after_opens' => 'Closing time must be after the opening time.',
        'staff_invalid' => 'Choose one of your own staff members.',
    ],

    /*
     * The type and status lists, and the days of the week.
     *
     * Keyed by the value stored in the database, so the dropdown and the
     * validation rule keep reading one list while each language decides what
     * the options are called.
     */
    'types' => [
        'salon' => 'Salon',
        'spa' => 'Spa',
        'barbershop' => 'Barbershop',
        'clinic' => 'Clinic',
        'studio' => 'Studio',
        'mobile' => 'Mobile',
        'other' => 'Other',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
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

    'hours_card' => 'Location hours',
    'hours_card_hint' => 'When this branch is open, in its own time zone. Add a second period to a day that closes in the middle.',

    /*
     * The opening-hours island's own controls.
     *
     * Passed in as props rather than read inside the component: the server
     * knows the reader's language, and a Vue island that shipped its own
     * copy of every string would be a second place to translate.
     */
    'hours_editor' => [
        'copy_monday' => 'Copy Monday to Tue–Fri',
        'open' => 'Open',
        'closed' => 'Closed',
        'closed_all_day' => 'Closed all day',
        'add_period' => 'Add another period',
        'to' => 'to',
        'remove_period' => 'Remove this period from :day',
        'opening_time' => ':day opening time',
        'closing_time' => ':day closing time',
        'copied' => "Monday's hours applied to Tuesday through Friday.",
    ],
];
