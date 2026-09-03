<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nobody works for a business the platform has switched off.
 *
 * This is the enforcement, not the login form's message. Refusing at the form
 * alone would leave three doors open: a session that was already signed in
 * when the business was disabled, a "remember me" cookie, and passkey sign-in,
 * which never reaches the password path at all. Checking on every request
 * closes all three, and closes them on the next click rather than whenever the
 * session happens to expire.
 *
 * Appended to the web group for the same reason SetApplicationLocale is: a
 * screen added next year is covered by existing rather than by somebody
 * remembering to opt it in.
 */
class EnsureBusinessIsActive
{
    /** Flashed so the login screen can say what happened. */
    public const FLAG = 'business_disabled';

    public function handle(Request $request, Closure $next): Response
    {
        /* The console is a different guard against a different table, and a
           StyleDesk administrator belongs to no business. Without this skip,
           the screen that disables a business would sign the administrator
           out of the console for doing it. */
        if ($request->is('backoffice') || $request->is('backoffice/*')) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        /*
         * Signing out is itself allowed.
         *
         * Somebody whose business was disabled mid-session should be able to
         * close their session properly rather than be bounced from the button
         * that would have ended it.
         */
        if ($user === null || $request->routeIs('logout')) {
            return $next($request);
        }

        /* A user with no business at all is not this middleware's problem —
           the onboarding gate answers that one. */
        $tenant = $user->tenant;

        if ($tenant === null || $tenant->isActive()) {
            return $next($request);
        }

        return $this->refuse($request);
    }

    private function refuse(Request $request): Response
    {
        Auth::guard('web')->logout();

        /* Invalidated and re-keyed, not merely flushed: the point of ending a
           session is that the identifier stops being usable, and a flushed
           session keeps its id. */
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash(self::FLAG, true);

        /* A page-load gets the login screen; anything expecting JSON gets 401.
           Sending a redirect to a fetch() call produces a login page parsed as
           data, and the caller reports a JSON error rather than a refusal. */
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('auth.business_disabled'),
                'redirect' => route('login'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login');
    }
}
