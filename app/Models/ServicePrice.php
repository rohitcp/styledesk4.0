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

    public function amount(): string
    {
        return number_format($this->price_minor / 100, 2);
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
