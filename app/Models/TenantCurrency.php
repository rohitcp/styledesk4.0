<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/** One currency a business trades in. Position 0 is the primary. */
class TenantCurrency extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
