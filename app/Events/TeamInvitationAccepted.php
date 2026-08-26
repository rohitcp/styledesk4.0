<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TeamInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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

    public function __construct(public TeamInvitation $invitation) {}

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
