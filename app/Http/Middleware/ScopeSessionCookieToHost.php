<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the session cookie on a domain the current host actually belongs to.
 *
 * `session.domain` is set to `.styledesk.test` so a tenant's booking subdomain
 * and the central app share one session. That is right whenever the request is
 * for one of those hosts and silently wrong whenever it is not: a browser will
 * not send — or accept — a cookie whose Domain attribute does not cover the
 * host it is talking to, so the session simply never exists. Nothing errors.
 * The reader is bounced to the login screen, logs in, and is bounced again.
 *
 * That bites the moment the app is reached by any other name: a tunnel while
 * testing an OAuth callback that Google will not send to a `.test` domain, an
 * IP address, a staging host that shares this configuration.
 *
 * So the configured domain is used when it covers the host, and dropped when it
 * does not — a host-only cookie, which is what a browser does by default and is
 * always correct for the host being served. Subdomain sharing is untouched for
 * the hosts it was configured for.
 *
 * Runs before StartSession, which reads the config when it builds the cookie.
 */
class ScopeSessionCookieToHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('session.domain');

        if (! is_string($configured) || $configured === '') {
            return $next($request);
        }

        /* A leading dot means "this domain and everything under it", which is
           what has to be compared against — `.styledesk.test` covers
           `styledesk.test` and `acme.styledesk.test`, and nothing else. */
        $domain = ltrim($configured, '.');
        $host = $request->getHost();

        $covers = $host === $domain || str_ends_with($host, '.'.$domain);

        if (! $covers) {
            config(['session.domain' => null]);
        }

        return $next($request);
    }
}
