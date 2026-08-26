<?php

declare(strict_types=1);

namespace App\Models;

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

    public function amount(): string
    {
        return number_format($this->price_minor / 100, 2);
    }
}
