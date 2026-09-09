<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a service costs in one currency.
 *
 * Not tenant-scoped directly: it hangs off a service, which already is, and a
 * second tenant_id could contradict its parent's.
 */
class ServicePrice extends Model
{
    protected $guarded = [];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected function casts(): array
    {
        return ['deposit_required' => 'boolean'];
    }

    /** How the client is paying, and so which of the two prices applies. */
    public const METHODS = ['card', 'cash'];

    public function amount(): string
    {
        return number_format($this->price_minor / 100, 2);
    }

    /** The cash price as a form field holds it, blank where there is none. */
    public function cashAmount(): string
    {
        return $this->cash_price_minor === null ? '' : number_format($this->cash_price_minor / 100, 2);
    }

    /**
     * What this costs, paid that way.
     *
     * Null cash means "the same as card" rather than nothing: every service
     * priced before there were two prices has one, and should keep charging
     * it whichever way somebody pays.
     */
    public function minorFor(string $method = 'card'): int
    {
        return $method === 'cash'
            ? (int) ($this->cash_price_minor ?? $this->price_minor)
            : (int) $this->price_minor;
    }

    /** Whether the two prices actually differ, which is what makes it worth showing both. */
    public function hasTwoPrices(): bool
    {
        return $this->cash_price_minor !== null
            && (int) $this->cash_price_minor !== (int) $this->price_minor;
    }

    /**
     * The deposit as a form field holds it.
     *
     * Read through the type, because the column means two things: minor units
     * for a fixed amount, whole percent for a percentage. Empty when no
     * deposit is required, so an untouched field reads as "none" rather than
     * as zero.
     */
    public function depositValue(): string
    {
        if (! $this->deposit_required || $this->deposit_value === null) {
            return '';
        }

        return $this->deposit_type === 'percent'
            ? (string) $this->deposit_value
            : number_format($this->deposit_value / 100, 2, '.', '');
    }

    /**
     * The deposit rule this price insists on, or nothing.
     *
     * Read through the type, because the column means two things: whole
     * percent for a percentage, minor units for a flat sum. Shaped for the
     * booking screen, which has to work the figure out for itself as the
     * bill moves.
     *
     * @return array{type: string, percent: ?int, minor: ?int}|null
     */
    public function requiredDeposit(): ?array
    {
        if (! $this->deposit_required || $this->deposit_value === null) {
            return null;
        }

        return [
            'type' => (string) $this->deposit_type,
            'percent' => $this->deposit_type === 'percent' ? (int) $this->deposit_value : null,
            'minor' => $this->deposit_type === 'percent' ? null : (int) $this->deposit_value,
        ];
    }

    /**
     * What that rule comes to on this price, paid this way.
     *
     * A percentage follows the price the booking is actually worked out at,
     * so a service quoted at its cash price asks for a deposit on the cash
     * price rather than on the card one.
     */
    public function requiredDepositMinor(string $method = 'card'): int
    {
        $rule = $this->requiredDeposit();

        if ($rule === null) {
            return 0;
        }

        return $rule['type'] === 'percent'
            ? (int) round($this->minorFor($method) * $rule['percent'] / 100)
            : (int) $rule['minor'];
    }

    /** What the deposit comes to, in words, for a read-only screen. */
    public function depositLabel(): string
    {
        if (! $this->deposit_required || $this->deposit_value === null) {
            return '';
        }

        return $this->deposit_type === 'percent'
            ? $this->deposit_value.'%'
            : Money::symbol($this->currency_code).number_format($this->deposit_value / 100, 2);
    }
}
