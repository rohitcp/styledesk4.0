<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dialling code for a branch's second number.
 *
 * The first number has had one since locations existed; the second never did,
 * because the form offered a bare text box for both. Now that both are
 * entered with a country picker beside them, the second needs somewhere to
 * put the answer — without it the picker would appear to work and quietly
 * discard what was chosen.
 *
 * Guarded, like every migration here now is: one that changes a column and
 * then fails on a later step has to be safe to resume.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('locations', 'phone_secondary_country')) {
            return;
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->char('phone_secondary_country', 2)->nullable()->after('phone_secondary');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('phone_secondary_country');
        });
    }
};
