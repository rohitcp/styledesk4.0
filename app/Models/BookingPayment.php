<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One payment against one booking.
 *
 * A bill settled half in cash and half on a card is two of these, and a
 * refund later is a third, so what has been paid is the sum of the rows
 * rather than a column somebody has to remember to keep in step.
 *
 * Nothing here is a card number. What is stored is what a person would write
 * on a paper receipt: which way it was paid, how much, and whatever reference
 * the machine printed.
 */
class BookingPayment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function methodLabel(): string
    {
        return __('bookings.methods.'.$this->method.'.name');
    }

    public function amountLabel(): string
    {
        return Money::format($this->amount_minor / 100, $this->currency_code);
    }
}
