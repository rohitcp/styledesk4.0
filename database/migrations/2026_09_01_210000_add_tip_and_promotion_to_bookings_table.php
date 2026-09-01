<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tip and the coupon a booking was taken with.
 *
 * Both were being chosen on the booking screen and then thrown away, so the
 * till started again from nothing: a receptionist who agreed fifteen per cent
 * with the client while taking the booking had to remember and re-enter it at
 * the counter.
 *
 * `tip_percent` and `tip_minor` are what was *intended*, not what was
 * collected — the money is on the payment, where it belongs. This is the
 * answer the till starts from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            /* One or the other. A percentage moves with the bill; an amount
               somebody typed does not, and storing both would leave the till
               guessing which the reader meant. */
            $table->unsignedTinyInteger('tip_percent')->nullable()->after('paid_minor');
            $table->unsignedInteger('tip_minor')->nullable()->after('tip_percent');

            /* Which promotion took the discount off. `discount_minor` already
               holds how much; this says why, so a bill can be explained
               months later. */
            $table->foreignId('promotion_id')->nullable()->after('discount_minor')
                ->constrained('promotions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropColumn(['tip_percent', 'tip_minor', 'promotion_id']);
        });
    }
};
