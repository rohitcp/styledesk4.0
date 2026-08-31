<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

/**
 * A website address, split between the dropdown and the field beside it.
 *
 * Nobody types "https://" correctly every time — the two commonest mistakes
 * are typing nothing at all and typing "https//" — so the scheme is chosen
 * from a list and only the host is typed. That means the stored value and the
 * typed value are different strings, and something has to join and split
 * them. This is that something, in one place: onboarding and Business
 * Settings ask the same question, and two copies of this logic would answer
 * it differently the moment either was touched.
 *
 * The pattern is deliberately the one in resources/js/website-field.js. A
 * field that accepts what the server refuses is a form that looks fine and
 * then fails on submit, with no indication of which of eight fields was
 * wrong.
 */
class WebsiteAddress
{
    /** A host, optionally followed by a path. Mirrored in website-field.js. */
    private const HOST = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}(\/\S*)?$/i';

    /** The schemes the dropdown offers. */
    public static function schemes(): array
    {
        return config('business_profile.website_schemes');
    }

    /** The stored address: what the dropdown says, then what was typed. */
    public static function join(?string $scheme, ?string $host): ?string
    {
        $host = self::host($scheme, $host);

        return $host === '' ? null : ($scheme ?: 'https://').$host;
    }

    /**
     * The typed half, with a pasted scheme taken out of it.
     *
     * People paste the whole address — "https://www.example.com" — because
     * that is what their browser gave them, and refusing the paste teaches
     * them nothing except that the field is fussy. website-field.js absorbs
     * it in the browser; this is the same absorption for the request, so a
     * paste still works with no JavaScript and an older client posting a full
     * URL is not suddenly refused.
     *
     * The "www." goes only when the chosen scheme already carries one, or
     * "https://www." plus "www.example.com" would be stored with it twice.
     */
    public static function host(?string $scheme, ?string $host): string
    {
        $host = trim((string) $host);
        $host = preg_replace('#^https?://#i', '', $host);

        if ($scheme !== null && str_ends_with(mb_strtolower($scheme), 'www.')) {
            $host = preg_replace('#^www\.#i', '', $host);
        }

        return trim($host);
    }

    /**
     * A stored address, back into the two controls it is edited in.
     *
     * The scheme is matched against the list rather than parsed, so an
     * address saved before the dropdown existed — or one typed by hand into
     * the database — opens on the closest option the form actually offers
     * instead of on a blank.
     *
     * @return array{scheme: string, host: string}
     */
    public static function split(?string $stored): array
    {
        $stored = trim((string) $stored);
        $schemes = self::schemes();

        if ($stored === '') {
            return ['scheme' => $schemes[0] ?? 'https://', 'host' => ''];
        }

        /* Longest first, so "https://www." wins over "https://" — the shorter
           one is a prefix of the longer, and matching it first would leave
           "www." sitting in the host field. */
        $ordered = collect($schemes)->sortByDesc(fn (string $scheme) => mb_strlen($scheme));

        foreach ($ordered as $scheme) {
            if (str_starts_with(mb_strtolower($stored), mb_strtolower($scheme))) {
                return ['scheme' => $scheme, 'host' => mb_substr($stored, mb_strlen($scheme))];
            }
        }

        return ['scheme' => $schemes[0] ?? 'https://', 'host' => preg_replace('#^https?://#i', '', $stored)];
    }

    /**
     * The rule for the typed half.
     *
     * Checked as the whole address rather than as the fragment: the scheme
     * lives in the dropdown, so "hello world" passes a string rule on its own
     * and is then stored as "https://hello world".
     *
     * Both halves earn their place. filter_var accepts "https://hello" with
     * no dot in it, and the pattern alone would accept a string filter_var
     * rejects.
     */
    public static function rule(?string $scheme): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($scheme): void {
            if (blank($value)) {
                return;
            }

            /* Checked after the paste is absorbed, so the reader is held to
               the address they will actually get rather than to the exact
               characters they happened to paste. */
            $host = self::host($scheme, $value);
            $joined = self::join($scheme, $host);

            if (! preg_match(self::HOST, $host) || filter_var($joined, FILTER_VALIDATE_URL) === false) {
                $fail(__('business.validation.url_invalid'));
            }
        };
    }
}
