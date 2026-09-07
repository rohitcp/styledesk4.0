<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Which languages a business may be served in, given where it operates.
 *
 * The language fields on onboarding step 1 are filtered by the countries
 * chosen just above them, so the list answers a question the reader has
 * already answered rather than offering all seven every time. A salon
 * operating only in Germany is not asked to rule out Hindi.
 *
 * This is the server half of that filter. The Vue island applies the same map
 * as the reader ticks countries, and both read config('currencies.
 * country_languages') — a filter the browser applies and the validation does
 * not is a form that hides an option and then accepts it when posted.
 */
class MarketLanguages
{
    /**
     * The languages offered for a set of operating countries, named.
     *
     * The union, not the intersection: a business operating in Canada and
     * Mexico serves clients in English, French and Spanish, and asking it to
     * pick from the empty set the two countries share would be absurd.
     *
     * Ordered by the language register rather than by country, so the list
     * reads the same way whichever order the countries were ticked in.
     *
     * @param  array<int, string>  $countryCodes
     * @return array<string, string>
     */
    public static function forCountries(array $countryCodes): array
    {
        $map = config('currencies.country_languages', []);

        $offered = collect($countryCodes)
            ->flatMap(fn (string $code) => $map[$code] ?? [])
            ->unique();

        return collect(config('currencies.languages'))
            ->filter(fn (string $name, string $code) => $offered->contains($code))
            ->all();
    }

    /**
     * The codes only, for validation.
     *
     * Falls back to every language when no country has been chosen. The
     * country field is required, so that case is a request that is going to
     * be rejected anyway — narrowing the language rule as well would bury the
     * one error that matters under two that follow from it.
     *
     * @param  array<int, string>  $countryCodes
     * @return array<int, string>
     */
    public static function codesFor(array $countryCodes): array
    {
        if ($countryCodes === []) {
            return array_keys(config('currencies.languages'));
        }

        return array_keys(self::forCountries($countryCodes));
    }
}
