<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A day's opening periods must not overlap or run backwards.
 *
 * Applied to a whole day rather than to each period, because overlap is a
 * relationship between two of them: 9–1 and 12–5 are each perfectly valid
 * times, and only together are they wrong.
 *
 * Two overlapping periods are not a cosmetic problem. The booking engine reads
 * these to work out free slots, and a noon that is inside two periods is a noon
 * that can be double-booked — a fault a business would discover from a client
 * standing in reception, not from this screen.
 */
class NonOverlappingPeriods implements ValidationRule
{
    public function __construct(private string $dayLabel) {}

    /**
     * @param  mixed  $value  One day: ['is_open' => '1', 0 => ['opens_at' => …], …]
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        // A closed day's leftover times are ignored, matching what is saved:
        // the toggle decides, not the inputs sitting behind it.
        if (! ($value['is_open'] ?? false)) {
            return;
        }

        $periods = [];

        foreach ($value as $key => $period) {
            if ($key === 'is_open' || ! is_array($period)) {
                continue;
            }

            $opens = $period['opens_at'] ?? null;
            $closes = $period['closes_at'] ?? null;

            // Incomplete periods are dropped on save, so they are not compared
            // here either — flagging one as an overlap would be an error about
            // a row that is never going to exist.
            if (! $opens || ! $closes) {
                continue;
            }

            /**
             * Reported separately from overlap, and first.
             *
             * "Closing time is before opening time" tells someone exactly what
             * to fix. Rolled into an overlap message it would read as a
             * complaint about a different period entirely.
             */
            if ($closes <= $opens) {
                $fail("{$this->dayLabel}: closing time must be after the opening time.");

                return;
            }

            $periods[] = ['opens' => $opens, 'closes' => $closes];
        }

        // Compared in order, so the message can name the times a person is
        // looking at rather than the positions they happen to occupy.
        usort($periods, fn (array $a, array $b) => $a['opens'] <=> $b['opens']);

        foreach ($periods as $index => $period) {
            $next = $periods[$index + 1] ?? null;

            if ($next === null) {
                continue;
            }

            /**
             * Touching is allowed; overlapping is not.
             *
             * 9–1 followed by 1–5 is a business that does not close for lunch,
             * written in two rows. Refusing that would be refusing something
             * correct because it looks unusual.
             */
            if ($next['opens'] < $period['closes']) {
                $fail("{$this->dayLabel}: opening periods must not overlap. {$period['opens']}–{$period['closes']} and {$next['opens']}–{$next['closes']} overlap.");

                return;
            }
        }
    }
}
