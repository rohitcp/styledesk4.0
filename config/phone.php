<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Dialling codes
    |--------------------------------------------------------------------------
    |
    | The same countries the phone field offers, in the same order, because a
    | number the browser let somebody type must be one the server can read
    | back. The browser's copy is the country list in
    | resources/js/prototype/styledesk.js plus the additions in
    | resources/js/phone.js; this is the PHP side of that one list.
    |
    | `digits` is how many digits the national number has once the dialling
    | code is off — taken from the same mask the field formats with, so the
    | two cannot disagree about what a whole number looks like. It is what
    | tells a number somebody finished typing from one they abandoned
    | halfway, which is the difference between a client record worth having
    | and a row nobody can ever ring.
    |
    | Several countries share +1. That is not a problem to solve here: E.164
    | does not distinguish them either, and a number is the same number
    | whichever of them issued it.
    |
    */

    'countries' => [
        'US' => ['dial' => '1', 'digits' => 10],
        'CA' => ['dial' => '1', 'digits' => 10],
        'GB' => ['dial' => '44', 'digits' => 10],
        'MX' => ['dial' => '52', 'digits' => 10],
        'FR' => ['dial' => '33', 'digits' => 9],
        'DE' => ['dial' => '49', 'digits' => 11],
        'ES' => ['dial' => '34', 'digits' => 9],
        'AU' => ['dial' => '61', 'digits' => 9],
        'IE' => ['dial' => '353', 'digits' => 9],
        'NL' => ['dial' => '31', 'digits' => 9],
        'BE' => ['dial' => '32', 'digits' => 9],
        'IT' => ['dial' => '39', 'digits' => 10],
        'PT' => ['dial' => '351', 'digits' => 9],
        'CH' => ['dial' => '41', 'digits' => 9],
        'AT' => ['dial' => '43', 'digits' => 9],
        'SE' => ['dial' => '46', 'digits' => 9],
        'NO' => ['dial' => '47', 'digits' => 8],
        'DK' => ['dial' => '45', 'digits' => 8],
        'FI' => ['dial' => '358', 'digits' => 9],
        'PL' => ['dial' => '48', 'digits' => 9],
        'CZ' => ['dial' => '420', 'digits' => 9],
        'GR' => ['dial' => '30', 'digits' => 10],
        'NZ' => ['dial' => '64', 'digits' => 9],
        'ZA' => ['dial' => '27', 'digits' => 9],
        'AE' => ['dial' => '971', 'digits' => 9],
        'SA' => ['dial' => '966', 'digits' => 9],
        'IN' => ['dial' => '91', 'digits' => 10],
        'CN' => ['dial' => '86', 'digits' => 11],
        'SG' => ['dial' => '65', 'digits' => 8],
        'HK' => ['dial' => '852', 'digits' => 8],
        'MY' => ['dial' => '60', 'digits' => 9],
        'JP' => ['dial' => '81', 'digits' => 10],
        'BR' => ['dial' => '55', 'digits' => 11],
        'AR' => ['dial' => '54', 'digits' => 10],
    ],

    /*
    |--------------------------------------------------------------------------
    | The country a bare number belongs to
    |--------------------------------------------------------------------------
    |
    | Used only when a number arrives with no dialling code and nothing else
    | says where it is from — the tenant's own country answers first. This is
    | the last resort behind that.
    |
    */

    'default_country' => env('PHONE_DEFAULT_COUNTRY', 'US'),

    /*
    |--------------------------------------------------------------------------
    | The outer bounds
    |--------------------------------------------------------------------------
    |
    | E.164 allows fifteen digits including the dialling code, and no real
    | number is shorter than seven. A number outside this is not a number
    | that was typed short — it is one that was mistyped, and storing it
    | gives the desk something that can never be rung.
    |
    */

    'min_digits' => 7,

    'max_digits' => 15,
];
