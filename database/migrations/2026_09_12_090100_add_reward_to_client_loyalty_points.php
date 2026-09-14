<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which reward a redemption was for, and what it was worth on the day.
 *
 * The ledger already knew that five hundred points left the balance. It did
 * not know what the client got for them, so "what are people actually
 * redeeming" was a question the business could not ask, and a client querying
 * a line could only be told a number.
 *
 * `reward_value_minor` is a snapshot, for the same reason a membership copies
 * its price at the moment of sale: the catalogue is what the business offers
 * today, and a reward repriced in June must not rewrite what somebody was
 * given in March. The name is not copied — `loyalty_rewards` rows are
 * deactivated rather than deleted, so the reward is still there to read, and
 * a renamed reward is the same reward.
 *
 * Both nullable: every line already in this table predates the catalogue, and
 * an earning, an expiry and a manual adjustment are never for a reward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_loyalty_points', function (Blueprint $table) {
            $table->foreignId('loyalty_reward_id')->nullable()->after('booking_id')
                ->constrained('loyalty_rewards')->nullOnDelete();

            $table->unsignedInteger('reward_value_minor')->nullable()->after('loyalty_reward_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_loyalty_points', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loyalty_reward_id');
            $table->dropColumn('reward_value_minor');
        });
    }
};
