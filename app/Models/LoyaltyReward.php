<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One thing a client's points can buy.
 *
 * The scheme's conversion rule — so many points for so much money — is the
 * floor, and lives on LoyaltySettings. A reward is the business saying
 * something more specific than that: this many points, for this, on these
 * services. What it is worth depends on which kind it is, which is why
 * `value_minor`, `percent` and `service_id` are all nullable and only one of
 * them is ever the answer.
 */
class LoyaltyReward extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'scope_ids' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The ledger lines spent on this reward.
     *
     * What makes a reward retireable rather than deletable: while anybody's
     * history points at it, the row is where that line reads its name from.
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(ClientLoyaltyPoint::class);
    }

    // -------------------------------------------------------------- scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The order the business put them in, then oldest first as a tiebreak. */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    // --------------------------------------------------------- what it is

    /** The kind of reward, in the reader's language. */
    public function typeLabel(): string
    {
        return __('loyalty.rewards.types.'.$this->type);
    }

    /** Which field this type is configured by: amount, percent, service, none. */
    public function needs(): string
    {
        return (string) config('loyalty.reward_types.'.$this->type.'.needs', 'none');
    }

    /**
     * What it is worth, said the way the catalogue reads it.
     *
     * A free service quotes the service rather than a price: the price is
     * whatever it costs on the day it is taken, and a figure printed here
     * would be the one number on the screen that could quietly go stale.
     */
    public function valueLabel(): string
    {
        return match ($this->needs()) {
            'amount' => Money::format(((int) $this->value_minor) / 100),
            'percent' => $this->percent.'%',
            'service' => $this->service?->name ?? __('loyalty.rewards.no_service'),
            default => __('loyalty.rewards.value_varies'),
        };
    }

    // ------------------------------------------------------ what it covers

    /**
     * Whether this reward may be spent on that service.
     *
     * Asked per service rather than per booking, because a reward restricted
     * to massages on a booking of a massage and a haircut applies to one line
     * and not the other — and a rule that answered for the whole booking
     * would have to choose between refusing it and discounting the haircut.
     */
    public function covers(?Service $service): bool
    {
        if ($this->scope === 'all_services') {
            return true;
        }

        if ($service === null) {
            return false;
        }

        $ids = array_map('intval', $this->scope_ids ?? []);

        return $this->scope === 'categories'
            ? in_array((int) $service->service_category_id, $ids, true)
            : in_array((int) $service->id, $ids, true);
    }

    /**
     * What this reward takes off a line of that much money.
     *
     * Never more than the line itself: a £10 reward against a £6 add-on takes
     * six pounds off, not ten, because the difference would be the salon
     * paying the client to attend. A percentage rounds to the penny the same
     * way every other discount in StyleDesk does.
     *
     * `custom` is worth nothing here on purpose. It is the type for a reward
     * whose value is a sentence — "a glass of fizz on the house" — and a till
     * that invented a figure for it would be making the business's decision
     * for it.
     */
    public function discountMinorOn(int $lineMinor): int
    {
        $off = match ($this->needs()) {
            'amount' => (int) $this->value_minor,
            'percent' => (int) round($lineMinor * ((int) $this->percent) / 100),
            /* The service is given, so what comes off is what it costs. */
            'service' => $lineMinor,
            default => 0,
        };

        return max(0, min($off, $lineMinor));
    }
}
