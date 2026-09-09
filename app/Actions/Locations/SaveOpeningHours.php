<?php

declare(strict_types=1);

namespace App\Actions\Locations;

use App\Models\Location;
use Illuminate\Support\Facades\DB;

/**
 * Write one week of opening hours to one schedule of one location.
 *
 * Two screens save hours — Edit Location and Business Hours — and they must
 * agree about what a submitted week means. A day with no toggle is closed; an
 * empty period is somebody who changed their mind, not midnight to midnight;
 * the order the periods arrive in is the order they are read back. Those three
 * decisions living in one place is what stops the two screens from storing the
 * same form differently.
 */
class SaveOpeningHours
{
    /**
     * Replace a schedule's hours with what was submitted.
     *
     * Rewritten rather than reconciled: periods have no identity of their own
     * — "the second period on Tuesday" is a position, not a thing — so
     * matching submitted rows to stored ones would be guessing.
     *
     * @param  array<int, array<int|string, mixed>>  $hours
     */
    public function save(Location $location, array $hours, ?string $effectiveFrom = null): void
    {
        $effectiveFrom ??= $location->currentScheduleDate() ?? Location::EPOCH;

        DB::transaction(function () use ($location, $hours, $effectiveFrom) {
            /**
             * Deleted through scheduleHours(), not hours().
             *
             * hours() picks the current schedule with a correlated subquery
             * over location_hours, and MySQL refuses to delete from a table
             * its own subquery reads (error 1093). Naming the schedule is
             * also more honest about what is being replaced.
             */
            $location->scheduleHours($effectiveFrom)->delete();

            $rows = self::rows($location, $hours, $effectiveFrom);

            if ($rows !== []) {
                DB::table('location_hours')->insert($rows);
            }
        });
    }

    /**
     * Apply one week to several locations at once.
     *
     * The spec's "apply the same hours across selected locations". Each
     * location keeps its own rows rather than pointing at a shared schedule,
     * because the point of copying hours is that a branch can then diverge —
     * a shared record would make the next single-branch edit change all of
     * them.
     *
     * @param  array<int, array<int|string, mixed>>  $hours
     * @param  iterable<int, Location>  $locations
     */
    public function saveMany(iterable $locations, array $hours, ?string $effectiveFrom = null): void
    {
        DB::transaction(function () use ($locations, $hours, $effectiveFrom) {
            foreach ($locations as $location) {
                $this->save($location, $hours, $effectiveFrom);
            }
        });
    }

    /**
     * The submitted week as rows, or nothing where a day is closed.
     *
     * @param  array<int, array<int|string, mixed>>  $hours
     * @return array<int, array<string, mixed>>
     */
    private static function rows(Location $location, array $hours, string $effectiveFrom): array
    {
        $rows = [];

        foreach (array_keys(config('locations.weekdays')) as $day) {
            $periods = $hours[$day] ?? [];

            /**
             * The day toggle. A day with no is_open flag is closed, whatever
             * times its inputs still hold — someone who closes Sunday should
             * not have to clear the times to make it stick.
             */
            if (! ($periods['is_open'] ?? false)) {
                continue;
            }

            unset($periods['is_open']);

            $order = 0;

            foreach ($periods as $period) {
                $opens = $period['opens_at'] ?? null;
                $closes = $period['closes_at'] ?? null;

                // An empty extra period is someone who added a row and changed
                // their mind, not a period from midnight to midnight.
                if (! $opens || ! $closes) {
                    continue;
                }

                $rows[] = [
                    'location_id' => $location->id,
                    'effective_from' => $effectiveFrom,
                    'day_of_week' => $day,
                    'sort_order' => $order++,
                    'is_open' => true,
                    'opens_at' => $opens,
                    'closes_at' => $closes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        return $rows;
    }
}
