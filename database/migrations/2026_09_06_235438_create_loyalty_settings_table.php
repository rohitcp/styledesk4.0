<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How this business's loyalty scheme works.
 *
 * Its own table for the same reasons `review_settings` and `tip_settings` are:
 * it is a decision about how the business treats its clients, it will grow — a
 * rate per location, a bonus per service — and a salon that pauses the scheme
 * for a difficult quarter must find its rules exactly as it left them.
 *
 * Switching the scheme off stops the earning and the redeeming. It erases
 * nothing: every balance and every line of history stays, because a business
 * that turned rewards off in November and on again in March has not told its
 * clients their points were forfeited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* Off until somebody turns it on. A scheme whose rate nobody has
               chosen should not start quietly issuing points. */
            $table->boolean('is_enabled')->default(false);

            /* What the business calls it. Clients read this word on their
               receipt, so "Glow Rewards" belongs here rather than StyleDesk
               deciding everybody's scheme is called Points. */
            $table->string('program_name', 60)->default('Rewards');
            $table->string('description', 255)->nullable();

            /* The earning rule, as the business states it: this much spent
               earns that many points. Two columns rather than one rate,
               because "$5 = 1 point" is how it is written on a poster and a
               stored 0.2 is a number nobody can check.

               Whole currency units rather than minor: a business setting
               "$2.50 spent" is not a rule anybody writes, and an integer here
               keeps the arithmetic exact. */
            $table->unsignedInteger('spend_amount')->default(1);
            $table->unsignedInteger('points_earned')->default(1);

            /* Which transaction types earn. A list rather than seven columns,
               because whether a spa gives points on gift cards is not a
               decision StyleDesk should be making in a migration. */
            $table->json('eligible_purchases')->nullable();

            /* The redemption rule, stated the same way round: this many
               points are worth that much money. The value is minor units —
               it is money, and every other amount in StyleDesk is. */
            $table->unsignedInteger('points_required')->default(500);
            $table->unsignedInteger('reward_value_minor')->default(500);

            /* The floor and the ceiling. The floor stops a client redeeming
               forty cents of goodwill; the ceiling stops a five-year balance
               emptying into one appointment. Null ceiling means no ceiling,
               which is different from a ceiling of zero. */
            $table->unsignedInteger('minimum_redemption')->default(500);
            $table->unsignedInteger('maximum_reward_minor')->nullable();

            /* How long a point lives. Never by default — a balance that
               evaporates costs more goodwill than the scheme buys. */
            $table->string('expiry', 8)->default('never');

            /* The four loyalty messages. Storable now and deliverable later,
               the same way the review module's SMS channel is: the setting
               screen renders them disabled until something can send them. */
            $table->boolean('notify_earned_email')->default(false);
            $table->boolean('notify_earned_sms')->default(false);
            $table->boolean('notify_reward_email')->default(false);
            $table->boolean('notify_reward_sms')->default(false);

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
