<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A membership's price, once per currency it is sold in.
 *
 * The same shape service_prices uses, for the same reason: a business pricing
 * in three currencies sets three prices, and nothing here converts. $150 and
 * C$205 are two decisions the business made, not one decision and an exchange
 * rate that moves overnight — a client quoted a price should be charged that
 * price whatever the market did between the quote and the till.
 *
 * Not tenant-scoped directly: it hangs off a plan, which already is, and a
 * second tenant_id could contradict its parent's.
 *
 * The fees travel with the price rather than staying on the plan. A CAD sale
 * taking a USD joining fee is the bug that would otherwise be waiting here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);

            $table->unsignedBigInteger('price_minor');
            /* Package only: what the included services would have cost bought
               separately, which is the claim the saving is worked out from. */
            $table->unsignedBigInteger('regular_value_minor')->nullable();
            /* Recurring only. Null and nought differ: no joining fee at all
               against a joining fee of nothing. */
            $table->unsignedBigInteger('joining_fee_minor')->nullable();
            $table->unsignedBigInteger('setup_fee_minor')->nullable();

            $table->timestamps();

            $table->unique(['membership_plan_id', 'currency_code'], 'mpp_plan_currency_unique');
        });

        /* Every plan priced before today keeps its price, in the currency it
           was priced in. Copied rather than left to a fallback: a plan with
           no row would be a plan the sale screen cannot price, and the
           columns it comes from are about to stop being read. */
        $primary = DB::table('tenants')->pluck('currency_code', 'id');

        DB::table('membership_plans')->orderBy('id')->chunk(200, function ($plans) use ($primary) {
            $rows = [];

            foreach ($plans as $plan) {
                $rows[] = [
                    'membership_plan_id' => $plan->id,
                    'currency_code' => mb_strtoupper((string) ($primary[$plan->tenant_id] ?? 'USD')),
                    'price_minor' => (int) $plan->price_minor,
                    'regular_value_minor' => $plan->regular_value_minor,
                    'joining_fee_minor' => $plan->joining_fee_minor,
                    'setup_fee_minor' => $plan->setup_fee_minor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                DB::table('membership_plan_prices')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plan_prices');
    }
};
