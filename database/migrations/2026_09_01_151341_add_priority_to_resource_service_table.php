<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which resource a service would rather have.
 *
 * A service is attached to every resource that could carry it, and until now
 * that list had no order — so "a chair first, a room if the client would
 * rather lie down" could not be said at all. Ordering the resources
 * themselves cannot say it either: the same room is the first choice for a
 * body massage and the second for a reflexology, and one number on the room
 * can only be one of those.
 *
 * So the order belongs to the pairing rather than to either side of it.
 * Lowest first, and equal numbers mean the business has no preference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_service', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(0)->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('resource_service', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
