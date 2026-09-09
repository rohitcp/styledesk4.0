<?php

use App\Models\ReasonCode;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why something happened, chosen from a list rather than typed.
 *
 * "Why do we lose bookings" is a question of counting, and forty spellings of
 * "client changed their mind" answer none of it. Every place StyleDesk asks
 * for a reason now asks it the same way.
 *
 * One row per business per reason, not one global catalogue with per-tenant
 * overrides. A business that renames "Client No Show" to "Didn't turn up" has
 * renamed its own row; `key` is what still identifies it as StyleDesk's, so a
 * later default can be added without guessing which of the renamed ones it
 * already is. A custom reason has no key at all.
 *
 * Nothing is ever deleted out from under a record that used it: a reason is
 * switched off, and the appointment cancelled under it last March still says
 * what it said. That is why `is_active` exists and why a system reason has no
 * delete route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* Which list it belongs to. A string from config/reasons.php
               rather than an enum, because a tenth reason type is meant to be
               a block added to that file and nothing else. */
            $table->string('type', 40);

            /* StyleDesk's own name for it, and null for a reason the business
               invented. It is what survives a rename. */
            $table->string('key', 60)->nullable();

            $table->string('name', 120);
            $table->string('description', 300)->nullable();

            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);

            /* Picking this one demands a written explanation. "Other" is the
               obvious case; a business can ask it of any of its own. */
            $table->boolean('requires_details')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* One row per business per system reason: seeding twice is the
               same statement, not a second one. */
            $table->unique(['tenant_id', 'type', 'key']);
            $table->index(['tenant_id', 'type', 'is_active']);
        });

        /* Businesses that already exist get the library too. A feature that
           only reached tenants created after it shipped is one the people
           already using StyleDesk would have to be told to ask for. */
        Tenant::query()->cursor()->each(fn (Tenant $tenant) => ReasonCode::seedDefaultsFor($tenant));
    }

    public function down(): void
    {
        Schema::dropIfExists('reason_codes');
    }
};
