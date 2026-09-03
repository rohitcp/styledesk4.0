<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two ways to take card payments through Stripe.
 *
 *   platform  The business connects its own account under StyleDesk's, and
 *             StyleDesk's platform key authorises the charge on it. Onboarding
 *             is Stripe-hosted; StyleDesk never sees a credential.
 *
 *   own       The business already has a Stripe account and supplies its own
 *             secret key. StyleDesk charges directly on that account.
 *
 * The second is a materially different risk and is treated as one. A Stripe
 * secret key is not a reference — it can charge, refund, read every customer
 * and move money, with no scoping. It is encrypted at rest, never rendered
 * back to the screen after it is saved, and never logged.
 *
 * `stripe_account_id` is nullable because a business using its own key has no
 * connected account under the platform: there is nothing for StyleDesk to
 * point at, and inventing an id would make the two modes look alike when they
 * are not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_stripe_accounts', function (Blueprint $table) {
            $table->string('mode', 20)->default('platform')->after('tenant_id');

            /* Encrypted by the model's cast. The column is text because
               ciphertext is far longer than the key it hides. */
            $table->text('api_key')->nullable()->after('stripe_account_id');

            /* Publishable, and therefore not a secret — it is printed in the
               page a client pays on. Stored beside its partner so the two
               cannot drift to different accounts. */
            $table->string('publishable_key')->nullable()->after('api_key');
        });

        /* A business on its own keys has no connected account id. */
        Schema::table('tenant_stripe_accounts', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_stripe_accounts', function (Blueprint $table) {
            $table->dropColumn(['mode', 'api_key', 'publishable_key']);
        });
    }
};
