<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a connection moves real money, and a record of what Stripe told us.
 *
 * `livemode` is deliberately a CACHE of a fact the key already carries. A
 * Stripe secret key is test or live in itself — `sk_test_` or `sk_live_` — so
 * sandbox is not a switch a business can flip. A toggle that claimed
 * otherwise would be the worst possible lie to tell on a payments screen: a
 * salon believing it was testing while charging real cards.
 *
 * It is stored anyway so the settings list, a badge and a report can be drawn
 * without decrypting a secret key on every render, and it is rewritten from
 * the key on every sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_stripe_accounts', function (Blueprint $table) {
            /* Null means "not established yet" — an account row that exists
               because onboarding started but has never been synced. Neither
               true nor false is honest for that. */
            $table->boolean('livemode')->nullable()->after('mode');
        });

        /*
         * Every event Stripe has sent, and what StyleDesk did about it.
         *
         * A payments integration that cannot answer "did that webhook arrive,
         * and what happened to it" is one that gets debugged by asking Stripe.
         * This is the log that makes a failed renewal or a missing refund
         * something the desk can be told about.
         *
         * Not tenant-scoped by a global scope: webhooks arrive with no
         * authenticated user, and a scope that silently returned nothing
         * there would look exactly like "never received".
         */
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            /* Which business it concerned, once that is known. Null while the
               event is being read: some events name an account StyleDesk has
               never seen, and dropping those on the floor unlogged is how a
               misconfigured endpoint stays invisible. */
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();

            /* Stripe's own id. Unique, because Stripe retries: the same event
               arriving twice must be recognised as one, not processed twice.
               That is what makes a renewal charge idempotent end to end. */
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->string('stripe_account_id')->nullable();

            /* received | processing | completed | failed | ignored
             *
             * `ignored` is a real outcome and not a failure: Stripe sends
             * events StyleDesk has no opinion about, and logging those as
             * errors would bury the ones that matter. */
            $table->string('status', 16)->default('received');
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_type'], 'swe_tenant_type_index');
            $table->index(['status', 'received_at'], 'swe_status_received_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');

        Schema::table('tenant_stripe_accounts', function (Blueprint $table) {
            $table->dropColumn('livemode');
        });
    }
};
