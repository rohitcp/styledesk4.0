<?php

declare(strict_types=1);

/*
| What the login form says when it refuses.
|
| Overrides the framework's file so the wording is StyleDesk's. 'failed' is
| deliberately the same answer for a wrong password and for an address with no
| account: telling them apart is telling a stranger which addresses hold
| accounts here.
*/

return [
    'failed' => 'Incorrect email or password. Please try again.',

    /* When the attempt broke rather than being refused — see
       App\Http\Middleware\ReportSignInFailures. Deliberately says nothing
       about what broke: the reader cannot act on it, and the log can. */
    'unavailable' => "We couldn't sign you in right now. Please try again.",

    'unverified' => 'Your email address has not been verified. Please verify your email to continue.',
    'password' => 'That password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
];
