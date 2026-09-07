<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application languages
|--------------------------------------------------------------------------
|
| The single register of what StyleDesk's interface can be shown in. Adding a
| language is an entry here plus a directory under lang/ — no screen, control
| or query changes, which is the whole point of routing every label through a
| translation key.
|
| `active` is what separates a language we ship from one we have planned.
| Listing the future ones rather than omitting them keeps the roadmap in the
| same place as the implementation, and stops a half-finished French from
| being switched on by accident.
|
| `native` is what the language calls itself. A selector that offers "Spanish"
| to a Spanish speaker is asking them to read English to escape English.
|
*/

return [

    /*
     * What the app falls back to.
     *
     * Every key exists in English, so a missing translation anywhere else has
     * somewhere to land. This is also the language a brand-new business gets.
     */
    'fallback' => 'en',

    'supported' => [
        'en' => ['name' => 'English', 'native' => 'English', 'active' => true],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'active' => true],

        /*
         * Registered and offerable, but not translated: neither has a lang/
         * directory at all, so choosing one gives an English app under a
         * French or German name.
         *
         * `active` marks that rather than hiding it. The selector labels an
         * unfinished language "partly translated" and lets the business
         * decide — withholding the choice entirely is what made French
         * selectable at sign-up and then silently ignored everywhere else.
         */
        'fr' => ['name' => 'French', 'native' => 'Français', 'active' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'active' => false],
        /*
         * The shell is translated — navigation, the shared buttons and
         * validation, the dashboard, the settings directory, the login screen
         * and the language picker. The deeper modules (clients, bookings,
         * staff, resources) have no lang/zh file yet and fall back to English
         * key by key.
         *
         * `active` is false because it is false: 343 of 5,151 keys. It no
         * longer decides whether the language can be chosen — every language
         * here can be — it decides whether the selector calls this one
         * finished. Set it true when lang/zh is complete.
         */
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'active' => false],
    ],
];
