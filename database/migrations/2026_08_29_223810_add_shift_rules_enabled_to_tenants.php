<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            /**
             * Whether this business uses shift rules at all.
             *
             * Default on, not off: the feature already exists and businesses
             * already have rules, and shipping a switch that defaults to off
             * would make everybody's rules disappear on deploy. The switch is
             * there to be turned off by a business that does not want it.
             *
             * Turning it off hides the feature and keeps every row — the
             * rules are still there when it comes back on, which is the whole
             * difference between this and deleting them.
             */
            $table->boolean('shift_rules_enabled')->default(true)->after('session_timeout_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('shift_rules_enabled');
        });
    }
};
