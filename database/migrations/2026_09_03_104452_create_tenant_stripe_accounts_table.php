<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's own Stripe account, connected under the StyleDesk platform.
 *
 * The money belongs to the salon: a client pays, Stripe settles into the
 * salon's connected account, and Stripe pays out to the salon's bank.
 * StyleDesk never holds or redistributes those funds, which is the whole
 * reason for Connect rather than one shared merchant account.
 *
 * Nothing here is a credential. `stripe_account_id` is a reference — it
 * identifies the account, and it cannot move money without the platform's own
 * secret key, which lives in the environment and never in the database.
 *
 * `charges_enabled` and `payouts_enabled` are Stripe's answers, copied on the
 * way through so the checkout can ask a local question rather than an API one
 * on every page load. They are refreshed by the account.updated webhook —
 * a business that fails verification a week later must stop being offered as
 * ready.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_stripe_accounts', function (Blueprint $table) {
            $table->id();

            /* One account per business. Reconnecting overwrites rather than
               accumulating, so there is never a stale account still taking
               money behind the live one. */
            $table->string('tenant_id')->unique();
            $table->string('stripe_account_id')->unique();

            /* What Stripe calls them, for the settings screen. */
            $table->string('business_name')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('default_currency', 3)->nullable();
            /* Last four of the payout bank account. Not the account number —
               enough to recognise, not enough to use. */
            $table->string('payout_last4', 4)->nullable();

            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);

            /* What Stripe is still waiting for, so the screen can say more
               than "not ready". */
            $table->json('requirements')->nullable();

            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_stripe_accounts');
    }
};
