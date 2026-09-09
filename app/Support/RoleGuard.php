<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Role;
use App\Models\Staff;
use App\Models\User;

/**
 * The rules that stop an administrator dismantling the business, from §32-33.
 *
 * Kept out of the policies deliberately. A policy answers "may this user touch
 * this record"; these answer "is the resulting state of the business legal at
 * all" — a question that has the same answer whichever screen asked it, and
 * one that must not be re-derived per controller.
 */
class RoleGuard
{
    /**
     * Whether `$actor` may put someone into `$target` role.
     *
     * The rule is that nobody hands out authority they do not hold. Comparing
     * the two roles' permission sets, rather than a hardcoded rank, means a
     * custom role invented next year is measured the same way — and a Manager
     * cannot promote someone to Admin because Admin holds grants Manager does
     * not, not because a list said so.
     */
    public static function canAssignRole(User $actor, Role $target): bool
    {
        if (! $actor->hasPermission('staff.assign_role', 'all')) {
            return false;
        }

        // Only an owner can make another owner, and only through the
        // ownership transfer workflow rather than by assigning a role.
        if ($target->key === Role::OWNER) {
            return false;
        }

        if ($actor->isOwner()) {
            return true;
        }

        $actorRole = $actor->role();

        if ($actorRole === null) {
            return false;
        }

        return self::grantsNothingBeyond($target, $actorRole);
    }

    /**
     * Whether `$actor` may change what `$role` is allowed to do.
     *
     * Editing the matrix is how a user with limited authority would otherwise
     * grant themselves more: give a role every permission, then take it.
     */
    public static function canEditRole(User $actor, Role $role): bool
    {
        if (! $actor->hasPermission('roles.manage_matrix', 'all')) {
            return false;
        }

        // The Owner role's authority is not editable by anyone. Removing a
        // grant from it would leave the business with no account able to
        // restore it.
        if ($role->key === Role::OWNER) {
            return false;
        }

        if ($actor->isOwner()) {
            return true;
        }

        $actorRole = $actor->role();

        return $actorRole !== null && self::grantsNothingBeyond($role, $actorRole);
    }

    /**
     * Which permissions `$actor` may grant.
     *
     * Anything they do not themselves hold is withheld, so the matrix a
     * non-owner sees cannot be used to mint authority they lack.
     *
     * @return array<int, string>
     */
    public static function grantablePermissions(User $actor): array
    {
        $all = array_keys(Permissions::all());

        if ($actor->isOwner()) {
            // Even the owner does not hand these out; they belong to whoever
            // holds the business, and move only by transfer.
            return array_values(array_filter($all, fn ($key) => ! Permissions::isOwnerOnly($key)));
        }

        return array_values(array_filter(
            $all,
            fn ($key) => ! Permissions::isOwnerOnly($key) && $actor->hasPermission($key, 'all')
        ));
    }

    /** Nobody may change their own role. */
    public static function canChangeOwnRole(): bool
    {
        return false;
    }

    /**
     * Whether removing this person as owner would leave the business without
     * one, per §33.
     *
     * Asked before deactivating, archiving, deleting or re-roling, because all
     * four arrive at the same illegal state by different routes.
     */
    public static function wouldLeaveTenantWithoutOwner(User $user): bool
    {
        if (! $user->isOwner()) {
            return false;
        }

        // Ownership is a single column on the tenant, so the current owner is
        // by definition the only one. Counting rather than assuming keeps this
        // honest if ownership ever becomes a set.
        $others = User::where('tenant_id', $user->tenant_id)
            ->where('id', '!=', $user->id)
            ->whereHas('tenant', fn ($q) => $q->where('owner_user_id', $user->id))
            ->count();

        return $others === 0;
    }

    public static function canDeactivate(User $actor, Staff $staff): bool
    {
        if (! $actor->hasPermission('staff.deactivate', 'all')) {
            return false;
        }

        // Nobody deactivates themselves out of the business.
        if ($staff->user_id === $actor->id) {
            return false;
        }

        return ! ($staff->user && self::wouldLeaveTenantWithoutOwner($staff->user));
    }

    /**
     * Whether `$target` grants nothing that `$reference` does not also grant.
     *
     * Scope counts: holding `bookings.edit` at `location` does not authorise
     * handing it out at `all`.
     */
    private static function grantsNothingBeyond(Role $target, Role $reference): bool
    {
        foreach ($target->permissionMap() as $permission => $scope) {
            if (! $reference->grants($permission, $scope)) {
                return false;
            }
        }

        return true;
    }
}
