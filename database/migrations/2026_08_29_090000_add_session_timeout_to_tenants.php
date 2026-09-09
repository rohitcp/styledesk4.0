<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long a business lets its people sit idle before signing them out.
 *
 * A business decision rather than a deployment one: a salon with a shared
 * front-desk machine wants a short window, a solo practitioner working alone
 * does not. Stored on the tenant so it applies to everyone in that business.
 *
 * Nullable, meaning "whatever StyleDesk's default is" — a business that has
 * never opened the setting follows the platform, and follows it again if the
 * platform default changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedSmallInteger('session_timeout_minutes')->nullable()->after('default_staff_assignment');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('session_timeout_minutes');
        });
    }
};
