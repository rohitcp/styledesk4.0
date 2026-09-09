<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Roles & Permissions, read-only.
 *
 * Phase 1 shows what each role may do and offers no way to change it. There
 * are deliberately no store, update or destroy actions here — not disabled
 * ones, not guarded ones. A route that exists and refuses is still a route
 * somebody can find; a route that does not exist cannot be called at all.
 */
class RolePermissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount(['permissions', 'staff'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('settings.roles.index', [
            'roles' => $roles,
            'permissionTotal' => count(Permissions::all()),
        ]);
    }

    public function show(Request $request, Role $role): View
    {
        $this->authorize('view', $role);

        $held = $role->permissionMap();

        /**
         * Every group, every permission — held or not.
         *
         * A screen that listed only what a role has would answer "what can
         * they do" but not "what are they missing", and the second question is
         * the one someone opens this page with.
         */
        $groups = collect(config('permissions.groups'))
            ->map(function (array $group) use ($role, $held) {
                $permissions = collect($group['permissions'])->map(function (array $permission, string $key) use ($role, $held) {
                    // Owner is answered without consulting the table, the same
                    // way the check itself is.
                    $granted = $role->grants($key, 'own');
                    $scope = $granted ? ($role->key === Role::OWNER ? 'all' : ($held[$key] ?? 'all')) : null;

                    return [
                        'key' => $key,
                        'label' => $permission['label'],
                        'granted' => $granted,
                        // Only worth showing when it is narrower than
                        // everything: "All" on every row is noise.
                        'scope' => $scope !== null && $scope !== 'all' ? config('permissions.scopes.'.$scope) : null,
                        'owner_only' => Permissions::isOwnerOnly($key),
                        'phase' => $permission['phase'] ?? 1,
                    ];
                })->values();

                return [
                    ...$group,
                    'permissions' => $permissions->all(),
                    'grantedCount' => $permissions->where('granted', true)->count(),
                ];
            })
            ->values()
            ->all();

        return view('settings.roles.show', [
            'role' => $role->loadCount('staff'),
            'groups' => $groups,
            'grantedTotal' => collect($groups)->sum('grantedCount'),
            'permissionTotal' => count(Permissions::all()),
        ]);
    }
}
