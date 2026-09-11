<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reserved is not the same as used.
 *
 * A credit held against next Thursday's appointment is spoken for — it cannot
 * be spent twice — but the client has not had their massage yet, and a screen
 * that called it used would be telling them they had. The counter on the
 * credit already covers "spoken for"; what was missing is which of the two it
 * is, which is a fact about the redemption rather than about the balance.
 *
 * Null means reserved. Set means the client walked in and took it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_credit_redemptions', function (Blueprint $table) {
            $table->timestamp('consumed_at')->nullable()->after('released_at');

            /* Named, like its siblings: the generated name runs past MySQL's
               64 characters on a table with a name this long. */
            $table->index(['client_membership_id', 'consumed_at'], 'mcr_membership_consumed_index');
        });

        /* What is already true, written down.
         *
         * A redemption on an appointment the client has been checked in for
         * was consumed at that moment. Backdating it to the booking's own
         * time rather than to now keeps the history honest — these visits
         * happened before this column existed, not when it was added. */
        DB::table('membership_credit_redemptions')
            ->whereNull('released_at')
            ->whereIn('booking_id', DB::table('bookings')
                ->whereIn('status', ['arrived', 'completed'])
                ->select('id'))
            ->update(['consumed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('membership_credit_redemptions', function (Blueprint $table) {
            $table->dropIndex('mcr_membership_consumed_index');
            $table->dropColumn('consumed_at');
        });
    }
};
