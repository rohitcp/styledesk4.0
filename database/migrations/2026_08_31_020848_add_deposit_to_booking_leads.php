<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a lead was going to be asked to pay up front.
 *
 * Captured from the booking screen's fourth card, which most leads never
 * reach — which is exactly why it is worth recording when one does. "Waiting
 * on a $25 deposit" is a different call from "waiting on a decision".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_leads', function (Blueprint $table) {
            $table->unsignedInteger('deposit_minor')->default(0)->after('total_minor');
            $table->unsignedInteger('deposit_paid_minor')->default(0)->after('deposit_minor');
        });
    }

    public function down(): void
    {
        Schema::table('booking_leads', function (Blueprint $table) {
            $table->dropColumn(['deposit_minor', 'deposit_paid_minor']);
        });
    }
};
