<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Wizard progress, one row per tenant.
         *
         * Kept out of `tenants` on purpose: this is transient setup state,
         * read on every onboarding request and never again afterwards.
         *
         * It lives in the database rather than localStorage because the
         * prototype's own styledesk.js says completion must never be decided
         * from front-end state. Clearing browser storage must not change which
         * step a user is on.
         */
        Schema::create('tenant_onboarding', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('current_step', 20)->default('business');

            $table->boolean('business_completed')->default(false);
            $table->boolean('location_completed')->default(false);
            $table->boolean('services_completed')->default(false);
            $table->boolean('team_completed')->default(false);
            $table->boolean('booking_completed')->default(false);

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name')->default('Main Location');
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code', 20);
            $table->char('country', 2);
            $table->string('phone', 32)->nullable();
            $table->char('phone_country', 2)->nullable();
            $table->string('timezone', 64);
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        /**
         * Opening hours, one row per weekday per location.
         *
         * A row per day rather than a JSON blob: availability is queried when
         * working out bookable slots, and a blob cannot be joined or indexed.
         * opens_at/closes_at are null on a closed day.
         */
        Schema::create('location_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday
            $table->boolean('is_open')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_hours');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('tenant_onboarding');
    }
};
