<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A period a resource cannot be booked, and why.
 *
 * Not a flag on the resource: "unavailable" without an end is a state
 * somebody has to remember to undo, and a calendar cannot draw it. A row
 * with a start and an end is something the schedule can show and something
 * that clears itself.
 */
class ResourceBlock extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Whether this block is in force at a given moment. */
    public function covers(Carbon $moment): bool
    {
        if ($this->starts_at->greaterThan($moment)) {
            return false;
        }

        // No end is "until further notice", which covers every later moment.
        return $this->ends_at === null || $this->ends_at->greaterThan($moment);
    }

    public function reasonLabel(): string
    {
        return __('resources.block_reasons.'.$this->reason);
    }
}
