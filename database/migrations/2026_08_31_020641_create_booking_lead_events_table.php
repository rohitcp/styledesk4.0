<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a lead, in order.
 *
 * The lead's own columns say where it got to; this says how it got there. A
 * front desk picking up somebody else's call needs the second as much as the
 * first — "created at 4:02, service chosen, time chosen, then nothing" is a
 * different conversation from "created at 4:02, rung twice, no answer".
 *
 * Append-only: an event is a thing that happened, and things that happened do
 * not change. Nothing here is edited or deleted by the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_lead_id')->constrained()->cascadeOnDelete();

            /* What happened, as a key the language file names. Free text
               would be forty spellings of "client called". */
            $table->string('kind', 40);

            /* The one thing that varies within a kind — which step, which
               reason, which staff member — kept as a key where it is one and
               as text where a person typed it. */
            $table->string('detail', 255)->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'booking_lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_lead_events');
    }
};
