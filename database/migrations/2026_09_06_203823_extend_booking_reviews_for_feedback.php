<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A review row now starts before the client has said anything.
 *
 * The table was written for a rating somebody had already given. Asking for
 * one changes when the row begins: it is created the moment a booking is
 * completed, carrying the link that will be sent and the time it is due, and
 * it stays unanswered until the client taps a star. That is why `rating`
 * becomes nullable — an unanswered request is a real row, and a zero there
 * would read as one star.
 *
 * Location and service are stored rather than read through the booking, for
 * the same reason `staff_id` already is: the review is about the visit that
 * happened. A service repriced or a room renamed afterwards must not move
 * what somebody said about a Tuesday in September.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_reviews', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('staff_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->after('location_id')
                ->constrained()->nullOnDelete();

            /* The whole credential, 64 random characters, never derived from
               anything about the booking — the same rule the payment link
               follows. Nullable because a review recorded at the desk was
               never asked for by link. */
            $table->string('token', 64)->nullable()->unique()->after('service_id');

            /* Which way it was asked. Stored even for `sms`, which nothing
               can deliver yet: what the business chose is worth keeping
               whether or not StyleDesk could act on it. */
            $table->string('channel', 10)->nullable()->after('token');

            /* Due, sent, answered. Three clocks rather than one, because a
               request that was scheduled and never sent and one that was sent
               and never answered are different things to look at. */
            $table->timestamp('scheduled_for')->nullable()->after('channel');
            $table->timestamp('sent_at')->nullable()->after('scheduled_for');
            $table->timestamp('submitted_at')->nullable()->after('sent_at');
            $table->timestamp('google_opened_at')->nullable()->after('submitted_at');

            /* Would you recommend us — yes, maybe, no. Nullable because it is
               optional and "no answer" is not "no". */
            $table->string('recommend', 10)->nullable()->after('comment');
            $table->boolean('contact_requested')->default(false)->after('recommend');

            /* Where the business has got to with this feedback. Every row
               carries one, including the happy ones, so a single list can be
               filtered rather than two that drift. */
            $table->string('status', 20)->default('new')->after('contact_requested');
            $table->foreignId('assigned_to')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->foreignId('updated_by')->nullable()->after('assigned_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'rating']);
        });

        /* Separately, because changing a column and adding one cannot share a
           statement on every driver. */
        Schema::table('booking_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('booking_reviews', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'rating']);

            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('updated_by');

            $table->dropColumn([
                'token', 'channel', 'scheduled_for', 'sent_at', 'submitted_at',
                'google_opened_at', 'recommend', 'contact_requested', 'status',
                'assigned_at',
            ]);
        });
    }
};
