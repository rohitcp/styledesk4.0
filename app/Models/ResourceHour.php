<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of a resource's own opening hours.
 *
 * Not tenant-scoped directly: it hangs off a resource, which already is, and
 * a second tenant_id could contradict its parent's.
 */
class ResourceHour extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_available' => 'boolean', 'day' => 'integer'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
