<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Someone who delivers services. user_id is nullable: the team step invites
 * people who do not have an account yet.
 */
class Staff extends Model
{
    use BelongsToTenant;

    protected $table = 'staff';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'provides_services' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The one location this person works at, or null for all of them.
     *
     * Copied from the invitation on acceptance rather than read through it,
     * because an invitation can be deleted and a staff member's location must
     * outlive it.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
