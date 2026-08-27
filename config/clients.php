<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Client configuration catalogue
|--------------------------------------------------------------------------
|
| What App Settings → Clients can be set to. The values live here so the
| dropdown, the validation rule and the Clients module all read one list —
| a rule built from one source and a control built from another will
| eventually offer something the rule refuses.
|
| Nothing here is a setting. The chosen values live on `client_settings`,
| one row per business.
|
*/

return [

    /*
     * The fields a client profile can carry.
     *
     * `locked` marks a field the business may not switch off or make
     * optional. §2 is explicit that First Name always stays enabled and
     * required — a client record with no name is not a record anyone can use,
     * and the setting screen should not pretend otherwise by offering a
     * toggle that will be refused.
     *
     * `default_enabled` / `default_required` are what a new business starts
     * with, chosen so a salon can create a usable client on day one without
     * visiting this screen at all.
     */
    'fields' => [
        'first_name' => ['label' => 'First name', 'locked' => true, 'default_enabled' => true, 'default_required' => true],
        'last_name' => ['label' => 'Last name', 'default_enabled' => true, 'default_required' => false],
        'mobile' => ['label' => 'Mobile number', 'default_enabled' => true, 'default_required' => false],
        'email' => ['label' => 'Email address', 'default_enabled' => true, 'default_required' => false],
        'date_of_birth' => ['label' => 'Date of birth', 'default_enabled' => true, 'default_required' => false],
        'gender' => ['label' => 'Gender', 'default_enabled' => false, 'default_required' => false],
        'address' => ['label' => 'Address', 'default_enabled' => false, 'default_required' => false],
        'city' => ['label' => 'City', 'default_enabled' => false, 'default_required' => false],
        'state' => ['label' => 'State / province', 'default_enabled' => false, 'default_required' => false],
        'postal_code' => ['label' => 'Postal code', 'default_enabled' => false, 'default_required' => false],
        'country' => ['label' => 'Country', 'default_enabled' => false, 'default_required' => false],
        'preferred_location' => ['label' => 'Preferred location', 'default_enabled' => true, 'default_required' => false],
        'preferred_staff' => ['label' => 'Preferred staff member', 'default_enabled' => true, 'default_required' => false],
        'avatar' => ['label' => 'Profile image', 'default_enabled' => false, 'default_required' => false],
        'notes' => ['label' => 'Notes', 'default_enabled' => true, 'default_required' => false],
    ],

    /*
     * How a client's name is written, everywhere it appears.
     *
     * One setting rather than each screen deciding, for the same reason money
     * has one formatter: a client listed as "Osei, Amara" on one screen and
     * "Amara Osei" on the next reads as two people.
     */
    'name_formats' => [
        'first_last' => 'First name, then last name',
        'last_first' => 'Last name, then first name',
        'first_initial' => 'First name and last initial',
        'preferred_last' => 'Preferred name, then last name',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archived',
    ],

    /*
     * The statuses a new client may be given.
     *
     * Archived is deliberately absent: it is where a record goes at the end
     * of its life, and a business creating a client already archived has
     * either made a mistake or wanted something this setting cannot express.
     */
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

    /*
     * What a business assumes about marketing consent for a new client.
     *
     * `ask` is the default and the honest one: consent is the client's to
     * give, and a business that starts everyone opted in has recorded a
     * consent nobody gave.
     */
    'marketing_defaults' => [
        'ask' => 'Ask the client',
        'in' => 'Opted in',
        'out' => 'Opted out',
    ],

    /*
     * How a possible duplicate is spotted.
     *
     * Matching is a warning, never a merge. §7 is explicit: records are not
     * merged automatically, because two people can share a phone and a
     * wrongly merged history is not something a receptionist can unpick.
     */
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

    /*
     * Where a client record can be created from.
     *
     * `pos` is listed and switched off: the point of sale does not exist yet,
     * and offering it would be a setting with nothing behind it.
     */
    'creation_sources' => [
        'client_list' => ['label' => 'Client list', 'available' => true],
        'booking' => ['label' => 'Booking screen', 'available' => true],
        'calendar' => ['label' => 'Calendar', 'available' => true],
        'walk_in' => ['label' => 'Walk-in booking', 'available' => true],
        'pos' => ['label' => 'Point of sale', 'available' => false],
    ],

    /*
     * What the booking screen shows about the client it has selected.
     */
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

    /*
     * What the client profile shows.
     */
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

    /*
     * Client ID.
     *
     * Fixed for now. §14 lists a customisable prefix and starting number as
     * future configuration, and a screen offering them today would be
     * offering settings the generator does not read.
     */
    'client_id' => [
        'prefix' => 'CL-',
        'padding' => 6,
    ],

    /*
     * The colours a tag can be.
     *
     * A named set rather than a colour picker: tags are read at a glance in a
     * list, and a business free to pick any hex will eventually pick two that
     * are indistinguishable at 12px.
     */
    'tag_colors' => [
        'slate' => '#475569',
        'violet' => '#6d28d9',
        'blue' => '#1d4ed8',
        'teal' => '#0f766e',
        'green' => '#15803d',
        'amber' => '#b45309',
        'rose' => '#be123c',
        'plum' => '#86198f',
    ],

    /*
     * What a new business starts with, so the preference and tag lists are
     * useful before anyone opens this screen. Taken from the spec's examples.
     */
    'seed_preferences' => [
        'Morning appointments', 'Afternoon appointments', 'Evening appointments',
        'Weekend appointments', 'Quiet appointment', 'Fragrance-free products',
        'Sensitive scalp', 'Sensitive skin', 'No heat on roots',
        'Books every 4 weeks', 'Books every 6 weeks',
    ],

    'seed_tags' => [
        'VIP' => 'violet',
        'New client' => 'blue',
        'Regular' => 'teal',
        'Bridal' => 'rose',
        'Corporate' => 'slate',
        'High value' => 'amber',
        'Walk-in' => 'green',
    ],
];
