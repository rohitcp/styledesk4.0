<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A booking somebody started.
 *
 * The desk takes an appointment in five steps, and a caller can hang up
 * between any two of them. Until now that left nothing behind: a receptionist
 * who had chosen a client and two services, then lost the call, had no record
 * that the conversation ever happened.
 *
 * A lead is that record. It is written as soon as the services are settled,
 * carries its own reference so it can be quoted back over the phone, and is
 * marked converted when the appointment it became is finally taken. It is not
 * an appointment: it holds no slot and blocks no time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* BL rather than BK, and said over the phone more often than it
               is read: the date makes it easy to find, the digits make it
               unique. */
            $table->string('reference', 32)->unique();

            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();

            /* What had been chosen when the lead was written. A snapshot, not
               a relation: the lead is a record of a conversation, and a
               service deleted next month should not empty it. */
            $table->json('services')->nullable();
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->unsignedInteger('total_minor')->default(0);
            $table->string('currency_code', 3)->nullable();
            $table->date('expected_date')->nullable();

            /* Open until it becomes a booking. Nothing sweeps the old ones
               yet; that is a decision for whoever builds the leads screen. */
            $table->string('status', 20)->default('open');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_leads');
    }
};
