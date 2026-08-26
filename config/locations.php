<?php

/*
|--------------------------------------------------------------------------
| Location Reference Data
|--------------------------------------------------------------------------
|
| Used by onboarding step 2. Kept in config rather than hardcoded in the view
| so the same lists are available to validation, and so adding a region is a
| one-line change in one place.
|
*/

return [

    /*
     * US states mapped to their timezone.
     *
     * The mapping exists to satisfy the spec's "determine timezone from the
     * address where possible" — picking a state proposes a timezone, which the
     * user can still override. Only the US is covered, which is what the
     * prototype does; other countries fall back to choosing a zone directly.
     *
     * Approximate by design: a handful of states straddle two zones, so this
     * is a sensible default rather than an authoritative lookup.
     */
    'us_states' => [
        'AL' => 'America/Chicago',     'AK' => 'America/Anchorage',   'AZ' => 'America/Phoenix',
        'AR' => 'America/Chicago',     'CA' => 'America/Los_Angeles', 'CO' => 'America/Denver',
        'CT' => 'America/New_York',    'DE' => 'America/New_York',    'FL' => 'America/New_York',
        'GA' => 'America/New_York',    'HI' => 'Pacific/Honolulu',    'ID' => 'America/Denver',
        'IL' => 'America/Chicago',     'IN' => 'America/New_York',    'IA' => 'America/Chicago',
        'KS' => 'America/Chicago',     'KY' => 'America/New_York',    'LA' => 'America/Chicago',
        'ME' => 'America/New_York',    'MD' => 'America/New_York',    'MA' => 'America/New_York',
        'MI' => 'America/New_York',    'MN' => 'America/Chicago',     'MS' => 'America/Chicago',
        'MO' => 'America/Chicago',     'MT' => 'America/Denver',      'NE' => 'America/Chicago',
        'NV' => 'America/Los_Angeles', 'NH' => 'America/New_York',    'NJ' => 'America/New_York',
        'NM' => 'America/Denver',      'NY' => 'America/New_York',    'NC' => 'America/New_York',
        'ND' => 'America/Chicago',     'OH' => 'America/New_York',    'OK' => 'America/Chicago',
        'OR' => 'America/Los_Angeles', 'PA' => 'America/New_York',    'RI' => 'America/New_York',
        'SC' => 'America/New_York',    'SD' => 'America/Chicago',     'TN' => 'America/Chicago',
        'TX' => 'America/Chicago',     'UT' => 'America/Denver',      'VT' => 'America/New_York',
        'VA' => 'America/New_York',    'WA' => 'America/Los_Angeles', 'WV' => 'America/New_York',
        'WI' => 'America/Chicago',     'WY' => 'America/Denver',
    ],

    /*
     * Timezones offered, with the labels the prototype uses.
     *
     * A curated list rather than DateTimeZone::listIdentifiers(), which
     * returns well over four hundred entries — most of them meaningless to a
     * salon owner, and unreadable even inside a searchable combo. The offset
     * shown beside each is computed at render time so it follows daylight
     * saving instead of going stale.
     */
    'timezones' => [
        'America/New_York' => 'Eastern — New York',
        'America/Chicago' => 'Central — Chicago',
        'America/Denver' => 'Mountain — Denver',
        'America/Phoenix' => 'Arizona — Phoenix',
        'America/Los_Angeles' => 'Pacific — Los Angeles',
        'America/Anchorage' => 'Alaska — Anchorage',
        'Pacific/Honolulu' => 'Hawaii — Honolulu',
        'America/Toronto' => 'Toronto',
        'America/Vancouver' => 'Vancouver',
        'America/Mexico_City' => 'Mexico City',
        'America/Sao_Paulo' => 'São Paulo',
        'America/Argentina/Buenos_Aires' => 'Buenos Aires',
        'Europe/London' => 'London',
        'Europe/Dublin' => 'Dublin',
        'Europe/Lisbon' => 'Lisbon',
        'Europe/Madrid' => 'Madrid',
        'Europe/Paris' => 'Paris',
        'Europe/Brussels' => 'Brussels',
        'Europe/Amsterdam' => 'Amsterdam',
        'Europe/Berlin' => 'Berlin',
        'Europe/Zurich' => 'Zurich',
        'Europe/Vienna' => 'Vienna',
        'Europe/Rome' => 'Rome',
        'Europe/Copenhagen' => 'Copenhagen',
        'Europe/Oslo' => 'Oslo',
        'Europe/Stockholm' => 'Stockholm',
        'Europe/Helsinki' => 'Helsinki',
        'Europe/Warsaw' => 'Warsaw',
        'Europe/Prague' => 'Prague',
        'Europe/Athens' => 'Athens',
        'Africa/Johannesburg' => 'Johannesburg',
        'Asia/Dubai' => 'Dubai',
        'Asia/Riyadh' => 'Riyadh',
        'Asia/Kolkata' => 'India — Kolkata',
        'Asia/Singapore' => 'Singapore',
        'Asia/Hong_Kong' => 'Hong Kong',
        'Asia/Kuala_Lumpur' => 'Kuala Lumpur',
        'Asia/Tokyo' => 'Tokyo',
        'Australia/Perth' => 'Perth',
        'Australia/Brisbane' => 'Brisbane',
        'Australia/Sydney' => 'Sydney',
        'Pacific/Auckland' => 'Auckland',
    ],

];
