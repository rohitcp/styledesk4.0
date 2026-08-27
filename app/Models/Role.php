<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A role, owned by one tenant.
 *
 * The five system roles are seeded per tenant rather than shared globally: a
 * business editing what Manager means must not change it for every other
 * business on the platform.
 */
class Role extends Model
{
    use BelongsToTenant;

    /** Roles the product itself relies on, which cannot be deleted. */
    public const OWNER = 'owner';

    public const SYSTEM_KEYS = ['owner', 'administrator', 'manager', 'front-desk', 'service-provider'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'role_id');
    }

    /**
     * This role's grants as permission => scope.
     *
     * @return array<string, string>
     */
    public function permissionMap(): array
    {
        return $this->permissions->pluck('scope', 'permission')->all();
    }

    /**
     * Whether this role grants a permission, optionally at a given scope.
     *
     * The Owner is answered without consulting the table. Its grants are
     * seeded from the catalogue, but a permission added to the catalogue
     * after seeding would otherwise leave the one role that can fix
     * everything else unable to reach the thing that needs fixing.
     */
    public function grants(string $permission, string $scope = 'own'): bool
    {
        if ($this->key === self::OWNER) {
            return true;
        }

        if (Permissions::isOwnerOnly($permission)) {
            return false;
        }

        $held = $this->permissionMap()[$permission] ?? null;

        return $held !== null && Permissions::scopeSatisfies($held, $scope);
    }

    /** The widest scope this role holds for a permission, or null. */
    public function scopeFor(string $permission): ?string
    {
        if ($this->key === self::OWNER) {
            return Permissions::isOwnerOnly($permission) ? 'all' : 'all';
        }

        return $this->permissionMap()[$permission] ?? null;
    }

    /**
     * The role's name in the reader's language.
     *
     * Only the system roles translate. A role a business created and named
     * itself keeps its own words, exactly as a service name or a client note
     * does — isSystem() is what tells the two apart, so a custom role called
     * "Owner" would still be shown as the business wrote it.
     */
    public function label(): string
    {
        $key = 'roles.'.$this->key.'.name';

        return $this->isSystem() && trans()->has($key) ? __($key) : $this->name;
    }

    /** The description, under the same rule as label(). */
    public function describe(): ?string
    {
        $key = 'roles.'.$this->key.'.description';

        return $this->isSystem() && trans()->has($key) ? __($key) : $this->description;
    }

    public function isSystem(): bool
    {
        return $this->is_system || in_array($this->key, self::SYSTEM_KEYS, true);
    }
}
