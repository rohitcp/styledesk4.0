<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the dashboard panels to the roles that already exist.
 *
 * A role's permissions are rows, written when the business was created. So
 * adding a permission to config/role_defaults.php gives it to businesses
 * created afterwards and to nobody else — every salon already using StyleDesk
 * would have opened a dashboard with two panels on it.
 *
 * Only the new `dashboard.view_*` keys, and only where the role does not
 * already have an opinion. A business that has deliberately taken something
 * away from a role should not have this hand it back.
 */
return new class extends Migration
{
    /** The panels each system role starts with, and at what scope. */
    private const GRANTS = [
        'administrator' => [
            'dashboard.view_revenue' => 'all',
            'dashboard.view_bookings' => 'all',
            'dashboard.view_checkin' => 'all',
            'dashboard.view_clients' => 'all',
            'dashboard.view_staff' => 'all',
            'dashboard.view_schedule' => 'all',
            'dashboard.view_payments' => 'all',
            'dashboard.view_reports' => 'all',
        ],

        /* Their own branch, and its takings — a manager who cannot see
           whether the location made money cannot manage it. */
        'manager' => [
            'dashboard.view_revenue' => 'location',
            'dashboard.view_bookings' => 'location',
            'dashboard.view_checkin' => 'location',
            'dashboard.view_staff' => 'location',
            'dashboard.view_schedule' => 'location',
        ],

        /* The operational panels and no revenue: the desk needs to know who
           is waiting and what is unpaid, not what the month came to. */
        'front-desk' => [
            'dashboard.view_bookings' => 'location',
            'dashboard.view_checkin' => 'location',
            'dashboard.view_clients' => 'location',
            'dashboard.view_payments' => 'location',
            'dashboard.view_staff' => 'location',
        ],

        /* Their own day only. Nothing here reads another member of staff's
           work, which is what makes it safe to give to everybody. */
        'service-provider' => [
            'dashboard.view_own_schedule' => 'all',
            'dashboard.view_own_clients' => 'all',
            'dashboard.view_own_performance' => 'all',
            'dashboard.view_tips' => 'all',
            'dashboard.view_checkin' => 'own',
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
                    /* Left alone where the role already says something about
                       it, whatever it says. */
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
     * Taken away again, and only these.
     *
     * The owner is untouched in both directions: their permissions are '*'
     * rather than rows.
     */
    public function down(): void
    {
        $permissions = collect(self::GRANTS)->flatMap(fn (array $grants) => array_keys($grants))->unique();

        RolePermission::query()->whereIn('permission', $permissions)->delete();
    }
};
