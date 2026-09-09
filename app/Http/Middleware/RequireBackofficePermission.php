<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * What this administrator may reach.
 *
 * Named on the route — `backoffice.can:clients.manage` — rather than checked
 * inside the controller, so a route that nobody thought about permission for
 * is a route that refuses. The alternative default, allowing anything not
 * explicitly denied, is how a console grows a door nobody knew was open.
 *
 * 403 rather than a redirect: the reader is signed in and this is a real
 * refusal, not a question about who they are.
 */
class RequireBackofficePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(Auth::guard('backoffice')->user()?->can($permission), 403);

        return $next($request);
    }
}
