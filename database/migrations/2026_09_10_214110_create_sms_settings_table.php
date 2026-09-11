<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What this business sends by text, and when.
 *
 * One row per business. The master switch is deliberately separate from the
 * per-message ones: a salon that has to stop texting for a week — a carrier
 * problem, a bill, a complaint — must be able to do it without losing which
 * of the seven messages it had chosen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete()->unique();

            $table->boolean('is_enabled')->default(false);

            /* The number this business sends from. Held per business because
               that is where a 10DLC registration belongs, even though only
               one number is configured for the platform today. */
            $table->string('sender_number', 32)->nullable();
            /* Where the registration has got to: not_started, submitted,
               pending, approved, rejected, suspended. A business may not send
               to US numbers until this reads approved. */
            $table->string('registration_status', 24)->default('not_started');

            /* Which messages go out at all. Stored as a list rather than a
               column each, so adding the eighth message is a line in the
               config and not a migration. */
            $table->json('messages')->nullable();

            /* How long before an appointment a reminder goes, in hours. A
               list, because a business may want one the day before and
               another two hours ahead. */
            $table->json('reminder_hours')->nullable();

            $table->time('birthday_send_at')->nullable();

            /* What the business is willing to spend. A runaway loop or a
               bulk import must not be able to text ten thousand people. */
            $table->unsignedInteger('monthly_limit')->nullable();
            $table->unsignedTinyInteger('alert_percent')->default(80);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_settings');
    }
};
