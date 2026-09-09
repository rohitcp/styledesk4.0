<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money against an appointment, and the totals it is measured against.
 *
 * Payment is tracked apart from the booking's own status on purpose: an
 * appointment that has happened and an appointment that has been paid for are
 * two different facts, and a salon has plenty of both kinds of mismatch — the
 * regular who settles at the end of the month, the deposit taken in March
 * against a wedding in June. One column could not hold both without lying
 * about one of them.
 *
 * A payment is a row rather than a column for the same reason: a bill settled
 * half in cash and half on a card is two payments, and a refund later is a
 * third. What is owed is the total; what is paid is the sum of the rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            /* Cash, card, or one of the handles people actually pay salons
               through. Not an enum: the list is config, because which of them
               a business uses is a business's own answer. */
            $table->string('method', 30);
            $table->string('status', 30)->default('paid');

            $table->integer('amount_minor')->default(0);
            $table->string('currency_code', 3)->nullable();

            /* What was handed over and what went back, for cash. Kept because
               the drawer has to balance at the end of the day, and "paid
               $91.80" does not say a hundred was tendered. */
            $table->unsignedInteger('received_minor')->nullable();
            $table->unsignedInteger('change_minor')->nullable();

            /* Whatever identifies the payment outside StyleDesk: a terminal's
               reference, a Zelle confirmation, the last four of a card. Never
               a card number — see the note in the controller. */
            $table->string('reference', 120)->nullable();
            $table->string('note', 255)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'booking_id']);
            $table->index(['tenant_id', 'method']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            /* The bill as it stood when the booking was taken. Recomputing it
               later from the price list would rewrite what somebody was
               charged every time a price or a tax rate changes. */
            $table->unsignedInteger('subtotal_minor')->default(0)->after('total_minor');
            $table->unsignedInteger('discount_minor')->default(0)->after('subtotal_minor');
            $table->unsignedInteger('tax_minor')->default(0)->after('discount_minor');

            $table->string('payment_status', 30)->default('unpaid')->after('deposit_action');
            $table->unsignedInteger('paid_minor')->default(0)->after('payment_status');

            $table->index(['tenant_id', 'payment_status']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            /* A rate to go with the tax behaviour the business already sets.
               Percent, two places, because that is how rates are published. */
            $table->decimal('default_tax_rate', 5, 2)->nullable()->after('default_tax_behavior');

            /* Where money is asked for, when it is not asked for in person.
               These are shown to whoever is taking the payment so they can
               read them out; StyleDesk does not talk to any of these
               services on the business's behalf. */
            $table->string('paypal_handle')->nullable();
            $table->string('zelle_handle')->nullable();
            $table->string('cash_app_handle')->nullable();
            $table->string('venmo_handle')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'payment_status']);
            $table->dropColumn([
                'subtotal_minor', 'discount_minor', 'tax_minor', 'payment_status', 'paid_minor',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'default_tax_rate', 'paypal_handle', 'zelle_handle', 'cash_app_handle', 'venmo_handle',
            ]);
        });
    }
};
