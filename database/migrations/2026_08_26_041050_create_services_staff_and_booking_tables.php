<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('duration_minutes');

            /**
             * Price in minor units (cents/pence), not a decimal.
             *
             * Money in a float rounds wrong, and a DECIMAL still invites
             * float arithmetic in PHP. An integer count of the smallest unit
             * cannot drift. `currency` lives on the tenant.
             */
            $table->unsignedInteger('price_minor')->default(0);

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            /**
             * Nullable: the team step invites people who have no account yet.
             * The owner is seeded here at step 4 already linked to their user,
             * which is why the prototype pre-populates owner-name/owner-email.
             */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('role', 40)->default('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('service_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            $table->unique(['service_id', 'staff_id']);
        });

        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            $table->boolean('is_enabled')->default(true);
            $table->boolean('allow_new_clients')->default(true);
            $table->boolean('allow_existing_clients')->default(true);

            $table->unsignedInteger('min_notice_minutes')->default(120);
            $table->unsignedSmallInteger('max_advance_days')->default(60);
            $table->unsignedSmallInteger('cancellation_window_hours')->default(24);

            $table->boolean('require_card')->default(false);
            $table->boolean('require_email')->default(true);
            $table->boolean('require_phone')->default(false);

            $table->timestamps();

            // The public booking URL is derived from tenants.slug, not stored:
            // storing it would let the two disagree after a rename.
            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
        Schema::dropIfExists('service_staff');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('services');
    }
};
