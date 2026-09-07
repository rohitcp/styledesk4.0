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
    /*
     * Every currency StyleDesk can price in.
     *
     * The whole shape lives here — symbol, decimals, symbol position,
     * separators and digit grouping — because the spec is explicit that
     * individual screens must not implement their own currency formatting.
     * App\Support\Money is the only thing that reads these, and every screen
     * goes through it.
     *
     * `symbol` is deliberately disambiguated where a bare glyph would not be
     * enough: seven of these use a dollar sign, so CAD is C$ and AUD is A$.
     * The code is still what identifies an amount — the symbol is only how it
     * is drawn.
     *
     * `grouping` is 'indian' for INR, where 123456.78 is written 1,23,456.78
     * rather than 123,456.78. That is exactly the kind of rule that ends up
     * wrong in five places if each screen formats its own money.
     *
     * `active` separates a currency we support from one we have listed.
     */
    'currencies' => [
        'USD' => [
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'CAD' => [
            'name' => 'Canadian Dollar',
            'symbol' => 'C$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'MXN' => [
            'name' => 'Mexican Peso',
            'symbol' => 'Mex$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'BRL' => [
            'name' => 'Brazilian Real',
            'symbol' => 'R$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => '.',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'ARS' => [
            'name' => 'Argentine Peso',
            'symbol' => 'AR$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => '.',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'GBP' => [
            'name' => 'British Pound',
            'symbol' => '£',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'EUR' => [
            'name' => 'Euro',
            'symbol' => '€',
            'decimals' => 2,
            'position' => 'after',
            'thousands' => '.',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'CHF' => [
            'name' => 'Swiss Franc',
            'symbol' => 'CHF',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => '\'',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'DKK' => [
            'name' => 'Danish Krone',
            'symbol' => 'kr',
            'decimals' => 2,
            'position' => 'after',
            'thousands' => ' ',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'NOK' => [
            'name' => 'Norwegian Krone',
            'symbol' => 'kr',
            'decimals' => 2,
            'position' => 'after',
            'thousands' => ' ',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'SEK' => [
            'name' => 'Swedish Krona',
            'symbol' => 'kr',
            'decimals' => 2,
            'position' => 'after',
            'thousands' => ' ',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'PLN' => [
            'name' => 'Polish Złoty',
            'symbol' => 'zł',
            'decimals' => 2,
            'position' => 'after',
            'thousands' => ' ',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'CZK' => [
            'name' => 'Czech Koruna',
            'symbol' => 'Kč',
            'decimals' => 2,
            'position' => 'after',
            'thousands' => ' ',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'ZAR' => [
            'name' => 'South African Rand',
            'symbol' => 'R',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ' ',
            'decimal' => ',',
            'grouping' => 'western',
            'active' => true,
        ],
        'AED' => [
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'SAR' => [
            'name' => 'Saudi Riyal',
            'symbol' => '﷼',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'INR' => [
            'name' => 'Indian Rupee',
            'symbol' => '₹',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'indian',
            'active' => true,
        ],
        'CNY' => [
            'name' => 'Chinese Yuan',
            'symbol' => '¥',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'SGD' => [
            'name' => 'Singapore Dollar',
            'symbol' => 'S$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'HKD' => [
            'name' => 'Hong Kong Dollar',
            'symbol' => 'HK$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'MYR' => [
            'name' => 'Malaysian Ringgit',
            'symbol' => 'RM',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'JPY' => [
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'decimals' => 0,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'AUD' => [
            'name' => 'Australian Dollar',
            'symbol' => 'A$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
        'NZD' => [
            'name' => 'New Zealand Dollar',
            'symbol' => 'NZ$',
            'decimals' => 2,
            'position' => 'before',
            'thousands' => ',',
            'decimal' => '.',
            'grouping' => 'western',
            'active' => true,
        ],
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
        'SA' => 'SAR', 'IN' => 'INR', 'CN' => 'CNY', 'SG' => 'SGD', 'HK' => 'HKD', 'MY' => 'MYR',
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
        'zh' => 'Chinese',
        'hi' => 'Hindi',
        'ar' => 'Arabic',
    ],

    /*
     * Operating country => the languages a business there can be served in.
     *
     * The language fields on onboarding step 1 are filtered to the union of
     * these for the countries chosen, because the full list is a menu of
     * questions most businesses will never be asked: a salon operating only
     * in Germany has no use for Hindi, and offering it invites a choice that
     * is simply wrong.
     *
     * English is listed wherever it is genuinely a working language rather
     * than everywhere as a safety net — a France-only business gets French,
     * which is the honest answer.
     *
     * Keys are operating countries (config/locations.php), values are keys of
     * the list above. Both are checked by the onboarding tests, so a country
     * added to one list without the other fails rather than silently offering
     * an empty language dropdown.
     */
    'country_languages' => [
        'US' => ['en', 'es'],
        'CA' => ['en', 'fr'],
        'AU' => ['en'],
        'MX' => ['es'],
        'CN' => ['zh'],
        'FR' => ['fr'],
        'DE' => ['de'],
        'IN' => ['en', 'hi'],
    ],

    'default_language' => 'en',

];
