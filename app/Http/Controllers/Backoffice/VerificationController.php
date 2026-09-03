<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Mail\BackofficeVerificationCodeMail;
use App\Models\BackofficeAdmin;
use App\Models\BackofficeAuditLog;
use App\Models\BackofficeLoginCode;
use App\Support\BackofficeVerification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The two steps in front of the Backoffice password screen.
 *
 * The rule that shapes every method here: **an address that is not an
 * administrator's must be indistinguishable from one that is.** Same screen,
 * same wording, same delay, same redirect. An endpoint that answers "no such
 * administrator" is one an attacker can walk through the alphabet against, and
 * what they get back is a staff list.
 *
 * So the code is only really sent to a real administrator, but every address
 * moves to the code screen, and a wrong code and an unknown address fail with
 * the same message.
 */
class VerificationController extends Controller
{
    /** Step one: who are you. */
    public function email(Request $request): View
    {
        return view('backoffice.auth.email', [
            'email' => old('email', BackofficeVerification::pendingEmail($request) ?? ''),
        ]);
    }

    /**
     * Issue a code, and say nothing about who it went to.
     *
     * Rate limited twice over — by address here, and by IP on the route —
     * because either alone is worked around by varying the other.
     */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($data['email']));

        if (BackofficeLoginCode::tooManyRecentlyFor($email)) {
            /* Named on the field, so it appears where the reader is looking,
               and worded as "too many" rather than "too many for this
               administrator" — which would confirm the address. */
            throw ValidationException::withMessages(['email' => __('backoffice.auth.too_many_codes')]);
        }

        BackofficeVerification::beginFor($request, $email);

        $admin = BackofficeAdmin::forEmail($email);

        /* A row either way. The attempt against an address nobody has is the
           one somebody will want to read about later. */
        [$row, $code] = BackofficeLoginCode::issueFor($email, $request->ip(), $request->userAgent());

        if ($admin?->isActive()) {
            Mail::to($admin->email)->send(new BackofficeVerificationCodeMail($admin, $code));
        }

        BackofficeAuditLog::record(
            action: $admin?->isActive() ? 'auth.code_sent' : 'auth.code_requested_unknown',
            actor: $admin?->isActive() ? $admin : null,
            subject: $row,
            actorEmail: $email,
        );

        return redirect()->route('backoffice.verify.code')
            ->with('status', __('backoffice.auth.code_sent'));
    }

    /** Step two: the code itself. */
    public function code(Request $request): View|RedirectResponse
    {
        $email = BackofficeVerification::pendingEmail($request);

        if ($email === null) {
            return redirect()->route('backoffice.verify.email');
        }

        return view('backoffice.auth.code', ['email' => $email]);
    }

    /**
     * Answer it.
     *
     * A wrong code and an address that never had one fail identically, and
     * both burn an attempt: the code is spent after a handful of guesses
     * whether or not anybody is guessing at a real one.
     */
    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        $email = BackofficeVerification::pendingEmail($request);

        if ($email === null) {
            return redirect()->route('backoffice.verify.email');
        }

        $row = BackofficeLoginCode::liveFor($email);

        if ($row === null || ! $row->matches($data['code'])) {
            BackofficeAuditLog::record(
                action: 'auth.code_failed',
                actor: BackofficeAdmin::forEmail($email),
                actorEmail: $email,
            );

            throw ValidationException::withMessages(['code' => __('backoffice.auth.code_wrong')]);
        }

        BackofficeVerification::passed($request, $email);

        BackofficeAuditLog::record(
            action: 'auth.code_verified',
            actor: BackofficeAdmin::forEmail($email),
            actorEmail: $email,
        );

        return redirect()->route('backoffice.login');
    }

    /** Another one, on the same terms as the first. */
    public function resend(Request $request): RedirectResponse
    {
        $email = BackofficeVerification::pendingEmail($request);

        if ($email === null) {
            return redirect()->route('backoffice.verify.email');
        }

        return $this->send($request->merge(['email' => $email]));
    }
}
