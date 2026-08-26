<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Languages a business supports. Position 0 is the primary and is mirrored
 * into tenants.default_language, the same arrangement as countries and
 * currencies: the table is the record, the mirrored column is the hot path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_languages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('language_code', 10);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'language_code']);
            $table->index(['tenant_id', 'position']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        foreach (DB::table('tenants')->whereNotNull('default_language')->get(['id', 'default_language']) as $tenant) {
            DB::table('tenant_languages')->insert([
                'tenant_id' => $tenant->id,
                'language_code' => $tenant->default_language,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_languages');
    }
};
