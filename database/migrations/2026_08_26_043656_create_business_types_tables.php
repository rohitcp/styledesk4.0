<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business types (spec section 7).
 *
 * A reference table rather than an enum or a free-text column: the spec says
 * these should eventually be manageable by StyleDesk administrators, which an
 * enum would make a migration every time.
 *
 * Not tenant-scoped — this is a global catalogue every tenant chooses from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A tenant may select more than one, so this is many-to-many.
        Schema::create('business_type_tenant', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('business_type_id')->constrained()->cascadeOnDelete();

            $table->unique(['tenant_id', 'business_type_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_type_tenant');
        Schema::dropIfExists('business_types');
    }
};
