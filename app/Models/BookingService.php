<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One service on one booking, priced and timed as it was on the day.
 *
 * The name, the minutes and the price are copied rather than read through
 * the service: a price list edited in March must not rewrite what somebody
 * was quoted in February, and a service deleted afterwards must not empty
 * the appointment that was taken for it.
 */
class BookingService extends Model
{
    protected $guarded = [];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
