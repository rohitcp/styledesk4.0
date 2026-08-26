<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Events\TeamInvitationAccepted;
use App\Models\Staff;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turn an accepted invitation into a member of the business.
 *
 * Everything that makes someone part of a tenant happens here in one
 * transaction — the tenant link, the role, the staff record and the closing of
 * the invitation. Split across a controller they could half-apply: a user with
 * a tenant_id but no staff row is invisible to the team screen while already
 * being able to read the business's data.
 */
class AcceptTeamInvitation
{
    public function accept(TeamInvitation $invitation, User $user): void
    {
        $this->guard($invitation, $user);

        DB::transaction(function () use ($invitation, $user) {
            $user->forceFill([
                'tenant_id' => $invitation->tenant_id,
                /**
                 * Arriving through the link proves control of the inbox, which
                 * is the same thing the verification email asks for. Without
                 * this the new member would be dropped straight onto the
                 * "verify your email" wall by an address we have already
                 * verified.
                 */
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $serviceIds = $invitation->services()->pluck('services.id');

            /**
             * Complete the staff record the invitation was created for, if
             * there is one.
             *
             * §15 is explicit that accepting must not produce a duplicate. The
             * link is the staff_id on the invitation rather than a match on
             * email, because the address is exactly what changes between being
             * invited and accepting — someone invited at a personal address
             * who signs up with their work one would otherwise arrive as a
             * second person with the same name.
             */
            $existing = $invitation->staff_id
                ? Staff::withoutGlobalScopes()->find($invitation->staff_id)
                : null;

            $staff = $existing ?? Staff::withoutGlobalScopes()->firstOrNew(
                ['tenant_id' => $invitation->tenant_id, 'user_id' => $user->id],
            );

            $staff->fill([
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $invitation->role,
                'job_title' => $invitation->job_title,
                'location_id' => $invitation->location_id,
                /**
                 * Being assigned services is what makes someone bookable,
                 * whatever their role — a manager who also cuts hair is
                 * ordinary. The role only decides the default for someone
                 * who was assigned none.
                 */
                'provides_services' => $serviceIds->isNotEmpty() || $invitation->role === 'service-provider',

                // The person is in: the record stops being a pending
                // invitation and becomes a member of the team.
                'invite_status' => 'accepted',
                'membership_status' => 'active',
                'is_active' => true,
            ]);

            $staff->tenant_id = $invitation->tenant_id;
            $staff->user_id = $user->id;
            $staff->save();

            $staff->services()->sync($serviceIds->all());

            /**
             * The token is rotated on acceptance too.
             *
             * Status alone would leave a working link in an inbox that anyone
             * with access to that mailbox could replay. After this, the link
             * in the email hashes to a value no row holds.
             */
            $invitation->regenerateToken();

            $invitation->forceFill([
                'status' => TeamInvitation::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ])->save();
        });

        // Tells the inviter's open team screen to flip Pending to Active.
        TeamInvitationAccepted::dispatch($invitation->fresh());
    }

    /**
     * Every reason an otherwise-valid link must still be refused.
     *
     * Checked here rather than in the controller so that no future caller —
     * a console command, a second route — can reach the write above without
     * passing them.
     */
    private function guard(TeamInvitation $invitation, User $user): void
    {
        if (! $invitation->isAcceptable()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        /**
         * The invitation is bound to one address.
         *
         * Without this, forwarding the email would let anyone with the link
         * join the business under someone else's invitation — the link is a
         * bearer token, and this is what stops it being transferable.
         */
        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'invitation' => "This invitation was sent to {$invitation->email}. Sign in with that address to accept it.",
            ]);
        }

        /**
         * StyleDesk is one business per user.
         *
         * Silently moving someone would cut them off from the business they
         * are already part of, so this is refused and explained rather than
         * resolved by guessing which one they meant.
         */
        if ($user->tenant_id !== null && $user->tenant_id !== $invitation->tenant_id) {
            throw ValidationException::withMessages([
                'invitation' => 'This account already belongs to another business. Ask for an invitation to a different email address.',
            ]);
        }
    }
}
