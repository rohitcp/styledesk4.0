<?php

declare(strict_types=1);

/*
| The Business settings module: the read-only screen and its edit form.
|
| Field labels are shared between the two screens deliberately. "Primary
| email" naming one thing on the view page and another on the form is the
| drift that a single key exists to prevent.
*/

return [
    'title' => 'Business',
    'intro' => 'Business name, type, contact details and operating configuration.',
    'edit' => 'Edit business',
    'edit_title' => 'Edit business',
    'edit_intro' => 'Update your business information and operating details. Locations, hours, currencies and booking rules have their own settings pages.',
    'saved' => 'Business settings updated successfully.',
    'save_failed' => "We couldn't save your changes right now. Please try again.",
    'correct_fields' => 'Please correct the highlighted fields and try again.',

    'cards' => [
        'information' => 'Business information',
        'contact' => 'Contact information',
        'address' => 'Business address',
        'address_hint' => 'Your primary address. :count locations in total.',
        'regional' => 'Regional settings',
        'regional_hint' => 'Configured in their own modules; shown here for context.',
        'security' => 'Security',
        'defaults' => 'Business defaults',
        'defaults_hint' => 'Starting points for new bookings and services.',
        'presence' => 'Business presence',
        'presence_hint' => 'Where clients find you outside StyleDesk.',
        'advanced' => 'Advanced information',
    ],

    'fields' => [
        'name' => 'Business name',
        'legal_name' => 'Legal business name',
        'business_type' => 'Business type',
        'category' => 'Category / specialisation',
        'description' => 'Description',
        'logo' => 'Business logo',
        'status' => 'Status',

        'business_email' => 'Primary email',
        'business_phone' => 'Primary phone',
        'support_email' => 'Support email',
        'booking_email' => 'Booking contact email',
        'website' => 'Website',

        'address_line1' => 'Address line 1',
        'address_line2' => 'Address line 2',
        'city' => 'City',
        'state' => 'State / province',
        'postal_code' => 'ZIP / postal code',
        'country' => 'Country',
        'address' => 'Address',

        'primary_language' => 'Primary language',
        'secondary_languages' => 'Secondary languages',
        'primary_currency' => 'Primary currency',
        'secondary_currencies' => 'Secondary currencies',
        'timezone' => 'Time zone',
        'date_format' => 'Date format',
        'time_format' => 'Time format',
        'first_day_of_week' => 'First day of week',

        'default_location' => 'Default location',
        'default_booking_duration' => 'Default booking duration',
        'default_appointment_interval' => 'Default appointment interval',
        'default_tax_behavior' => 'Default tax behaviour',
        'session_timeout' => 'Session timeout',
        'default_staff_assignment' => 'Default staff assignment',
        'allow_online_booking' => 'Allow online booking',
        'guest_booking' => 'Guest booking enabled',

        'business_id' => 'Business ID',
        'booking_address' => 'Booking address',

        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'google_business' => 'Google Business Profile',
    ],

    'manage' => 'Manage',
    'manage_branding' => 'Branding →',
    'manage_locations' => 'Manage locations →',
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',

    /*
     * Validation messages.
     *
     * Written as instructions rather than as descriptions of a rule. "The
     * business email field must be a valid email address" names the
     * validator; "Enter a valid email address" names what to do, and it is
     * read inches from the box it is about.
     */
    'validation' => [
        'name_required' => 'Business name is required.',
        'email_required' => 'Primary email is required.',
        'email_invalid' => 'Enter a valid email address.',
        'url_invalid' => 'Enter a valid website URL, including https://',
        'instagram_invalid' => 'Enter a valid Instagram URL, including https://',
        'facebook_invalid' => 'Enter a valid Facebook URL, including https://',
        'tiktok_invalid' => 'Enter a valid TikTok URL, including https://',
        'google_invalid' => 'Enter a valid Google Business URL, including https://',
        'status_required' => 'Choose whether the business is active.',
        'location_invalid' => 'Choose one of your own locations.',
    ],

    /*
     * The values inside the dropdowns, not just their labels.
     *
     * §"This approach should also be used for dropdown values" — a form whose
     * labels are Spanish and whose options are English is the same
     * half-translated screen, moved one level in.
     *
     * Date and time *format* names stay as they are: "DD/MM/YYYY" is a
     * pattern, not a sentence, and translating the letters would describe a
     * format nobody uses.
     */
    'first_day_of_week' => [
        '0' => 'Sunday',
        '1' => 'Monday',
        '6' => 'Saturday',
    ],

    'time_formats' => [
        '12' => '12-hour (1:30 PM)',
        '24' => '24-hour (13:30)',
    ],

    'durations' => [
        'minutes' => '{1} :count minute|[2,*] :count minutes',
        'hour' => '1 hour',
        'hour_thirty' => '1 hour 30 minutes',
        'hours' => ':count hours',
    ],

    'tax_behaviors' => [
        'inclusive' => 'Prices include tax',
        'exclusive' => 'Tax added at checkout',
        'none' => 'No tax applied',
    ],

    'staff_assignment' => [
        'any' => 'Any available staff member',
        'client-chooses' => 'Client chooses a staff member',
        'manual' => 'Assigned manually by the business',
    ],

    'hints' => [
        'inactive' => 'An inactive business is hidden from public booking.',
        'session_timeout' => 'Automatically sign users out after a period of inactivity.',
        'interval' => 'The grid booking start times snap to.',
        'regional' => 'Languages, currencies and time zone are set in their own modules.',
    ],

    'placeholders' => [
        'legal_name' => 'As registered, if different from the trading name',
        'category' => 'Curly hair specialists',
        'description' => 'A sentence clients will read on your booking page.',
    ],

    'choose' => [
        'date_format' => 'Choose a date format',
        'time_format' => 'Choose a time format',
        'day' => 'Choose a day',
        'duration' => 'Choose a duration',
        'interval' => 'Choose an interval',
        'tax' => 'Choose tax behaviour',
        'session_timeout' => 'Choose a timeout',
        'assignment' => 'Choose an assignment rule',
    ],

    /*
     * The business-type list.
     *
     * StyleDesk's own reference data, keyed by the slug the seeder assigns —
     * not something a business typed, so it is ours to translate. Anything a
     * business wrote itself stays in its own words.
     *
     * The database name is the fallback, so a type added by a later seeder
     * without a translation reads as itself.
     */
    'types' => [
        'hair-salon' => 'Hair Salon',
        'barber-shop' => 'Barber Shop',
        'nail-salon' => 'Nail Salon',
        'spa' => 'Spa',
        'massage' => 'Massage',
        'med-spa' => 'Med Spa',
        'esthetics' => 'Esthetics',
        'eyebrows-lashes' => 'Eyebrows & Lashes',
        'makeup-studio' => 'Makeup Studio',
        'tattoo-studio' => 'Tattoo Studio',
        'wellness' => 'Wellness',
        'fitness' => 'Fitness',
        'other' => 'Other',
    ],

    'session_timeouts' => [
        15 => '15 minutes',
        30 => '30 minutes (recommended)',
        60 => '1 hour',
        120 => '2 hours',
        240 => '4 hours',
        480 => '8 hours',
    ],
];
