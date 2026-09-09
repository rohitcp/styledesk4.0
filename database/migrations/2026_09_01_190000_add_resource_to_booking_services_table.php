<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which room each service on a booking happens in.
 *
 * The booking already carries one resource, which was right while an
 * appointment was one treatment in one place. It stops being right the moment
 * somebody books a massage and then a facial: those are two rooms, at two
 * times, and one column cannot hold both.
 *
 * The booking's own `resource_id` stays as the appointment's principal room —
 * what the header shows, and what a single-service booking means — and these
 * are the per-line answers underneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->foreignId('resource_id')->nullable()->after('service_id')
                ->constrained('resources')->nullOnDelete();

            /* Whether a person chose it.
             *
             * An automatic assignment may be replaced whenever the booking
             * moves; one somebody picked deliberately may not, unless it has
             * actually become impossible. Without this flag the engine would
             * quietly undo a receptionist's decision the next time the clock
             * changed, which is the fastest way to lose their trust in it. */
            $table->boolean('resource_manual')->default(false)->after('resource_id');

            $table->index(['resource_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropForeign(['resource_id']);
            $table->dropIndex(['resource_id', 'booking_id']);
            $table->dropColumn(['resource_id', 'resource_manual']);
        });
    }
};
