<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            // Real columns, mirrored in App\Models\Tenant::getCustomColumns().
            // Anything not listed there is swept into the `data` JSON blob and
            // becomes invisible to indexes and joins.
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active')->index();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
