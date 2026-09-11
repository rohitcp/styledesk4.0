<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a client came from, and when they first came.
 *
 * Both are facts about the moment a record is created, and neither could be
 * recovered afterwards. config('clients.creation_sources') already named the
 * ways a client can be added — including a walk-in booking — but only as a
 * settings gate deciding which are allowed; nothing wrote down which one
 * actually happened.
 *
 * `first_visit_at` is written once, at creation, and never again. That is the
 * whole reason it is safe to store: `last_visit_at` on this same table is the
 * cautionary example, a column nothing maintains and every reader believes.
 * A first visit cannot change, so there is nothing to keep true.
 *
 * Null on every existing row, which is honest — those records predate anyone
 * asking, and a backfilled guess would be indistinguishable from a fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->after('status');
            $table->timestamp('first_visit_at')->nullable()->after('last_visit_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['source', 'first_visit_at']);
        });
    }
};
