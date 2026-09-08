<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where an edit screen goes when somebody leaves it.
 *
 * The same form is opened from more than one place — Languages is reached
 * from its own module page and from the Business summary — so a Back that
 * always returns to one of them strands everyone who arrived from the other.
 * The caller's address travels with the link as `?return=`, and Back, Cancel
 * and the redirect after a save all read it.
 *
 * Not `url()->previous()`: that is the referer, which a browser may withhold
 * and which is already gone by the time a rejected form redirects back to
 * itself. The parameter survives both because it is part of the address.
 */
class ReturnTo
{
    /** The query and form field the caller's address travels in. */
    public const KEY = 'return';

    /**
     * The address to leave to, or the module's own page when none was passed.
     */
    public static function resolve(Request $request, string $fallback): string
    {
        $path = self::path($request);

        /*
         * Built from the request's own host rather than url(), which reads
         * the application URL — on a tenant subdomain those are not the same
         * host, and returning somebody to the wrong one signs them out.
         */
        return $path === null ? $fallback : $request->getSchemeAndHttpHost().'/'.$path;
    }

    /**
     * The parameter as it should be carried onward, or null.
     *
     * A form posts this back in a hidden field so the redirect after a save
     * lands where Back would have.
     */
    public static function path(Request $request): ?string
    {
        $value = $request->input(self::KEY);

        if (! is_string($value)) {
            return null;
        }

        /*
         * A path on this application and nothing else.
         *
         * Without this every edit link would be somewhere to send a signed-in
         * user by handing them a URL. "//host" and a backslash are refused
         * outright because a browser reads both as the start of a host, and
         * what is left may only hold the characters a route and its query
         * use — so a scheme, an "@" or a space cannot get through either.
         */
        if (str_starts_with($value, '//') || str_contains($value, '\\')) {
            return null;
        }

        $path = ltrim($value, '/');

        if ($path === '' || ! preg_match('#^[A-Za-z0-9\-._~/]+(\?[A-Za-z0-9\-._~=&%+]*)?$#', $path)) {
            return null;
        }

        return $path;
    }

    /**
     * The parameter naming the page being viewed now.
     *
     * Spread into `route()` by a link that opens a form elsewhere:
     * `route('settings.languages.edit', ReturnTo::from($request))`.
     *
     * @return array{return: string}
     */
    public static function from(Request $request): array
    {
        return [self::KEY => $request->path()];
    }
}
