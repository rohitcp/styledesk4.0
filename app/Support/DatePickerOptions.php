<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * What the shared date picker needs to be told.
 *
 * The picker itself is `SD.datePicker` in the design system. Everything it
 * cannot work out for itself — the month names in the reader's language,
 * whether 03/04 means March or April, the wording on its two buttons — is
 * decided here, once, so the Blade field and the calendar's own header cannot
 * end up drawing two different calendars in one app.
 *
 * Month and weekday names come from Carbon rather than a translation file: it
 * already carries them for every locale StyleDesk offers, and a second
 * hand-written list is a second thing to drift.
 */
class DatePickerOptions
{
    /**
     * @param  array<string, mixed>  $overrides  min, max, minYear, maxYear, openTo, clearable, dialogLabel
     * @return array<string, mixed>
     */
    public static function build(array $overrides = []): array
    {
        $locale = app()->getLocale();

        $options = array_filter([
            'min' => $overrides['min'] ?? null,
            'max' => $overrides['max'] ?? null,
            'minYear' => isset($overrides['minYear']) ? (int) $overrides['minYear'] : null,
            'maxYear' => isset($overrides['maxYear']) ? (int) $overrides['maxYear'] : null,
            'openTo' => $overrides['openTo'] ?? null,
            'dialogLabel' => $overrides['dialogLabel'] ?? __('common.choose_a_date'),
        ], fn ($option) => $option !== null);

        /* false has to survive array_filter, which drops it along with null,
           or `clearable: false` would silently leave the Clear button on. */
        $options['clearable'] = (bool) ($overrides['clearable'] ?? true);

        $options['labels'] = [
            'months' => collect(range(1, 12))
                ->map(fn (int $month) => Str::ucfirst(
                    Carbon::create(2000, $month, 1)->locale($locale)->isoFormat('MMMM')
                ))->all(),
            // Sunday first, matching the grid the picker draws.
            'dow' => collect(range(0, 6))
                ->map(fn (int $day) => Str::ucfirst(
                    Carbon::create(2024, 1, 7)->addDays($day)->locale($locale)->isoFormat('dd')
                ))->all(),
            'clear' => __('common.clear'),
            'today' => __('common.today'),
            'month' => __('common.month'),
            'year' => __('common.year'),
            'previousMonth' => __('common.previous_month'),
            'nextMonth' => __('common.next_month'),
        ];

        /* Day-first everywhere except the United States, which is the one
           place 03/04 means March. Read off the locale rather than asked for
           at each call site, so no screen has to decide. */
        $options['order'] = str_starts_with($locale, 'en') && ! str_contains($locale, 'GB') ? 'mdy' : 'dmy';

        return $options;
    }

    /** What an empty field should show, given the order above. */
    public static function placeholder(array $options): string
    {
        return ($options['order'] ?? 'dmy') === 'mdy' ? 'MM/DD/YYYY' : 'DD/MM/YYYY';
    }
}
