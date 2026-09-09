<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the client thought, and how they like to be booked.
 *
 * Two small tables behind the panel the booking screen shows once a client is
 * chosen. Both are about the person rather than the appointment, which is why
 * neither lives on the booking itself: a review outlives the visit it is
 * about, and a preference outlives every visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            /* One review per appointment: a second one is an edit of the
               first, not a second opinion. */
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            /* Whose work is being rated, kept even if the booking's own staff
               is later reassigned: the review is about who did it. */
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();

            /* Whole stars. Half stars read as precision the question does not
               have — nobody means 3.5 rather than 4 about a haircut. */
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'staff_id']);
        });

        Schema::create('client_booking_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /* The preference as it is said at the desk: "Prefers afternoon
               appointments", "No fragranced products". Free text rather than
               a list, because the useful ones are the ones nobody thought to
               put on a list. */
            $table->string('label');

            /* Whether a person said this or the diary worked it out. The two
               are shown differently on purpose: a client who asked for
               afternoons and a client who happens to have booked three of
               them are not the same fact, and a panel that flattened them
               would have receptionists quoting the software back to people
               as though they had said it. Only what a person said is stored
               here; what the diary noticed is worked out when it is read. */
            $table->string('source', 20)->default('client');

            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_booking_preferences');
        Schema::dropIfExists('booking_reviews');
    }
};
