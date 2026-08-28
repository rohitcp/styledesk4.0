<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client records.
 *
 * Every field App Settings → Clients can switch on has a column, whether or
 * not a business uses it: which fields a client record *carries* is that
 * business's choice, and a nullable column costs nothing while a schema
 * change per business is not a thing that can exist.
 *
 * Contact and consent are stored separately rather than as one "can we
 * contact them" flag, per §12: a client who agreed to appointment reminders
 * has not agreed to marketing, and collapsing the two would be recording a
 * consent nobody gave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            /**
             * The human-readable identifier, CL-000123.
             *
             * Unique within the business and never reused. Generated rather
             * than typed, so it cannot collide with one a receptionist
             * invented, and searchable because §8 lists it as a way to find
             * someone.
             */
            $table->string('client_ref', 20);

            // ------------------------------------------------------- name
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('preferred_name', 100)->nullable();

            // ---------------------------------------------------- contact
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();

            // ------------------------------------------------------ about
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('avatar_path')->nullable();
            $table->text('notes')->nullable();

            // -------------------------------------------------- who they see
            /**
             * nullOnDelete on both.
             *
             * A stylist leaving the salon must not take their clients with
             * them. The record loses its preference and says so; it does not
             * disappear along with every appointment that points at it.
             */
            $table->foreignId('preferred_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('preferred_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            // ----------------------------------------------------- status
            /**
             * A string, not a flag. §11 has three states and archived means
             * something a boolean cannot say: out of booking search, still in
             * every appointment and report that already names them.
             */
            $table->string('status', 12)->default('active');

            // --------------------------------------- §12 communication
            $table->boolean('comm_email')->default(true);
            $table->boolean('comm_sms')->default(true);
            $table->boolean('comm_phone')->default(true);

            // ------------------------------------------- §13 consent
            $table->boolean('marketing_email')->default(false);
            $table->boolean('marketing_sms')->default(false);
            $table->timestamp('consent_recorded_at')->nullable();
            // Who captured it, kept even if that person later leaves.
            $table->foreignId('consent_recorded_by')->nullable()->constrained('users')->nullOnDelete();

            /**
             * Booking facts, filled in by the booking module.
             *
             * Columns now and null until then, so the listing can show the
             * columns §Client List asks for without every row running its own
             * query once appointments exist.
             */
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamp('next_booking_at')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'client_ref']);
            $table->index(['tenant_id', 'status']);
            // The listing sorts by name, so the index matches the read.
            $table->index(['tenant_id', 'first_name', 'last_name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        /**
         * Which preferences and tags a client carries.
         *
         * Pivots rather than JSON on the client, because both are curated
         * lists a business reorders and deactivates — and because "everyone
         * who prefers mornings" is a question the booking module will ask.
         */
        Schema::create('client_client_preference', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_preference_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'client_preference_id'], 'client_preference_unique');
        });

        Schema::create('client_client_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'client_tag_id'], 'client_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_client_tag');
        Schema::dropIfExists('client_client_preference');
        Schema::dropIfExists('clients');
    }
};
