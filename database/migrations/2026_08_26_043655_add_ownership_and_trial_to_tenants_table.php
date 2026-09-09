<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant identity and trial fields from spec sections 8, 18 and 24.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Who owns the workspace. Separate from membership: the owner is a
            // single accountable user, not merely someone with owner rights.
            $table->foreignId('owner_user_id')->nullable()->after('slug')->constrained('users')->nullOnDelete();

            $table->string('timezone', 64)->default('UTC')->after('currency');
            $table->string('locale', 10)->default('en')->after('timezone');

            /**
             * Trial and subscription. Payment is never required to create an
             * account, so a new tenant starts on a trial and the subscription
             * module converts it later.
             */
            $table->timestamp('trial_started_at')->nullable()->after('locale');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            $table->string('subscription_status', 20)->nullable()->after('trial_ends_at');
            $table->string('plan_id', 60)->nullable()->after('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn([
                'timezone', 'locale', 'trial_started_at', 'trial_ends_at',
                'subscription_status', 'plan_id',
            ]);
        });
    }
};
