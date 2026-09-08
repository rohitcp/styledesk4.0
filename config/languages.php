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
         * Registered, offerable, and translated as far as the screens somebody
         * uses on their first day: the shared words and validation, the
         * navigation, and the Clients, Services and Staff modules — the "add"
         * forms included. Everything else falls back to English key by key.
         *
         * `active` marks that rather than hiding it. The selector labels an
         * unfinished language "partly translated" and lets the business
         * decide — withholding the choice entirely is what made French
         * selectable at sign-up and then silently ignored everywhere else.
         *
         * The Bookings module is translated too — appointments, leads,
         * payment settings and the reason lists behind them.
         *
         * 2,194 of 5,176 keys each. Set active true when lang/fr and lang/de
         * are complete.
         */
        'fr' => ['name' => 'French', 'native' => 'Français', 'active' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'active' => false],
        /*
         * The shell is translated — navigation, the shared buttons and
         * validation, the dashboard, the settings directory, the login screen
         * and the language picker — and so are the Clients, Services and Staff
         * modules, and the Bookings module with them. The rest (resources,
         * email templates, marketing, the back office) has no lang/zh file yet
         * and falls back to English key by key.
         *
         * `active` is false because it is false: 2,417 of 5,176 keys. It does
         * not decide whether the language can be chosen — every language here
         * can be — it decides whether the selector calls this one finished.
         * Set it true when lang/zh is complete.
         */
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'active' => false],
    ],
];
