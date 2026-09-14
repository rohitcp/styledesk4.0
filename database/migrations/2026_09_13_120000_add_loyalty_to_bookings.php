<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points spent against a booking, and what they took off it.
 *
 * Its own pair of columns rather than being folded into `discount_minor`, for
 * the same reason `membership_credit_minor` is its own: a coupon is the
 * business giving money away and a redemption is a client spending something
 * they already earned. One column for both makes "what did we discount" and
 * "what did members spend" the same unanswerable number.
 *
 * `loyalty_points` is kept beside the money because the two can only be
 * derived from each other through a rate that may change. What the client
 * spent was five hundred points; what it was worth was five pounds on the day
 * — and a business that repriced its rewards in June has not changed what
 * somebody was given in March.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points')->default(0)->after('membership_credit_minor');
            $table->unsignedInteger('loyalty_discount_minor')->default(0)->after('loyalty_points');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points', 'loyalty_discount_minor']);
        });
    }
};
