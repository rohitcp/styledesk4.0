<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a service without losing what it was.
 *
 * A service names the appointments it was booked for, so the row cannot
 * actually go: a hard delete would rewrite last year's takings. Soft, so
 * "delete" means gone from every list while the history that refers to it
 * still resolves — which is what resources have always done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
