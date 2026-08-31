<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            /**
             * The named period this shift was generated from — "Morning
             * Shift", "Evening Shift".
             *
             * Nullable, because a shift entered by hand belongs to no period,
             * and nullOnDelete because losing the period must not lose the
             * record that somebody worked those hours. The shift keeps its own
             * times either way: this says where they came from, not what they
             * are, so editing one generated shift never reaches back into the
             * rule everybody else is on.
             */
            $table->foreignId('shift_period_id')->nullable()->after('location_id')
                ->constrained('shift_periods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_period_id');
        });
    }
};
