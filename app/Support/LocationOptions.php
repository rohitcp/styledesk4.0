<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Locations module's option lists, in the reader's language.
 *
 * Same division as BusinessProfile: config/locations.php decides which options
 * exist, because validation reads that same list and a rule built from one
 * source with a dropdown built from another will eventually offer something
 * the rule refuses. This decides only what each option is called.
 */
class LocationOptions
{
    /** @return array<string, string> */
    public static function types(): array
    {
        return self::translate('locations.types', config('locations.types'));
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return self::translate(
            'locations.statuses',
            collect(config('locations.statuses'))->map(fn (array $status) => $status['label'])->all()
        );
    }

    /**
     * The countries a business can choose, in the order to offer them.
     *
     * The handful most businesses pick first, then the rest alphabetically.
     * A dropdown of thirty-three sorted from Argentina puts the likeliest
     * answers last, which is a scroll every reader pays for.
     *
     * Only presentation: validation still reads the config directly, so
     * promoting a country cannot accidentally narrow what is accepted.
     *
     * @return array<string, string>
     */
    public static function countries(): array
    {
        $countries = config('locations.countries');

        $promoted = collect(config('locations.countries_first', []))
            /* Ignoring any code that is not in the list, so a typo in the
               promotion list cannot invent a country. */
            ->filter(fn (string $code) => isset($countries[$code]))
            ->mapWithKeys(fn (string $code) => [$code => $countries[$code]]);

        return $promoted->union(collect($countries))->all();
    }

    /**
     * The countries a business can operate in, in the order to offer them.
     *
     * A subset of countries(), and a different question: this is where a
     * business may run, not where an address may be. Same promotion applies,
     * so the likeliest markets stay at the top of the list.
     *
     * Names come from the full country list, so a market cannot be listed in
     * one place and spelled differently in the other.
     *
     * @return array<string, string>
     */
    public static function operatingCountries(): array
    {
        $countries = config('locations.countries');

        $operating = collect(config('locations.operating_countries', []))
            /* Ignoring any code that is not a known country, so a typo in the
               market list cannot invent one. */
            ->filter(fn (string $code) => isset($countries[$code]))
            ->mapWithKeys(fn (string $code) => [$code => $countries[$code]]);

        $promoted = collect(config('locations.countries_first', []))
            ->filter(fn (string $code) => $operating->has($code))
            ->mapWithKeys(fn (string $code) => [$code => $operating[$code]]);

        return $promoted->union($operating->sort())->all();
    }

    /**
     * Weekday names, keyed by the integer stored in location_hours.
     *
     * @return array<int, string>
     */
    public static function weekdays(): array
    {
        return collect(config('locations.weekdays'))
            ->map(function (string $label, $day) {
                $key = 'locations.weekdays.'.$day;

                return trans()->has($key) ? __($key) : $label;
            })
            ->all();
    }

    /**
     * A config list with its labels replaced by translations.
     *
     * The config's own label is the fallback, so an option added without a
     * translation reads as itself rather than as a key.
     *
     * @param  array<string, string>  $fallbacks
     * @return array<string, string>
     */
    private static function translate(string $langKey, array $fallbacks): array
    {
        return collect($fallbacks)
            ->mapWithKeys(function (string $label, $value) use ($langKey) {
                $key = $langKey.'.'.$value;

                return [(string) $value => trans()->has($key) ? __($key) : $label];
            })
            ->all();
    }
}
