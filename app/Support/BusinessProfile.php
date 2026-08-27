<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Business module's dropdown options, in the reader's language.
 *
 * The values live in config/business_profile.php because validation reads the
 * same list — a rule and a dropdown built from two sources will eventually
 * offer something the rule refuses. So the config stays the source of which
 * options exist, and this decides what each one is called.
 *
 * The spec is explicit that dropdown *values* need translating too: a form
 * whose labels are Spanish and whose options are English is the same
 * half-translated screen, moved one level in.
 */
class BusinessProfile
{
    /**
     * Minutes rendered as a person would say them.
     *
     * "90 minutes" is technically right and nobody says it, so the awkward
     * ones are named rather than computed. Under an hour, the count does the
     * work and the language states its own plural.
     */
    public static function duration(int $minutes): string
    {
        return match (true) {
            $minutes === 60 => __('business.durations.hour'),
            $minutes === 90 => __('business.durations.hour_thirty'),
            $minutes % 60 === 0 => __('business.durations.hours', ['count' => intdiv($minutes, 60)]),
            default => trans_choice('business.durations.minutes', $minutes, ['count' => $minutes]),
        };
    }

    /**
     * Date format names are deliberately not translated.
     *
     * "DD/MM/YYYY" is a pattern, not a sentence. Translating the letters
     * would describe a format the business does not use and cannot type.
     *
     * @return array<string, string>
     */
    public static function dateFormats(): array
    {
        return config('business_profile.date_formats');
    }

    /** @return array<string, string> */
    public static function timeFormats(): array
    {
        return self::translate('business_profile.time_formats', 'business.time_formats');
    }

    /** @return array<string, string> */
    public static function firstDayOfWeek(): array
    {
        return self::translate('business_profile.first_day_of_week', 'business.first_day_of_week');
    }

    /** @return array<string, string> */
    public static function bookingDurations(): array
    {
        return collect(config('business_profile.booking_durations'))
            ->mapWithKeys(fn (string $label, $minutes) => [(string) $minutes => self::duration((int) $minutes)])
            ->all();
    }

    /** @return array<string, string> */
    public static function appointmentIntervals(): array
    {
        return collect(config('business_profile.appointment_intervals'))
            ->mapWithKeys(fn (string $label, $minutes) => [(string) $minutes => self::duration((int) $minutes)])
            ->all();
    }

    /** @return array<string, string> */
    public static function taxBehaviors(): array
    {
        return self::translate('business_profile.tax_behaviors', 'business.tax_behaviors');
    }

    /** @return array<string, string> */
    public static function staffAssignment(): array
    {
        return self::translate('business_profile.staff_assignment', 'business.staff_assignment');
    }

    /**
     * One option's label, for a screen showing a stored value rather than a
     * list of choices.
     */
    public static function label(string $set, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::{$set}()[$value] ?? null;
    }

    /**
     * A config list with its labels replaced by translations.
     *
     * The config's own label is the fallback, so an option added without a
     * translation reads as itself rather than as a key.
     *
     * @return array<string, string>
     */
    private static function translate(string $configKey, string $langKey): array
    {
        return collect(config($configKey))
            ->mapWithKeys(function (string $label, $value) use ($langKey) {
                $key = $langKey.'.'.$value;

                return [(string) $value => trans()->has($key) ? __($key) : $label];
            })
            ->all();
    }
}
