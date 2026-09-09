<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client as they were when this appointment was taken.
 *
 * Behavioural tags and insights are worked out from the diary, so they move:
 * a client who books every four weeks this year may book every eight next
 * year. The profile should say the new thing — and the booking from March
 * should still say what was true in March, or every historical record
 * quietly rewrites itself as the person changes.
 *
 * A snapshot rather than a set of columns because it is read whole and never
 * queried: nothing filters bookings by what a tag said at the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('client_snapshot')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('client_snapshot');
        });
    }
};
