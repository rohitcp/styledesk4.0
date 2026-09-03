<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of what a disable has to remember, and what an enable does.
 *
 * `disabled_reason` already existed as free text; it becomes the reason KEY
 * chosen from config('backoffice.disable_reasons'), and the prose moves to
 * `disabled_note`. A reason that is picked from a list can be counted; one
 * that is typed cannot.
 *
 * `previous_status` is what makes the account restorable to what it was
 * rather than to a guess: a business disabled while past due should come back
 * past due, not active.
 *
 * The enable side is recorded too. "Who turned them back on and why" is asked
 * exactly as often as the disable question, and the audit log is a list to be
 * searched where these are a fact about the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('disabled_note', 1000)->nullable()->after('disabled_reason');
            $table->string('previous_status')->nullable()->after('disabled_note');

            $table->timestamp('enabled_at')->nullable()->after('previous_status');
            $table->foreignId('enabled_by')->nullable()->after('enabled_at')
                ->constrained('backoffice_admins')->nullOnDelete();
            $table->string('enable_note', 1000)->nullable()->after('enabled_by');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enabled_by');
            $table->dropColumn(['disabled_note', 'previous_status', 'enabled_at', 'enable_note']);
        });
    }
};
