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
     * Shortcuts into the box beside them rather than a setting: a flat tip
     * has no business-wide list to read, and three round numbers save the
     * typing without claiming to be what the business offers.
     */
    public const QUICK_FIXED_AMOUNTS = [5, 10, 15];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'percentages' => 'array',
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
}
