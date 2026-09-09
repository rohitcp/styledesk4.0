<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles become data, per §37.
 *
 * Until now a role was a string on staff.role compared against three
 * hardcoded lists in the codebase. That cannot express what the spec asks for
 * — custom roles, per-permission scopes, an editable matrix — and the three
 * lists were already a set of copies waiting to disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            /**
             * Roles belong to a tenant, including the system ones.
             *
             * A shared global row would mean one business editing Manager
             * changes it for every other business. Each tenant gets its own
             * copy of the five system roles, seeded from the same defaults;
             * `key` is what code recognises, `is_system` is what stops them
             * being deleted or renamed out of existence.
             */
            $table->string('tenant_id');

            $table->string('key', 40);
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);

            // Where the role sits in the list, and which one new staff get.
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // A tenant cannot hold two roles with the same key, which is what
            // makes "custom role names must be unique within tenant" true in
            // the database rather than only in a form request.
            $table->unique(['tenant_id', 'key']);
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            // The catalogue key from config/permissions.php. Deliberately not
            // a foreign key to a permissions table: the catalogue is code, so
            // a row for a permission that no longer exists should be ignored
            // rather than block a deploy.
            $table->string('permission', 60);

            /**
             * Always present, even for a plain toggle, which stores `all`.
             *
             * A nullable scope would make every check ask "is this scoped?"
             * before asking "what scope?", and that second question is the
             * one that gets forgotten.
             */
            $table->string('scope', 20)->default('all');

            $table->timestamps();

            // One row per permission per role: granting twice with different
            // scopes is a contradiction, not two grants.
            $table->unique(['role_id', 'permission']);
        });

        Schema::table('staff', function (Blueprint $table) {
            // Nullable through the backfill; the string column stays for now
            // so nothing breaks mid-migration.
            $table->foreignId('role_id')->nullable()->after('role')->constrained()->nullOnDelete();

            // §37's membership fields.
            $table->string('membership_status', 20)->default('active')->after('is_active');
            $table->boolean('login_enabled')->default(true)->after('membership_status');
            $table->string('invite_status', 20)->default('not-sent')->after('login_enabled');

            $table->index(['tenant_id', 'membership_status']);
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'membership_status']);
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['membership_status', 'login_enabled', 'invite_status']);
        });

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
