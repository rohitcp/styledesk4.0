<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a business was disabled, by whom, and why.
 *
 * `status` already says whether it is disabled. These say enough to answer the
 * support call that follows — "who turned us off and what for" — without
 * reading the audit log, and they are on the row the login check already
 * loads.
 *
 * Real columns rather than the `data` blob: the base tenant model sweeps any
 * undeclared attribute into JSON, where nothing can index or join on it. They
 * are listed in Tenant::getCustomColumns() for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('status');

            /* The administrator who did it, not a salon user: only the
               platform console can disable a business. Nulled rather than
               cascaded if that administrator is ever removed — the fact that
               it happened outlives the account that did it. */
            $table->foreignId('disabled_by')->nullable()->after('disabled_at')
                ->constrained('backoffice_admins')->nullOnDelete();

            $table->string('disabled_reason')->nullable()->after('disabled_by');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disabled_by');
            $table->dropColumn(['disabled_at', 'disabled_reason']);
        });
    }
};
