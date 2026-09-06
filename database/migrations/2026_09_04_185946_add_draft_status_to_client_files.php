<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A file or a treatment somebody started and has not finished.
 *
 * Uploading is now a page rather than a dialog, and a page is a sitting: a
 * stylist photographs the "before", is called away, and comes back after the
 * appointment to add the "after". Without somewhere to put that, the choice
 * is to hold half a treatment in a browser tab or to lose it.
 *
 * `status` rather than a boolean, because "draft" is a state a record is in
 * and not a property it has — and a third state (archived, superseded) is a
 * value here rather than a second column that can contradict the first.
 *
 * `batch_id` groups the files that arrived in one action. A standard upload
 * of four consultation photographs is four rows sharing one name, one note
 * and one category; reopening that draft has to bring back all four, and
 * without a group there is nothing to bring back but whichever row was
 * clicked. Every upload gets one, draft or not, so resuming and editing read
 * the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_files', function (Blueprint $table) {
            $table->string('status', 20)->default('saved')->after('category');
            $table->uuid('batch_id')->nullable()->after('record_id');

            /* Listed together: the Files tab asks for one client's drafts,
               and one upload's rows are fetched as a group. */
            $table->index(['client_id', 'status']);
            $table->index('batch_id');
        });

        Schema::table('client_file_records', function (Blueprint $table) {
            $table->string('status', 20)->default('saved')->after('title');

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('client_files', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'status']);
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['status', 'batch_id']);
        });

        Schema::table('client_file_records', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
