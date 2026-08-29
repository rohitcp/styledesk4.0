<?php

declare(strict_types=1);

/*
| What the password broker says.
|
| Overrides the framework's own file so the wording is StyleDesk's. Only the
| copy changes — which line is chosen, and when, is still the broker's
| decision.
*/

return [
    'reset' => 'Your password has been reset. You can sign in with it now.',

    /*
    | Said whether or not the address is on an account — see the note in
    | 'user' below — so it must not promise that an email is on its way to
    | this particular person. "If we have an account" does the work.
    */
    'sent' => 'If we have an account for that address, we have emailed a password reset link to it. Follow the instructions in that email to set a new password.',

    'throttled' => 'You have asked for a reset link recently. Please wait a moment before asking for another.',

    'token' => 'That password reset link is invalid or has already been used. Request a new one below.',

    /*
    | Never reached: FortifyServiceProvider answers an unknown address with
    | the 'sent' line instead, because a form that says "we can't find that
    | user" is a way of asking which addresses hold accounts. Kept because the
    | broker's contract expects the key to exist.
    */
    'user' => 'If we have an account for that address, we have emailed a password reset link to it.',
];
