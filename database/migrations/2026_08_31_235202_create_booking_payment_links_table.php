<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request to pay, sent to the client to answer in their own time.
 *
 * Its own table rather than columns on the booking, because a booking can be
 * asked twice: a deposit link in March that expired, and a balance link in
 * April that was paid. Two rows, two amounts, two answers — and "we sent it
 * and they never opened it" is the useful half of that history.
 *
 * The status is what the desk reads. Sent is what we did; opened is what they
 * did; paid is what arrived; expired is what ran out. Only the first two can
 * be known from this side of the link, and paid is settled from the payments
 * against the booking, because StyleDesk takes no money itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            /* What the client is being asked for, copied rather than derived:
               a link sent for a $50 deposit must keep asking for $50 after
               somebody adds a service to the booking. */
            $table->unsignedInteger('amount_minor');
            $table->string('currency_code', 3)->nullable();

            /* Long and random. The link is the whole credential — anybody
               holding it can read the appointment it names — so it has to be
               unguessable rather than merely unique. */
            $table->string('token', 64)->unique();

            $table->string('status', 20)->default('sent');
            $table->string('channel', 20)->nullable();
            $table->string('sent_to')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payment_links');
    }
};
