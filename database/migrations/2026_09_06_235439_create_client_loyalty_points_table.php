<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every point a client has ever earned, spent, lost or been given.
 *
 * A ledger rather than a balance column, for the reason `booking_payments` is
 * a ledger: a number somebody has to remember to keep in step goes out of step,
 * and the question a client actually asks at the desk is not "what is my
 * balance" but "where did my four hundred points go". Rows answer that; a
 * column cannot.
 *
 * The balance is the sum of the rows. `balance_after` is written beside each
 * one anyway — not as the authority, but so the history reads the way a bank
 * statement reads, and so a line written in March still shows what the balance
 * was in March rather than what a replay of today's rules would make it.
 *
 * One balance per client for the whole business, not one per branch: §15. A
 * client earning at Riverside and redeeming at High Street is the whole point,
 * so `location_id` records where a line happened and never divides it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /* What happened — a key from config('loyalty.activities'). */
            $table->string('type', 32);

            /* Signed, so the balance is a sum and nothing has to know which
               types add and which take away. A redemption is -500 here, and
               the sign is the fact rather than a rendering decision. */
            $table->integer('points');

            /* The balance immediately after this line, as it stood then. */
            $table->integer('balance_after');

            /* What the points were worked out from, so a refund can take back
               proportionally without re-deriving a price list that may have
               changed since. Null on a line that came from no money at all —
               a manual adjustment, an expiry. */
            $table->integer('eligible_amount_minor')->nullable();
            $table->string('currency_code', 3)->nullable();

            /* Where the line came from. Nullable because a manual adjustment
               comes from a person rather than an appointment, and set null on
               delete rather than cascading: a booking removed from the diary
               must not silently rewrite somebody's balance history. */
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            /* The audit trail §7 asks for: why, in whose words, and whose. */
            $table->string('reason', 40)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            /* When these points stop counting. Null is never, which is the
               default and the common case. Read at the moment the balance is
               summed, so a business that sets a deadline gets one without
               anything having to sweep the table nightly. */
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            /* The two reads this table exists for: one client's history
               newest first, and one booking's lines when a refund lands. */
            $table->index(['tenant_id', 'client_id', 'id']);
            $table->index(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_loyalty_points');
    }
};
