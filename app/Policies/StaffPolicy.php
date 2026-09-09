<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;
use App\Support\RoleGuard;

/**
 * Who may see and change staff records.
 *
 * Every method re-checks the tenant rather than trusting the BelongsToTenant
 * global scope. Staff is the record that decides what someone can do, so a
 * refactor that loosened the scope would be a refactor that handed out access.
 */
class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.view', 'own');
    }

    public function view(User $user, Staff $staff): bool
    {
        if (! $this->sameTenant($user, $staff)) {
            return false;
        }

        // Someone with only `own` may still open their own record.
        if ($staff->user_id === $user->id) {
            return $user->hasPermission('staff.view', 'own');
        }

        return $user->hasPermission('staff.view', $staff->location_id ? 'location' : 'all');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.create', 'all');
    }

    public function update(User $user, Staff $staff): bool
    {
        if (! $this->sameTenant($user, $staff)) {
            return false;
        }

        if ($staff->user_id === $user->id) {
            return $user->hasPermission('staff.edit', 'own');
        }

        return $user->hasPermission('staff.edit', $staff->location_id ? 'location' : 'all');
    }

    public function deactivate(User $user, Staff $staff): bool
    {
        return $this->sameTenant($user, $staff) && RoleGuard::canDeactivate($user, $staff);
    }

    public function archive(User $user, Staff $staff): bool
    {
        return $this->sameTenant($user, $staff)
            && $user->hasPermission('staff.archive', 'all')
            && ! ($staff->user && RoleGuard::wouldLeaveTenantWithoutOwner($staff->user));
    }

    public function delete(User $user, Staff $staff): bool
    {
        if (! $this->sameTenant($user, $staff) || ! $user->hasPermission('staff.delete', 'all')) {
            return false;
        }

        // Nobody deletes themselves out of the business.
        if ($staff->user_id === $user->id) {
            return false;
        }

        // And the business is never left without an owner, §33.
        return ! ($staff->user && RoleGuard::wouldLeaveTenantWithoutOwner($staff->user));
    }

    public function invite(User $user): bool
    {
        return $user->hasPermission('staff.invite', 'all');
    }

    private function sameTenant(User $user, Staff $staff): bool
    {
        return $user->tenant_id !== null && $staff->tenant_id === $user->tenant_id;
    }
}
