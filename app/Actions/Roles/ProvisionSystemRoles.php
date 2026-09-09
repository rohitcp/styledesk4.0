<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Give a tenant its own copy of the five system roles.
 *
 * Run when a tenant is created, and safe to run again: it updates the system
 * roles' permissions in place rather than duplicating them, so a business that
 * predates a new permission picks it up without losing its custom roles or its
 * own edits to a custom role's matrix.
 */
class ProvisionSystemRoles
{
    public function forTenant(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            foreach (config('role_defaults') as $key => $definition) {
                $role = Role::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->getTenantKey(), 'key' => $key],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'display_order' => $definition['display_order'],
                        'is_system' => true,
                        'is_default' => $definition['is_default'] ?? false,
                    ]
                );

                $this->syncPermissions($role, $definition['permissions']);
            }
        });
    }

    /**
     * @param  array<string, string>|string  $permissions
     */
    public function syncPermissions(Role $role, array|string $permissions): void
    {
        // '*' means every permission in the catalogue at full scope, which is
        // only ever the Owner. Writing those 135 rows by hand would be a list
        // to keep in step with the catalogue forever.
        if ($permissions === '*') {
            $permissions = [];

            foreach (array_keys(Permissions::all()) as $key) {
                $permissions[$key] = 'all';
            }
        }

        $rows = [];

        foreach ($permissions as $permission => $scope) {
            /**
             * Unknown keys are dropped rather than stored.
             *
             * A permission removed from the catalogue leaves rows behind, and
             * an unrecognised grant is one nothing will ever check — keeping
             * it would only make the matrix show a row the product cannot
             * honour.
             */
            if (! Permissions::exists($permission)) {
                continue;
            }

            // A scope the permission does not accept is corrected rather than
            // stored, so a check can never receive a value it cannot compare.
            $allowed = Permissions::scopesFor($permission);
            $scope = in_array($scope, $allowed, true) ? $scope : end($allowed);

            $rows[] = [
                'role_id' => $role->id,
                'permission' => $permission,
                'scope' => $scope,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Replace wholesale: a permission absent from the new set has been
        // revoked, and diffing would leave a removed grant in place.
        RolePermission::where('role_id', $role->id)->delete();

        foreach (array_chunk($rows, 200) as $chunk) {
            RolePermission::insert($chunk);
        }

        $role->unsetRelation('permissions');
    }
}
