<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which room or chair the appointment is actually in.
 *
 * Until now a booking knew what was being done and who was doing it, but not
 * where — so nothing stopped two clients being booked into the same room at
 * the same hour. Counting how many rooms exist is not the same as knowing
 * which one is free.
 *
 * One resource per booking rather than one per service on it: a client lies
 * on one bed for the whole appointment, and a cupping added to a massage
 * happens in the room they are already in rather than consuming a second.
 *
 * Nullable, because it has to be. Every booking taken before this column
 * existed has no answer, and a business that needs no rooms at all — a mobile
 * therapist, a barber with nothing but chairs they do not track — never will.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('resource_id')->nullable()->after('location_id')
                ->constrained('resources')->nullOnDelete();

            /* How the engine asks "what is taken that day": by resource and
               date, which is the only question it puts to this column. */
            $table->index(['resource_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['resource_id']);
            $table->dropIndex(['resource_id', 'date']);
            $table->dropColumn('resource_id');
        });
    }
};
