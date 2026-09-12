<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * What StyleDesk accepts as a phone number, and the one form it compares in.
 *
 * Numbers are stored the way they were typed — "(973) 555-1234" is what the
 * receptionist reads back down the phone, and rewriting it into +19735551234
 * on every screen would be this class deciding how the business talks to its
 * clients. So the typed form stays, and this produces the second, canonical
 * form beside it: E.164, digits and a leading plus, which is what two numbers
 * are actually compared as and what a carrier is handed.
 *
 * The sibling of {@see EmailAddress}, and deliberately shaped like it: a
 * `rules()` for forms, a `looksValid()` for code that has to decide, and the
 * normaliser the duplicate check runs on.
 *
 * Not a libphonenumber: that is a dependency, and the question here is only
 * whether a number is whole enough to ring and which of two records it is the
 * same as. The country list it works from is the one the phone field offers,
 * in config/phone.php.
 */
class PhoneNumber
{
    /**
     * The rules a number is held to on a form.
     *
     * @return array<int, mixed>
     */
    public static function rules(bool $required = false): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'max:40',
            static::rule(),
        ]));
    }

    /**
     * The shape check on its own, for a field that already has its own rules.
     *
     * A closure rather than a regex: whether a number is whole depends on the
     * country it belongs to, and no pattern can say that on its own.
     */
    public static function rule(?string $country = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($country): void {
            if (trim((string) $value) === '') {
                return;
            }

            if (! self::looksValid((string) $value, $country)) {
                $fail(__('clients.module.validation.mobile_invalid'));
            }
        };
    }

    /** Whether this is a number StyleDesk would ring. */
    public static function looksValid(?string $number, ?string $country = null): bool
    {
        return self::normalise($number, $country) !== null;
    }

    /**
     * One number, in E.164: `+19735551234`.
     *
     * Null where there is no number worth keeping — empty, mistyped, or cut
     * off halfway. Null is the answer that stops it being stored, so the
     * decisions about what is too short to keep all live here.
     *
     * Accepts the punctuation people write numbers with: "(973) 555-1234",
     * "973-555-1234", "+1 973 555 1234" and "0044 20 7946 0100" are the same
     * number as far as this is concerned.
     */
    public static function normalise(?string $number, ?string $country = null): ?string
    {
        $raw = trim((string) $number);

        if ($raw === '') {
            return null;
        }

        /* Letters are not a number somebody typed badly — 555-CALL is a
           vanity number no carrier here takes, and treating it as its digits
           would invent one. */
        if (preg_match('/\p{L}/u', $raw) === 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        $international = str_starts_with($raw, '+');

        /* The international prefix people dial rather than type: 00 in most
           of the world, 011 from North America. Both mean "what follows is a
           country code", which is what the plus means. */
        if (! $international) {
            foreach (['011', '00'] as $prefix) {
                if (str_starts_with($digits, $prefix) && strlen($digits) > strlen($prefix) + 6) {
                    $digits = substr($digits, strlen($prefix));
                    $international = true;

                    break;
                }
            }
        }

        $e164 = $international
            ? self::fromInternational($digits)
            : self::fromNational($digits, $country);

        if ($e164 === null) {
            return null;
        }

        $length = strlen(substr($e164, 1));

        return $length >= (int) config('phone.min_digits') && $length <= (int) config('phone.max_digits')
            ? $e164
            : null;
    }

    /**
     * The number as the desk should read it back.
     *
     * The typed form wherever there is one, because that is what the client
     * gave; the normalised form only when there is nothing else — a number
     * that arrived from a carrier rather than from a person.
     */
    public static function display(?string $typed, ?string $e164 = null): ?string
    {
        $typed = trim((string) $typed);

        return $typed !== '' ? $typed : ($e164 ?: null);
    }

    /**
     * Already carrying its country code.
     *
     * Checked against the countries the phone field offers rather than taken
     * on trust: a leading plus on eleven digits that match no dialling code
     * is a mistyped number wearing a plus.
     */
    private static function fromInternational(string $digits): ?string
    {
        foreach (self::dialCodes() as $dial => $countries) {
            if (! str_starts_with($digits, (string) $dial)) {
                continue;
            }

            $national = substr($digits, strlen((string) $dial));

            /* Any of the countries on this dialling code will do. They share
               a code precisely because their numbers are the same shape, and
               +1 covers both the US and Canada. */
            foreach ($countries as $iso) {
                if (self::fitsCountry($national, $iso)) {
                    return '+'.$digits;
                }
            }
        }

        /* A plus and a plausible length, on a code this application does not
           list. Kept rather than refused: the business may have a client
           abroad, and refusing a number somebody read off a card is worse
           than storing one nobody here can validate. */
        return strlen($digits) >= (int) config('phone.min_digits') ? '+'.$digits : null;
    }

    /**
     * No country code, so somebody has to say which country it is.
     *
     * The country asked for, then the tenant's own, then the configured
     * default — the number was typed at a desk, and that desk is almost
     * always in the country the business operates in.
     */
    private static function fromNational(string $digits, ?string $country): ?string
    {
        $iso = self::resolveCountry($country);
        $dial = config('phone.countries.'.$iso.'.dial');

        if ($dial === null) {
            return null;
        }

        /* The trunk prefix: the 0 in "020 7946 0100" is how the number is
           dialled inside the country and is not part of it. North America
           has none — a US number never begins with 0 — so it is dropped only
           where the country actually uses one. */
        if ($dial !== '1' && str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        /* Somebody typed the dialling code without the plus. "1 973 555
           1234" is the number, not an eleven-digit local one. */
        if (str_starts_with($digits, (string) $dial) && self::fitsCountry(substr($digits, strlen((string) $dial)), $iso)) {
            return '+'.$digits;
        }

        return self::fitsCountry($digits, $iso) ? '+'.$dial.$digits : null;
    }

    /** Whether a national number is the length that country's numbers are. */
    private static function fitsCountry(string $national, string $iso): bool
    {
        $expected = config('phone.countries.'.$iso.'.digits');

        if ($expected === null) {
            return false;
        }

        /* One digit either side of the mask. The masks are the common shape
           rather than the whole truth — Germany and the UK both issue
           numbers of more than one length — and refusing a real number is a
           worse failure here than accepting a slightly odd one. */
        return abs(strlen($national) - (int) $expected) <= 1;
    }

    /**
     * The country a bare number is assumed to belong to.
     *
     * The tenant's own comes before the configured default because a salon in
     * London taking a walk-in's number is not taking a US one.
     */
    private static function resolveCountry(?string $country): string
    {
        $iso = Str::upper(trim((string) $country));

        if ($iso !== '' && config('phone.countries.'.$iso) !== null) {
            return $iso;
        }

        $tenant = function_exists('tenant') ? tenant() : null;
        $tenantIso = Str::upper((string) ($tenant?->country_code ?? ''));

        if ($tenantIso !== '' && config('phone.countries.'.$tenantIso) !== null) {
            return $tenantIso;
        }

        return Str::upper((string) config('phone.default_country'));
    }

    /**
     * Dialling codes longest first, mapped to the countries that use them.
     *
     * Longest first because +1 is a prefix of nothing but +1, while +35 would
     * swallow +351 if the shorter were tried first.
     *
     * @return array<string, array<int, string>>
     */
    private static function dialCodes(): array
    {
        $codes = [];

        foreach ((array) config('phone.countries') as $iso => $country) {
            $codes[(string) $country['dial']][] = (string) $iso;
        }

        uksort($codes, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $codes;
    }
}
