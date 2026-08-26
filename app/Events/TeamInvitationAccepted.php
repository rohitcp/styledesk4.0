<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TeamInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Someone accepted an invitation.
 *
 * Broadcast so the team screen can flip Pending to Active without the person
 * who sent the invite reloading. They are typically still on that screen when
 * it happens, and a list that quietly goes stale is the thing that makes
 * people press Resend on an invitation that already worked.
 */
class TeamInvitationAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * One attempt.
     *
     * This is a cosmetic live update. Retrying it three times against a
     * websocket server that is not running turns one unreachable host into
     * three failed jobs and a minute of a worker's attention, for a row that
     * a page refresh would have redrawn anyway.
     */
    public int $tries = 1;

    public function __construct(public TeamInvitation $invitation) {}

    /**
     * Broadcasting failed, which changes nothing that matters.
     *
     * The invitation was accepted and committed before this was dispatched;
     * all that is lost is the open team screen redrawing by itself. Logged at
     * warning rather than error, and explained, because the usual cause is
     * simply that Reverb is not running locally.
     */
    public function failed(?Throwable $e = null): void
    {
        Log::warning('Could not broadcast an accepted invitation. The team screen will need a refresh to show it.', [
            'team_invitation_id' => $this->invitation->id,
            'tenant_id' => $this->invitation->tenant_id,
            'exception' => $e?->getMessage(),
        ]);
    }

    /**
     * One private channel per tenant.
     *
     * Named by tenant id rather than by user so that every administrator
     * watching the team screen sees it, not only whoever happened to send
     * this particular invitation.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tenant.'.$this->invitation->tenant_id.'.team');
    }

    public function broadcastAs(): string
    {
        return 'invitation.accepted';
    }

    /**
     * Only what the list needs to redraw a row.
     *
     * Not the whole model: it carries the token hash and the inviter's id,
     * neither of which the browser has any use for.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->invitation->id,
            'status' => TeamInvitation::STATUS_ACCEPTED,
            'status_label' => 'Active',
            'name' => $this->invitation->name,
            'email' => $this->invitation->email,
        ];
    }
}
