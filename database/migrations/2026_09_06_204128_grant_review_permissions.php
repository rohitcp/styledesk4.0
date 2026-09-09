<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the Reviews permissions — and Complete — to the roles that already
 * exist.
 *
 * A role's permissions are rows written when the business was created, so
 * adding keys to config/role_defaults.php gives them to businesses created
 * afterwards and to nobody else. Without this, every salon already using
 * StyleDesk would find the review module refusing them and no appointment
 * anywhere able to be finished.
 *
 * Only where the role has no opinion already: a business that has
 * deliberately taken something away should not have this hand it back.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const GRANTS = [
        'administrator' => [
            'appointments.complete' => 'all',
            'reviews.view' => 'all',
            'reviews.send_request' => 'all',
            'reviews.assign' => 'all',
            'reviews.add_note' => 'all',
            'reviews.change_status' => 'all',
            'reviews.manage_settings' => 'all',
            'reviews.view_reports' => 'all',
        ],

        /* Their locations' feedback and the handling of it. Not the settings:
           how the business asks is one decision for the whole business. */
        'manager' => [
            'appointments.complete' => 'location',
            'reviews.view' => 'location',
            'reviews.send_request' => 'all',
            'reviews.assign' => 'all',
            'reviews.add_note' => 'all',
            'reviews.change_status' => 'all',
            'reviews.view_reports' => 'location',
        ],

        /* Reads them, and can ask again for one. Assigning a complaint and
           closing it are somebody else's decisions. */
        'front-desk' => [
            'appointments.complete' => 'location',
            'reviews.view' => 'location',
            'reviews.send_request' => 'all',
        ],

        /* Reviews of their own work, and nothing else's. */
        'service-provider' => [
            'appointments.complete' => 'own',
            'reviews.view' => 'own',
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
            ->where(function ($query) {
                $query->where('permission', 'like', 'reviews.%')
                    ->orWhere('permission', 'appointments.complete');
            })
            ->delete();
    }
};
