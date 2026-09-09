<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remaining onboarding fields from spec sections 11, 12, 13 and 14.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Section 12. A service can exist without being publicly bookable
            // — staff-only or add-on services are set up the same way.
            $table->boolean('online_booking_enabled')->default(true)->after('is_active');
            $table->boolean('taxable')->default(true)->after('online_booking_enabled');
            // Calendar colour, stored as a hex string including the hash.
            $table->string('color', 7)->nullable()->after('taxable');
        });

        Schema::table('staff', function (Blueprint $table) {
            // Section 13. Distinct from `role`: role is permissions, job title
            // is what the client sees ("Senior Stylist").
            $table->string('job_title', 100)->nullable()->after('role');

            // Section 14. Nullable rather than defaulting to false, so
            // "not asked yet" stays distinguishable from "answered no".
            $table->boolean('provides_services')->nullable()->after('job_title');
        });

        Schema::table('tenant_onboarding', function (Blueprint $table) {
            // Section 24 lists hours separately from location, and section 11
            // treats it as its own part of setup.
            $table->boolean('hours_completed')->default(false)->after('location_completed');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['online_booking_enabled', 'taxable', 'color']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'provides_services']);
        });

        Schema::table('tenant_onboarding', function (Blueprint $table) {
            $table->dropColumn('hours_completed');
        });
    }
};
