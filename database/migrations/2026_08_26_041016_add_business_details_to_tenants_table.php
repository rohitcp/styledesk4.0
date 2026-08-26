<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business details captured by onboarding step 1.
 *
 * Fields mirror onboarding-business.html exactly. `slug` and `name` already
 * exist on the table; `slug` doubles as the public booking subdomain.
 *
 * Anything added here must also be listed in Tenant::getCustomColumns(), or
 * the base model sweeps it into the `data` JSON blob and it becomes invisible
 * to indexes and joins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('business_phone', 32)->nullable()->after('status');
            $table->char('business_phone_country', 2)->nullable()->after('business_phone');
            $table->string('business_email')->nullable()->after('business_phone_country');
            $table->string('website')->nullable()->after('business_email');
            $table->string('logo_path')->nullable()->after('website');

            // Never asked for during onboarding; inferred from the location's
            // country and editable later in Settings. Services price in this.
            $table->char('currency', 3)->default('USD')->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'business_phone', 'business_phone_country', 'business_email',
                'website', 'logo_path', 'currency',
            ]);
        });
    }
};
