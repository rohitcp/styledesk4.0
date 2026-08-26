<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A bookable service. Priced in minor units; currency lives on the tenant.
 */
class Service extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'online_booking_enabled' => 'boolean',
            'taxable' => 'boolean',
        ];
    }

    public function prices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    /**
     * Price in one currency, as a decimal string for a form field.
     *
     * Empty rather than "0.00" when unset, so an untouched currency reads as
     * "no price yet" instead of "free".
     */
    public function priceIn(string $currency): string
    {
        $price = $this->prices->firstWhere('currency_code', $currency);

        return $price ? $price->amount() : '';
    }

    /**
     * Replace the price set.
     *
     * @param  array<string, string|null>  $prices  currency => decimal amount
     */
    public function syncPrices(array $prices): void
    {
        foreach ($prices as $currency => $amount) {
            if ($amount === null || $amount === '') {
                $this->prices()->where('currency_code', $currency)->delete();

                continue;
            }

            $this->prices()->updateOrCreate(
                ['currency_code' => $currency],
                // Rounded once, here, so no float arithmetic happens later.
                ['price_minor' => (int) round(((float) $amount) * 100)]
            );
        }
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'service_staff');
    }

    /** Price in the tenant's primary currency, for display. */
    public function priceFormatted(): string
    {
        return $this->priceIn((string) $this->tenant?->currency_code);
    }
}
