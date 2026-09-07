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
         * Planned. Inactive until their lang/ directory is complete: a
         * language offered in the selector and only half translated is a
         * business switching to French and finding half its app still in
         * English, which reads as a fault rather than as a work in progress.
         */
        'fr' => ['name' => 'French', 'native' => 'Français', 'active' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'active' => false],
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'active' => false],
        'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी', 'active' => false],
    ],
];
