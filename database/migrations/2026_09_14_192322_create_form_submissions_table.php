<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One client, one form, once.
 *
 * Assignment and submission are the same row rather than two. A form's life is
 * a single line — assigned, sent, viewed, started, completed, signed — and
 * splitting it would mean a request with no answers and answers with no
 * request, joined on the way to every screen that shows either. The status
 * column says where on that line this one is.
 *
 * The dates are kept as their own columns rather than reconstructed from an
 * activity log, because §58's audit trail is a fact about the submission: a
 * signed waiver has to be able to say when it was signed without depending on
 * a log that may have been trimmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('form_id')->constrained()->cascadeOnDelete();

            /*
             * The version pinned at assignment, not read at submit.
             *
             * A client half-way through a form when the business publishes an
             * edit must keep answering the questions they started on. This
             * column is what makes that true, and it is restricted on delete
             * for the same reason: the version is what the answers mean.
             */
            $table->foreignId('form_version_id')->constrained('form_versions')->restrictOnDelete();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /* What it was for. All nullable: a form can be assigned to a
               client with no appointment in sight, and a booking can be
               deleted without taking a signed consent with it. */
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained()->nullOnDelete();

            /*
             * The client's way in, and the only credential they have.
             *
             * No StyleDesk account is involved, so this token is the whole of
             * the authorisation — long, random, unique, and indexed because
             * every public request looks a submission up by it. Following
             * `review_token`, which is the same problem already solved once.
             */
            $table->string('token', 64)->unique();

            /* Where on the line this one is, from
               config('forms.submission_statuses'). */
            $table->string('status', 20)->default('not_sent');

            /* Who filled it in and from where — client link, front desk,
               check-in, staff, kiosk. What tells a signed waiver completed on
               a salon tablet from one the client did at home. */
            $table->string('source', 20)->nullable();

            /*
             * The answers.
             *
             * Encrypted at rest when the form is marked as holding sensitive
             * information, which is why `answers_encrypted` is a column on
             * the row rather than read from the form: a business that turns
             * the flag off next year must not make every existing row
             * undecryptable, and one that turns it on must not make the old
             * plain rows unreadable. The row states how it stored its own
             * answers. Text rather than json because ciphertext is not json.
             */
            $table->text('answers')->nullable();
            $table->boolean('answers_encrypted')->default(false);

            /* The signature, as drawn or as typed, and the name it was given
               under. Encrypted with the answers when the form is sensitive. */
            $table->text('signature')->nullable();
            $table->string('signature_type', 10)->nullable();
            $table->string('signed_name', 120)->nullable();

            /* Who it was signed by, from where, on what. Kept because a
               waiver's value is partly in being able to say this. */
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_agent', 255)->nullable();

            /* The audit trail. Each is null until it happens, so the set of
               non-null dates is the history. */
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('signed_at')->nullable();

            /* When this answer stops counting, computed from the form's
               validity rule at completion and stored because the rule may
               change afterwards. Null means it never expires.

               Expiry is applied when a submission is read, not by a nightly
               sweep — the same shape as loyalty point expiry. */
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('assigned_by')->nullable()
                ->constrained('users')->nullOnDelete();

            /* A business trying its own form out. Kept out of the client's
               history rather than deleted, so the sender can see their test
               arrived. */
            $table->boolean('is_test')->default(false);

            $table->timestamps();

            $table->index(['tenant_id', 'client_id', 'status']);
            $table->index(['tenant_id', 'form_id']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
