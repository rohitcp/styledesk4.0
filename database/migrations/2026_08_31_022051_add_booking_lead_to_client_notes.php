<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The note written while chasing a booking that was never finished.
 *
 * It belongs on the client — that is where anybody looking this person up
 * will read it, and a note kept only against a lead would be invisible the
 * next time they call. But which call it was written about is worth keeping:
 * "rang, no answer" means something different against a lead for a wedding
 * than against a walk-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->foreignId('booking_lead_id')->nullable()->after('client_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_lead_id');
        });
    }
};
