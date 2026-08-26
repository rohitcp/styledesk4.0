<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the Business settings screen shows that onboarding never asked for.
 *
 * All real columns rather than entries in the tenants `data` JSON: every one
 * of these is displayed on a page, and several are searched or reported on
 * later. Anything added here must also be listed in Tenant::getCustomColumns(),
 * or stancl/tenancy sweeps it into `data` and the column stays empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // --- identity
            // The name on the paperwork, which is often not the trading name.
            $table->string('legal_name')->nullable()->after('name');
            $table->string('business_category')->nullable()->after('legal_name');
            $table->text('description')->nullable()->after('business_category');

            // --- contact
            // Separate from business_email: the address clients write to for
            // help is rarely the one that appears on a booking confirmation.
            $table->string('support_email')->nullable()->after('business_email');
            $table->string('booking_email')->nullable()->after('support_email');

            // --- regional display
            $table->string('date_format', 20)->nullable()->after('default_language');
            $table->string('time_format', 4)->nullable()->after('date_format');
            /**
             * 0 = Sunday through 6 = Saturday, matching PHP's `w`.
             *
             * Nullable rather than defaulting to 0: "not chosen" and "chose
             * Sunday" are different, and the app falls back on the country's
             * convention only while the answer is genuinely unknown.
             */
            $table->unsignedTinyInteger('first_day_of_week')->nullable()->after('time_format');

            // --- operational defaults
            $table->unsignedSmallInteger('default_booking_duration')->nullable()->after('first_day_of_week');
            $table->unsignedSmallInteger('default_appointment_interval')->nullable()->after('default_booking_duration');
            $table->string('default_tax_behavior', 20)->nullable()->after('default_appointment_interval');
            $table->string('default_staff_assignment', 20)->nullable()->after('default_tax_behavior');

            // --- presence
            $table->string('instagram_url')->nullable()->after('website');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('tiktok_url')->nullable()->after('facebook_url');
            $table->string('google_business_url')->nullable()->after('tiktok_url');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'business_category', 'description',
                'support_email', 'booking_email',
                'date_format', 'time_format', 'first_day_of_week',
                'default_booking_duration', 'default_appointment_interval',
                'default_tax_behavior', 'default_staff_assignment',
                'instagram_url', 'facebook_url', 'tiktok_url', 'google_business_url',
            ]);
        });
    }
};
