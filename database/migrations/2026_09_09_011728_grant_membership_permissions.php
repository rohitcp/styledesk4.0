<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the Membership permissions to the roles that already exist.
 *
 * A role's permissions are rows written when the business was created, so
 * adding keys to config/role_defaults.php gives them to businesses created
 * afterwards and to nobody else. Without this, every salon already using
 * StyleDesk would find the membership screen refusing them — including the
 * people whose job it is to configure it.
 *
 * Only where the role has no opinion already: a business that has deliberately
 * taken something away should not have this hand it back.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const GRANTS = [
        'administrator' => [
            'membership.view_settings' => 'all',
            'membership.manage_settings' => 'all',
        ],

        /* They may read the terms the business sells on — a manager fielding
           "can I cancel?" needs the answer. Setting those terms is one
           decision for the whole business. */
        'manager' => [
            'membership.view_settings' => 'all',
        ],
    ];

    public function up(): void
    {
        Role::withoutGlobalScopes()
            ->whereIn('key', array_keys(self::GRANTS))
            ->with('permissions')
            ->cursor()
            ->each(function (Role $role): void {
                $held = $role->permissions->pluck('permission');

                foreach (self::GRANTS[$role->key] as $permission => $scope) {
                    if ($held->contains($permission)) {
                        continue;
                    }

                    RolePermission::create([
                        'role_id' => $role->id,
                        'permission' => $permission,
                        'scope' => $scope,
                    ]);
                }
            });
    }

    /**
     * Taken away again, and only these. The owner is untouched in both
     * directions: their permissions are '*' rather than rows.
     */
    public function down(): void
    {
        RolePermission::query()->where('permission', 'like', 'membership.%')->delete();
    }
};
