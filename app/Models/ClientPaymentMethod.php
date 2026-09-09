<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A card StyleDesk can charge again, without StyleDesk holding the card.
 *
 * What this model deliberately cannot do is tell you a card number. It holds
 * the gateway's reference and the four digits a receptionist says out loud —
 * the card itself lives at the gateway, whose business that is.
 *
 * Never add an accessor, a cast or a column that would carry a full number or
 * a CVC through here. The absence is the feature.
 */
class ClientPaymentMethod extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public const STATUSES = ['active', 'expired', 'removed'];

    protected function casts(): array
    {
        return [
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'is_default' => 'boolean',
            'removed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** The memberships that renew on this card. */
    public function memberships(): HasMany
    {
        return $this->hasMany(ClientMembership::class, 'payment_method_id');
    }

    /* ----------------------------------------------------------- reading */

    /** "Visa •••• 4242", the way a receptionist would say it. */
    public function label(): string
    {
        return trim(($this->brandLabel() ?? '').' •••• '.($this->last4 ?? '????'));
    }

    /** The brand as a person writes it, not as a gateway keys it. */
    public function brandLabel(): ?string
    {
        if ($this->brand === null) {
            return null;
        }

        return __('payments.brands.'.$this->brand, [], null) === 'payments.brands.'.$this->brand
            ? ucfirst(str_replace('_', ' ', $this->brand))
            : __('payments.brands.'.$this->brand);
    }

    /** "08/29". */
    public function expiryLabel(): ?string
    {
        if ($this->exp_month === null || $this->exp_year === null) {
            return null;
        }

        return str_pad((string) $this->exp_month, 2, '0', STR_PAD_LEFT)
            .'/'.substr((string) $this->exp_year, -2);
    }

    /**
     * The last moment this card is good for.
     *
     * A card expires at the END of its month — one dated 08/29 works
     * throughout August. Treating the 1st as the deadline would refuse a card
     * for thirty days it was still valid for.
     */
    public function expiresAfter(): ?Carbon
    {
        if ($this->exp_month === null || $this->exp_year === null) {
            return null;
        }

        return Carbon::create($this->exp_year, $this->exp_month, 1)->endOfMonth();
    }

    public function isExpired(): bool
    {
        $expires = $this->expiresAfter();

        return $expires !== null && $expires->isPast();
    }

    /**
     * Whether it runs out this month or next.
     *
     * The warning window, not the failure: a membership renewing in three
     * weeks on a card that dies in two is a payment nobody has to lose if
     * somebody is told now.
     */
    public function isExpiringSoon(): bool
    {
        $expires = $this->expiresAfter();

        return $expires !== null
            && ! $expires->isPast()
            && $expires->lte(Carbon::today()->addMonth()->endOfMonth());
    }

    public function isRemoved(): bool
    {
        return $this->status === 'removed';
    }

    /** Whether a renewal could actually go through on it. */
    public function isChargeable(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    public function statusLabel(): string
    {
        if ($this->isRemoved()) {
            return __('payments.methods_list.statuses.removed');
        }

        if ($this->isExpired()) {
            return __('payments.methods_list.statuses.expired');
        }

        return __('payments.methods_list.statuses.active');
    }

    /* ----------------------------------------------------------- writing */

    /**
     * Make this the card renewals reach for.
     *
     * One statement per side rather than a loop, and both inside a
     * transaction: a client with two defaults is a client whose renewal
     * picks one at random, and a client with none is a renewal that cannot
     * run at all.
     */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('client_id', $this->client_id)
                ->whereKeyNot($this->getKey())
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }

    /**
     * Take it out of use without erasing it.
     *
     * A membership renewed on this card last month still points at it, and a
     * row that vanished would leave that payment unexplained. The gateway is
     * told separately — this is only StyleDesk's own record.
     */
    public function markRemoved(): void
    {
        $this->forceFill([
            'status' => 'removed',
            'is_default' => false,
            'removed_at' => now(),
        ])->save();
    }

    /* ------------------------------------------------------------ scopes */

    /** The cards a client could actually be charged on. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * The one to reach for: the client's default, or their only other card.
     *
     * Newest last so a client whose default was removed falls to the card
     * they added most recently, which is the one they are most likely to
     * have meant.
     */
    public static function defaultFor(int $clientId): ?self
    {
        return static::query()
            ->forClient($clientId)
            ->usable()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
    }
}
