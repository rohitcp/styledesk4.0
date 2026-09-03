<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateBackoffice;
use App\Models\BackofficeAdmin;
use App\Models\BackofficeAuditLog;
use App\Support\BackofficeVerification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Step three: the password.
 *
 * Only reachable once the code has been answered — the middleware on the route
 * enforces that, because a gate that is merely offered in front of a screen is
 * one anybody can type past.
 *
 * The verified address is pre-filled and pinned. Pinned matters: without it a
 * session verified for one administrator could be used to sign in as another,
 * which would make the second factor a formality rather than a factor.
 */
class LoginController extends Controller
{
    public function show(Request $request): View
    {
        return view('backoffice.auth.login', [
            'email' => BackofficeVerification::verifiedEmail($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $verified = BackofficeVerification::verifiedEmail($request);
        $email = mb_strtolower(trim($data['email']));

        /* The address that answered the code is the only one this session may
           sign in as. Anything else sends them back to the beginning rather
           than refusing on the password, because the mismatch is about who
           they are and not about what they typed. */
        if ($verified === null || $verified !== $email) {
            BackofficeVerification::clear($request);

            return redirect()->route('backoffice.verify.email');
        }

        $throttleKey = 'backoffice-login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('backoffice.auth.throttled', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $admin = BackofficeAdmin::forEmail($email);

        if ($admin === null || ! $admin->isActive() || ! Auth::guard('backoffice')->attempt(
            ['email' => $email, 'password' => $data['password']],
            (bool) ($data['remember'] ?? false),
        )) {
            RateLimiter::hit($throttleKey, 300);

            BackofficeAuditLog::record(
                action: 'auth.login_failed',
                actor: $admin,
                actorEmail: $email,
            );

            /* One message for a wrong password, a disabled account and an
               address that is not an administrator's. Three messages would be
               three different facts about somebody else's account. */
            throw ValidationException::withMessages(['password' => __('backoffice.auth.refused')]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();
        $request->session()->put(AuthenticateBackoffice::ACTIVITY_KEY, time());

        /* The code has done its work. Left standing it would let a signed-out
           session walk back to the password screen without asking again. */
        BackofficeVerification::clear($request);

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        BackofficeAuditLog::record(action: 'auth.login', actor: $admin);

        return redirect()->intended(route('backoffice.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = Auth::guard('backoffice')->user();

        BackofficeAuditLog::record(action: 'auth.logout', actor: $admin);

        Auth::guard('backoffice')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('backoffice.verify.email');
    }
}
