<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BackofficeAdmin;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The reset link for the platform console.
 *
 * A mailable of its own rather than Laravel's ResetPassword notification,
 * because that notification builds its URL from `route('password.reset')` —
 * the salon application's screen. An administrator following it arrived at
 * the wrong console and at a form backed by the wrong broker, where their
 * token could never be redeemed.
 *
 * Sent rather than queued, like the sign-in code: somebody is waiting on it,
 * and a worker that is behind turns a short-lived link into a dead one.
 */
class BackofficePasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BackofficeAdmin $admin,
        public string $resetUrl,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('backoffice.email.reset_subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backoffice-password-reset',
            with: [
                'admin' => $this->admin,
                'resetUrl' => $this->resetUrl,
                'minutes' => $this->minutes,
            ],
        );
    }
}
