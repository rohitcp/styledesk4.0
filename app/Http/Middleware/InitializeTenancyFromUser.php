<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initializes tenancy from the authenticated user's membership.
 *
 * StyleDesk identifies tenants two different ways, on purpose:
 *
 *   Public booking  *.styledesk.app  -> InitializeTenancyBySubdomain
 *   Central app      styledesk.app   -> this middleware
 *
 * The central app is a single hostname shared by every business, so the host
 * header carries no tenant. The signed-in user does: `users.tenant_id`.
 * Resolving from the user (rather than from a URL segment the visitor can
 * edit) means a request can never be pointed at a tenant the user is not a
 * member of.
 *
 * Runs after `auth`. A guest reaches this with no user and is passed through
 * untouched — letting the auth middleware issue the redirect keeps the
 * "where do unauthenticated visitors go" decision in one place.
 */
class InitializeTenancyFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // A user mid-signup has no business yet. Onboarding is responsible for
        // creating one; blocking here would make those routes unreachable.
        if ($user->tenant_id === null) {
            return $next($request);
        }

        if (tenancy()->initialized && tenant()?->getTenantKey() === $user->tenant_id) {
            return $next($request);
        }

        tenancy()->initialize($user->tenant);

        return $next($request);
    }
}
