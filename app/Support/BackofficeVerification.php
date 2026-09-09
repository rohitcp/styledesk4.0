<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which address has answered the code, and for how long.
 *
 * In the session rather than a cookie: this is a step in one sign-in, and it
 * should end when that sign-in does. A cookie would leave the second factor
 * answered on a shared machine after somebody walked away.
 *
 * The address is kept beside the timestamp, so a verified session cannot be
 * used to sign in as somebody else — the login screen checks that the address
 * being submitted is the one that was verified.
 */
class BackofficeVerification
{
    public const EMAIL = 'backoffice_verified_email';

    public const AT = 'backoffice_verified_at';

    /** The address being verified, before the code has been answered. */
    public const PENDING = 'backoffice_pending_email';

    public static function beginFor(Request $request, string $email): void
    {
        $request->session()->put(self::PENDING, mb_strtolower(trim($email)));
    }

    public static function pendingEmail(Request $request): ?string
    {
        $email = $request->session()->get(self::PENDING);

        return is_string($email) && $email !== '' ? $email : null;
    }

    public static function passed(Request $request, string $email): void
    {
        $request->session()->forget(self::PENDING);
        $request->session()->put(self::EMAIL, mb_strtolower(trim($email)));
        $request->session()->put(self::AT, time());
    }

    /**
     * The address that has answered the code, if it still counts.
     *
     * The window is checked here rather than only when it is set: a session
     * left open overnight has to ask again, and a check that only happens at
     * the moment of verifying is one that never happens again.
     */
    public static function verifiedEmail(Request $request): ?string
    {
        $email = $request->session()->get(self::EMAIL);
        $at = (int) $request->session()->get(self::AT, 0);
        $window = (int) config('backoffice.verification.window_minutes') * 60;

        if (! is_string($email) || $email === '' || $at === 0 || (time() - $at) > $window) {
            self::clear($request);

            return null;
        }

        return $email;
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget([self::EMAIL, self::AT, self::PENDING]);
    }
}
