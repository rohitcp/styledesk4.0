<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The memberships a business sells.
 *
 * One table for both kinds, the same way `promotions` holds coupons and
 * offers: a recurring membership and a package are one thing with one
 * difference — whether it bills again — and every other question about them
 * (what it is called, what it costs, what it includes, where it is sold) is
 * the same question. Two tables would be two places to answer it, and the
 * booking screen would have to merge them back together to show one list.
 *
 * The columns only one kind uses are nullable and named for that kind:
 * `billing_frequency` and the fees belong to a recurring plan,
 * `regular_value_minor` to a package. A row that carries the wrong one is a
 * bug, not a variation.
 *
 * Status is not stored. Draft and Disabled are decisions somebody made and
 * live on the row; Active is simply neither of them. There are no dates to
 * make it stale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* recurring | package — billed again, or bought once. */
            $table->string('type', 16)->default('recurring');

            $table->string('name');
            /* What the client reads on the card in the booking screen. */
            $table->text('description')->nullable();
            /* The business's own reference. Unique per business where it is
               given at all, because a code that names two things is a code
               that names nothing. */
            $table->string('internal_code', 40)->nullable();
            /* A stored file rather than a path, the way a service picture
               is: the path is an implementation detail of whichever disk was
               in use the day the file arrived, and a column holding one
               breaks the day the business moves to another. */
            $table->foreignId('image_file_id')->nullable()->constrained('stored_files')->nullOnDelete();

            /* What the client pays. For a recurring plan it is the amount
               per cycle; for a package it is the one-off price. Minor units,
               like every other amount in StyleDesk. */
            $table->unsignedInteger('price_minor')->default(0);

            /* -------------------------------------------------- recurring */

            /* monthly | quarterly | yearly. Null on a package, which does
               not have a next billing date and never will. */
            $table->string('billing_frequency', 16)->nullable();
            /* Charged once, at the start, on top of the first cycle. Null
               rather than zero: "no joining fee" and "a joining fee of
               nothing" read the same on a receipt but not in a report. */
            $table->unsignedInteger('joining_fee_minor')->nullable();
            $table->unsignedInteger('setup_fee_minor')->nullable();
            /* Days before the first charge. Null is no trial. */
            $table->unsignedSmallInteger('trial_days')->nullable();

            /* ---------------------------------------------------- package */

            /* What the included services would cost bought separately. Kept
               rather than summed from the service list, because the saving
               is a claim the business is making to the client and it must
               not move the next time somebody edits a price. */
            $table->unsignedInteger('regular_value_minor')->nullable();

            /* --------------------------------------- additional benefits */

            /* What a member gets off everything else they buy. Percent as
               whole points, fixed as minor units — one column, because only
               one of them is meaningful at a time. Null type is no discount,
               which is different from a discount of zero. */
            $table->string('discount_type', 16)->nullable();
            $table->unsignedInteger('discount_value')->default(0);
            $table->boolean('priority_booking')->default(false);

            /* ------------------------------------------- credit overrides */

            /* Null means "whatever the business decided in App Settings".
               A plan that repeated the business default in its own columns
               would silently stop following it the day the default changed —
               which is the bug this shape exists to avoid. */
            $table->string('credit_expiry', 8)->nullable();
            $table->boolean('allow_rollover')->nullable();
            $table->unsignedInteger('maximum_rollover')->nullable();
            $table->boolean('allow_service_substitution')->nullable();

            /* ------------------------------------------------ availability */

            /* all | selected — where it may be sold and used. */
            $table->string('location_mode', 16)->default('all');
            /* Which channels this particular plan is offered through. The
               business's own channel switches are a ceiling over these: a
               plan marked online-sellable in a business that does not sell
               online is not for sale. */
            $table->boolean('sell_in_store')->default(true);
            $table->boolean('sell_online')->default(false);

            /* Only the two a person decides. Active is neither of them. */
            $table->boolean('is_draft')->default(true);
            $table->boolean('is_disabled')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'internal_code']);
            $table->index(['tenant_id', 'type']);
        });

        /*
         * What is included, and how much of it.
         *
         * Its own row per service rather than a json blob, because "which
         * memberships include a facial" is a question the desk asks and a
         * blob cannot answer it.
         *
         * `quantity` is read against the plan's kind: on a recurring plan it
         * is how many are granted each cycle, on a package how many the
         * client gets in total. One column rather than two, because a plan
         * is only ever one kind — and a per-row frequency would let a
         * monthly membership include something quarterly, which is a product
         * nobody sells and a credit engine nobody can explain.
         */
        Schema::create('membership_plan_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['membership_plan_id', 'service_id']);
        });

        /* Where it may be sold and used. A pivot rather than a column of
           ids: a chain running one membership across nine branches is a
           normal thing to want. */
        Schema::create('membership_plan_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plan_location');
        Schema::dropIfExists('membership_plan_services');
        Schema::dropIfExists('membership_plans');
    }
};
