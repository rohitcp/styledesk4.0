<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One grant: a role, a permission from the catalogue, and the scope it holds
 * it at. Not tenant-scoped itself — it hangs off a role that already is.
 */
class RolePermission extends Model
{
    protected $guarded = [];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
