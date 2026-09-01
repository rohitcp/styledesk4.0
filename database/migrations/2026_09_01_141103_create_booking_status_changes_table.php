<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a booking after it was taken, and why.
 *
 * A booking's own row only ever says where it got to. "Cancelled" is not the
 * question anybody asks on a Monday morning — "cancelled by whom, when, and
 * what did they say" is, and none of that survives on a column that the next
 * status change overwrites.
 *
 * So the reason is written here rather than on the booking, and written twice
 * over: the reason code it was chosen from, and the words that reason had on
 * the day. A business that renames "Client Did Not Arrive" next spring has
 * renamed their list, not last March's no-show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);

            /* Which list the reason came from, kept beside the reason itself:
               a type is how the entry is read back when the code it points at
               has since been switched off. */
            $table->string('reason_type', 64)->nullable();
            /* Nulled rather than cascaded if a custom reason is ever deleted —
               the words below are what the entry is read from, and losing the
               pointer costs the history nothing. */
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            /* The reason as it read on the day. This is what the timeline
               prints, so renaming a reason code does not rewrite history. */
            $table->string('reason_label')->nullable();

            $table->text('note')->nullable();
            /* What "Other" asked for. Its own column rather than folded into
               the note: one is the answer to a required question and the
               other is whatever somebody wanted to add. */
            $table->text('details')->nullable();

            /* Where the appointment was, and where it went. Only a reschedule
               fills these, and both halves are kept because "moved" without
               the previous slot answers half the question. */
            $table->date('from_date')->nullable();
            $table->time('from_starts_at')->nullable();
            $table->date('to_date')->nullable();
            $table->time('to_starts_at')->nullable();
            /* Who was going to do it, and who is now. Same reasoning. */
            $table->foreignId('from_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('to_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();

            /* Whoever did it. Kept if they later leave — an audit row that
               forgets its author the day somebody resigns is not one. */
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

            /* Created, and never updated. A second timestamp on an immutable
               row is a column that can only ever lie. */
            $table->timestamp('created_at')->nullable();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_changes');
    }
};
