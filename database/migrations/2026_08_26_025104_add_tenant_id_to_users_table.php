<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership link for the central authenticated app.
 *
 * StyleDesk is one-tenant-per-user, so membership is a single foreign key
 * rather than a pivot. On styledesk.app the active tenant is resolved from
 * this column (see App\Http\Middleware\InitializeTenancyFromUser); the
 * subdomain middleware is only used for public booking routes.
 *
 * Nullable because a user exists for a moment during sign-up, before the
 * business they are creating has been persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
