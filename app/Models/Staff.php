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

    /**
     * Keep role_id in step with the role string.
     *
     * Every existing caller writes `role` as a string — onboarding, invitation
     * acceptance, the seeders, the tests. Resolving the id here means none of
     * them has to change and none of them can forget, which matters because a
     * staff row with a role name but no role_id resolves to no permissions at
     * all: an owner locked out of their own business by a save.
     */
    protected static function booted(): void
    {
        static::saving(function (self $staff) {
            if ($staff->role_id !== null || $staff->role === null || $staff->tenant_id === null) {
                return;
            }

            $staff->role_id = Role::withoutGlobalScopes()
                ->where('tenant_id', $staff->tenant_id)
                ->where('key', $staff->role)
                ->value('id');
        });
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

    /**
     * Not named role().
     *
     * `staff.role` is still a string column, and Eloquent resolves an
     * attribute before a relation of the same name — so $staff->role would
     * hand back "manager" rather than the Role model, silently, wherever a
     * model was expected. The column stays for now because every existing
     * caller writes it; the relation takes a name that cannot collide.
     */
    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
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
