<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every time a membership credit paid for something.
 *
 * A row rather than only a counter on the credit, for the reason
 * `promotion_redemptions` is a table: "how many are left" is what a counter
 * answers and "which appointment used one, and what it was worth" is not.
 * The second is what a cancellation needs in order to give the credit back,
 * and what the client needs when they ask why they have three left and not
 * four.
 *
 * `value_minor` is what the credit actually took off, worked out at the time.
 * Kept rather than recomputed, because the service may be repriced afterwards
 * and last month's appointment cost what it cost.
 *
 * `released_at` rather than a delete: a credit handed back when a booking was
 * cancelled is a thing that happened, and a row that vanished would leave the
 * client's history with a gap where their explanation used to be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_credit_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('membership_credit_id')->constrained()->cascadeOnDelete();
            /* Denormalised from the credit so "what has this membership paid
               for" is one query rather than a join through every credit. */
            $table->foreignId('client_membership_id')->constrained()->cascadeOnDelete();
            /* Null when the booking is deleted. The redemption stays, because
               the credit was spent whether or not the appointment survived —
               releasing it is a separate, deliberate act. */
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('quantity')->default(1);
            /* What it took off the bill, at the time. */
            $table->unsignedInteger('value_minor')->default(0);

            /* Given back. Set when the appointment it paid for was cancelled,
               declined or marked a no-show — a client whose visit was called
               off has not used their massage. */
            $table->timestamp('released_at')->nullable();

            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* Named, because the generated names run past MySQL's 64
               characters on a table with a name this long. */
            $table->index(['membership_credit_id', 'released_at'], 'mcr_credit_released_index');
            $table->index(['booking_id', 'released_at'], 'mcr_booking_released_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            /* What membership credits took off this bill.
             *
             * Its own column rather than folded into `discount_minor`: a
             * coupon is the business giving money away and a credit is the
             * client spending something they already bought, and a receipt
             * that called them the same thing would be a receipt nobody can
             * reconcile. Both reduce what is owed; only one is a discount. */
            $table->unsignedInteger('membership_credit_minor')->default(0)->after('discount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('membership_credit_minor');
        });

        Schema::dropIfExists('membership_credit_redemptions');
    }
};
