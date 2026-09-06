<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the client Files permissions to the roles that already exist.
 *
 * A role's permissions are rows written when the business was created, so
 * adding keys to config/role_defaults.php gives them to businesses created
 * afterwards and to nobody else — every salon already using StyleDesk would
 * have opened a Files tab that refused them.
 *
 * Deleting is granted to nobody but Owner, Admin and Manager, which is the
 * whole point of it being a separate key: the desk files documents, and
 * removing one from a client's record is somebody else's decision.
 *
 * Only where the role has no opinion already. A business that has
 * deliberately taken something away should not have this hand it back.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const GRANTS = [
        'administrator' => [
            'clients.view_files' => 'all',
            'clients.upload_files' => 'all',
            'clients.manage_files' => 'all',
        ],

        'manager' => [
            'clients.view_files' => 'location',
            'clients.upload_files' => 'all',
            'clients.manage_files' => 'all',
        ],

        /* Read and file. Not remove. */
        'front-desk' => [
            'clients.view_files' => 'location',
            'clients.upload_files' => 'all',
        ],

        /* The clients they are assigned, and the before-and-afters they take
           themselves. */
        'service-provider' => [
            'clients.view_files' => 'assigned',
            'clients.upload_files' => 'all',
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
        RolePermission::query()
            ->whereIn('permission', ['clients.view_files', 'clients.upload_files', 'clients.manage_files'])
            ->delete();
    }
};
