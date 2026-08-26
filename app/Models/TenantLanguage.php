<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/** One language a business supports. Position 0 is the primary. */
class TenantLanguage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
