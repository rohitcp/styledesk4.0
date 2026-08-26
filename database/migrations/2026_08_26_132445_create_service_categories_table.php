<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service categories, owned per tenant.
 *
 * There is no "system category" table. Defaults are copied into each tenant on
 * creation and from then on behave as that tenant's own rows, which is what
 * lets a business rename or deactivate "Waxing" without touching anyone else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Archive rather than delete: a category in use is still the
            // truthful label on historical services.
            $table->softDeletes();

            /**
             * Unique per tenant, not globally — two businesses may both have a
             * "Massage". The column collation is utf8mb4_unicode_ci, so this
             * index is already case-insensitive and "massage" collides with
             * "Massage" as the spec requires.
             */
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('service_category_id')->nullable()->after('name')
                ->constrained('service_categories')->nullOnDelete();
        });

        // Convert the free-text category already captured during onboarding
        // into real rows, so nothing entered so far is lost.
        foreach (DB::table('services')->whereNotNull('category')->get() as $service) {
            $name = trim((string) $service->category);

            if ($name === '') {
                continue;
            }

            $id = DB::table('service_categories')
                ->where('tenant_id', $service->tenant_id)
                ->where('name', $name)
                ->value('id');

            $id ??= DB::table('service_categories')->insertGetId([
                'tenant_id' => $service->tenant_id,
                'name' => $name,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('services')->where('id', $service->id)->update(['service_category_id' => $id]);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_category_id');
        });

        Schema::dropIfExists('service_categories');
    }
};
