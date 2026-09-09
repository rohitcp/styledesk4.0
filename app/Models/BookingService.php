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

    /**
     * The room this line was actually given.
     *
     * Per line rather than per booking, because a client having two services
     * can be in two rooms — and because the alternative, listing every room
     * the service *could* use, reads as though one client had been given the
     * whole building. Null on bookings taken before rooms were recorded per
     * service; those still answer from the booking itself.
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
