<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The "Join Team" email.
 *
 * Not ShouldQueue itself: App\Jobs\SendTeamInvitationEmail wraps the send so
 * that each attempt can be recorded and a provider failure can be written
 * against the invitation. Queueing the mailable directly would put the send
 * inside a job this application does not own, leaving nowhere to hang that.
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The plain token is passed in, not read from the model.
     *
     * Only the hash is stored, so by the time this job runs the token exists
     * nowhere else. Serialising it into the job payload is what lets the mail
     * be built after the request that created it has ended.
     */
    public function __construct(
        public TeamInvitation $invitation,
        public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        $business = $this->invitation->tenant?->name ?? 'a StyleDesk business';

        return new Envelope(
            subject: "You're invited to join {$business} on StyleDesk",
            /**
             * Replies reach the person who sent the invite, not a no-reply
             * mailbox. "Who is this and why am I getting it" is the most
             * likely reply, and the inviter is the only one who can answer.
             */
            replyTo: array_filter([$this->invitation->inviter?->email]),
        );
    }

    public function content(): Content
    {
        $invitation = $this->invitation;

        return new Content(
            view: 'emails.team-invitation',
            with: [
                'businessName' => $invitation->tenant?->name ?? 'your new team',
                'inviterName' => $invitation->inviter?->name ?? 'A colleague',
                'roleLabel' => self::roleLabel($invitation->role),
                'locationName' => $invitation->location?->name,
                /**
                 * Not 'message'.
                 *
                 * Laravel injects the Illuminate\Mail\Message being built
                 * into every mail view under that exact name, so a payload key
                 * called `message` is silently replaced by an object and the
                 * template dies escaping it.
                 */
                'inviteMessage' => $invitation->message,
                'email' => $invitation->email,
                'acceptUrl' => route('team-invite.show', $this->plainToken),
                'expiresAt' => $invitation->expires_at?->format('j F Y'),
                'expiresInDays' => TeamInvitation::EXPIRES_AFTER_DAYS,
                'supportEmail' => config('mail.support_address'),
            ],
        );
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'owner' => 'Owner',
            'administrator' => 'Administrator',
            'manager' => 'Manager',
            'front-desk' => 'Front desk',
            default => 'Service provider',
        };
    }
}
