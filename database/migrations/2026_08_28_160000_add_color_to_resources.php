<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The colour a resource is drawn in.
 *
 * The availability view puts rooms and chairs side by side down a day, and a
 * column of identically grey lanes is one nobody can scan. Same column type
 * and same palette as a service's, because the two appear on the same screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
