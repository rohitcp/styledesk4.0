<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Staff module's option lists, in the reader's language.
 *
 * Same division as LocationOptions and BusinessProfile: config/staff.php
 * decides which options exist, because validation reads that same list; this
 * decides what each is called.
 */
class StaffOptions
{
    /** @return array<string, string> */
    public static function employmentTypes(): array
    {
        return self::translate('employment_types');
    }

    /** @return array<string, string> */
    public static function providerTypes(): array
    {
        return self::translate('provider_types');
    }

    /** @return array<string, string> */
    public static function specialities(): array
    {
        return self::translate('specialities');
    }

    /** @return array<string, string> */
    public static function pronouns(): array
    {
        return self::translate('pronouns');
    }

    /** @return array<string, string> */
    public static function phoneTypes(): array
    {
        return self::translate('phone_types');
    }

    /** @return array<string, string> */
    public static function sorts(): array
    {
        return self::translate('sorts');
    }

    /**
     * Status labels only. The badge class stays in the config, because it is
     * a styling decision and has nothing to do with language.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return collect(config('staff.statuses'))
            ->mapWithKeys(function (array $status, string $value) {
                $key = 'staff_options.statuses.'.$value;

                return [$value => trans()->has($key) ? __($key) : $status['label']];
            })
            ->all();
    }

    public static function statusLabel(?string $value): ?string
    {
        return $value === null ? null : (self::statuses()[$value] ?? null);
    }

    /** One option's label from any of the lists above. */
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
    private static function translate(string $set): array
    {
        return collect(config('staff.'.$set))
            ->mapWithKeys(function (string $label, string $value) use ($set) {
                $key = 'staff_options.'.$set.'.'.$value;

                return [$value => trans()->has($key) ? __($key) : $label];
            })
            ->all();
    }
}
