<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents and treatment photos on a client's record.
 *
 * Two tables rather than one, because a before-and-after is not a file. It is
 * a treatment somebody carried out — a title, a day, a service, the member of
 * staff who did it — that happens to have photographs of both ends of it. Six
 * loose images tagged "before" would answer "what pictures are on this
 * client" and nothing about which visit they belong to, and the whole point of
 * the pair is the comparison.
 *
 * So `client_file_records` is the treatment, and `client_files` is one file.
 * A standard upload is a `client_files` row with no record: the same row shape
 * either way, so the All Files view is one query rather than a union of two.
 *
 * Neither table holds a path. `stored_file_id` points at `stored_files`, which
 * is the one place that knows which disk a file landed on — see
 * App\Services\Storage\TenantStorageService.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | One treatment, photographed at both ends.
        */
        Schema::create('client_file_records', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /** What the treatment was called, in the salon's own words. */
            $table->string('title');

            /* The service and the booking it belongs to, where it belongs to
               one. Optional in both directions: a treatment photographed
               today may have been walked in for, and a record whose booking
               is later deleted is still a record of work that was done. */
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();

            /* The day the work happened, which is not the day the photographs
               were uploaded — a stylist who photographs Friday's colour on
               Monday morning has not moved the appointment. */
            $table->date('treatment_date');

            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['client_id', 'treatment_date']);
        });

        /*
        | One file on a client, whether or not it belongs to a treatment.
        */
        Schema::create('client_files', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /* The stored file itself. Cascades: a client_files row whose
               stored_file is gone describes nothing. */
            $table->foreignId('stored_file_id')->constrained('stored_files')->cascadeOnDelete();

            /**
             * The treatment this is part of, and which end of it.
             *
             * Null for a standard upload. `side` is only ever meaningful
             * beside a record — a consent form is neither before nor after.
             */
            $table->foreignId('record_id')->nullable()->constrained('client_file_records')->cascadeOnDelete();
            $table->string('side', 10)->nullable();

            /* What the person called it, which is not what the file is called
               on disk and not necessarily what it was called on theirs. */
            $table->string('name');
            $table->text('note')->nullable();

            /** Consent form, intake, receipt — the business's own filing. */
            $table->string('category', 60)->nullable();

            /* What it is about, for a document that relates to one service or
               one visit without being a treatment record. */
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            /** Where it sits among the others in its record. */
            $table->unsignedInteger('position')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            /* Soft deleted for the same reason stored_files is: a document
               removed from a client's record is a thing that has to be
               accounted for afterwards. */
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['record_id', 'side', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_files');
        Schema::dropIfExists('client_file_records');
    }
};
