<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this business sells memberships, and on what terms.
 *
 * Its own table for the same reasons `loyalty_settings` and `review_settings`
 * are: it is a standing decision about how the business treats its clients,
 * and it will grow — terms per location, terms per plan.
 *
 * What is NOT here is what any particular membership costs or includes. That
 * is a plan, and a plan is a row of its own. This table holds only the rules
 * that are true of every membership the business sells: where one may be
 * bought, when it starts, what happens to a credit nobody used, and what
 * cancelling means.
 *
 * Switching membership off stops the selling. It erases nothing: existing
 * members keep their plans, their credits and their history, because a
 * business that paused sales in November has not told the people who already
 * paid that their month is void.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* Off until somebody turns it on. Nothing about memberships
               appears anywhere in the app until it is: no navigation, no
               purchase type in the booking screen, no plans to build. */
            $table->boolean('is_enabled')->default(false);

            /* ------------------------------------------------------ selling */

            /* Where one may be bought. In store is on because that is the
               only channel that exists; online is off and stays off until
               the public booking pages can take the money. */
            $table->boolean('allow_purchase_in_store')->default(true);
            $table->boolean('allow_purchase_online')->default(false);

            /* Whether anybody with the sell permission may complete the sale,
               or only a manager. Separate from the permission itself: the
               permission says who could, this says whether the business
               wants it happening at the front desk at all. */
            $table->boolean('allow_staff_to_sell')->default(true);

            /* Whether the desk may date a membership forward, and what it
               offers when nobody chooses. A business that says no gets
               "starts today" with no date picker rather than a picker with
               one option in it. */
            $table->boolean('allow_start_date_selection')->default(true);
            $table->string('default_activation', 16)->default('immediately');

            /* ------------------------------------------------------ credits */

            /* What happens to a credit the client did not use.
               Reset is the honest default for a recurring plan — a month's
               massage is a month's massage — and rollover is the concession a
               business may choose to make. The cap only means anything with
               rollover on; null is "as many as they accrue". */
            $table->boolean('reset_credits_on_cycle')->default(true);
            $table->boolean('allow_rollover')->default(false);
            $table->unsignedInteger('maximum_rollover')->nullable();

            /* How long a granted credit lives. 'cycle' is not a duration —
               it dies with the billing period it belongs to — which is why
               this is a key rather than a number of months. */
            $table->string('credit_expiry', 8)->default('cycle');

            /* Whether a credit earned at one branch may be spent at another,
               and whether a "massage" credit may pay for a facial of equal
               value. Both are business policy, not arithmetic. */
            $table->boolean('allow_credits_across_locations')->default(true);
            $table->boolean('allow_service_substitution')->default(false);

            /* ------------------------------------------------- cancellation */

            $table->boolean('allow_cancellation')->default(true);
            $table->boolean('allow_pause')->default(false);

            /* The two ways a business can hold somebody to a membership: a
               period they cannot leave inside, and a warning they must give.
               Zero for both, because a default that quietly locks a client
               into six months is a term nobody agreed to. */
            $table->unsignedSmallInteger('minimum_commitment_months')->default(0);
            $table->unsignedSmallInteger('cancellation_notice_days')->default(0);

            /* When a cancellation takes effect. End of cycle by default: the
               client paid for the month they are standing in. */
            $table->string('cancellation_effective', 16)->default('end_of_cycle');

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_settings');
    }
};
