<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A card StyleDesk can charge again, without StyleDesk holding the card.
 *
 * The whole point of this table is what is NOT in it. There is no column for
 * a card number, a CVC, a PIN or anything else a thief could use: the card
 * lives at the gateway, which is PCI-compliant and whose business that is,
 * and what StyleDesk keeps is the reference it was handed back plus the four
 * digits and the expiry a receptionist needs in order to say "the Visa ending
 * 4242" out loud.
 *
 * Adding a raw card column here later would move this business inside PCI
 * scope in a single migration. Don't.
 *
 * The gateway is named on every row rather than assumed. A salon that moves
 * from Stripe to Square has cards at both for a while, and a token is only
 * meaningful to the gateway that issued it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /* Which vault holds it. A payment method id is a reference into
               one gateway's records and means nothing to another. */
            $table->string('gateway', 24);
            /* Who the client is to that gateway. Kept alongside the method
               because charging off-session needs both, and looking the
               customer up again on every renewal is a round trip for a fact
               that never changes. */
            $table->string('gateway_customer_id');
            $table->string('gateway_payment_method_id');

            /* Everything below here exists so a person can recognise their
               own card. None of it can be used to charge one. */
            $table->string('brand', 24)->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedSmallInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();

            /* Which one renewals reach for. Exactly one per client, kept true
               by ClientPaymentMethod::makeDefault. */
            $table->boolean('is_default')->default(false);

            /* active | expired | removed
             *
             * Removed rather than deleted: a membership that was renewed on
             * this card last month still points at it, and a row that
             * vanished would leave that payment unexplained. */
            $table->string('status', 16)->default('active');
            $table->timestamp('removed_at')->nullable();

            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* The same card saved twice is one card. The gateway gives a new
               id per save, so this catches a double-submit rather than a
               genuine second card. */
            $table->unique(['gateway', 'gateway_payment_method_id'], 'cpm_gateway_method_unique');
            $table->index(['client_id', 'status'], 'cpm_client_status_index');
        });

        Schema::table('client_memberships', function (Blueprint $table) {
            /* Which card renews this membership.
             *
             * Nullable, and null is a real answer: a package never renews, and
             * a recurring membership sold before the vault existed has no card
             * against it. Null on delete rather than cascade — losing the card
             * must not take the membership with it, it must make the renewal
             * ask for a new one. */
            $table->foreignId('payment_method_id')->nullable()->after('location_id')
                ->constrained('client_payment_methods')->nullOnDelete();

            /* Whether it actually renews.
             *
             * Separate from the plan's own type: a plan may be sold as a
             * one-off where the business allows it, and what this membership
             * agreed to is what governs it. */
            $table->boolean('auto_renew')->default(false)->after('next_billing_on');
        });
    }

    public function down(): void
    {
        Schema::table('client_memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('auto_renew');
        });

        Schema::dropIfExists('client_payment_methods');
    }
};
