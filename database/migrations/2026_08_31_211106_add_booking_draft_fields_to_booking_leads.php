<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the booking screen has been told, kept on the lead.
 *
 * A lead used to be a note about a call: who rang, what they asked for, and
 * roughly when they wanted it. The booking screen now saves itself into one
 * as it is filled in, and a receptionist who picks that call back up expects
 * the screen exactly as they left it — the branch, the stylist, the time they
 * had settled on, the note they typed. A lead that remembered only the
 * services would make them ask every question twice.
 *
 * The money is copied alongside the services for the reason bookings copy
 * theirs: what somebody was quoted on the phone is not a sum to be worked out
 * again next month against a price list that has moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_leads', function (Blueprint $table) {
            /* The walk-in's own details. The lead already held a name; a name
               with no way to reach them is a call that cannot be returned. */
            $table->string('guest_phone', 40)->nullable()->after('guest_name');
            $table->string('guest_email')->nullable()->after('guest_phone');

            /* Where, with whom, and at what time. Nullable because the whole
               point of a lead is that these are the questions still to ask. */
            $table->foreignId('location_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->after('location_id')->constrained('staff')->nullOnDelete();
            $table->time('starts_at')->nullable()->after('expected_date');

            /* The bill as the summary showed it. `total_minor` was already
               here; these are the lines above it. */
            $table->unsignedInteger('subtotal_minor')->default(0)->after('minutes');
            $table->unsignedInteger('discount_minor')->default(0)->after('subtotal_minor');
            $table->unsignedInteger('tax_minor')->default(0)->after('discount_minor');

            /* The last three cards. `follow_up_note` is what the desk wrote
               about chasing the lead, which is a different thing from the
               note the client asked to be put on the appointment. */
            $table->string('source', 40)->nullable()->after('starts_at');
            $table->string('payment_type', 20)->nullable()->after('deposit_paid_minor');
            $table->string('deposit_action', 20)->nullable()->after('payment_type');
            $table->string('confirmation', 20)->nullable()->after('deposit_action');
            $table->text('notes')->nullable()->after('confirmation');
            $table->text('client_note')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('booking_leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('staff_id');
            $table->dropColumn([
                'guest_phone', 'guest_email', 'starts_at',
                'subtotal_minor', 'discount_minor', 'tax_minor',
                'source', 'payment_type', 'deposit_action', 'confirmation',
                'notes', 'client_note',
            ]);
        });
    }
};
