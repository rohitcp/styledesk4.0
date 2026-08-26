<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Jobs\SendTeamInvitationEmail;
use App\Models\TeamInvitation;
use App\Models\TeamInvitationDelivery;
use App\Models\Tenant;
use App\Models\User;
use App\Support\InputCase;
use Illuminate\Support\Facades\DB;

/**
 * Create and send a team invitation.
 *
 * One place owns the sequence — mint the token, stamp the send window, queue
 * the email — because those three have to happen together. A token written
 * without a matching expiry, or an email carrying a token that was replaced a
 * moment later, are both silently broken invitations.
 */
class InviteTeamMember
{
    /**
     * @param  array{first_name: string, last_name: string, email: string, role?: string, job_title?: string|null, location_id?: int|null, message?: string|null, service_ids?: array<int>|null}  $data
     */
    public function create(Tenant $tenant, User $inviter, array $data): TeamInvitation
    {
        $email = self::normalizeEmail($data['email']);

        $invitation = DB::transaction(function () use ($tenant, $inviter, $data, $email) {
            $invitation = new TeamInvitation([
                'tenant_id' => $tenant->getTenantKey(),
                'email' => $email,
                // Names follow the project capitalisation rule; the address
                // deliberately does not, because lower-casing it is what keeps
                // one inbox to one invitation.
                'first_name' => InputCase::sentence($data['first_name']),
                'last_name' => InputCase::sentence($data['last_name']),
                'job_title' => InputCase::sentence($data['job_title'] ?? null),
                'role' => $data['role'] ?? 'service-provider',
                'location_id' => $data['location_id'] ?? null,
                'message' => $data['message'] ?? null,
                'invited_by' => $inviter->id,
                'status' => TeamInvitation::STATUS_PENDING,
            ]);

            $invitation->regenerateToken();
            $invitation->save();

            // Inside the transaction: an invitation saved without the services
            // it promised is one the accepting member silently joins without.
            $invitation->services()->sync($data['service_ids'] ?? []);

            return $invitation;
        });

        $this->dispatchEmail($invitation);

        return $invitation;
    }

    /**
     * Send again with a brand-new token.
     *
     * Regenerating rather than re-mailing the old link is the point: the spec
     * requires the previous token to stop working, and rotating the hash is
     * what makes that true rather than a rule some future code path has to
     * remember to apply.
     */
    public function resend(TeamInvitation $invitation): TeamInvitation
    {
        $invitation->regenerateToken();
        $invitation->markSending();

        $this->dispatchEmail($invitation);

        return $invitation;
    }

    /**
     * The pending invitation already held for this address, if any.
     *
     * Only pending ones block a new invite. A revoked or expired invitation is
     * a decision that has already been undone or run out; treating either as a
     * duplicate would leave an administrator unable to invite someone they had
     * previously removed.
     */
    public function existingPendingInvitation(Tenant $tenant, string $email): ?TeamInvitation
    {
        return TeamInvitation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('email', self::normalizeEmail($email))
            ->where('status', TeamInvitation::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function dispatchEmail(TeamInvitation $invitation): void
    {
        $invitation->markSending();

        /**
         * The attempt is recorded as queued before the job exists.
         *
         * If the queue itself is the thing that is broken, a row written by
         * the job would never appear and the invitation would look as though
         * nobody ever tried to send it.
         */
        TeamInvitationDelivery::create([
            'team_invitation_id' => $invitation->id,
            'status' => TeamInvitationDelivery::STATUS_QUEUED,
            'attempted_at' => now(),
        ]);

        SendTeamInvitationEmail::dispatch($invitation, $invitation->plainToken);
    }
}
