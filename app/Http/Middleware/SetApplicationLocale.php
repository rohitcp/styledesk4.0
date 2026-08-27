<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the request into the reader's language before anything renders.
 *
 * Middleware rather than a call in each controller, so a screen added next
 * year is translated by existing in the group rather than by someone
 * remembering. It runs for signed-out requests too, where it settles on the
 * fallback — a login page in English is correct, and a login page that throws
 * because there is no user is not.
 */
class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::forUser($request->user());

        app()->setLocale($locale);

        /**
         * The fallback is set explicitly, not left to config/app.php.
         *
         * Laravel resolves a missing key against `app.fallback_locale`, and
         * the two disagreeing would mean a gap in Spanish falling through to
         * whatever that file happened to say rather than to the language every
         * key is guaranteed to exist in.
         */
        app()->setFallbackLocale(Locale::fallback());

        return $next($request);
    }
}
