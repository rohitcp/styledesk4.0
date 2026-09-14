<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this client joined the rewards scheme, and who signed them up.
 *
 * Columns on `clients` rather than a table of their own: a client joins once.
 * A join table would say they could join several times, and the first thing
 * anybody would then have to write is the query that picks which enrolment is
 * the real one.
 *
 * `loyalty_enrolled_at` is the whole of "are they a member" — null is not,
 * set is. A separate status column would be a second answer to the same
 * question, and the two would disagree the first time one was written without
 * the other.
 *
 * Every existing client is left null, which is honest: they have points
 * because they came in, not because anybody enrolled them, and backfilling a
 * joining date would be inventing one. Their balances are untouched and go on
 * working — enrolment gates nothing retrospectively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            /* What the client is told to quote. Its own identifier rather
               than client_ref because a member number outlives the record's
               internal one and is the thing printed on a card. */
            $table->string('loyalty_member_id', 24)->nullable()->after('source');

            $table->timestamp('loyalty_enrolled_at')->nullable()->after('loyalty_member_id');

            /* Where and by whom. A walk-in enrolled at the front desk of the
               Riverside branch on a Tuesday is a different fact from one who
               joined online, and neither can be recovered later. */
            $table->foreignId('loyalty_enrolled_by')->nullable()->after('loyalty_enrolled_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('loyalty_enrollment_location_id')->nullable()->after('loyalty_enrolled_by')
                ->constrained('locations')->nullOnDelete();
            $table->string('loyalty_enrollment_source', 32)->nullable()->after('loyalty_enrollment_location_id');

            $table->unique(['tenant_id', 'loyalty_member_id']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'loyalty_member_id']);
            $table->dropConstrainedForeignId('loyalty_enrolled_by');
            $table->dropConstrainedForeignId('loyalty_enrollment_location_id');
            $table->dropColumn(['loyalty_member_id', 'loyalty_enrolled_at', 'loyalty_enrollment_source']);
        });
    }
};
