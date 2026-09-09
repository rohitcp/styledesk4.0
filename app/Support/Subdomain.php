<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The business address: the part before the dot.
 *
 * One place decides what a subdomain may contain, because three things have
 * to agree about it — the suggestion the browser makes as the name is typed,
 * the value the server stores, and the availability check between them. Two
 * copies of these rules is a form that says "Available" and then refuses to
 * save.
 *
 * Spaces are removed rather than hyphenated: "Bell Body" becomes "bellbody",
 * which is what a business reads out over the phone. A hyphen is legal in a
 * subdomain but is one more thing to spell.
 */
class Subdomain
{
    /**
     * Subdomains that must never become a tenant's address: they either
     * already resolve to something else or would be mistaken for
     * infrastructure.
     */
    public const RESERVED = [
        'www', 'app', 'admin', 'api', 'mail', 'billing', 'status',
        'support', 'help', 'docs', 'blog', 'staging', 'dev', 'test',
        'assets', 'cdn', 'static', 'book', 'booking', 'my', 'account',
    ];

    /** The longest a subdomain may be. */
    public const MAX_LENGTH = 60;

    /**
     * A business name, as an address.
     *
     * Lowercased, stripped of accents, and reduced to letters and digits, so
     * "John's Hair & Beauty" becomes "johnshairbeauty".
     */
    public static function fromName(?string $name): string
    {
        /* Transliterated first, so "Cafe Noir" written with an accent keeps
           its e rather than losing the letter entirely. */
        $ascii = Str::ascii((string) $name);
        $cleaned = preg_replace('/[^a-z0-9]/', '', mb_strtolower($ascii)) ?? '';

        return mb_substr($cleaned, 0, self::MAX_LENGTH);
    }

    /**
     * What the user typed, as an address.
     *
     * Kinder than fromName: a hyphen someone typed deliberately is kept,
     * because it is legal and they meant it. Leading and trailing hyphens go,
     * since a subdomain may not begin or end with one.
     */
    public static function normalise(?string $value): string
    {
        $ascii = mb_strtolower(Str::ascii((string) $value));
        $cleaned = preg_replace('/[^a-z0-9-]/', '', $ascii) ?? '';

        return mb_substr(trim($cleaned, '-'), 0, self::MAX_LENGTH);
    }

    /** Whether this is a shape a subdomain may take. */
    public static function isValid(?string $value): bool
    {
        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', (string) $value)
            && mb_strlen((string) $value) <= self::MAX_LENGTH;
    }

    public static function isReserved(?string $value): bool
    {
        return in_array((string) $value, self::RESERVED, true);
    }
}
