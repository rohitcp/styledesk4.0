<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a resource needs before a diary can book it.
 *
 * A chair was a name, a category and a capacity. Booking one needs to know
 * when it is open, how long it is occupied either side of the appointment,
 * and which services may claim it.
 *
 * The four periods stay apart rather than folded into one number, for the
 * same reason the service timings do: a room is occupied for the cleanup and
 * the stylist is not, and a single total cannot be taken back apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            /* A business's own reference — CHAIR-01 — for reporting and for
               the label somebody sticks on the thing itself. */
            $table->string('code', 40)->nullable()->after('name');

            /* Available, unavailable, maintenance. Distinct from a block,
               which is a period with a reason and an end: this is the state
               the resource is in right now, set by hand. */
            $table->string('availability_status', 20)->default('available')->after('is_active');

            /* Whose hours it keeps: the location's, or its own. */
            $table->string('availability_type', 20)->default('location')->after('availability_status');

            /* Null means "whatever App Settings says", which is different
               from zero — a resource with no booking interval of its own is
               not a resource booked in zero-minute steps. */
            $table->unsignedSmallInteger('booking_interval_minutes')->nullable()->after('availability_type');
            $table->unsignedSmallInteger('preparation_minutes')->default(0)->after('booking_interval_minutes');
            $table->unsignedSmallInteger('cleanup_minutes')->default(0)->after('preparation_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0)->after('cleanup_minutes');

            /* Never shown to a client: calibration notes, setup instructions,
               what the squeaky wheel needs. */
            $table->text('internal_notes')->nullable()->after('description');
        });

        /*
         * A resource's own opening hours, one row per day it keeps.
         *
         * Rows rather than a JSON column: "which rooms are open on a Sunday"
         * is a question the diary will ask, and a query cannot ask it of a
         * blob without unpacking every resource first.
         */
        Schema::create('resource_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();

            /* 0 is Sunday, matching Carbon's dayOfWeek — one convention, so
               nothing has to translate between two. */
            $table->unsignedTinyInteger('day');
            $table->boolean('is_available')->default(true);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();

            $table->unique(['resource_id', 'day']);
        });

        /*
         * Which services may claim this resource.
         *
         * The other half of the mapping the services module will grow: a
         * service says it needs a room, and this says which rooms will do.
         */
        Schema::create('resource_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->unique(['resource_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_service');
        Schema::dropIfExists('resource_hours');

        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'availability_status', 'availability_type',
                'booking_interval_minutes', 'preparation_minutes',
                'cleanup_minutes', 'buffer_minutes', 'internal_notes',
            ]);
        });
    }
};
