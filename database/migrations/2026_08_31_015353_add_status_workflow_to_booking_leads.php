<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a lead got to, and where the client stopped.
 *
 * Two different questions, kept in two columns on purpose. The status is what
 * happened — nobody has called them back, they asked for time to think, they
 * went elsewhere. The step is how far through the booking they were when the
 * call ended. A front desk needs both: "follow-up required" says to ring, and
 * "deposit & payment" says what to ring about.
 *
 * Folding them into one field is the usual mistake, and it produces a status
 * list that is half workflow and half progress bar, where "abandoned at
 * payment" and "abandoned at service" cannot be told apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_leads', function (Blueprint $table) {
            /* How far through the five cards the booking got. Not a status. */
            $table->string('current_step', 30)->default('service')->after('status');

            /* When somebody last did anything to it. What ages a lead into
               "follow-up required" and then out to "abandoned", and the only
               reason those two statuses can mean anything without a person
               setting them by hand. */
            $table->timestamp('last_activity_at')->nullable()->after('current_step');

            /* The call that was made about it. Kept as four columns rather
               than a note, because "who rang, when, and how" is what a
               manager asks of a queue and free text cannot answer. */
            $table->foreignId('contacted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('contacted_at')->nullable();
            $table->string('contact_method', 30)->nullable();
            $table->string('follow_up_note', 500)->nullable();

            /* Why it ended, where it ended badly. A code for the reports and
               a line of text for the person who reads the row. */
            $table->string('reason_code', 40)->nullable();
            $table->string('reason_note', 500)->nullable();
        });

        /* One status renamed. A lead nobody has touched yet is "new"; a lead
           that became a booking keeps the name it had, because "converted"
           is exactly what it is and it leaves the queue on that basis. */
        DB::table('booking_leads')->where('status', 'open')->update(['status' => 'new']);
        DB::table('booking_leads')->whereNull('last_activity_at')->update([
            'last_activity_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        DB::table('booking_leads')->whereIn('status', ['new', 'in-progress', 'follow-up'])->update(['status' => 'open']);

        Schema::table('booking_leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contacted_by');
            $table->dropColumn([
                'current_step', 'last_activity_at', 'contacted_at',
                'contact_method', 'follow_up_note', 'reason_code', 'reason_note',
            ]);
        });
    }
};
