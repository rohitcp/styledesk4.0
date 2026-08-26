<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 17: the getting-started checklist is dismissible by the owner.
 *
 * Stored server-side rather than in localStorage so the dismissal follows the
 * owner between browsers and devices, the same reasoning as onboarding
 * progress itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_onboarding', function (Blueprint $table) {
            $table->timestamp('getting_started_dismissed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_onboarding', function (Blueprint $table) {
            $table->dropColumn('getting_started_dismissed_at');
        });
    }
};
