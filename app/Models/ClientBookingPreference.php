<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * How one client likes to be booked, as somebody actually said it.
 *
 * Only what a person told the business is kept here. What the diary noticed —
 * that they come every four weeks, that they always take an afternoon — is
 * worked out when the panel is read, so it cannot go stale and can never be
 * quoted back to a client as though they had asked for it.
 */
class ClientBookingPreference extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
