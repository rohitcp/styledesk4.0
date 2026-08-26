<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One price per service per currency.
 *
 * A row rather than a column per currency, because the set of currencies is
 * the tenant's to choose and adding one must not mean a migration. Prices stay
 * in minor units for the same reason as before: an integer count of the
 * smallest unit cannot drift the way a float does.
 *
 * Prices are entered independently per currency rather than converted from the
 * primary — a salon charging 75 USD does not charge whatever today's rate makes
 * that in CAD, it charges a round number it chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->char('currency_code', 3);
            $table->unsignedInteger('price_minor')->default(0);
            $table->timestamps();

            $table->unique(['service_id', 'currency_code']);
        });

        // Carry the single price across as the tenant's primary currency.
        $services = DB::table('services')
            ->join('tenants', 'tenants.id', '=', 'services.tenant_id')
            ->get(['services.id', 'services.price_minor', 'tenants.currency_code']);

        foreach ($services as $service) {
            DB::table('service_prices')->insert([
                'service_id' => $service->id,
                'currency_code' => $service->currency_code ?: 'USD',
                'price_minor' => $service->price_minor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('price_minor');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('price_minor')->default(0)->after('duration_minutes');
        });

        Schema::dropIfExists('service_prices');
    }
};
