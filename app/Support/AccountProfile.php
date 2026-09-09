<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * The parts of somebody's profile that they may read but not change.
 *
 * Role, locations and account status are the business's answers about a
 * person, not the person's answers about themselves: an administrator sets
 * them in Staff Management. They are shown here anyway, because "what am I
 * allowed to do and where do I work" is a question people ask of their own
 * profile — and because a screen that silently omits them invites somebody to
 * go looking for a setting that does not exist.
 */
class AccountProfile
{
    /**
     * @return array{role: string, locations: string, status: string, member_since: ?string}
     */
    public static function facts(User $user): array
    {
        return [
            'role' => $user->role()?->name ?? __('account.profile.no_role'),
            'locations' => self::locations($user),
            'status' => self::status($user),
            'member_since' => $user->created_at?->format(AccountPreferences::dateFormat($user)),
        ];
    }

    /**
     * Where they work, as a sentence.
     *
     * Somebody with no staff record is not tied to a location — the owner
     * before onboarding seeds them is the ordinary case — and "All locations"
     * is the honest description of what they can reach, not an error.
     */
    private static function locations(User $user): string
    {
        $staff = $user->staffRecord();

        if ($staff === null) {
            return __('account.profile.all_locations');
        }

        $names = $staff->location?->name;

        return $names ?: __('account.profile.no_location');
    }

    /**
     * Active, inactive or archived, from the staff record.
     *
     * A user with no staff row is active by the only measure that exists:
     * they are signed in and reading this.
     */
    private static function status(User $user): string
    {
        $staff = $user->staffRecord();

        if ($staff === null) {
            return __('account.profile.status_active');
        }

        return match (true) {
            $staff->archived_at !== null => __('account.profile.status_archived'),
            (bool) $staff->is_active === false => __('account.profile.status_inactive'),
            default => __('account.profile.status_active'),
        };
    }
}
