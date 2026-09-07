<?php

/*
|--------------------------------------------------------------------------
| Location Reference Data
|--------------------------------------------------------------------------
|
| Used by onboarding step 2. Kept in config rather than in the view so the same
| lists back both the form and its validation, and adding a region is one line
| in one place.
|
| Coverage is deliberate rather than exhaustive. A country listed here gets a
| region dropdown; a country not listed gets a free-text field, which is the
| honest outcome for places that have no meaningful subdivision (Singapore,
| Hong Kong) and better than showing a half-remembered list for the rest.
|
*/

return [

    /*
     * The countries most businesses choose, lifted to the top of the list.
     *
     * A dropdown of thirty-three in alphabetical order puts Argentina first
     * and the United States last, which is the wrong way round for almost
     * everyone who opens it. The rest keep their alphabetical order below.
     *
     * Codes only: the names come from the list underneath, so a country
     * cannot be promoted here and spelled differently there.
     */
    'countries_first' => ['US', 'GB', 'CA', 'AU', 'ES', 'MX'],

    /*
     * The countries a business may operate in — the markets StyleDesk sells
     * into today.
     *
     * Deliberately narrower than 'countries' below, and the two answer
     * different questions. This one is "where can a business run?", which is
     * a commercial decision; that one is "what addresses can we record?",
     * which is a data question, and a salon in Austin may well have a client
     * who lives in Tokyo. Narrowing both together would make that client
     * unrecordable to solve a problem nobody has.
     *
     * Codes only: the names come from 'countries', so a market cannot be
     * listed here and spelled differently there. Every code must exist there
     * too — a market whose name we do not know renders as its own code.
     *
     * Use App\Support\LocationOptions::operatingCountries() to render one.
     */
    'operating_countries' => ['US', 'CA', 'AU', 'MX', 'CN', 'FR', 'DE', 'IN'],

    /*
     * The countries a business can be based in.
     *
     * Matches the set the phone widget offers, so the two controls cannot
     * disagree about which countries exist.
     *
     * Alphabetical, and read in that order everywhere a country is validated.
     * Use App\Support\LocationOptions::countries() to render one, which
     * applies the promotion above.
     */
    'countries' => [
        'AR' => 'Argentina', 'AU' => 'Australia', 'AT' => 'Austria', 'BE' => 'Belgium',
        'BR' => 'Brazil', 'CA' => 'Canada', 'CN' => 'China', 'CZ' => 'Czechia',
        'DK' => 'Denmark', 'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany',
        'GR' => 'Greece',
        'HK' => 'Hong Kong', 'IN' => 'India', 'IE' => 'Ireland', 'IT' => 'Italy',
        'JP' => 'Japan', 'MY' => 'Malaysia', 'MX' => 'Mexico', 'NL' => 'Netherlands',
        'NZ' => 'New Zealand', 'NO' => 'Norway', 'PL' => 'Poland', 'PT' => 'Portugal',
        'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'ZA' => 'South Africa',
        'ES' => 'Spain', 'SE' => 'Sweden', 'CH' => 'Switzerland',
        'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
    ],

    /*
     * Country => region code => region name.
     *
     * Codes follow ISO 3166-2 where the country has them in common use, and
     * are stored rather than the display name so renaming a region later does
     * not orphan existing rows.
     */
    'regions' => [

        'US' => [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
            'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
            'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
            'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
            'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
            'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
            'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
            'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
            'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
            'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
            'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        ],

        'CA' => [
            'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba',
            'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia',
            'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario',
            'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon',
        ],

        'GB' => [
            'ENG' => 'England', 'SCT' => 'Scotland', 'WLS' => 'Wales', 'NIR' => 'Northern Ireland',
        ],

        'AU' => [
            'ACT' => 'Australian Capital Territory', 'NSW' => 'New South Wales',
            'NT' => 'Northern Territory', 'QLD' => 'Queensland', 'SA' => 'South Australia',
            'TAS' => 'Tasmania', 'VIC' => 'Victoria', 'WA' => 'Western Australia',
        ],

        'NZ' => [
            'AUK' => 'Auckland', 'BOP' => 'Bay of Plenty', 'CAN' => 'Canterbury',
            'GIS' => 'Gisborne', 'HKB' => "Hawke's Bay", 'MBH' => 'Marlborough',
            'MWT' => 'Manawatū-Whanganui', 'NSN' => 'Nelson', 'NTL' => 'Northland',
            'OTA' => 'Otago', 'STL' => 'Southland', 'TAS' => 'Tasman',
            'TKI' => 'Taranaki', 'WGN' => 'Wellington', 'WKO' => 'Waikato', 'WTC' => 'West Coast',
        ],

        'IE' => [
            'CW' => 'Carlow', 'CN' => 'Cavan', 'CE' => 'Clare', 'CO' => 'Cork',
            'DL' => 'Donegal', 'D' => 'Dublin', 'G' => 'Galway', 'KY' => 'Kerry',
            'KE' => 'Kildare', 'KK' => 'Kilkenny', 'LS' => 'Laois', 'LM' => 'Leitrim',
            'LK' => 'Limerick', 'LD' => 'Longford', 'LH' => 'Louth', 'MO' => 'Mayo',
            'MH' => 'Meath', 'MN' => 'Monaghan', 'OY' => 'Offaly', 'RN' => 'Roscommon',
            'SO' => 'Sligo', 'TA' => 'Tipperary', 'WD' => 'Waterford', 'WH' => 'Westmeath',
            'WX' => 'Wexford', 'WW' => 'Wicklow',
        ],

        'IN' => [
            'AN' => 'Andaman and Nicobar Islands', 'AP' => 'Andhra Pradesh',
            'AR' => 'Arunachal Pradesh', 'AS' => 'Assam', 'BR' => 'Bihar', 'CH' => 'Chandigarh',
            'CT' => 'Chhattisgarh', 'DH' => 'Dadra and Nagar Haveli and Daman and Diu',
            'DL' => 'Delhi', 'GA' => 'Goa', 'GJ' => 'Gujarat', 'HR' => 'Haryana',
            'HP' => 'Himachal Pradesh', 'JK' => 'Jammu and Kashmir', 'JH' => 'Jharkhand',
            'KA' => 'Karnataka', 'KL' => 'Kerala', 'LA' => 'Ladakh', 'LD' => 'Lakshadweep',
            'MP' => 'Madhya Pradesh', 'MH' => 'Maharashtra', 'MN' => 'Manipur',
            'ML' => 'Meghalaya', 'MZ' => 'Mizoram', 'NL' => 'Nagaland', 'OR' => 'Odisha',
            'PY' => 'Puducherry', 'PB' => 'Punjab', 'RJ' => 'Rajasthan', 'SK' => 'Sikkim',
            'TN' => 'Tamil Nadu', 'TG' => 'Telangana', 'TR' => 'Tripura',
            'UP' => 'Uttar Pradesh', 'UK' => 'Uttarakhand', 'WB' => 'West Bengal',
        ],

        'ZA' => [
            'EC' => 'Eastern Cape', 'FS' => 'Free State', 'GP' => 'Gauteng',
            'KZN' => 'KwaZulu-Natal', 'LP' => 'Limpopo', 'MP' => 'Mpumalanga',
            'NC' => 'Northern Cape', 'NW' => 'North West', 'WC' => 'Western Cape',
        ],

        'AE' => [
            'AZ' => 'Abu Dhabi', 'AJ' => 'Ajman', 'DU' => 'Dubai', 'FU' => 'Fujairah',
            'RK' => 'Ras Al Khaimah', 'SH' => 'Sharjah', 'UQ' => 'Umm Al Quwain',
        ],

        'DE' => [
            'BW' => 'Baden-Württemberg', 'BY' => 'Bavaria', 'BE' => 'Berlin',
            'BB' => 'Brandenburg', 'HB' => 'Bremen', 'HH' => 'Hamburg', 'HE' => 'Hesse',
            'MV' => 'Mecklenburg-Vorpommern', 'NI' => 'Lower Saxony',
            'NW' => 'North Rhine-Westphalia', 'RP' => 'Rhineland-Palatinate',
            'SL' => 'Saarland', 'SN' => 'Saxony', 'ST' => 'Saxony-Anhalt',
            'SH' => 'Schleswig-Holstein', 'TH' => 'Thuringia',
        ],

        'AT' => [
            'B' => 'Burgenland', 'K' => 'Carinthia', 'NO' => 'Lower Austria',
            'OO' => 'Upper Austria', 'S' => 'Salzburg', 'ST' => 'Styria',
            'T' => 'Tyrol', 'V' => 'Vorarlberg', 'W' => 'Vienna',
        ],

        'BE' => [
            'BRU' => 'Brussels-Capital', 'VLG' => 'Flanders', 'WAL' => 'Wallonia',
        ],

        'NL' => [
            'DR' => 'Drenthe', 'FL' => 'Flevoland', 'FR' => 'Friesland', 'GE' => 'Gelderland',
            'GR' => 'Groningen', 'LI' => 'Limburg', 'NB' => 'North Brabant',
            'NH' => 'North Holland', 'OV' => 'Overijssel', 'UT' => 'Utrecht',
            'ZE' => 'Zeeland', 'ZH' => 'South Holland',
        ],

        'ES' => [
            'AN' => 'Andalusia', 'AR' => 'Aragon', 'AS' => 'Asturias', 'IB' => 'Balearic Islands',
            'PV' => 'Basque Country', 'CN' => 'Canary Islands', 'CB' => 'Cantabria',
            'CL' => 'Castile and León', 'CM' => 'Castile-La Mancha', 'CT' => 'Catalonia',
            'EX' => 'Extremadura', 'GA' => 'Galicia', 'RI' => 'La Rioja', 'MD' => 'Madrid',
            'MC' => 'Murcia', 'NC' => 'Navarre', 'VC' => 'Valencia',
        ],

        'IT' => [
            'ABR' => 'Abruzzo', 'BAS' => 'Basilicata', 'CAL' => 'Calabria', 'CAM' => 'Campania',
            'EMR' => 'Emilia-Romagna', 'FVG' => 'Friuli-Venezia Giulia', 'LAZ' => 'Lazio',
            'LIG' => 'Liguria', 'LOM' => 'Lombardy', 'MAR' => 'Marche', 'MOL' => 'Molise',
            'PIE' => 'Piedmont', 'PUG' => 'Apulia', 'SAR' => 'Sardinia', 'SIC' => 'Sicily',
            'TOS' => 'Tuscany', 'TAA' => 'Trentino-Alto Adige', 'UMB' => 'Umbria',
            'VDA' => 'Aosta Valley', 'VEN' => 'Veneto',
        ],

        'MX' => [
            'AGU' => 'Aguascalientes', 'BCN' => 'Baja California', 'BCS' => 'Baja California Sur',
            'CAM' => 'Campeche', 'CHP' => 'Chiapas', 'CHH' => 'Chihuahua', 'CMX' => 'Mexico City',
            'COA' => 'Coahuila', 'COL' => 'Colima', 'DUR' => 'Durango', 'GUA' => 'Guanajuato',
            'GRO' => 'Guerrero', 'HID' => 'Hidalgo', 'JAL' => 'Jalisco', 'MEX' => 'México',
            'MIC' => 'Michoacán', 'MOR' => 'Morelos', 'NAY' => 'Nayarit', 'NLE' => 'Nuevo León',
            'OAX' => 'Oaxaca', 'PUE' => 'Puebla', 'QUE' => 'Querétaro', 'ROO' => 'Quintana Roo',
            'SLP' => 'San Luis Potosí', 'SIN' => 'Sinaloa', 'SON' => 'Sonora', 'TAB' => 'Tabasco',
            'TAM' => 'Tamaulipas', 'TLA' => 'Tlaxcala', 'VER' => 'Veracruz', 'YUC' => 'Yucatán',
            'ZAC' => 'Zacatecas',
        ],

        'BR' => [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia',
            'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
            'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ],

    ],

    /*
     * Region => timezone, for countries that span more than one.
     *
     * Approximate by design: several regions straddle a boundary, so this is a
     * sensible default the user can override, not an authoritative lookup.
     * Countries absent here are covered by 'country_timezones' below.
     */
    'region_timezones' => [

        'US' => [
            'AL' => 'America/Chicago',     'AK' => 'America/Anchorage',   'AZ' => 'America/Phoenix',
            'AR' => 'America/Chicago',     'CA' => 'America/Los_Angeles', 'CO' => 'America/Denver',
            'CT' => 'America/New_York',    'DE' => 'America/New_York',    'DC' => 'America/New_York',
            'FL' => 'America/New_York',    'GA' => 'America/New_York',    'HI' => 'Pacific/Honolulu',
            'ID' => 'America/Denver',      'IL' => 'America/Chicago',     'IN' => 'America/New_York',
            'IA' => 'America/Chicago',     'KS' => 'America/Chicago',     'KY' => 'America/New_York',
            'LA' => 'America/Chicago',     'ME' => 'America/New_York',    'MD' => 'America/New_York',
            'MA' => 'America/New_York',    'MI' => 'America/New_York',    'MN' => 'America/Chicago',
            'MS' => 'America/Chicago',     'MO' => 'America/Chicago',     'MT' => 'America/Denver',
            'NE' => 'America/Chicago',     'NV' => 'America/Los_Angeles', 'NH' => 'America/New_York',
            'NJ' => 'America/New_York',    'NM' => 'America/Denver',      'NY' => 'America/New_York',
            'NC' => 'America/New_York',    'ND' => 'America/Chicago',     'OH' => 'America/New_York',
            'OK' => 'America/Chicago',     'OR' => 'America/Los_Angeles', 'PA' => 'America/New_York',
            'RI' => 'America/New_York',    'SC' => 'America/New_York',    'SD' => 'America/Chicago',
            'TN' => 'America/Chicago',     'TX' => 'America/Chicago',     'UT' => 'America/Denver',
            'VT' => 'America/New_York',    'VA' => 'America/New_York',    'WA' => 'America/Los_Angeles',
            'WV' => 'America/New_York',    'WI' => 'America/Chicago',     'WY' => 'America/Denver',
        ],

        'CA' => [
            'AB' => 'America/Edmonton', 'BC' => 'America/Vancouver', 'MB' => 'America/Winnipeg',
            'NB' => 'America/Halifax', 'NL' => 'America/St_Johns', 'NS' => 'America/Halifax',
            'NT' => 'America/Edmonton', 'NU' => 'America/Toronto', 'ON' => 'America/Toronto',
            'PE' => 'America/Halifax', 'QC' => 'America/Toronto', 'SK' => 'America/Regina',
            'YT' => 'America/Vancouver',
        ],

        'AU' => [
            'ACT' => 'Australia/Sydney', 'NSW' => 'Australia/Sydney',
            'NT' => 'Australia/Darwin', 'QLD' => 'Australia/Brisbane',
            'SA' => 'Australia/Adelaide', 'TAS' => 'Australia/Hobart',
            'VIC' => 'Australia/Melbourne', 'WA' => 'Australia/Perth',
        ],

        'BR' => [
            'AC' => 'America/Rio_Branco', 'AM' => 'America/Manaus', 'RR' => 'America/Boa_Vista',
            'RO' => 'America/Porto_Velho', 'MT' => 'America/Cuiaba', 'MS' => 'America/Campo_Grande',
        ],

        'MX' => [
            'BCN' => 'America/Tijuana', 'BCS' => 'America/Mazatlan', 'SIN' => 'America/Mazatlan',
            'SON' => 'America/Hermosillo', 'CHH' => 'America/Chihuahua', 'NAY' => 'America/Mazatlan',
            'ROO' => 'America/Cancun',
        ],

    ],

    /*
     * Timezones offered, with a human label.
     *
     * A curated list rather than DateTimeZone::listIdentifiers(), which
     * returns well over four hundred entries — most meaningless to a salon
     * owner, and unreadable even inside a searchable combo. The offset shown
     * beside each is computed at render time so it follows daylight saving
     * instead of going stale.
     *
     * Every zone referenced by region_timezones and country_timezones must
     * appear here, or an inferred value would not be selectable.
     */
    'timezones' => [
        'America/Anchorage' => 'Alaska — Anchorage',
        'America/Vancouver' => 'Pacific — Vancouver',
        'America/Los_Angeles' => 'Pacific — Los Angeles',
        'America/Tijuana' => 'Pacific — Tijuana',
        'America/Phoenix' => 'Arizona — Phoenix',
        'America/Hermosillo' => 'Hermosillo',
        'America/Edmonton' => 'Mountain — Edmonton',
        'America/Denver' => 'Mountain — Denver',
        'America/Mazatlan' => 'Mazatlán',
        'America/Chihuahua' => 'Chihuahua',
        'America/Regina' => 'Saskatchewan — Regina',
        'America/Winnipeg' => 'Central — Winnipeg',
        'America/Chicago' => 'Central — Chicago',
        'America/Mexico_City' => 'Mexico City',
        'America/Cancun' => 'Cancún',
        'America/Toronto' => 'Eastern — Toronto',
        'America/New_York' => 'Eastern — New York',
        'America/Halifax' => 'Atlantic — Halifax',
        'America/St_Johns' => 'Newfoundland — St. John’s',
        'America/Boa_Vista' => 'Boa Vista',
        'America/Campo_Grande' => 'Campo Grande',
        'America/Cuiaba' => 'Cuiabá',
        'America/Manaus' => 'Manaus',
        'America/Porto_Velho' => 'Porto Velho',
        'America/Rio_Branco' => 'Rio Branco',
        'America/Sao_Paulo' => 'São Paulo',
        'America/Argentina/Buenos_Aires' => 'Buenos Aires',
        'Pacific/Honolulu' => 'Hawaii — Honolulu',
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
        'Asia/Shanghai' => 'China — Shanghai',
        'Asia/Singapore' => 'Singapore',
        'Asia/Hong_Kong' => 'Hong Kong',
        'Asia/Kuala_Lumpur' => 'Kuala Lumpur',
        'Asia/Tokyo' => 'Tokyo',
        'Australia/Perth' => 'Perth',
        'Australia/Darwin' => 'Darwin',
        'Australia/Adelaide' => 'Adelaide',
        'Australia/Brisbane' => 'Brisbane',
        'Australia/Sydney' => 'Sydney',
        'Australia/Melbourne' => 'Melbourne',
        'Australia/Hobart' => 'Hobart',
        'Pacific/Auckland' => 'Auckland',
    ],

    /*
     * The zone to fall back to for a country, used when it has no region-level
     * mapping or the region chosen is not in one.
     */
    'country_timezones' => [
        'US' => 'America/New_York',   'CA' => 'America/Toronto',   'MX' => 'America/Mexico_City',
        'BR' => 'America/Sao_Paulo',  'AR' => 'America/Argentina/Buenos_Aires',
        'GB' => 'Europe/London',      'IE' => 'Europe/Dublin',     'PT' => 'Europe/Lisbon',
        'ES' => 'Europe/Madrid',      'FR' => 'Europe/Paris',      'BE' => 'Europe/Brussels',
        'NL' => 'Europe/Amsterdam',   'DE' => 'Europe/Berlin',     'CH' => 'Europe/Zurich',
        'AT' => 'Europe/Vienna',      'IT' => 'Europe/Rome',       'DK' => 'Europe/Copenhagen',
        'NO' => 'Europe/Oslo',        'SE' => 'Europe/Stockholm',  'FI' => 'Europe/Helsinki',
        'PL' => 'Europe/Warsaw',      'CZ' => 'Europe/Prague',     'GR' => 'Europe/Athens',
        'ZA' => 'Africa/Johannesburg', 'AE' => 'Asia/Dubai',        'SA' => 'Asia/Riyadh',
        'CN' => 'Asia/Shanghai',      'IN' => 'Asia/Kolkata',      'SG' => 'Asia/Singapore',
        'HK' => 'Asia/Hong_Kong',
        'MY' => 'Asia/Kuala_Lumpur',  'JP' => 'Asia/Tokyo',        'AU' => 'Australia/Sydney',
        'NZ' => 'Pacific/Auckland',
    ],

    /*
     * What kind of place a branch is.
     *
     * Not the same question as the tenant's business type: a spa group can
     * have one site that is only a clinic, and the booking page eventually
     * needs to say which. `other` exists so the list never forces a wrong
     * answer.
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

    /*
     * Whether a branch is trading.
     *
     * Inactive is the retirement path for a location that has history: §12
     * requires bookings, transactions and staff assignments to survive it,
     * which deleting the row cannot do.
     */
    'statuses' => [
        'active' => ['label' => 'Active', 'class' => 'styledesk_badge--active'],
        'inactive' => ['label' => 'Inactive', 'class' => 'styledesk_badge--soon'],
    ],

    /*
     * Weekdays, keyed by the integer stored in location_hours.day_of_week.
     *
     * 0 = Sunday, matching Carbon and the column onboarding already writes.
     * The display order is a separate question, answered by the business's
     * first_day_of_week setting, not by this list.
     */
    'weekdays' => [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ],

    /*
     * What kind of exception a date carries.
     *
     * The spec's seven kinds, plus whether each one is normally a closure.
     * `closes` is the default the form starts on, not a rule: a business that
     * stays open on a public holiday with shorter hours is ordinary, and the
     * form lets them say so.
     */
    'closure_types' => [
        'public_holiday' => ['label' => 'Public holiday', 'closes' => true],
        'closure' => ['label' => 'Location closure', 'closes' => true],
        'special_hours' => ['label' => 'Special opening hours', 'closes' => false],
        'training' => ['label' => 'Staff training day', 'closes' => true],
        'maintenance' => ['label' => 'Maintenance closure', 'closes' => true],
        'private_event' => ['label' => 'Private event', 'closes' => true],
        'emergency' => ['label' => 'Emergency closure', 'closes' => true],
    ],

];
