<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The timings and rules a service needs before it can be booked.
 *
 * `duration_minutes` was only ever the part the client is in the chair. A
 * colour service occupies it for far longer than it occupies the stylist,
 * and the room it is in cannot be given to anyone else in between — so the
 * four periods are kept apart rather than folded into one number nobody can
 * take apart afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            /* Set up before, tidy up after: the resource is occupied for
               both, and the staff member is only occupied for the second. */
            $table->unsignedSmallInteger('preparation_minutes')->default(0)->after('duration_minutes');
            /* Colour developing, a treatment settling. The chair is taken;
               the stylist is free to start someone else. */
            $table->unsignedSmallInteger('processing_minutes')->default(0)->after('preparation_minutes');
            $table->unsignedSmallInteger('cleanup_minutes')->default(0)->after('processing_minutes');
            /* Breathing room the business wants after the work is done,
               which is a preference rather than a part of the service. */
            $table->unsignedSmallInteger('buffer_minutes')->default(0)->after('cleanup_minutes');

            /* Whether a booking needs a room or a chair at all. The which
               is the mapping's business; this is only the yes or no. */
            $table->boolean('requires_resource')->default(false)->after('online_booking_enabled');
            $table->boolean('deposit_required')->default(false)->after('requires_resource');
        });

        /*
         * Where a service is offered.
         *
         * A pivot rather than a column: a business with three salons offers
         * most of its list at all three and its laser treatments at one, and
         * a single location_id cannot say that. An empty set reads as "every
         * location", which is what a single-site business never has to think
         * about.
         */
        Schema::create('location_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->unique(['service_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_service');

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'preparation_minutes', 'processing_minutes', 'cleanup_minutes',
                'buffer_minutes', 'requires_resource', 'deposit_required',
            ]);
        });
    }
};
