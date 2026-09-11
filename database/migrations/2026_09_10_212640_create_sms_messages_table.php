<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every text message StyleDesk has tried to send.
 *
 * Written before the provider is called, never after: a row that only appears
 * once sending succeeded is a row that cannot explain a message which never
 * went. The status column is what moves — queued, sent, delivered, failed —
 * and it moves on the carrier's word rather than on ours, because an API that
 * accepted a message has not delivered it.
 *
 * The body is stored as it was sent. Templates change, and a client asking
 * "what did you text me" is asking what they actually received.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* Who it was for and what it was about. All nullable: a message
               can outlive the booking it announced, and deleting an
               appointment must not delete the record of what was said. */
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_membership_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 60);

            $table->string('from_number', 32)->nullable();
            $table->string('to_number', 32);
            $table->text('body');

            /* What this will be charged as. Worked out before sending and
               kept, because a template edited next month must not change what
               last month's bill says. */
            $table->unsignedSmallInteger('segments')->default(1);

            $table->string('provider', 32)->default('log');
            /* The provider's own id, which is what a delivery webhook arrives
               carrying. Indexed because that is the only thing the webhook
               has to find this row by. */
            $table->string('provider_message_id', 120)->nullable();

            $table->string('status', 24)->default('queued');
            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();

            /*
             * What must not be sent twice.
             *
             * A queued job that retries after the provider already accepted
             * the message would otherwise text the client again. The key is
             * the event rather than the row — "booking 345, confirmation,
             * first version" — so the second attempt collides instead of
             * sending.
             */
            $table->string('event_key', 120)->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_key'], 'sms_messages_event_key_unique');
            $table->index(['tenant_id', 'created_at'], 'sms_messages_tenant_created_index');
            $table->index(['tenant_id', 'client_id'], 'sms_messages_tenant_client_index');
            $table->index('provider_message_id', 'sms_messages_provider_id_index');
        });

        /*
         * What a delivery webhook has already been told.
         *
         * Telnyx delivers at least once and retries on anything that is not a
         * 2xx, so the same event arrives more than once as a matter of course.
         * Recognised here rather than guarded at each handler.
         */
        Schema::create('sms_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 120)->unique();
            $table->string('event_type', 80);
            $table->string('provider', 32)->default('telnyx');
            $table->string('status', 24)->default('received');
            $table->text('error')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_webhook_events');
        Schema::dropIfExists('sms_messages');
    }
};
