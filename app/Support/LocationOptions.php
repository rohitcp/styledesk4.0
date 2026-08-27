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
