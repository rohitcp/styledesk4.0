<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * How this business's loyalty scheme works.
 *
 * Two rules and a name. The earning rule turns money into points, the
 * redemption rule turns points back into money, and everything else on the
 * screen is a boundary on one of the two.
 *
 * Switching the scheme off stops both. It erases nothing: the balances stay,
 * the history stays readable, and the rules are exactly as they were when the
 * business comes back — a salon that paused rewards for a difficult quarter
 * has not told its clients their points were forfeited.
 */
class LoyaltySettings extends Model
{
    use BelongsToTenant;

    protected $table = 'loyalty_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'eligible_purchases' => 'array',
            'notify_earned_email' => 'boolean',
            'notify_earned_sms' => 'boolean',
            'notify_reward_email' => 'boolean',
            'notify_reward_sms' => 'boolean',
        ];
    }

    /**
     * This business's settings, whether or not anybody has saved any.
     *
     * Unsaved rather than created on read, the same way ReviewSettings works:
     * a business that has never opened the screen has no row, and reading the
     * defaults must not quietly write one. The screen's save is what makes it
     * real.
     */
    public static function forTenant(?Tenant $tenant): self
    {
        $existing = $tenant === null
            ? null
            : self::query()->where('tenant_id', $tenant->getTenantKey())->first();

        return $existing ?? new self(self::defaults() + [
            'tenant_id' => $tenant?->getTenantKey(),
        ]);
    }

    /**
     * The rule a business gets before it has chosen one.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = config('loyalty.defaults');

        return [
            'is_enabled' => false,
            'program_name' => $defaults['program_name'],
            'description' => null,
            'spend_amount' => $defaults['spend_amount'],
            'points_earned' => $defaults['points_earned'],
            'eligible_purchases' => self::defaultPurchases(),
            'points_required' => $defaults['points_required'],
            'reward_value_minor' => $defaults['reward_value'] * 100,
            'minimum_redemption' => $defaults['minimum_redemption'],
            'maximum_reward_minor' => null,
            'expiry' => 'never',
        ];
    }

    /** The purchase types the MVP recommends, from the config's own list. */
    public static function defaultPurchases(): array
    {
        return collect(config('loyalty.purchases'))
            ->filter(fn (array $purchase) => $purchase['default'] && $purchase['available'])
            ->keys()
            ->all();
    }

    /** Every purchase type, including the ones nothing can sell yet. */
    public static function purchases(): array
    {
        return array_keys(config('loyalty.purchases'));
    }

    /** The ones a save may actually name. */
    public static function availablePurchases(): array
    {
        return collect(config('loyalty.purchases'))
            ->filter(fn (array $purchase) => $purchase['available'])
            ->keys()
            ->all();
    }

    public static function expiries(): array
    {
        return array_keys(config('loyalty.expiry'));
    }

    /** Does this business give points for this kind of money? */
    public function earnsOn(string $purchase): bool
    {
        return in_array($purchase, $this->eligible_purchases ?? [], true);
    }

    /**
     * What an amount of eligible spend is worth in points.
     *
     * Whole units of the rule only. A business saying "$5 = 1 point" means a
     * $17 sale earns 3, not 3.4 — points are counted things, and a fraction of
     * one is a number no client can check against their receipt.
     */
    public function pointsFor(int $eligibleMinor): int
    {
        $step = max(1, (int) $this->spend_amount) * 100;

        return intdiv(max(0, $eligibleMinor), $step) * max(0, (int) $this->points_earned);
    }

    /**
     * What a number of points is worth in money, in minor units.
     *
     * Whole rewards only, for the same reason: 500 points is $5 and 900 points
     * is still $5. The remainder stays on the balance rather than being spent
     * as change nobody agreed to.
     */
    public function rewardMinorFor(int $points): int
    {
        $required = max(1, (int) $this->points_required);
        $value = intdiv(max(0, $points), $required) * (int) $this->reward_value_minor;

        return $this->maximum_reward_minor === null
            ? $value
            : min($value, (int) $this->maximum_reward_minor);
    }

    /**
     * The points a client still needs before their next reward.
     *
     * Measured against the redemption rule rather than the minimum, because
     * the minimum is a floor on spending and this is a target to reach. Where
     * a business has set the floor higher than one reward, the floor is what
     * a client is actually working towards, so the larger of the two wins.
     */
    public function pointsToNextReward(int $balance): int
    {
        $target = $this->nextRewardAt($balance);

        return max(0, $target - $balance);
    }

    /** The balance the next reward unlocks at. */
    public function nextRewardAt(int $balance): int
    {
        $required = max(1, (int) $this->points_required);
        $minimum = max((int) $this->minimum_redemption, $required);

        if ($balance < $minimum) {
            return $minimum;
        }

        return (intdiv($balance, $required) + 1) * $required;
    }

    /** Enough points to be spent at all. */
    public function canRedeem(int $balance): bool
    {
        return $balance >= max((int) $this->minimum_redemption, (int) $this->points_required)
            && $this->rewardMinorFor($balance) > 0;
    }

    /** "500 points = $5", as the business stated it. */
    public function rewardRuleLabel(?string $currency = null): string
    {
        return __('loyalty.settings.rule', [
            'points' => number_format((int) $this->points_required),
            'value' => Money::format($this->reward_value_minor / 100, $currency),
        ]);
    }
}
