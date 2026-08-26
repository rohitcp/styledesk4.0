<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\TeamInvitationMail;
use App\Models\TeamInvitation;
use App\Models\TeamInvitationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Deliver one invitation email, recording the attempt either way.
 *
 * Queued so the request that added the colleague returns immediately. A mail
 * provider that is slow or down would otherwise hold the onboarding screen
 * open for the length of the connection timeout, and the person waiting has no
 * way to act on a failure that is not theirs.
 */
class SendTeamInvitationEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Three attempts, backing off 10s then 30s.
     *
     * Enough to ride out the transient case this is really guarding — a
     * provider rate-limit or a dropped connection — without turning a genuine
     * misconfiguration into a job that retries forever. When the last attempt
     * fails the invitation stays Pending and the team list offers Resend, so a
     * permanent failure becomes something a person can see and act on.
     */
    public int $tries = 3;

    public array $backoff = [10, 30];

    /**
     * The plain token travels in the payload because it is stored nowhere.
     *
     * Only its hash is persisted, so if this job had to look the token up it
     * could not; passing it is what lets the link be built after the original
     * request has ended.
     */
    public function __construct(
        public TeamInvitation $invitation,
        public string $plainToken,
    ) {}

    public function handle(): void
    {
        /**
         * Do not deliver a link that has already been superseded.
         *
         * A revoke or a resend between this job being queued and being run
         * replaces token_hash. Sending anyway would put a dead link in
         * someone's inbox, or — worse, on revoke — imply they are still
         * welcome to join.
         */
        if ($this->invitation->token_hash !== TeamInvitation::hashToken($this->plainToken)) {
            return;
        }

        Mail::to($this->invitation->email)
            ->send(new TeamInvitationMail($this->invitation, $this->plainToken));

        $this->record(TeamInvitationDelivery::STATUS_SENT);

        /**
         * The staff record only says "sent" once it has been.
         *
         * Marking it at queue time was a small lie with a real cost: with no
         * worker running, the directory reported an invitation as sent that
         * was still sitting in the jobs table, and the only way to find out
         * was for the person to say they never received it.
         */
        $this->invitation->staff?->forceFill(['invite_status' => 'sent'])->save();
    }

    /**
     * Runs after the final attempt has failed.
     *
     * The provider's message is written to the delivery row and the log, and
     * nowhere else: it can name hosts, credentials and internal reasons, none
     * of which belong in front of the person who pressed Add.
     */
    public function failed(Throwable $e): void
    {
        $this->record(TeamInvitationDelivery::STATUS_FAILED, $e->getMessage());

        $this->invitation->staff?->forceFill(['invite_status' => 'failed'])->save();

        Log::error('Team invitation email failed to send.', [
            'team_invitation_id' => $this->invitation->id,
            'tenant_id' => $this->invitation->tenant_id,
            'exception' => $e->getMessage(),
        ]);
    }

    private function record(string $status, ?string $error = null): void
    {
        TeamInvitationDelivery::create([
            'team_invitation_id' => $this->invitation->id,
            'status' => $status,
            'error' => $error,
            'attempted_at' => now(),
        ]);
    }
}
