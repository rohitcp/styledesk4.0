<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A membership somebody actually bought.
 *
 * Separate from `membership_plans` for the reason every sale is separate from
 * the thing it sold: the plan is what the business offers today and this is
 * what one client agreed to on one day. The plan may be renamed, repriced or
 * taken off sale afterwards, and none of that may change what the person
 * standing at the desk in March was told they were paying.
 *
 * That is why the price, the billing frequency and the fees are copied onto
 * the row rather than read through the plan. The plan id stays as well, so
 * "what does this include" still has an answer — but the money does not move.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            /* Restricted rather than cascading: a plan somebody bought cannot
               simply be deleted out from under them. Plans are taken off sale,
               never removed — see MembershipPlanController::toggle. */
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            /* Where it was sold. Records what happened; it never divides the
               membership — credits cross branches or not by policy, not by
               which desk took the money. */
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            /* scheduled | active | paused | cancelled | ended
             *
             * Stored rather than derived, unlike a plan's, because most of
             * these are events with a date attached — somebody cancelled,
             * somebody paused — and the two that are not (scheduled becoming
             * active) are settled by the start date on the way past. */
            $table->string('status', 16)->default('active');

            $table->date('starts_on');
            /* When it stops being anything. Null while it is running: a
               recurring membership has no end until somebody ends it, and a
               package ends when its credits do rather than on a date. */
            $table->date('ends_on')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paused_at')->nullable();

            /* What the client agreed to, as it was on the day. */
            $table->string('type', 16);
            $table->unsignedInteger('price_minor');
            $table->string('currency_code', 3);
            /* Null on a package, which never bills again. */
            $table->string('billing_frequency', 16)->nullable();
            $table->date('next_billing_on')->nullable();
            $table->unsignedInteger('joining_fee_minor')->nullable();
            $table->unsignedInteger('setup_fee_minor')->nullable();

            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('next_billing_on');
        });

        /*
         * What is left of what they bought.
         *
         * One row per service per period, rather than a counter on the
         * membership: "one massage and two facials this month" is three
         * numbers, and the month it belongs to is part of each of them.
         *
         * Granted and used rather than a remaining balance. A balance is one
         * number that has to be right; these are two facts that can each be
         * checked against a receipt, and "how many did they get" survives the
         * credit being spent.
         */
        Schema::create('membership_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('quantity_granted');
            $table->unsignedSmallInteger('quantity_used')->default(0);

            /* The cycle these belong to. Null on a package: what it contains
               is not a month's worth of anything, it is the whole purchase. */
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            /* When an unused one dies, worked out from the plan's rules at
               the time it was granted. Null is never. */
            $table->date('expires_on')->nullable();

            $table->timestamps();

            $table->index(['client_membership_id', 'service_id']);
            $table->index('expires_on');
        });

        /*
         * Money taken for a membership.
         *
         * Its own table rather than a nullable booking on `booking_payments`:
         * a membership sale is not an appointment, and a payments table whose
         * booking is sometimes absent is one every report has to remember to
         * ask about.
         */
        Schema::create('membership_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_membership_id')->constrained()->cascadeOnDelete();

            $table->string('method', 24);
            $table->string('status', 24)->default('paid');
            $table->unsignedInteger('amount_minor');
            $table->string('currency_code', 3);
            /* What it was for: the first cycle, a renewal, or a joining fee
               charged alongside it. Kept apart because "what did this
               business take in membership fees" and "what did it take in
               subscriptions" are different questions. */
            $table->string('purpose', 24)->default('initial');

            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_payments');
        Schema::dropIfExists('membership_credits');
        Schema::dropIfExists('client_memberships');
    }
};
