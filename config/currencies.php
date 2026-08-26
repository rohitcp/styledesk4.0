<?php

/*
|--------------------------------------------------------------------------
| Currencies and Languages
|--------------------------------------------------------------------------
|
| ISO 4217 currency codes and ISO 639-1 language codes. Display names and
| symbols live here rather than in the database, so relabelling one never
| rewrites tenant rows.
|
*/

return [

    /*
     * code => [name, symbol]
     *
     * Limited to the countries StyleDesk offers, so the dropdown cannot show a
     * currency no business here can select a country for.
     */
    'currencies' => [
        'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => '$'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => '$'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$'],
        'ARS' => ['name' => 'Argentine Peso', 'symbol' => '$'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
        'PLN' => ['name' => 'Polish Złoty', 'symbol' => 'zł'],
        'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => '$'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => '$'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => '$'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => '$'],
    ],

    /*
     * Country => default currency. A suggestion the user may override, which
     * is why it is a default rather than a derived value.
     */
    'country_currencies' => [
        'US' => 'USD', 'CA' => 'CAD', 'MX' => 'MXN', 'BR' => 'BRL', 'AR' => 'ARS',
        'GB' => 'GBP', 'IE' => 'EUR', 'PT' => 'EUR', 'ES' => 'EUR', 'FR' => 'EUR',
        'BE' => 'EUR', 'NL' => 'EUR', 'DE' => 'EUR', 'AT' => 'EUR', 'IT' => 'EUR',
        'GR' => 'EUR', 'FI' => 'EUR', 'CH' => 'CHF', 'DK' => 'DKK', 'NO' => 'NOK',
        'SE' => 'SEK', 'PL' => 'PLN', 'CZ' => 'CZK', 'ZA' => 'ZAR', 'AE' => 'AED',
        'SA' => 'SAR', 'IN' => 'INR', 'SG' => 'SGD', 'HK' => 'HKD', 'MY' => 'MYR',
        'JP' => 'JPY', 'AU' => 'AUD', 'NZ' => 'NZD',
    ],

    /*
     * Languages StyleDesk supports today. English is the fallback, so it is
     * first and is what an unset value resolves to.
     */
    'languages' => [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'ar' => 'Arabic',
    ],

    'default_language' => 'en',

];
