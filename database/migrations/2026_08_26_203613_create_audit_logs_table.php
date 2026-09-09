<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrative history, per §35.
 *
 * Deliberately generic rather than one table per subject: the questions asked
 * of it — who changed this, when, from what — are the same whether the target
 * was a staff member, a role or a permission, and a table per subject means a
 * new one every time the product grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            // Dotted, matching the permission catalogue's shape:
            // staff.deactivated, roles.permission_removed.
            $table->string('action', 60);

            // Who did it. Nullable because the actor's account may later be
            // deleted, and the record of what happened must outlive them.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();

            // What it was done to, as a polymorphic pair so one table can
            // record a staff member, a role or a business setting.
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            /**
             * Before and after, as JSON.
             *
             * "Role changed" is not useful without both halves, and storing
             * them as columns would mean a schema that knows every field of
             * every subject it will ever record.
             */
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
