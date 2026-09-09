<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a membership includes anything, or is only a set of perks.
 *
 * The master switch above every other credit question. Off, and a membership
 * grants nothing to draw down: what the client buys is the discount, the
 * priority booking, the standing they get for being a member — a real product
 * a lot of businesses sell, and one StyleDesk could not describe until now.
 *
 * Distinct from `allow_rollover`, which was standing in for this and should
 * not have been. Rollover asks whether an unused credit survives its cycle;
 * this asks whether there is a credit at all. A business can perfectly well
 * grant a massage a month that does not carry over, and switching rollover
 * off should never have taken the massage with it.
 *
 * Default true, because every membership that already exists includes
 * services and would otherwise wake up granting nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_settings', function (Blueprint $table) {
            $table->boolean('credits_enabled')->default(true)->after('allow_start_date_selection');
        });
    }

    public function down(): void
    {
        Schema::table('membership_settings', function (Blueprint $table) {
            $table->dropColumn('credits_enabled');
        });
    }
};
