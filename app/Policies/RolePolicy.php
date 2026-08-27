<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Who may look at roles.
 *
 * Phase 1 has no update, delete or duplicate methods because there are no
 * such actions. They arrive with the screens that need them rather than
 * sitting here permitting something nothing can do.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view', 'all');
    }

    public function view(User $user, Role $role): bool
    {
        // The tenant is re-checked rather than left to the global scope: a
        // role is the record that decides what everyone else may do.
        return $user->tenant_id !== null
            && $role->tenant_id === $user->tenant_id
            && $user->hasPermission('roles.view_permissions', 'all');
    }
}
