<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What has happened to a client, written down as it happens.
 *
 * The profile used to reconstruct its timeline at render time from whatever
 * still existed — the notes on file, the archived flag, the created date.
 * That is a summary, not a history: it can only ever say what is true now, so
 * a note somebody wrote and deleted left no trace, and "who changed this
 * number" had no answer at all.
 *
 * This is the history. Written when the thing happens, by whoever did it, and
 * never touched again: there is no update path and no delete route, because
 * an audit trail somebody can edit is not one anybody can rely on. A row here
 * outlives the thing it describes — the note it records may be gone.
 *
 * `created_at` alone, and no `updated_at`. A second timestamp on an immutable
 * row is a column that can only ever lie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /* What happened, and which filter it belongs under. Two columns
               rather than one: "booking.rescheduled" is the event, "bookings"
               is the tab, and deriving the second from the first by string
               surgery is how a filter quietly stops matching. */
            $table->string('type', 40);
            $table->string('category', 20);

            /* The thing it happened to, whatever kind of thing that is. Kept
               as a loose reference rather than a foreign key: the row has to
               survive the note it describes being deleted, which is the whole
               point of writing it down. */
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            /* The two the timeline links to by name. Nulled rather than
               cascaded for the same reason: a cancelled booking's history is
               the half of the story worth keeping. */
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('booking_payments')->nullOnDelete();

            /* Who did it. Null is not missing data — it is StyleDesk itself,
               and the timeline says so. */
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            /* What it said at the time. A note's body, a cancellation reason,
               a service name: copied rather than looked up, so the entry still
               reads correctly after the thing it names has changed. */
            $table->text('description')->nullable();

            /* Field-by-field before and after, for the entries that have any.
               One row per save, not per field: somebody who changed three
               things did one thing. */
            $table->json('changes')->nullable();
            $table->json('meta')->nullable();

            /* A private note's body must not be read by somebody who may not
               read the note. Marked on the row so the timeline can withhold
               it without loading the note back — which may no longer exist. */
            $table->boolean('is_private')->default(false);

            $table->timestamp('created_at')->nullable()->index();

            /* The one question this table is asked: what has happened to this
               client, newest first, optionally narrowed to one category. */
            $table->index(['client_id', 'created_at']);
            $table->index(['client_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_activities');
    }
};
