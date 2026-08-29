<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Everything the "Verify your email" screen can do.
 *
 * The link in the email is handled elsewhere (VerifyEmailLinkController),
 * because that request arrives from a mail client — possibly in a browser
 * with no session — while everything here is a signed-in person acting on the
 * page in front of them.
 */
class VerificationEmailController extends Controller
{
    /**
     * How long before another email may be asked for.
     *
     * Short enough that someone whose first email is genuinely lost is not
     * kept waiting, long enough that an impatient second and third click do
     * not send three emails — which is what makes a provider mark the
     * account, and what fills a reader's inbox with codes that are all dead
     * except the last.
     */
    private const RESEND_COOLDOWN_SECONDS = 30;

    /** Where the earliest permitted resend time is kept. */
    public const RESEND_KEY = 'verification_resend_available_at';

    /**
     * Send another verification email to the current address.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        if (($remaining = self::resendCooldownRemaining($request)) > 0) {
            /* Refused rather than silently ignored: a button that appears to
               do nothing is read as the product being broken. */
            throw ValidationException::withMessages([
                'resend' => "Please wait {$remaining} seconds before requesting another email.",
            ]);
        }

        $user->sendEmailVerificationNotification();
        $this->startResendCooldown($request);

        return back()->with('status', 'We sent another verification email to '.$user->email.'.');
    }

    /**
     * Verify with the six digits from the email.
     */
    public function code(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        /* The six inputs post as one value. Joining them in the browser keeps
           the server's contract to a single field, so a pasted code and six
           typed digits arrive the same way. */
        $data = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], [
            'code.required' => 'Enter the 6-digit code from your email.',
            'code.digits' => 'The code is 6 digits.',
        ]);

        if (! $user->emailVerificationCodeMatches($data['code'])) {
            throw ValidationException::withMessages([
                /* One message for wrong and for expired, deliberately: telling
                   a guesser which of the two they hit tells them whether the
                   digits were right. The resend button beside it is the way
                   out of both. */
                'code' => 'That code is incorrect or has expired. Request a new one below.',
            ]);
        }

        $user->markEmailAsVerified();
        $user->clearEmailVerificationCode();

        event(new Verified($user));

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Lets someone correct the address they signed up with.
     *
     * Without this, a typo in the email field is a dead end: the verification
     * link goes somewhere the user cannot read, and they cannot sign up again
     * because the address is already taken by the account they are locked out
     * of.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ], [
            'email.unique' => 'An account already exists with this email address.',
        ]);

        // Changing the address invalidates any verification already granted —
        // otherwise a user could verify one address and then swap in another.
        $user->forceFill([
            'email' => $this->normalize($data['email']),
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        /* The cooldown restarts with the new address rather than carrying the
           old one's, so changing the email is never refused for being too
           soon after the email it is replacing. */
        $this->startResendCooldown($request);

        return back()->with('status', 'We sent a verification email to '.$user->email.'.');
    }

    /**
     * Seconds left before another email may be requested, zero if none.
     *
     * Public because the page renders the countdown from it: the button is
     * disabled for exactly as long as the server would refuse it, so the two
     * cannot disagree.
     */
    public static function resendCooldownRemaining(Request $request): int
    {
        $availableAt = $request->session()->get(self::RESEND_KEY);

        if ($availableAt === null) {
            return 0;
        }

        return max(0, (int) $availableAt - now()->getTimestamp());
    }

    private function startResendCooldown(Request $request): void
    {
        $request->session()->put(
            self::RESEND_KEY,
            now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)->getTimestamp()
        );
    }

    /**
     * Lower-case the address so the same inbox cannot register twice under
     * different casing, which the unique index alone would allow.
     */
    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
