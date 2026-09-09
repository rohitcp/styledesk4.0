<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The "Verify Email" button in the email.
 *
 * Registered by this application rather than left to Fortify's own route so
 * that two things in the specification can happen, neither of which the
 * framework's version does:
 *
 *   - A signed-out visitor is signed in. The link is opened in whichever
 *     browser the reader's mail client hands it to, which is routinely not
 *     the one they signed up in. Fortify's route sits behind `auth` and shows
 *     them a login form; the URL is signed, single-address and short-lived,
 *     which is enough to establish who they are.
 *   - An expired or tampered link explains itself. The `signed` middleware
 *     answers 403, so the signature is checked here instead and a failure
 *     lands back on the verification screen with a way to get a fresh email.
 */
class VerifyEmailLinkController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        /**
         * The hash is checked before the signature is trusted to have named
         * the right person: it ties the link to the address it was sent to,
         * so a link stays dead once the address changes.
         */
        if ($user === null || ! hash_equals($hash, sha1((string) $user->getEmailForVerification()))) {
            return $this->failed($request, 'That verification link is not valid. We can send you a new one.');
        }

        if (! $request->hasValidSignature()) {
            return $this->failed($request, 'That verification link has expired. Request a new one below.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $user->clearEmailVerificationCode();

            event(new Verified($user));
        }

        /**
         * Signed in only if nobody is. Logging in over a session that already
         * belongs to somebody else would switch accounts under them because
         * they opened a link — the second account's owner is told to sign in
         * instead.
         */
        if (Auth::guest()) {
            Auth::login($user);
            $request->session()->regenerate();
        } elseif (! Auth::user()->is($user)) {
            return redirect()->route('login')
                ->with('status', 'That address is verified. Sign in to continue.');
        }

        /* The dashboard is the address, not the destination: a user who has
           not finished setup is moved on to the step they left off at by
           EnsureOnboardingIsComplete, which is the one place that knows which
           step that is. */
        return redirect()->route('dashboard');
    }

    /**
     * A dead link is not an error page.
     *
     * Someone signed in is returned to their own verification screen where the
     * resend button is; someone signed out has nothing to resend from until
     * they log in, so they are told that there.
     */
    private function failed(Request $request, string $message): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('verification.notice')->withErrors(['link' => $message]);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
