<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which membership payment a line of points came from.
 *
 * Points have only ever been earned from bookings, so `booking_id` was the
 * whole of "what earned this". Memberships can earn now, and the engine is a
 * reconciler rather than an accumulator — it asks "what should this payment
 * have earned, and what has it already earned" — so it needs to find the
 * lines belonging to one payment. Without this it could only add, and adding
 * is what makes a repeated call award twice.
 *
 * Nullable, because the vast majority of lines are a booking, an expiry or
 * somebody's adjustment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_loyalty_points', function (Blueprint $table) {
            $table->foreignId('membership_payment_id')->nullable()->after('loyalty_reward_id')
                ->constrained('membership_payments')->nullOnDelete();

            $table->index(['membership_payment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('client_loyalty_points', function (Blueprint $table) {
            $table->dropIndex(['membership_payment_id', 'type']);
            $table->dropConstrainedForeignId('membership_payment_id');
        });
    }
};
