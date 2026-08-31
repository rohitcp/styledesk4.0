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
         * Working hours on a named date.
         *
         * What tells a shift from a staff schedule: the schedule is the
         * recurring pattern — Monday to Friday, nine to five — and a shift is
         * one dated block that can supplement or override it. Both are needed,
         * because "she works Tuesdays" and "she is covering this Saturday" are
         * different facts and storing the second as a change to the first
         * loses the pattern.
         */
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            /**
             * Nullable, and set null rather than cascade: a branch closing
             * must not silently delete the record that somebody worked those
             * hours. A shift with no location belongs to a single-site
             * business, which is the reading it never has to think about.
             */
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->date('date');

            /**
             * Times of day, not timestamps.
             *
             * A shift is "ten until four" at its own branch. Storing it as an
             * instant would bind it to a timezone and make the same rota read
             * differently after the business moved server — and the date is
             * already its own column.
             */
            $table->time('starts_at');
            $table->time('ends_at');

            /** Unpaid minutes inside the shift, not a second pair of times. */
            $table->unsignedSmallInteger('break_minutes')->default(0);

            $table->string('type', 20)->default('regular');
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();

            $table->timestamps();

            /* The rota is always read as "this location, this week", and the
               conflict check is always "this person, this day". */
            $table->index(['tenant_id', 'date']);
            $table->index(['staff_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_shifts');
    }
};
