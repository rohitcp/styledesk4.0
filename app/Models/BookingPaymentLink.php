<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A request to pay, sent to the client to answer in their own time.
 *
 * The desk sends one when the client is not standing in front of them and the
 * money is not being taken at the till. What comes back is not money — this
 * application charges nothing — but an answer the desk can read: they opened
 * it, or they never did.
 *
 * Which is why `paid` is settled from the payments recorded against the
 * booking rather than claimed here. A link that marked itself paid because
 * somebody clicked it would be the one lie this table could tell.
 */
class BookingPaymentLink extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A token nobody can guess.
     *
     * The link is the whole credential: anybody holding it can read the
     * appointment it names, without signing in. So it is 64 characters of
     * randomness rather than an id, and it is never derived from anything
     * about the booking.
     */
    public static function newToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::withoutGlobalScopes()->where('token', $token)->exists());

        return $token;
    }

    /** Past its hour, whatever the column still says. */
    public function hasExpired(): bool
    {
        return $this->status !== 'paid'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Where this link has got to.
     *
     * Worked out rather than merely read, because two of the four answers
     * come from outside this row: paid is the money against the booking, and
     * expired is the clock. Reading the column alone would show "Sent" for a
     * link that ran out on Friday.
     */
    public function currentStatus(): string
    {
        if ($this->status === 'paid') {
            return 'paid';
        }

        if ($this->hasExpired()) {
            return 'expired';
        }

        return $this->status;
    }

    public function statusLabel(): string
    {
        return __('bookings.payment.link_statuses.'.$this->currentStatus());
    }

    public function statusClass(): string
    {
        return config('bookings.payment_link_statuses.'.$this->currentStatus().'.class', 'styledesk_badge--soon');
    }

    /**
     * Write down that the client opened it.
     *
     * Only the first time. A link read three times is one person checking
     * their appointment, not three answers, and a timestamp that moved every
     * visit would stop meaning "they have seen this".
     */
    public function markOpened(): void
    {
        if ($this->opened_at !== null || $this->currentStatus() !== 'sent') {
            return;
        }

        $this->forceFill(['status' => 'opened', 'opened_at' => now()])->save();
    }

    /**
     * Settle the link against what the booking has actually been paid.
     *
     * Called when money is recorded, because that is the only thing that can
     * make a link paid. Anything less than what was asked for leaves it where
     * it is: a $20 payment against a $50 link is a part payment, and the
     * client still owes what the link went out for.
     */
    public function settleAgainst(Booking $booking): void
    {
        if ($this->currentStatus() === 'paid') {
            return;
        }

        if ($booking->paidMinor() >= (int) $this->amount_minor) {
            $this->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        }
    }
}
