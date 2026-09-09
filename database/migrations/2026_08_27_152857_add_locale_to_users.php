<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each person's own interface language.
 *
 * On the user rather than on the tenant, because two colleagues in the same
 * salon may read different languages and changing one must not change the
 * other. The business still decides which languages are on offer; this is the
 * choice a person makes within that.
 *
 * Nullable means "whatever the business uses" — a real answer, and the one
 * almost everybody keeps. Storing a copy of the tenant default instead would
 * freeze people on a language the business later changed away from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
