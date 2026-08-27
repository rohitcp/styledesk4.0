<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The kinds of exception a date can carry, in the reader's language.
 *
 * Same division as LocationOptions and BusinessProfile: config/locations.php
 * decides which types exist, because the validation rule reads that same list;
 * this decides what each is called and keeps `closes` alongside it, because
 * the form's own script reads that flag to pick a starting answer.
 */
class ClosureTypes
{
    /**
     * @return array<string, array{label: string, closes: bool}>
     */
    public static function all(): array
    {
        return collect(config('locations.closure_types'))
            ->map(function (array $type, string $value) {
                $key = 'hours.types.'.$value;

                return [
                    'label' => trans()->has($key) ? __($key) : $type['label'],
                    'closes' => $type['closes'],
                ];
            })
            ->all();
    }
}
