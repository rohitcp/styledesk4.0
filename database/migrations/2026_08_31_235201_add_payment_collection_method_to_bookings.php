<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How the money is actually collected, and who let somebody off it.
 *
 * `deposit_action` was the right name while a deposit was the only thing
 * ever collected up front. It is not: a booking can be taken with the whole
 * bill charged there and then, and "deposit_action = later" on a full payment
 * is a column lying about what it holds. So it is renamed for what it is —
 * how this booking's money is collected, whatever amount that is.
 *
 * The old `now` becomes `desk`. It always meant "the desk took it", and the
 * new `collect-now` is the different thing that sits beside it: charge it in
 * this screen, right now, before the booking is finished.
 *
 * Waiving is the one collection method that is a decision rather than a
 * mechanism, so it is the one that gets a name and a date against it. A
 * deposit nobody can say who waived is a deposit nobody is accountable for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('deposit_action', 'collection_method');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('waived_by')->nullable()->after('collection_method')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable()->after('waived_by');
            $table->string('waiver_reason', 300)->nullable()->after('waived_at');
        });

        Schema::table('booking_leads', function (Blueprint $table) {
            $table->renameColumn('deposit_action', 'collection_method');
        });

        Schema::table('booking_leads', function (Blueprint $table) {
            $table->string('waiver_reason', 300)->nullable()->after('collection_method');
        });

        foreach (['bookings', 'booking_leads'] as $table) {
            DB::table($table)->where('collection_method', 'now')->update(['collection_method' => 'desk']);
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'booking_leads'] as $table) {
            DB::table($table)->whereIn('collection_method', ['desk', 'collect-now'])
                ->update(['collection_method' => 'now']);
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waived_by');
            $table->dropColumn(['waived_at', 'waiver_reason']);
            $table->renameColumn('collection_method', 'deposit_action');
        });

        Schema::table('booking_leads', function (Blueprint $table) {
            $table->dropColumn('waiver_reason');
            $table->renameColumn('collection_method', 'deposit_action');
        });
    }
};
