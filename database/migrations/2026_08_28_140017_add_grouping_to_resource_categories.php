<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a resource category is, beyond its name.
 *
 * Three facts the catalogue could not previously hold: which section of the
 * list it belongs to, whether StyleDesk supplied it, and — for the ones
 * StyleDesk supplied — which default it is.
 *
 * `key` is what makes seeding idempotent after a rename. Matching on the name
 * meant a business that renamed "Styling chair" to "Chair" got a second
 * styling chair category the next time defaults were seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_categories', function (Blueprint $table) {
            /* The heading it sits under: Chairs & stations, Rooms, Equipment.
               Nullable, because a category somebody invents belongs wherever
               they put it and may belong nowhere. */
            $table->string('group', 60)->nullable()->after('name');

            /* A system category may be deactivated and reordered but never
               deleted: a default that can be removed is a default a business
               has to be able to get back, and there is no way back. */
            $table->boolean('is_system')->default(false)->after('group');

            $table->string('key', 60)->nullable()->after('is_system');
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('resource_categories', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
            $table->dropColumn(['group', 'is_system', 'key']);
        });
    }
};
