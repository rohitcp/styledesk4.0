<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opening hours for one weekday at one location.
 *
 * No BelongsToTenant: this hangs off a location, which is already scoped.
 * Adding the trait would require a tenant_id column that could contradict
 * its parent's.
 */
class LocationHour extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_open' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
