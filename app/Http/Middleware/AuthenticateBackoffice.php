<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BackofficeAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door to the platform console.
 *
 * Three questions, in this order, because each is only worth asking if the one
 * before it passed: is anybody signed in, are they still allowed to be, and
 * have they been away too long.
 *
 * The second is the one that is easy to leave out. An administrator disabled
 * at ten past nine keeps a valid session cookie until it expires, and without
 * this check they keep the console with it — so the status is read from the
 * database on every request rather than trusted from the session.
 */
class AuthenticateBackoffice
{
    /** Where the last request's time is kept, for this guard alone. */
    public const ACTIVITY_KEY = 'backoffice_last_activity_at';

    /** Flashed so the sign-in screen can say what happened. */
    public const TIMED_OUT = 'backoffice_timed_out';

    /** Flashed when the account itself was switched off underneath them. */
    public const DISABLED = 'backoffice_disabled';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('backoffice')->user();

        if (! $admin instanceof BackofficeAdmin) {
            return redirect()->route('backoffice.verify.email');
        }

        /* Read fresh. The session holds who they were when they signed in,
           which is exactly the thing that may have changed. */
        if (! $admin->fresh()?->isActive()) {
            return $this->end($request, self::DISABLED);
        }

        $timeout = (int) config('backoffice.session_timeout_minutes') * 60;
        $last = (int) $request->session()->get(self::ACTIVITY_KEY, 0);

        if ($timeout > 0 && $last > 0 && (time() - $last) > $timeout) {
            return $this->end($request, self::TIMED_OUT);
        }

        $request->session()->put(self::ACTIVITY_KEY, time());

        return $next($request);
    }

    /** Sign them out and send them back to the front door with a reason. */
    private function end(Request $request, string $reason): Response
    {
        Auth::guard('backoffice')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash($reason, true);

        return redirect()->route('backoffice.verify.email');
    }
}
