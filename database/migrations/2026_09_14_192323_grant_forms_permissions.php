<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the Forms & Waivers permissions to the roles that already exist.
 *
 * A role's permissions are rows written when the business was created, so
 * adding keys to config/role_defaults.php gives them to businesses created
 * afterwards and to nobody else. Without this, every salon already using
 * StyleDesk would find the new module refusing them — including the people
 * whose job it is to get a waiver signed before a treatment starts.
 *
 * Only where the role has no opinion already: a business that has
 * deliberately taken something away should not have this hand it back.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const GRANTS = [
        'administrator' => [
            'forms.view' => 'all',
            'forms.create' => 'all',
            'forms.edit' => 'all',
            'forms.publish' => 'all',
            'forms.archive' => 'all',
            'forms.view_responses' => 'all',
            'forms.assign' => 'all',
            'forms.send' => 'all',
            'forms.download' => 'all',
            'forms.view_sensitive' => 'all',
        ],

        /* Builds and sends them, reads what came back for their own
           locations. Sensitive answers withheld until the business says so. */
        'manager' => [
            'forms.view' => 'all',
            'forms.create' => 'all',
            'forms.edit' => 'all',
            'forms.publish' => 'all',
            'forms.view_responses' => 'location',
            'forms.assign' => 'all',
            'forms.send' => 'all',
            'forms.download' => 'all',
        ],

        /* Gets them completed. Does not read them. */
        'front-desk' => [
            'forms.view' => 'all',
            'forms.assign' => 'all',
            'forms.send' => 'all',
        ],

        /* The intake answers of the clients they are treating, and nothing
           else — which is the point of taking an intake form. */
        'service-provider' => [
            'forms.view_responses' => 'own',
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
        RolePermission::query()->where('permission', 'like', 'forms.%')->delete();
    }
};
