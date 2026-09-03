<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\BackofficeVerification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The password screen is only reachable once the code has been answered.
 *
 * Without this the second step is decoration: anybody who knows the address of
 * the login page walks straight past the code to a password form. The gate has
 * to be enforced on the screen it protects, not merely offered in front of it.
 */
class RequireBackofficeVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        return BackofficeVerification::verifiedEmail($request) === null
            ? redirect()->route('backoffice.verify.email')
            : $next($request);
    }
}
