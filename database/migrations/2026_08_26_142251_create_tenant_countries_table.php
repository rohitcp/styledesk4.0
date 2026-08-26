<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every country a business operates in.
 *
 * tenants.country_code stays as the primary and is what drives the
 * country-specific filtering on later screens — regions, currency, phone code,
 * timezones. This table records the full set, so a business working across
 * borders can say so without making "which regions do we show?" ambiguous.
 *
 * Position 0 is the primary and is mirrored into tenants.country_code. Keeping
 * the mirror means the hot path — every later screen — reads one column
 * instead of joining, and there is exactly one row that can be position 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_countries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->char('country_code', 2);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'country_code']);
            $table->index(['tenant_id', 'position']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // Existing tenants operate in the country they already recorded.
        foreach (DB::table('tenants')->whereNotNull('country_code')->get(['id', 'country_code']) as $tenant) {
            DB::table('tenant_countries')->insert([
                'tenant_id' => $tenant->id,
                'country_code' => $tenant->country_code,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_countries');
    }
};
