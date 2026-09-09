<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the Loyalty permissions to the roles that already exist.
 *
 * A role's permissions are rows written when the business was created, so
 * adding keys to config/role_defaults.php gives them to businesses created
 * afterwards and to nobody else. Without this, every salon already using
 * StyleDesk would find the rewards tab refusing them — including the people
 * whose job it is to read a balance at the desk.
 *
 * Only where the role has no opinion already: a business that has deliberately
 * taken something away should not have this hand it back.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const GRANTS = [
        'administrator' => [
            'loyalty.view_settings' => 'all',
            'loyalty.manage_settings' => 'all',
            'loyalty.view_rewards' => 'all',
            'loyalty.adjust_points' => 'all',
            'loyalty.redeem_points' => 'all',
        ],

        /* Their locations' balances, and the authority to correct and spend
           one. Not the rules: what a point is worth is one decision for the
           whole business. */
        'manager' => [
            'loyalty.view_settings' => 'all',
            'loyalty.view_rewards' => 'location',
            'loyalty.adjust_points' => 'all',
            'loyalty.redeem_points' => 'all',
        ],

        /* Reads a balance and spends it at the till. Inventing points is
           somebody else's decision. */
        'front-desk' => [
            'loyalty.view_rewards' => 'location',
            'loyalty.redeem_points' => 'all',
        ],

        /* The balances of the clients they see, and nothing else. */
        'service-provider' => [
            'loyalty.view_rewards' => 'own',
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
        RolePermission::query()->where('permission', 'like', 'loyalty.%')->delete();
    }
};
