<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the "manage members" permission to the roles that already exist.
 *
 * Its own permission rather than `clients.edit`, and therefore its own grant:
 * correcting a phone number and stopping a subscription somebody is paying
 * for are not the same authority, and the second costs the business money
 * whichever way it goes wrong.
 *
 * Only where the role has no opinion already, for the same reason the other
 * membership grant says so: a business that has deliberately taken something
 * away should not have this hand it back.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const GRANTS = [
        'administrator' => [
            'membership.view_members' => 'all',
            'membership.manage_members' => 'all',
        ],

        /* A manager runs the floor and fields "can I cancel?" — so they can.
           Setting the terms everybody is sold on is still not theirs. */
        'manager' => [
            'membership.view_members' => 'all',
            'membership.manage_members' => 'all',
        ],

        /* The desk reads a client's membership to answer "what have I got
           left". Ending one is somebody else's decision. */
        'front-desk' => [
            'membership.view_members' => 'all',
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

    public function down(): void
    {
        RolePermission::query()
            ->whereIn('permission', ['membership.view_members', 'membership.manage_members'])
            ->delete();
    }
};
