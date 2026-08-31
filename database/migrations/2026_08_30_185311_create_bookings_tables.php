<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An appointment: who, with whom, for what, and when.
 *
 * The services are their own table rather than a column of ids, because a
 * booking is priced and timed from them — a cut and a colour are two lines
 * with two durations and two prices, and the appointment's length is their
 * sum. Both are copied onto the line when the booking is taken: a price list
 * edited in March must not rewrite what somebody was quoted in February.
 *
 * The client is nullable on purpose. A walk-in is a booking with a name and a
 * phone number and no record behind it, and forcing one to exist would fill
 * the client list with people who came in once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 20)->nullable()->index();

            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            /* The walk-in's own details, where there is no client record. */
            $table->string('guest_name')->nullable();
            $table->string('guest_phone', 40)->nullable();
            $table->string('guest_email')->nullable();

            /* Nullable: "any available team member" is a real answer at the
               desk, and the person is settled when the day is worked. */
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('minutes')->default(0);

            /* Draft is a booking still being written; the rest are the states
               an appointment moves through once it exists. */
            $table->string('status', 20)->default('confirmed');
            $table->string('source', 40)->nullable();
            $table->boolean('is_walk_in')->default(false);

            /* Minor units, like every other price in the app. */
            $table->unsignedInteger('total_minor')->default(0);
            $table->string('currency_code', 3)->nullable();
            $table->string('payment_type', 20)->default('none');
            $table->unsignedInteger('deposit_minor')->default(0);
            $table->string('deposit_action', 20)->nullable();

            /* This booking only. A note about the person belongs on the
               person — see client_notes — and the form writes it there. */
            $table->text('notes')->nullable();

            $table->string('confirmation', 20)->default('none');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            /* The two questions asked of this table: what is on today, and
               what has this client booked. */
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'client_id']);
        });

        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            /* Copied rather than looked up: a service renamed or repriced
               later must not rewrite an appointment already taken. */
            $table->string('name');
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->unsignedInteger('price_minor')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_services');
        Schema::dropIfExists('bookings');
    }
};
