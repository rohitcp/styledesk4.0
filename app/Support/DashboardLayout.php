<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which panels this person gets, and in what order.
 *
 * The role decides the running order; the permissions decide what is in it.
 * That division matters: a business can hand a single panel to a custom role
 * without inventing a layout for it, and a role that loses a permission
 * quietly loses the panel rather than showing an empty card.
 *
 * A role with no layout of its own — every custom role — falls through to
 * `default`, which offers everything and lets the permissions sort it out.
 */
class DashboardLayout
{
    /**
     * The panels to render, in order.
     *
     * @return Collection<int, string>
     */
    public static function for(User $user): Collection
    {
        $widgets = config('dashboard.widgets');
        $order = config('dashboard.layouts.'.self::roleKey($user))
            ?? config('dashboard.layouts.default');

        return collect($order)
            ->filter(fn (string $key) => isset($widgets[$key]))
            ->filter(fn (string $key) => $user->hasPermission($widgets[$key]['permission'], 'own'))
            ->values();
    }

    /** Whether one panel is on this person's dashboard at all. */
    public static function shows(User $user, string $widget): bool
    {
        return self::for($user)->contains($widget);
    }

    /**
     * The locations whose work this person may see, or null for all of them.
     *
     * Null is not "none": it is the owner and the administrator, for whom
     * every location is theirs and a filter would be a query that does
     * nothing. A manager assigned to Downtown gets Downtown, and a manager
     * assigned to nothing gets nothing rather than everything — the safe
     * reading of an unfinished setup.
     *
     * @return array<int, int>|null
     */
    public static function locationScope(User $user, string $permission = 'dashboard.view_bookings'): ?array
    {
        if ($user->hasPermission($permission, 'all')) {
            return null;
        }

        return Staff::query()
            ->where('user_id', $user->id)
            ->pluck('location_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The locations to offer in the selector.
     *
     * Only where there is a choice to make: one location is not a filter,
     * it is the business, and a dropdown with a single entry is furniture.
     *
     * @return Collection<int, Location>
     */
    public static function selectableLocations(User $user): Collection
    {
        $scope = self::locationScope($user);

        $locations = Location::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('id', $scope))
            ->orderBy('name')
            ->get();

        return $locations->count() > 1 ? $locations : collect();
    }

    /**
     * This person's staff row, where they have one.
     *
     * The provider panels are all "my day", and the my is a staff record
     * rather than a login: an owner who does not see clients has no day to
     * show, and the panels are absent rather than empty.
     */
    public static function staffFor(User $user): ?Staff
    {
        return Staff::query()->where('user_id', $user->id)->first();
    }

    /** The role key the layouts are named by. */
    private static function roleKey(User $user): string
    {
        return (string) ($user->role()?->key ?? 'default');
    }
}
