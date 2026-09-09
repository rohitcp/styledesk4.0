<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a membership costs in one currency.
 *
 * Nothing here converts. The business enters each price itself, so a rate
 * moving overnight never changes what a client was quoted — the same rule
 * ServicePrice follows, and for the same reason.
 *
 * Not tenant-scoped directly: it hangs off a plan, which already is.
 */
class MembershipPlanPrice extends Model
{
    protected $guarded = [];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'regular_value_minor' => 'integer',
            'joining_fee_minor' => 'integer',
            'setup_fee_minor' => 'integer',
        ];
    }

    /** One amount as a form field holds it, blank where there is none. */
    public function amount(string $column = 'price_minor'): string
    {
        $minor = $this->{$column};

        return $minor === null ? '' : number_format($minor / 100, 2, '.', '');
    }

    public function priceLabel(): string
    {
        return Money::format($this->price_minor / 100, $this->currency_code);
    }

    /**
     * What the client saves against buying the services separately.
     *
     * Nought where no regular value was claimed, and never negative: a
     * package priced above its own regular value is a mistake the server
     * refuses, not a saving to advertise.
     */
    public function savingMinor(): int
    {
        return $this->regular_value_minor === null
            ? 0
            : max(0, (int) $this->regular_value_minor - (int) $this->price_minor);
    }

    /** Everything a sale freezes, in this currency. */
    public function totalMinor(): int
    {
        return (int) $this->price_minor
            + (int) $this->joining_fee_minor
            + (int) $this->setup_fee_minor;
    }
}
