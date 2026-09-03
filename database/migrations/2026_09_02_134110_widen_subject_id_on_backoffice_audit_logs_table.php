<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit subject's key is a string, because not every subject has a number.
 *
 * The column was `unsignedBigInteger`, which fits an administrator and fits
 * nothing else the log is meant to hold: a tenant's key is a UUID. Writing one
 * threw, and BackofficeAuditLog::record swallows its exceptions on purpose —
 * so the entries were not recorded and nothing said so. An audit log that
 * silently drops what it cannot type is worse than no audit log, because it is
 * trusted.
 *
 * 64 characters holds a UUID with room to spare; the index is dropped and
 * rebuilt because MySQL will not alter a column a composite index sits on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backoffice_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
        });

        Schema::table('backoffice_audit_logs', function (Blueprint $table) {
            $table->string('subject_id', 64)->nullable()->change();
        });

        Schema::table('backoffice_audit_logs', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('backoffice_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
        });

        Schema::table('backoffice_audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });

        Schema::table('backoffice_audit_logs', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id']);
        });
    }
};
