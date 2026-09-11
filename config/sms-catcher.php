<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMS catcher
|--------------------------------------------------------------------------
|
| The development inbox for text messages: Mailpit, for SMS. A notification
| sent on the `sms` channel is written down and shown at the address below
| instead of reaching a phone, which is what anybody working on the booking
| screen wants — a confirmation costs money and lands on a real person.
|
| The package's own defaults are loosened here on purpose. See each note.
|
*/

return [

    /*
    | Never on a live installation.
    |
    | The shipped default turns this on whenever APP_DEBUG is true, which
    | includes a staging box somebody left debugging on. This inbox holds
    | every client's phone number and what was said to them, so it is pinned
    | to the environments where the data is fake.
    */
    'enabled' => env('SMS_CATCHER_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),

    'route' => [
        'prefix' => 'dev/sms',

        /*
        | Behind a login. The shipped default is `web` alone, which leaves the
        | inbox open to anyone who can reach the host — and "it is only local"
        | stops being true the first time somebody runs it on a shared box or
        | over a tunnel.
        */
        'middleware' => ['web', 'auth'],
    ],

    /*
    | A file rather than a table: the inbox is scaffolding, and a migration
    | for scaffolding is a migration every production database also runs.
    | Safe to delete; it comes back on the next message.
    */
    'storage_path' => storage_path('logs/sms-catcher.json'),
];
