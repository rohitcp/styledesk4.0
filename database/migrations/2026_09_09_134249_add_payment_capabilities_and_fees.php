<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a business lets its payments do, and what each one actually cost.
 *
 * Two additions that look unrelated and are not: both exist so the money on a
 * receipt can be explained. Capabilities decide what may be offered; fees
 * record what was taken out of the amount before it reached the salon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            /*
             * Which payment features this business has switched on.
             *
             * A list rather than a column each, for the same reason
             * `accepted_methods` is: whether a salon takes Tap to Pay is not
             * a decision worth a migration, and the set will grow.
             *
             * Null means "whatever the gateway can do", which is the honest
             * default for a business that has never opened the screen — the
             * alternative is every existing salon waking up with everything
             * switched off.
             */
            $table->json('payment_capabilities')->nullable()->after('accepted_methods');
        });

        Schema::table('booking_payments', function (Blueprint $table) {
            /*
             * What the processor kept, and what the platform kept.
             *
             * Nullable and null by default, because for most payments nobody
             * knows yet: a card charge's fee is settled by Stripe minutes or
             * hours later on the balance transaction, and cash has no fee at
             * all. Zero would be a claim; null is the truth until Stripe
             * says otherwise.
             *
             * Kept apart rather than summed, because "what did Stripe cost
             * us" and "what did StyleDesk charge us" are two questions a
             * salon owner asks separately and gets angry about separately.
             */
            $table->unsignedInteger('processor_fee_minor')->nullable()->after('amount_minor');
            $table->unsignedInteger('platform_fee_minor')->nullable()->after('processor_fee_minor');

            /* What actually landed. Stored rather than derived, because the
               arithmetic depends on which fees are known yet and a column
               that recomputed itself would change under an old receipt. */
            $table->integer('net_minor')->nullable()->after('platform_fee_minor');

            /* Stripe's own id for the settlement line the fee came from, so
               a figure somebody disputes can be traced back to the source
               rather than re-derived. */
            $table->string('balance_transaction_id')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropColumn([
                'processor_fee_minor', 'platform_fee_minor', 'net_minor', 'balance_transaction_id',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('payment_capabilities');
        });
    }
};
