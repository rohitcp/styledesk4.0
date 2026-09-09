<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BackofficeAdmin;
use App\Models\BackofficeAuditLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Forgotten passwords, on the console's own broker.
 *
 * Its own broker rather than the application's: a reset link issued for a
 * salon account must never be redeemable against the platform console, and
 * two brokers sharing one token table is how that happens.
 *
 * Like the code screen, this says the same thing whatever the address turns
 * out to be. "No administrator with that address" is a staff list, offered one
 * guess at a time.
 */
class PasswordResetController extends Controller
{
    private const BROKER = 'backoffice_admins';

    public function request(): View
    {
        return view('backoffice.auth.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        $email = mb_strtolower(trim($data['email']));

        Password::broker(self::BROKER)->sendResetLink(['email' => $email]);

        BackofficeAuditLog::record(
            action: 'auth.password_reset_requested',
            actor: BackofficeAdmin::forEmail($email),
            actorEmail: $email,
        );

        /* The same answer for every address, always. */
        return back()->with('status', __('backoffice.auth.reset_sent'));
    }

    public function reset(Request $request, string $token): View
    {
        return view('backoffice.auth.reset', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()->symbols()->uncompromised()],
        ]);

        $status = Password::broker(self::BROKER)->reset(
            $data,
            function (BackofficeAdmin $admin, string $password) {
                $admin->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                BackofficeAuditLog::record(action: 'auth.password_reset', actor: $admin, subject: $admin, subjectLabel: $admin->name);

                event(new PasswordReset($admin));
            }
        );

        return $status === Password::PasswordReset
            ? redirect()->route('backoffice.verify.email')->with('status', __('backoffice.auth.reset_done'))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
