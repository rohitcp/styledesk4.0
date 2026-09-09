<?php

declare(strict_types=1);

/*
| The Clients module's catalogue labels.
|
| Keyed by the value stored in the database, so config/clients.php stays the
| one list the dropdown, the validation rule and the Clients module all read,
| while each language decides what the options are called.
|
| Every English value here is the label config/clients.php already carried.
*/

return [
    'fields' => [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'mobile' => 'Mobile number',
        'email' => 'Email address',
        'date_of_birth' => 'Date of birth',
        'gender' => 'Gender',
        'address' => 'Address',
        'city' => 'City',
        'state' => 'State / province',
        'postal_code' => 'Postal code',
        'country' => 'Country',
        'preferred_location' => 'Preferred location',
        'preferred_staff' => 'Preferred staff member',
        'avatar' => 'Profile image',
        'notes' => 'Notes',
    ],

    'name_formats' => [
        'first_last' => 'First name, then last name',
        'last_first' => 'Last name, then first name',
        'first_initial' => 'First name and last initial',
        'preferred_last' => 'Preferred name, then last name',
    ],

    'phone_types' => [
        'mobile' => 'Mobile',
        'home' => 'Home',
        'work' => 'Work',
        'other' => 'Other',
    ],

    'email_types' => [
        'personal' => 'Personal',
        'work' => 'Work',
        'other' => 'Other',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archived',
    ],

    'default_statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'communication_methods' => [
        'email' => 'Email',
        'sms' => 'SMS',
        'phone' => 'Phone',
        'none' => 'No preference',
    ],

    'marketing_defaults' => [
        'ask' => 'Ask the client',
        'in' => 'Opted in',
        'out' => 'Opted out',
    ],

    'duplicate_rules' => [
        'email' => 'Same email address',
        'mobile' => 'Same mobile number',
        'name_mobile' => 'Same first name, last name and mobile number',
    ],

    'search_fields' => [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'mobile' => 'Mobile number',
        'email' => 'Email address',
        'client_id' => 'Client ID',
    ],

    'creation_sources' => [
        'client_list' => 'Client list',
        'booking' => 'Booking screen',
        'calendar' => 'Calendar',
        'walk_in' => 'Walk-in booking',
        'pos' => 'Point of sale',
    ],

    'booking_panels' => [
        'preferred_staff' => 'Preferred staff member',
        'preferred_location' => 'Preferred location',
        'preferences' => 'Client preferences',
        'notes' => 'Important notes',
        'last_booking' => 'Last booking',
        'recent_visits' => 'Recent visits',
        'recent_staff' => 'Recently seen staff',
        'rating' => 'Average client rating',
        'book_same_again' => 'Book the same again',
    ],

    'history_panels' => [
        'upcoming' => 'Upcoming appointments',
        'previous' => 'Previous appointments',
        'cancelled' => 'Cancelled appointments',
        'no_shows' => 'No-shows',
        'services' => 'Services previously booked',
        'staff' => 'Staff previously booked',
        'locations' => 'Locations visited',
        'notes' => 'Client notes',
        'preferences' => 'Preferences',
        'activity' => 'Booking activity',
    ],

    'tag_colors' => [
        'slate' => 'Slate',
        'violet' => 'Violet',
        'blue' => 'Blue',
        'teal' => 'Teal',
        'green' => 'Green',
        'amber' => 'Amber',
        'rose' => 'Rose',
        'plum' => 'Plum',
    ],
];
