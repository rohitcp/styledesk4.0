<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * What the client thought of one appointment.
 *
 * Whole stars, and the staff member is stored on the review rather than read
 * through the booking: the review is about who did the work, and a booking
 * reassigned afterwards must not quietly move the compliment with it.
 */
class BookingReview extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
