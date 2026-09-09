<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the sign-in and sign-up screens behind an access code.
 *
 * StyleDesk is not open yet: the people trying it have been given a code, and
 * everybody else should not get as far as a form asking for an email address.
 *
 * Applied to the whole web group rather than to a route list, and it names the
 * routes it gates itself. Fortify registers login and signup from inside the
 * package, so there is no route definition here to hang middleware on without
 * either editing config/fortify.php — which would also gate signing out and
 * changing a password — or redeclaring Fortify's routes.
 *
 * The POST routes are gated as well as the GET ones. Gating only the pages
 * would leave the forms themselves open to anyone who knows the URL, which is
 * the half that actually creates accounts.
 */
class RequireAccessCode
{
    /**
     * Where "this visitor has answered the gate" is kept.
     *
     * A cookie rather than the session, because the two do not end at the same
     * time and only one of them should. Signing out and the idle timeout both
     * invalidate the session by design; keeping the flag there would send a
     * person who has just been timed out to the access-code screen instead of
     * to the login form that explains what happened, and would ask a returning
     * user for the code again every morning. Encrypted and http-only like
     * every other cookie the app sets, so it cannot be forged or read by
     * script.
     */
    public const COOKIE = 'styledesk_access';

    /** The cookie's only meaningful value. */
    public const VALUE = 'granted';

    /** A year — long enough that nobody is asked twice on the same machine. */
    public const LIFETIME_MINUTES = 525600;

    /**
     * The screens that are closed until a code has been entered.
     *
     * Password reset and the email-verification screens are deliberately
     * absent: reaching either of them means an account already exists, so the
     * gate has been passed once already, and locking someone out of their own
     * reset link would be a support ticket, not a protection.
     */
    private const GATED_ROUTES = [
        'login',
        'login.store',
        'register',
        'register.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::GATED_ROUTES) || $this->hasPassed($request)) {
            return $next($request);
        }

        /* Already signed in: the gate is about who may reach the forms, and
           somebody with a session is past that question. Fortify redirects
           them off these routes anyway. */
        if (Auth::check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('An access code is required.')], Response::HTTP_FORBIDDEN);
        }

        /**
         * Where they were going is remembered, so entering the code lands on
         * the sign-up form for someone who asked for sign-up rather than
         * dropping everybody on login.
         *
         * Only for page loads — a remembered POST cannot be replayed, and
         * sending them back to it would be a blank form at best.
         */
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('access-code.show');
    }

    /** Whether this visitor has already answered the gate. */
    public static function hasPassed(Request $request): bool
    {
        return $request->cookie(self::COOKIE) === self::VALUE;
    }
}
