<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every currency a business trades in. Position 0 is the primary and is
 * mirrored into tenants.currency_code, which is what services are priced in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->char('currency_code', 3);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'currency_code']);
            $table->index(['tenant_id', 'position']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        foreach (DB::table('tenants')->whereNotNull('currency_code')->get(['id', 'currency_code']) as $tenant) {
            DB::table('tenant_currencies')->insert([
                'tenant_id' => $tenant->id,
                'currency_code' => $tenant->currency_code,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_currencies');
    }
};
