<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Country, currency and language on the tenant.
 *
 * Renames `currency` and `locale` to the names the specification uses. Keeping
 * two vocabularies for the same values is how a codebase ends up with both
 * spellings and a bug where one is written and the other read.
 *
 * All three hold ISO codes rather than display names: ISO 3166-1 alpha-2 for
 * the country, ISO 4217 for the currency, ISO 639-1 for the language. Display
 * names live in config, so renaming "United Kingdom" never rewrites data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('slug');
            $table->renameColumn('currency', 'currency_code');
            $table->renameColumn('locale', 'default_language');
        });

        // Existing rows were seeded from the location's country; carry that
        // across so nobody's currency silently resets.
        DB::table('tenants')->whereNull('country_code')->update(['country_code' => 'US']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('currency_code', 'currency');
            $table->renameColumn('default_language', 'locale');
            $table->dropColumn('country_code');
        });
    }
};
