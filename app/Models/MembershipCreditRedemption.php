<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A membership credit paying for something.
 *
 * A row rather than only a counter on the credit, the same way
 * `promotion_redemptions` is a table: "how many are left" is what a counter
 * answers and "which appointment used one, and what it was worth" is not.
 * The second is what a cancellation needs in order to give the credit back.
 *
 * Released rather than deleted. A credit handed back when a booking was
 * cancelled is a thing that happened, and a row that vanished would leave the
 * client's history with a gap where their explanation used to be.
 */
class MembershipCreditRedemption extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'value_minor' => 'integer',
            'released_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(MembershipCredit::class, 'membership_credit_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class, 'client_membership_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    /**
     * Has the client actually taken it?
     *
     * A redemption is created when the appointment is booked, which holds the
     * credit down but does not spend it: the massage is still to come. It is
     * spent when they walk in — see MembershipCredits::consume().
     */
    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /** The ones still holding a credit down. */
    public function scopeHeld(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    /** Held against an appointment that has not happened yet. */
    public function scopeReserved(Builder $query): Builder
    {
        return $query->held()->whereNull('consumed_at');
    }
}
