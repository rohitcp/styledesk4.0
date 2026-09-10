<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Money taken for a membership.
 *
 * Its own table rather than a nullable booking on `booking_payments`: a
 * membership sale is not an appointment, and a payments table whose booking
 * is sometimes absent is one every report has to remember to ask about.
 */
class MembershipPayment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class, 'client_membership_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * How it was taken, in the words the booking screens use.
     *
     * One name per method wherever money is taken: a reader who has seen
     * "Credit card" on a booking receipt should not meet "card" on a
     * membership one.
     */
    public function methodLabel(): string
    {
        return __('bookings.methods.'.$this->method.'.name');
    }

    /**
     * Whether this row is money going back rather than money coming in.
     *
     * A refund is a second row rather than an edit to the first — the
     * original payment happened, and a history that rewrites it cannot
     * answer "what did we actually take in March". The same convention
     * BookingPayment follows.
     */
    public function isRefund(): bool
    {
        return $this->status === 'refunded';
    }
}
