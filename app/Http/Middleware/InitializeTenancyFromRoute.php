<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initializes tenancy from a {tenant} route parameter bound by slug.
 *
 * This is the third identification path, for the public booking URL the spec
 * specifies: styledesk.app/book/bella-beauty-studio. The subdomain remains a
 * working alias for businesses that want their own address, and both end up
 * in exactly the same tenant context.
 *
 * The parameter is bound to a real Tenant by route model binding, so an
 * unknown slug 404s before this runs — the value is never trusted as an id.
 */
class InitializeTenancyFromRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }
}
