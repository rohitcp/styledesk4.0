<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Whether this business takes tips, and what it suggests.
 *
 * Switching tips off hides the question at the till; it does not erase how
 * the business had answered it. A salon that turns tipping off for the winter
 * and back on in the spring should find their services exactly as they left
 * them, which is why nothing here cascades into the service rows.
 */
class TipSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'tip_settings';

    protected $guarded = [];

    /** Percentage of the bill, or a flat sum. */
    public const TYPES = ['percent', 'fixed'];

    /**
     * What a single service may say instead of following the business.
     *
     * Only a flat sum. The percentage is the business's own answer — set once
     * in App settings → Tips, suggested at the till, and changeable on the
     * booking itself — so a service repeating it would be a second copy of
     * one number, and the two would eventually disagree. A service either
     * follows that default or names a sum of its own.
     */
    public const SERVICE_TYPES = ['fixed'];

    /**
     * What a client is offered when the business has not said otherwise.
     *
     * Four is about as many as anybody reads before choosing; the custom box
     * and the no-tip option are separate from these because they are not
     * percentages.
     */
    public const DEFAULT_PERCENTAGES = [15, 18, 20, 25];

    /**
     * Quick picks for a flat sum, on the service form.
     *
     * Shortcuts into the box beside them rather than a setting: a service's
     * own flat tip is one number, and three round ones save the typing.
     */
    public const QUICK_FIXED_AMOUNTS = [5, 10, 15];

    /**
     * What a client is offered when the business tips in flat sums and has
     * not said otherwise. The same four the till shows for percentages, in
     * the shape money comes in.
     */
    public const DEFAULT_AMOUNTS = [5, 10, 15, 20];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'percentages' => 'array',
            'amounts' => 'array',
            'default_tip_value' => 'integer',
            'require_selection' => 'boolean',
            'allow_no_tip' => 'boolean',
        ];
    }

    /**
     * This business's settings, whether or not anybody has saved any.
     *
     * Never null: every screen that asks about tipping would otherwise have
     * to handle "no row yet", and the honest answer for a business that has
     * not thought about it is the defaults with tipping switched off.
     */
    public static function forTenant(Tenant $tenant): self
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            [
                'is_enabled' => false,
                'percentages' => self::DEFAULT_PERCENTAGES,
                'amounts' => self::DEFAULT_AMOUNTS,
                'default_tip_type' => 'percent',
                'default_tip_value' => 20,
                'require_selection' => false,
                'allow_no_tip' => true,
            ]
        );
    }

    /** The percentages to offer, falling back to ours. */
    public function offeredPercentages(): array
    {
        $percentages = collect($this->percentages ?: self::DEFAULT_PERCENTAGES)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0 && $value <= 100)
            ->unique()
            ->sort()
            ->values();

        return $percentages->isEmpty() ? self::DEFAULT_PERCENTAGES : $percentages->all();
    }

    /**
     * The flat sums to offer, falling back to ours.
     *
     * Whole units of the business's own money — the same units
     * default_tip_value holds for a flat tip, so nothing has to translate
     * between the default and the row it sits in.
     *
     * @return array<int, int>
     */
    public function offeredAmounts(): array
    {
        $amounts = collect($this->amounts ?: self::DEFAULT_AMOUNTS)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->sort()
            ->values();

        return $amounts->isEmpty() ? self::DEFAULT_AMOUNTS : $amounts->all();
    }

    /**
     * Whichever list the till should actually show.
     *
     * The type decides: a business tipping in flat sums has no use for a row
     * of percentages, and showing one is offering a choice its own settings
     * say it does not make.
     *
     * @return array<int, int>
     */
    public function offeredTips(): array
    {
        return $this->default_tip_type === 'fixed'
            ? $this->offeredAmounts()
            : $this->offeredPercentages();
    }

    /** Whether the business tips in flat sums rather than percentages. */
    public function tipsInAmounts(): bool
    {
        return $this->default_tip_type === 'fixed';
    }
}
