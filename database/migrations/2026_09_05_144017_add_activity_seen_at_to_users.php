<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this person last looked at the business activity panel.
 *
 * One timestamp rather than a row per activity marked read. The panel is a
 * glance at what has happened, not an inbox to be worked through: "since I
 * last looked" is the only question its badge answers, and a join table would
 * be a row per person per event to answer it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('activity_seen_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activity_seen_at');
        });
    }
};
