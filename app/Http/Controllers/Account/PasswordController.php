<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Changing your own password.
 *
 * Fortify has a route for this already (PUT /user/password) and this is not
 * a second implementation of it: the rules come from the same
 * PasswordValidationRules trait its action uses, so the checklist on the
 * screen, the sign-up form and this all grade a password identically. What
 * this adds is the part a headless endpoint cannot have — a screen that says
 * which rule is unmet, and the choice about other devices.
 */
class PasswordController extends Controller
{
    use PasswordValidationRules;

    public function show(): View
    {
        return view('account.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules(),
            'logout_other_devices' => ['boolean'],
        ]);

        /**
         * Checked by hand rather than with the `current_password` rule so the
         * refusal is keyed to the field it is about. The rule reports against
         * whatever field it is attached to either way, but doing it here
         * keeps the message ours and in the reader's language.
         */
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('account.password.current_wrong'),
            ]);
        }

        /* A "change" that changes nothing is worth refusing: somebody doing
           it has misread the form, and telling them is cheaper than letting
           them believe their password is now something else. */
        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('account.password.same_as_current'),
            ]);
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        /**
         * This session survives; the others are the choice.
         *
         * Being signed out of the tab you just changed your password in reads
         * as the change having failed. Everywhere else is the opposite: a
         * password is changed because somebody thinks it is known, and a
         * session opened with the old one should not outlive it.
         *
         * logoutOtherDevices re-hashes the password into the session, which
         * is why it is called with the new plain password and after the save.
         */
        if ($validated['logout_other_devices'] ?? false) {
            Auth::logoutOtherDevices($validated['password']);
        }

        return redirect()->route('account.password')
            ->with('toast', __('account.password.saved'));
    }
}
