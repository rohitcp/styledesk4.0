<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;

/**
 * Who may do what with service categories.
 *
 * Every method also re-checks the tenant. The BelongsToTenant global scope
 * already hides other tenants' rows, but a policy that assumed that would be
 * one refactor away from a cross-tenant hole — and the spec asks for the
 * backend to reject the access outright, not merely fail to find it.
 */
class ServiceCategoryPolicy
{
    /** Roles allowed to change the category configuration. */
    private const MANAGERS = ['owner', 'administrator', 'manager'];

    /** Roles allowed to remove or archive one. */
    private const DESTROYERS = ['owner', 'administrator'];

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, ServiceCategory $category): bool
    {
        return $this->sameTenant($user, $category);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(...self::MANAGERS);
    }

    public function update(User $user, ServiceCategory $category): bool
    {
        return $this->sameTenant($user, $category) && $user->hasRole(...self::MANAGERS);
    }

    public function reorder(User $user): bool
    {
        // Reordering changes what everyone sees, so it sits with the roles
        // that own the configuration rather than with Manager.
        return $user->hasRole(...self::DESTROYERS);
    }

    public function delete(User $user, ServiceCategory $category): bool
    {
        return $this->sameTenant($user, $category) && $user->hasRole(...self::DESTROYERS);
    }

    private function sameTenant(User $user, ServiceCategory $category): bool
    {
        return $user->tenant_id !== null && $category->tenant_id === $user->tenant_id;
    }
}
