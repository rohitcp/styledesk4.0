<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            /**
             * The invitation is bound to this address.
             *
             * Stored lower-cased so "Amelia@example.com" and
             * "amelia@example.com" cannot hold two live invitations to the
             * same inbox, and so the acceptance check comparing the signed-in
             * user's email to this one cannot fail on casing alone.
             */
            $table->string('email');

            // Known before acceptance, so the team list can name the person
            // rather than showing a bare address next to "Pending".
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('job_title', 100)->nullable();

            /**
             * A string, not a role_id.
             *
             * StyleDesk's roles are a fixed set defined in code
             * (OnboardingController::ROLES) rather than tenant-editable rows,
             * and staff.role is already a string. A roles table here would be
             * a second source of truth for the same four values.
             */
            $table->string('role', 40)->default('service-provider');

            // "if applicable" in the spec: null means every location, which is
            // what a single-location business always wants.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();

            /**
             * The hash of the token, never the token itself.
             *
             * A plain token in this column is a password stored in clear: a
             * leaked backup or a stray query in a log would let anyone join
             * the business. The link in the email is the only place the plain
             * token exists, and it is looked up by hashing what arrives.
             *
             * Unique, so a replaced token cannot collide with a live one.
             */
            $table->string('token_hash', 64)->unique();

            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            // Who ended up accepting. Kept so a later audit can answer "which
            // account did this invitation create" without matching on email.
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            /**
             * Not a unique index on (tenant_id, email).
             *
             * Duplicate protection applies only to *pending* invitations —
             * a revoked or expired invite must not block a fresh one, and an
             * accepted one is history worth keeping. MySQL has no partial
             * unique index, so the rule lives in the model where it can be
             * stated exactly; this index is here to make the lookup that
             * enforces it cheap.
             */
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        /**
         * One row per delivery attempt.
         *
         * Counters on the invitation would answer "how many times did we try"
         * but not "when, and why did it fail" — which is the question actually
         * asked when someone reports never receiving their invite. Provider
         * errors land in `error` and stay internal; the user is only ever told
         * that sending failed.
         */
        Schema::create('team_invitation_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_invitation_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['team_invitation_id', 'attempted_at']);
        });

        Schema::table('staff', function (Blueprint $table) {
            // Mirrors the invitation's location, applied when it is accepted.
            $table->foreignId('location_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('team_invitation_deliveries');
        Schema::dropIfExists('team_invitations');
    }
};
