<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * App Settings is an administrative module: Owner and Administrator only.
 *
 * Applied as middleware rather than checked in each controller so that the
 * guarantee covers every settings route by construction. The spec is explicit
 * that hiding the nav icon is not the control — the nav is a convenience, and
 * anyone can type the URL.
 */
class EnsureCanManageSettings
{
    /**
     * The permission App Settings requires.
     *
     * A permission rather than a role list: a custom role granted
     * settings.view should reach the module, and the three copies of
     * ['owner', 'administrator'] this replaced were already a set of lists
     * waiting to disagree with each other.
     */
    public const PERMISSION = 'settings.view';

    /** Roles seeded with that permission. Kept for callers that still ask. */
    public const ROLES = ['owner', 'administrator'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->canManageSettings()) {
            return $next($request);
        }

        /**
         * Redirected, not 403'd.
         *
         * A manager who follows a bookmarked settings link has done nothing
         * wrong, and an error page tells them only that they have hit a wall.
         * Sending them to the dashboard with a plain sentence is both kinder
         * and less informative to someone probing for what exists.
         */
        return redirect()
            ->route('dashboard')
            ->with('toast', [
                // Not 'success': being turned away is a notice, and a green
                // tick against it would be the wrong thing to say.
                'type' => 'info',
                'message' => 'App Settings is available to the account owner and administrators.',
            ]);
    }
}
