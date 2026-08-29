<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * End a session that has been idle too long.
 *
 * Laravel already expires the session cookie after config('session.lifetime'),
 * so why this exists: the cookie's expiry is enforced by the browser, and a
 * browser is not a thing to trust with when someone is signed out. A stolen
 * cookie, a clock that is wrong, a client that ignores Max-Age — in each case
 * the server should still be the one deciding. This checks the elapsed time on
 * the server against a timestamp the server wrote.
 *
 * It also gives the moment a name. Being bounced to a bare login form with no
 * explanation reads as the product losing your session; being told it timed
 * out reads as the product protecting it.
 */
class EnforceSessionTimeout
{
    /** Where the last request's time is kept. */
    public const KEY = 'last_activity_at';

    /** Flashed so the login screen can say what happened. */
    public const FLAG = 'session_timed_out';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeout = $this->timeoutSeconds();
        $lastActivity = $request->session()->get(self::KEY);

        if ($timeout > 0 && $lastActivity !== null && (now()->getTimestamp() - $lastActivity) > $timeout) {
            return $this->timeOut($request);
        }

        /**
         * Stamped after the check, not before, or every request would reset
         * the clock it is about to read and the timeout would never fire.
         */
        /**
         * now(), not time().
         *
         * The framework's clock is the one that can be moved — by a test, by
         * a scheduled task, by anything that needs to reason about elapsed
         * time. A raw time() call reads the machine instead, so the timeout
         * silently ignored every attempt to exercise it.
         */
        $request->session()->put(self::KEY, now()->getTimestamp());

        return $next($request);
    }

    private function timeOut(Request $request): Response
    {
        Auth::logout();

        /**
         * Invalidated and re-keyed, not merely flushed: the point of ending a
         * session is that the identifier stops being usable, and a flushed
         * session keeps its id.
         */
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash(self::FLAG, true);

        /**
         * A page-load gets the login screen; anything expecting JSON gets 401.
         * Sending a redirect to a fetch() call produces a login page parsed as
         * data, and the caller reports a JSON error rather than an expiry.
         */
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your session timed out. Please sign in again.',
                'redirect' => route('login'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login');
    }

    /**
     * Idle seconds allowed.
     *
     * From session.idle_timeout, not session.lifetime: lifetime is how long
     * the cookie may live and is deliberately the longer of the two, so this
     * check runs while there is still a session to end and a person to tell.
     */
    public static function timeoutSeconds(): int
    {
        return static::timeoutMinutes() * 60;
    }

    /**
     * The business's own limit, or the platform's.
     *
     * A tenant that has never opened the setting follows StyleDesk's default
     * — and keeps following it if that default changes, which is why the
     * column is nullable rather than seeded with 30.
     *
     * Only a value the setting actually offers is honoured: a number written
     * straight into the database cannot widen the window past what an
     * administrator could have chosen.
     */
    public static function timeoutMinutes(): int
    {
        $default = (int) config('session.idle_timeout');

        try {
            $chosen = Auth::user()?->tenant?->session_timeout_minutes;
        } catch (\Throwable $e) {
            /* Tenancy may not have resolved — during an error page, a console
               command, a request that failed before the tenant was known. The
               platform default is the safe answer. */
            return $default;
        }

        $allowed = array_keys(config('business_profile.session_timeouts', []));

        return $chosen !== null && in_array((int) $chosen, $allowed, true)
            ? (int) $chosen
            : $default;
    }

    /**
     * How long before the end the tab should warn.
     *
     * Two minutes, or a fifth of a very short window — a warning longer than
     * the session it warns about would appear the moment the page loaded.
     */
    public static function warningSeconds(): int
    {
        return (int) min(120, max(30, static::timeoutSeconds() / 5));
    }
}
