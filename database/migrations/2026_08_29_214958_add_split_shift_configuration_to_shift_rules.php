<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_rules', function (Blueprint $table) {
            /**
             * Whether one person may work more than one period in a day.
             *
             * Deliberately separate from allow_split_shift, which says only
             * that the business day is divided into periods at all. A salon
             * can run a morning and an evening shift without anybody working
             * both, and conflating the two would mean switching on coverage
             * and silently permitting twelve-hour days.
             */
            $table->boolean('allow_same_employee_multiple_periods')->default(false)->after('allow_split_shift');

            /**
             * A ceiling on how many of them, so a schedule cannot quietly put
             * one person on every period of the day. Two is the default
             * because two is what a split shift almost always means.
             */
            $table->unsignedTinyInteger('max_periods_per_employee_per_day')->default(2)->after('allow_same_employee_multiple_periods');

            /**
             * Non-working time required between two periods the same person
             * works. Minutes rather than hours: the setting is offered in
             * hours, but a business that wants ninety minutes should not have
             * to round.
             */
            $table->unsignedSmallInteger('min_gap_minutes')->nullable()->after('max_periods_per_employee_per_day');
        });

        /**
         * The named periods a business day is divided into — "Morning Shift,
         * 9:00–1:00", "Evening Shift, 4:00–8:00".
         *
         * A third thing, and the reason it is its own table: business working
         * hours say when the business is open, shift periods say how that day
         * is divided for coverage, and allow_same_employee_multiple_periods
         * says whether one person may work more than one of them. Folding any
         * two of those together is what makes a scheduler impossible to
         * reason about.
         *
         * Not per weekday. A period is a standing division of the day — the
         * morning shift is the morning shift on Tuesday and on Saturday — and
         * which days it runs on follows from the business's own week.
         */
        Schema::create('shift_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_rule_id')->constrained()->cascadeOnDelete();

            $table->string('name', 80);
            $table->time('starts_at');
            $table->time('ends_at');

            /** Unpaid minutes inside the period, as on a shift. */
            $table->unsignedSmallInteger('break_minutes')->nullable();

            /* Retired rather than deleted, so a period that was in use last
               season stays readable on the schedules that used it. */
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->index(['shift_rule_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_periods');

        Schema::table('shift_rules', function (Blueprint $table) {
            $table->dropColumn([
                'allow_same_employee_multiple_periods',
                'max_periods_per_employee_per_day',
                'min_gap_minutes',
            ]);
        });
    }
};
