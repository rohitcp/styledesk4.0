<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One country a business operates in. Position 0 is the primary.
 */
class TenantCountry extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }
}
