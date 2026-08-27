<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business's own identity: a favicon, and the three colours it picks.
 *
 * The logo already has a column — onboarding asks for it. These are the rest
 * of what Branding owns.
 *
 * Real columns rather than keys in the tenants `data` blob, because the
 * palette is read on every single page render. A JSON extract per request, for
 * three values that never change shape, is a cost with nothing to show for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('favicon_path')->nullable()->after('logo_path');

            /**
             * Nullable, meaning "StyleDesk's own".
             *
             * Storing the defaults instead would make "has this business
             * chosen a colour" unanswerable, and Reset to default would have
             * nothing to reset to but a copy of the same values.
             */
            $table->string('brand_primary', 7)->nullable()->after('favicon_path');
            $table->string('brand_secondary', 7)->nullable()->after('brand_primary');
            $table->string('brand_accent', 7)->nullable()->after('brand_secondary');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['favicon_path', 'brand_primary', 'brand_secondary', 'brand_accent']);
        });
    }
};
