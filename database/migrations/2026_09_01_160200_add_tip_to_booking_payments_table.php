<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tip, kept apart from the bill.
 *
 * Its own column rather than folded into the amount, because it is not the
 * salon's money in the same way: it is owed to whoever did the work, it will
 * eventually be reported on and paid out separately, and a total that has
 * quietly absorbed it can never be taken apart again.
 *
 * On the payment rather than the booking: a client who pays in two halves may
 * tip on one of them, and the tip belongs to the transaction it arrived with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->unsignedInteger('tip_minor')->default(0)->after('amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropColumn('tip_minor');
        });
    }
};
