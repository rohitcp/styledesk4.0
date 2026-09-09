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
}
