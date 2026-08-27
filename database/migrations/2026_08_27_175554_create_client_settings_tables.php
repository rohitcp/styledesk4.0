<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App Settings → Clients: what a business has configured about client records.
 *
 * One row per tenant for the settings themselves, plus two small tables for
 * the lists a business curates — preferences and tags — because §4 and §5 ask
 * for adding, editing, deactivating and reordering them, none of which a JSON
 * array does well.
 *
 * The settings that are a fixed set of switches are real columns; the ones
 * that are a set chosen from a catalogue are JSON. A column per booking panel
 * would be nine migrations the first time the panel list changes, and none of
 * them are ever queried on their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            // ------------------------------------------- §1 record defaults
            $table->string('default_status', 12)->default('active');
            $table->foreignId('default_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('default_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('default_communication', 12)->default('email');
            $table->string('default_marketing', 8)->default('ask');

            // --------------------------------------- §2, §3, §16 the record
            /**
             * Per-field configuration: enabled, required and display order.
             *
             * JSON because the shape is one row per field and the field list
             * is a catalogue that will grow. First Name's entry is written
             * like the rest and ignored on save — the lock lives in the
             * config, so a future field can be locked without a migration.
             */
            $table->json('fields')->nullable();
            $table->string('name_format', 20)->default('first_last');

            // ---------------------------------------------- §4, §5 the lists
            $table->boolean('preferences_enabled')->default(true);
            $table->boolean('preferences_multiple')->default(true);
            $table->boolean('tags_enabled')->default(true);

            // -------------------------------------------------- §6 the notes
            $table->boolean('notes_enabled')->default(true);
            $table->boolean('notes_multiple')->default(true);
            $table->boolean('notes_in_booking')->default(true);
            $table->boolean('notes_important_on_profile')->default(true);
            $table->boolean('notes_allow_important')->default(true);
            $table->boolean('notes_staff_can_edit')->default(true);
            $table->boolean('notes_admin_can_delete')->default(true);

            // ------------------------------------------------ §7 duplicates
            $table->json('duplicate_rules')->nullable();
            $table->boolean('duplicate_warning')->default(true);
            $table->boolean('duplicate_show_matches')->default(true);

            // --------------------------------------- §8, §9, §10, §15 panels
            $table->json('search_fields')->nullable();
            $table->json('booking_panels')->nullable();
            $table->json('history_panels')->nullable();
            $table->json('creation_sources')->nullable();

            // ------------------------------------------------- §11 statuses
            $table->boolean('allow_booking_inactive')->default(false);
            $table->boolean('archived_in_search')->default(false);

            // ------------------------------------------ §12 communication
            $table->boolean('comm_email')->default(true);
            $table->boolean('comm_sms')->default(true);
            $table->boolean('comm_phone')->default(true);
            $table->boolean('comm_marketing_email')->default(true);
            $table->boolean('comm_marketing_sms')->default(true);

            // -------------------------------------------------- §13 consent
            $table->boolean('consent_record')->default(true);
            $table->boolean('consent_record_date')->default(true);
            $table->boolean('consent_record_captured_by')->default(true);
            $table->boolean('consent_client_can_opt_out')->default(true);
            $table->boolean('consent_show_on_profile')->default(true);

            $table->timestamps();

            // One configuration per business, enforced rather than assumed:
            // two rows would mean two answers to every question on the screen.
            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        /**
         * The structured preferences staff can assign to a client.
         *
         * A table rather than a JSON list, because §4 asks for reordering and
         * deactivating, and because a preference will eventually be something
         * a booking can be filtered by.
         */
        Schema::create('client_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('label', 80);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'position']);
            // Two preferences with the same name are two ways to say one
            // thing, and a staff member picking between them is guessing.
            $table->unique(['tenant_id', 'label']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('client_tags', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('label', 60);
            // The colour key, not the hex: the palette lives in config, so a
            // shade can be corrected once rather than in every stored row.
            $table->string('color', 20)->default('slate');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'position']);
            $table->unique(['tenant_id', 'label']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tags');
        Schema::dropIfExists('client_preferences');
        Schema::dropIfExists('client_settings');
    }
};
