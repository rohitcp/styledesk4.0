<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many times a benefit can actually be redeemed.
 *
 * `quantity` describes the benefit — "4 × Swedish Massage" is what the card
 * says and what the client is being sold. `credits` is what the redemption
 * engine draws down. They are the same number in every ordinary membership,
 * and the split exists so a business can eventually describe a benefit whose
 * headline and whose redemptions differ.
 *
 * Credits is the one that governs. A row where the two disagree grants what
 * `credits` says, because that is the column the engine reads and a benefit
 * that granted its description would be a benefit nobody could reconcile.
 *
 * Backfilled from quantity so every membership that already exists keeps
 * granting exactly what it granted yesterday.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plan_services', function (Blueprint $table) {
            /* Nullable for the length of the backfill only — a default of 1
               would quietly rewrite a four-massage package to one. */
            $table->unsignedSmallInteger('credits')->nullable()->after('quantity');
        });

        DB::table('membership_plan_services')->update(['credits' => DB::raw('quantity')]);

        Schema::table('membership_plan_services', function (Blueprint $table) {
            $table->unsignedSmallInteger('credits')->default(1)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('membership_plan_services', function (Blueprint $table) {
            $table->dropColumn('credits');
        });
    }
};
