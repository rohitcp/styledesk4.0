<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

/**
 * Who may see and change locations.
 *
 * Access is stated as permissions, not as the role list in the spec. §Access
 * names Owner and Admin because those are the roles that hold `locations.*`
 * today; writing the names here instead would make the sentence "unless
 * permission is granted in a future Roles & Permissions phase" impossible to
 * honour without editing this file again.
 *
 * Every method re-checks the tenant rather than trusting the BelongsToTenant
 * global scope, for the same reason StaffPolicy does: a refactor that loosened
 * the scope would be a refactor that handed out another business's addresses.
 */
class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('locations.view', 'assigned');
    }

    public function view(User $user, Location $location): bool
    {
        if (! $this->sameTenant($user, $location)) {
            return false;
        }

        /**
         * Someone whose access is `assigned` may open the branch they work
         * at, and nothing else. `all` is what makes the whole list readable.
         */
        if ($user->staffRecord()?->location_id === $location->id) {
            return $user->hasPermission('locations.view', 'assigned');
        }

        return $user->hasPermission('locations.view', 'all');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('locations.create');
    }

    public function update(User $user, Location $location): bool
    {
        if (! $this->sameTenant($user, $location)) {
            return false;
        }

        if ($user->staffRecord()?->location_id === $location->id) {
            return $user->hasPermission('locations.edit', 'location');
        }

        return $user->hasPermission('locations.edit', 'all');
    }

    /**
     * Deleting is refused for anything with history, and for the last branch.
     *
     * §Actions is explicit that a location with bookings, transactions, staff
     * or clients must become inactive rather than disappear. That rule lives
     * here rather than in the controller so a future console command or API
     * route cannot reach the delete without it.
     */
    public function delete(User $user, Location $location): bool
    {
        if (! $this->sameTenant($user, $location) || ! $user->hasPermission('locations.delete')) {
            return false;
        }

        return ! $location->isInUse() && ! $this->isOnlyLocation($location);
    }

    public function assignStaff(User $user, Location $location): bool
    {
        return $this->sameTenant($user, $location) && $user->hasPermission('locations.assign_staff');
    }

    public function manageHours(User $user, Location $location): bool
    {
        if (! $this->sameTenant($user, $location)) {
            return false;
        }

        if ($user->staffRecord()?->location_id === $location->id) {
            return $user->hasPermission('locations.manage_hours', 'location');
        }

        return $user->hasPermission('locations.manage_hours', 'all');
    }

    private function sameTenant(User $user, Location $location): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $location->tenant_id;
    }

    private function isOnlyLocation(Location $location): bool
    {
        return Location::withoutGlobalScopes()
            ->where('tenant_id', $location->tenant_id)
            ->count() <= 1;
    }
}
