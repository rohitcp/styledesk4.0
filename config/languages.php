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
         * Complete for everything a salon ever sees: 4,995 of 5,187 keys.
         *
         * The 192 that remain are lang/en/backoffice.php, the platform
         * console. It cannot render in anything but English by construction —
         * an administrator authenticates on the `backoffice` guard, and
         * SetApplicationLocale resolves the locale from `$request->user('web')`,
         * which is null there. Translating that file would be strings nobody
         * can display. Fix the guard first if the console is ever to speak
         * another language.
         *
         * `active` stays false for the same reason it always did: it is the
         * selector's word for "finished", and this is not finished until the
         * console is either translated or made translatable.
         */
        'fr' => ['name' => 'French', 'native' => 'Français', 'active' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'active' => false],
        /* Same as French and German: 4,995 of 5,187 keys, everything but
           the platform console. See the note above. */
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'active' => false],
    ],
];
