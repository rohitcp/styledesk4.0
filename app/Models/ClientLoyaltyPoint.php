<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One line of a client's rewards history.
 *
 * Written when something happened and never edited afterwards. There is no
 * route that changes one and no method here that does: a balance somebody can
 * tidy is not a balance anybody can be shown at the desk. A mistake is
 * corrected the way a mistake in a till is corrected — with another line
 * saying so.
 *
 * `points` is signed, so the balance is a sum and nothing downstream has to
 * know which types add and which take away.
 */
class ClientLoyaltyPoint extends Model
{
    use BelongsToTenant;

    protected $table = 'client_loyalty_points';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'balance_after' => 'integer',
            'eligible_amount_minor' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The lines that still count towards a balance.
     *
     * Expiry is applied here, at the moment the balance is read, rather than
     * by a nightly sweep: a business that sets a twelve-month deadline gets
     * one immediately, and a business that never sets one — the default — has
     * nothing running against its data at three in the morning.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /** The lines a booking's points were written on. */
    public function scopeForBooking(Builder $query, int $bookingId): Builder
    {
        return $query->where('booking_id', $bookingId);
    }

    /** Which icon the history reads this line by. */
    public function icon(): string
    {
        return config('loyalty.activities.'.$this->type.'.icon', 'star');
    }

    public function label(): string
    {
        return __('loyalty.activities.'.$this->type);
    }

    /** "+120" or "−500", with the sign a reader expects rather than a minus. */
    public function pointsLabel(): string
    {
        return ($this->points >= 0 ? '+' : '−').number_format(abs($this->points));
    }

    public function isCredit(): bool
    {
        return $this->points >= 0;
    }

    /**
     * Who did it, or StyleDesk where nobody did.
     *
     * The same distinction the client timeline draws: points awarded by a
     * completed appointment and points added by a manager on a Tuesday are
     * different facts, and a blank would read as missing data.
     */
    public function actor(): string
    {
        return $this->createdBy?->name ?? __('loyalty.activity.system');
    }

    public function isSystem(): bool
    {
        return $this->created_by === null;
    }

    /** The reason a manual adjustment gave, in the reader's language. */
    public function reasonLabel(): ?string
    {
        return $this->reason === null ? null : __('loyalty.reasons.'.$this->reason);
    }

    /**
     * What became of this line.
     *
     * Derived rather than stored, for the same reason expiry is applied when
     * the balance is summed: a status column would need something to keep it
     * true, and the one thing that changes on its own is the clock.
     *
     * "Pending" is deliberately not among the answers. Points a client has
     * not earned yet are worked out from the diary and never written down —
     * a forecast in a ledger is the row nobody deletes when the client
     * cancels — so no line can ever be in that state. The figure above the
     * table is where pending points are reported.
     */
    public function status(): string
    {
        if ($this->type === 'redeemed') {
            return 'redeemed';
        }

        if ($this->type === 'expired') {
            return 'expired';
        }

        if (in_array($this->type, ['refund_adjustment', 'cancellation_adjustment', 'manual_deduct'], true)) {
            return 'reversed';
        }

        /* A credit whose deadline has passed. The line still reads as earned
           — it was — but what it is worth now is nothing. */
        return $this->expires_at !== null && $this->expires_at->isPast() ? 'expired' : 'available';
    }

    public function statusLabel(): string
    {
        return __('loyalty.statuses.'.$this->status());
    }

    /** The badge class the status is read by. */
    public function statusClass(): string
    {
        return match ($this->status()) {
            'available' => 'styledesk_badge--success',
            'redeemed' => 'styledesk_badge--info',
            'expired' => 'styledesk_badge--soon',
            default => 'styledesk_badge--attention',
        };
    }

    /**
     * Whose work earned it, where that is a different person from whoever
     * wrote the line.
     *
     * An appointment's points belong to the stylist who did the work; a
     * manual adjustment belongs to the manager who made it. Reading one
     * column for both is what makes the table answerable at a glance.
     */
    public function staffName(): ?string
    {
        return $this->booking?->staff?->displayName()
            ?? ($this->isSystem() ? null : $this->createdBy?->name);
    }

    /** What this line was against: an appointment, or a membership payment. */
    public function sourceLabel(): ?string
    {
        if ($this->booking !== null) {
            return $this->booking->reference;
        }

        return $this->membership_payment_id !== null
            ? __('loyalty.client.membership_sale')
            : null;
    }
}
