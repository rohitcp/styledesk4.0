<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What StyleDesk accepts as an email address.
 *
 * Laravel's `email` rule alone is looser than it looks: "nadia@salon" passes
 * it, because an address with no dot in the domain is legal on a local
 * network. It is not legal anywhere a salon's clients live, and a business
 * that saves one has a reply-to address that silently reaches nobody.
 *
 * Worse, it disagreed with the browser: the live-validation module has always
 * required a dot in the domain, so the same address was refused as it was
 * typed on the sign-up form and accepted by the server on this one. A form
 * that accepts what another form refuses is a bug wherever the reader
 * happens to notice it.
 *
 * The pattern is deliberately the one in resources/js/live-validation.js.
 *
 * @see resources/js/live-validation.js
 */
class EmailAddress
{
    /** Something, an @, something, a dot, something. No spaces anywhere. */
    private const SHAPE = '/^[^@\s]+@[^@\s]+\.[^@\s]+$/';

    /**
     * The rules an address is held to.
     *
     * `email` stays in front of the pattern: it is the one that knows about
     * quoting, unicode and the rest, and the pattern only adds the domain
     * StyleDesk needs on top of it.
     *
     * @return array<int, string>
     */
    public static function rules(bool $required = false): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'email',
            'regex:'.self::SHAPE,
            'max:255',
        ]));
    }

    /** Whether this is an address StyleDesk would accept. */
    public static function looksValid(?string $email): bool
    {
        $email = trim((string) $email);

        return $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match(self::SHAPE, $email) === 1;
    }
}
