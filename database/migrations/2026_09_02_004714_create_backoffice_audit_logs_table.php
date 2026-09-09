<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What administrators did to the platform.
 *
 * Separate from `audit_logs`, which is a tenant's own history of its own
 * settings and belongs to that tenant. This one records StyleDesk acting on
 * its customers — suspending an account, changing a plan, disabling a
 * colleague — and no tenant may ever read it.
 *
 * Append-only, and written even when the actor is unknown: a failed sign-in
 * naming an address nobody has is exactly the row somebody will want later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backoffice_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_id')->nullable()->constrained('backoffice_admins')->nullOnDelete();

            /* The actor's name and address as they were, beside the id rather
               than instead of it. A history is read years later, and a row
               that can only name its actor through a live relation stops
               naming them the moment that account is gone. */
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();

            $table->string('action')->index();

            /* What it was done to, where there is one. Kept as a type and an
               id rather than a foreign key: the subject may be a tenant, a
               plan, an invoice or another administrator, and a column per
               kind would be five nulls on every row. */
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->index(['subject_type', 'subject_id']);

            /* Before and after, so "who changed this and what was it" has an
               answer rather than only "somebody saved this record". */
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            /* Created only. There is no updated_at because there is no update:
               a history that can be edited answers no question worth asking. */
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backoffice_audit_logs');
    }
};
