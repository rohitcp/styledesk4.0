<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Icon key for the business-type chips.
 *
 * A key, not markup: the SVG lives in a Blade partial, so an administrator
 * editing a type never has to paste a path and can never inject markup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->string('icon', 30)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
