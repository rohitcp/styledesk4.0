<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One use of a coupon or offer.
 *
 * What it took off is kept rather than recomputed: the promotion may be
 * edited afterwards, and last month's discount was what it was.
 */
class PromotionRedemption extends Model
{
    use BelongsToTenant;

    /** Written once, like every other record of something that happened. */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'booking_total_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
