<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons and offers.
 *
 * One table for both, because they are one thing with one difference: a
 * coupon is typed in and an offer applies itself. Everything else — what it
 * takes off, what it applies to, who may use it, when, and how often — is the
 * same set of questions, and two tables would be two places to answer them.
 *
 * Status is not stored. Draft and Disabled are decisions somebody made and
 * live on the row; Scheduled, Active and Expired are what the dates say
 * today, and a column holding them would be wrong every midnight until
 * something rewrote it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            /* For the desk, not the client: "September new-client push". */
            $table->text('description')->nullable();

            /* coupon | offer — typed in, or applied by itself. */
            $table->string('type', 16)->default('coupon');
            /* Null for an offer. Unique per business rather than globally:
               two salons may both run WELCOME20 and neither is wrong. */
            $table->string('code', 40)->nullable();

            $table->string('discount_type', 16)->default('percent');
            /* Percent as whole points, fixed as minor units. One column
               because only one of them is ever meaningful at a time. */
            $table->unsignedInteger('discount_value')->default(0);

            /* booking | all_services | services | categories */
            $table->string('applies_to', 24)->default('booking');
            /* all | selected */
            $table->string('location_mode', 16)->default('all');
            /* all | new | existing | selected */
            $table->string('eligibility', 16)->default('all');

            $table->date('starts_on');
            /* Null is "no expiry", which is a real answer for a standing
               offer rather than a missing date. */
            $table->date('ends_on')->nullable();
            /* Which days of the week it runs, 0–6. Null is every day. */
            $table->json('days')->nullable();

            $table->unsignedInteger('min_spend_minor')->nullable();
            /* Null is unlimited on both. */
            $table->unsignedInteger('total_limit')->nullable();
            $table->unsignedInteger('per_client_limit')->nullable()->default(1);

            $table->boolean('allow_online')->default(false);
            /* One coupon per booking is the MVP rule; this is the switch
               that will let a business relax it later. */
            $table->boolean('combinable')->default(false);

            /* Only the two a person decides. The rest is read from dates. */
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_disabled')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'starts_on', 'ends_on']);
        });

        /* What it applies to, where it runs, and who may use it. Pivots
           rather than columns of ids: a promotion on forty services is a
           normal thing for a spa to want. */
        Schema::create('promotion_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('promotion_service_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('promotion_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('promotion_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        });

        /*
         * Every time it was used.
         *
         * Its own table rather than a counter on the promotion: "how many
         * times" is a question a counter answers and "by whom, on what, for
         * how much" is not — and the per-client limit needs the second one.
         * It is also what the reporting is read from.
         */
        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            /* What it actually took off, worked out at the time. Kept rather
               than recomputed: the promotion may be edited afterwards, and
               last month's discount was what it was. */
            $table->unsignedInteger('discount_minor')->default(0);
            /* What the booking came to, so "revenue generated" is answerable
               without joining back to a booking that may since have moved. */
            $table->unsignedInteger('booking_total_minor')->default(0);

            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['promotion_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotion_client');
        Schema::dropIfExists('promotion_location');
        Schema::dropIfExists('promotion_service_category');
        Schema::dropIfExists('promotion_service');
        Schema::dropIfExists('promotions');
    }
};
