<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Project-wide capitalisation for name and title fields.
 *
 * The rule is deliberately narrow: uppercase the first character of the value
 * and change nothing else. Everything the user typed after that survives
 * exactly as typed.
 *
 *   main location  -> Main location
 *   john smith     -> John smith
 *   styleDesk NYC  -> StyleDesk NYC
 *   JOHN SMITH     -> JOHN SMITH
 *
 * Full uppercase needs no special case: uppercasing an already-uppercase first
 * character is a no-op, and because nothing else is touched the rest survives.
 * That is the whole reason the rule is expressed as "change only the first
 * character" rather than as a list of cases to detect.
 *
 * Deliberately NOT Str::title() or ucwords(): those lowercase the remainder,
 * which would turn JOHN SMITH into John Smith and StyleDesk into Styledesk,
 * overriding capitalisation the user chose on purpose.
 *
 * Never apply this to values where case carries meaning — email addresses,
 * passwords, URLs, subdomains, usernames, codes or API keys.
 */
final class InputCase
{
    public static function sentence(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }

    /**
     * Apply to the given keys of an array, leaving absent keys absent.
     *
     * Keys may be dotted, so nested request input — services.0.name — can be
     * normalised without unpacking it by hand.
     *
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public static function apply(array $input, array $keys): array
    {
        foreach ($keys as $key) {
            if (! str_contains($key, '*')) {
                if (data_get($input, $key) !== null) {
                    data_set($input, $key, self::sentence((string) data_get($input, $key)));
                }

                continue;
            }

            // Wildcard: services.*.name walks each row.
            [$before, $after] = explode('.*.', $key, 2);

            foreach (array_keys((array) data_get($input, $before, [])) as $index) {
                $path = $before.'.'.$index.'.'.$after;

                if (data_get($input, $path) !== null) {
                    data_set($input, $path, self::sentence((string) data_get($input, $path)));
                }
            }
        }

        return $input;
    }
}
