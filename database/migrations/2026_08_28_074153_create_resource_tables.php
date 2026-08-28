<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resources: the physical things a booking needs as well as a person.
 *
 * A chair, a treatment room, a sauna. They are separate from staff because
 * they are booked on different rules — two stylists cannot share a chair, but
 * a couples massage room holds two clients at once, and a room can be out for
 * maintenance while everybody who works in it is available.
 *
 * Three tables, and the reason for each:
 *
 *  - categories, so "any available styling chair" is a thing a service can
 *    ask for without naming every chair in the building;
 *  - resources themselves, each belonging to one location, because a chair
 *    is in a room in a building and cannot be double-booked across branches;
 *  - blocks, because "unavailable" is a period with a reason and an end,
 *    not a flag. A flag cannot say when the room is back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_categories', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            $table->string('name');
            $table->string('description')->nullable();

            /** What a new resource in this category starts with. */
            $table->unsignedSmallInteger('default_capacity')->default(1);

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'position']);
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            $table->foreignId('resource_category_id')->nullable()->constrained()->nullOnDelete();

            /**
             * Where it physically is.
             *
             * Nullable only so a resource can be created before the business
             * has finished setting up its branches; everything that books one
             * reads this, and a resource with no location cannot be scheduled.
             */
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            /**
             * How many clients it holds at once.
             *
             * A massage room is 1 and a couples room is 2 — the difference
             * between them is this number, not two different kinds of thing.
             */
            $table->unsignedSmallInteger('capacity')->default(1);

            $table->unsignedInteger('position')->default(0);

            /**
             * Retired rather than deleted.
             *
             * A chair that is gone still appears in last year's appointments,
             * and deleting it would rewrite them.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'location_id']);
            $table->index(['tenant_id', 'resource_category_id']);
        });

        Schema::create('resource_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();

            /** maintenance, cleaning, repair, closure, other. */
            $table->string('reason', 40);
            $table->string('note')->nullable();

            /**
             * A period, not a flag.
             *
             * `ends_at` nullable is "until further notice" — a repair with no
             * date yet is a real state, and forcing an invented end date would
             * put a lie in the calendar.
             */
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'resource_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_blocks');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('resource_categories');
    }
};
