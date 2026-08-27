<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business Hours: future schedules, and the days that are not the usual week.
 *
 * Two additions. Weekly hours gain an effective date, so a business can enter
 * next month's hours today without those hours taking effect this afternoon.
 * And a closures table carries everything that overrides the weekly pattern
 * on a given date — a public holiday, a refit, a late night before Christmas.
 */
return new class extends Migration
{
    /**
     * The date rows written before schedules existed are treated as taking
     * effect from.
     *
     * A real date rather than null, so "which schedule applies today" is one
     * comparison rather than a comparison plus a null case. Far enough back
     * that no business's own effective date could sort below it.
     */
    private const EPOCH = '2000-01-01';

    public function up(): void
    {
        if (! Schema::hasColumn('location_hours', 'effective_from')) {
            Schema::table('location_hours', function (Blueprint $table) {
                $table->date('effective_from')->default(self::EPOCH)->after('location_id');
            });

            /**
             * The unique key gains the date.
             *
             * Without it a location could hold only one version of Monday, and
             * entering next month's hours would overwrite this month's — which
             * is the entire thing an effective date exists to prevent.
             */
            Schema::table('location_hours', function (Blueprint $table) {
                $table->dropUnique(['location_id', 'day_of_week', 'sort_order']);
            });

            Schema::table('location_hours', function (Blueprint $table) {
                $table->unique(
                    ['location_id', 'effective_from', 'day_of_week', 'sort_order'],
                    'location_hours_schedule_unique'
                );
            });

            DB::table('location_hours')->update(['effective_from' => self::EPOCH]);
        }

        /**
         * Everything that overrides the weekly pattern on a date.
         *
         * One table rather than three, because a holiday, a temporary closure
         * and a set of special hours differ only in what they are called and
         * whether they name times. Split apart they would need three screens,
         * three sets of overlap rules, and an answer for what happens when a
         * refit and a bank holiday fall on the same Tuesday.
         *
         * A range rather than a single date, so "closed for refurbishment,
         * 3–17 March" is one row a person can read and edit, not fourteen.
         */
        Schema::create('location_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->string('type', 24);
            $table->string('name');

            $table->date('starts_on');
            // Always populated, equal to starts_on for a single day. A nullable
            // end date would make every query that asks "is this date covered"
            // carry a null case it could get wrong.
            $table->date('ends_on');

            $table->boolean('is_closed_all_day')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();

            // Internal. Never shown to clients: "staff training, Priya covering
            // reception" is a note to the team, not an announcement.
            $table->text('notes')->nullable();

            $table->timestamps();

            // The overview asks "what is coming up at this location", which is
            // this index read in order.
            $table->index(['location_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_closures');

        if (Schema::hasColumn('location_hours', 'effective_from')) {
            /**
             * Future schedules cannot survive a table that holds one version
             * of each day, so they are dropped deliberately here rather than
             * left to break the unique index being restored below.
             */
            $current = DB::table('location_hours')
                ->selectRaw('location_id, max(effective_from) as effective_from')
                ->groupBy('location_id')
                ->get()
                ->keyBy('location_id');

            foreach ($current as $locationId => $row) {
                DB::table('location_hours')
                    ->where('location_id', $locationId)
                    ->where('effective_from', '!=', $row->effective_from)
                    ->delete();
            }

            Schema::table('location_hours', function (Blueprint $table) {
                $table->dropUnique('location_hours_schedule_unique');
                $table->dropColumn('effective_from');
            });

            Schema::table('location_hours', function (Blueprint $table) {
                $table->unique(['location_id', 'day_of_week', 'sort_order']);
            });
        }
    }
};
