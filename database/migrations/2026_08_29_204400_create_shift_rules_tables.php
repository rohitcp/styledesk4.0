<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * A reusable working pattern — "Standard Full-Time", "Weekend Shift".
         *
         * A template, not a schedule. The rule says Monday to Friday, nine to
         * five; a staff schedule applies it to a person and generates the
         * dated shifts. Changing a generated shift must never edit the rule
         * behind it, which is the whole reason these are separate records.
         */
        Schema::create('shift_rules', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            $table->string('name', 120);
            $table->text('description')->nullable();

            /**
             * 'all' or 'specific'. Stored rather than inferred from whether
             * any locations are attached: "everywhere" and "nowhere chosen
             * yet" are different answers, and a rule that quietly meant the
             * first when somebody meant the second would apply itself to
             * branches nobody picked.
             */
            $table->string('location_scope', 10)->default('all');
            $table->string('status', 10)->default('active');

            /* Break configuration. 'none', 'fixed' (a named hour) or
               'duration' (so many minutes, placed inside the shift later). */
            $table->string('break_type', 10)->default('none');
            $table->unsignedSmallInteger('break_minutes')->nullable();
            $table->time('break_starts_at')->nullable();
            $table->time('break_ends_at')->nullable();

            /**
             * The scheduling limits, in whole hours because that is how a rota
             * is discussed — "eight hours a day, forty a week". All nullable:
             * a limit nobody has set is not a limit of zero.
             */
            $table->unsignedSmallInteger('max_hours_per_day')->nullable();
            $table->unsignedSmallInteger('max_hours_per_week')->nullable();
            $table->unsignedSmallInteger('min_hours_per_shift')->nullable();
            $table->unsignedSmallInteger('max_hours_per_shift')->nullable();
            $table->unsignedSmallInteger('min_rest_hours')->nullable();
            $table->unsignedTinyInteger('max_consecutive_days')->nullable();

            $table->boolean('allow_overtime')->default(false);
            $table->unsignedSmallInteger('overtime_after_hours')->nullable();
            $table->unsignedSmallInteger('max_overtime_hours')->nullable();

            /* Whether a manager may move one generated shift without editing
               the rule, and whether a day may hold more than one block. */
            $table->boolean('allow_adjustment')->default(true);
            $table->boolean('allow_split_shift')->default(false);

            /* Seasonal rules — "Summer Schedule, June to August". Both
               optional: a rule with neither is simply always in force. */
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        /**
         * One working period on one weekday of one rule.
         *
         * A period rather than a day, exactly as location_hours is: a pattern
         * with a lunch break in the middle has two rows for Monday, ordered by
         * sort_order. A weekday with no rows is a day off — so "which days are
         * worked" is answered by the rows themselves rather than by a second
         * flag able to disagree with them.
         */
        Schema::create('shift_rule_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_rule_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->time('starts_at');
            $table->time('ends_at');

            $table->index(['shift_rule_id', 'day_of_week']);
        });

        /**
         * Which branches a rule applies to, when its scope is 'specific'.
         *
         * Emptied rather than kept when the scope goes back to 'all': a
         * dormant list of locations is a second answer waiting to contradict
         * the first.
         */
        Schema::create('location_shift_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->unique(['shift_rule_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_shift_rule');
        Schema::dropIfExists('shift_rule_periods');
        Schema::dropIfExists('shift_rules');
    }
};
