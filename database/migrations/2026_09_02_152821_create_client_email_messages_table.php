<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every email this business has sent a client.
 *
 * Named `client_email_messages` because `client_emails` is already taken by
 * the addresses on a client record — two different things one word apart, and
 * the shorter name would have been read as the other one forever.
 *
 * A record of what was sent, not a pointer to it: the recipient address, the
 * sender name and the body are copied onto the row rather than joined. A
 * client who changes their address next month must not rewrite the history of
 * where their appointment reminder actually went, and a template edited in
 * March must not change what a client was told in February.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_email_messages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /* Copied, not joined — see the doc block above. */
            $table->string('recipient_email');
            $table->string('sender_email');
            $table->string('sender_name');
            $table->string('subject');
            $table->text('message');

            /* Which way it went out. Kept per message because a business can
               change its default, and "sent via Gmail" has to stay true for
               the ones that were. */
            $table->string('provider', 20);

            /* The template it started from, or null for one typed by hand.
               Only ever a starting point: the body above is what was sent. */
            $table->string('template_key', 60)->nullable();

            /* What it was about, when it was about something. Nulled rather
               than cascaded: deleting a booking should not delete the letter
               that told the client about it. */
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            /* Who pressed send. Null means StyleDesk itself, the same
               convention client_activities uses. */
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

            /* queued → sent → delivered, or failed. Indexed with the client
               because the history screen reads exactly that pair. */
            $table->string('status', 20)->default('queued');
            $table->string('failure_reason')->nullable();

            /* Both times, because they answer different questions: when the
               desk pressed send, and when it actually left. */
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'client_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_email_messages');
    }
};
