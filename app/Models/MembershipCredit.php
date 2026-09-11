<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * What is left of what a client bought.
 *
 * Granted and used rather than a remaining balance. A balance is one number
 * that has to be right; these are two facts that can each be checked against
 * a receipt, and "how many did they get" survives the credit being spent.
 */
class MembershipCredit extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_granted' => 'integer',
            'quantity_used' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class, 'client_membership_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Every time this credit paid for something.
     *
     * The counter says how many are gone; these say where they went, and so
     * whether one is reserved against an appointment still to come or has
     * already been taken.
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(MembershipCreditRedemption::class, 'membership_credit_id');
    }

    /** How many are still there to spend. */
    public function remaining(): int
    {
        return max(0, $this->quantity_granted - $this->quantity_used);
    }

    /**
     * Has this one run out of time?
     *
     * Asked when the balance is read rather than swept nightly, the same way
     * a loyalty point's expiry is: a business that sets a deadline gets one
     * with no scheduled job, and a credit that expired at the weekend is
     * already gone by Monday morning without anything having run.
     */
    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    public function isSpendable(): bool
    {
        return $this->remaining() > 0 && ! $this->isExpired();
    }

    /** Credits that could actually be spent today. */
    public function scopeSpendable(Builder $query): Builder
    {
        return $query
            ->whereColumn('quantity_used', '<', 'quantity_granted')
            ->where(fn (Builder $q) => $q
                ->whereNull('expires_on')
                ->orWhereDate('expires_on', '>=', Carbon::today()->toDateString()));
    }
}
