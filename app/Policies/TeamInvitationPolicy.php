<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TeamInvitation;
use App\Models\User;

/**
 * Who may invite, resend and revoke.
 *
 * Every method re-checks the tenant. The BelongsToTenant global scope already
 * hides other businesses' invitations, but a policy that leaned on that would
 * be one refactor away from a cross-tenant hole — and an invitation is the one
 * object whose whole purpose is to grant access to a tenant.
 */
class TeamInvitationPolicy
{
    /** Bringing someone into the business is one permission, not a role. */
    private const INVITE = 'staff.invite';

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(self::INVITE, 'all');
    }

    public function resend(User $user, TeamInvitation $invitation): bool
    {
        return $this->sameTenant($user, $invitation) && $user->hasPermission(self::INVITE, 'all');
    }

    public function revoke(User $user, TeamInvitation $invitation): bool
    {
        return $this->sameTenant($user, $invitation) && $user->hasPermission(self::INVITE, 'all');
    }

    private function sameTenant(User $user, TeamInvitation $invitation): bool
    {
        return $user->tenant_id !== null && $invitation->tenant_id === $user->tenant_id;
    }
}
