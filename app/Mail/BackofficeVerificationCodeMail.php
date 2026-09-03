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
 * The sign-in code for the platform console.
 *
 * Sent rather than queued: somebody is looking at the code box waiting for it,
 * and a queue worker that is behind turns a ten-minute code into an expired
 * one. StyleDesk's own colours, not a tenant's — this one really is from us.
 */
class BackofficeVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BackofficeAdmin $admin,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('backoffice.email.code_subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backoffice-code',
            with: [
                'admin' => $this->admin,
                'code' => $this->code,
                'minutes' => (int) config('backoffice.verification.ttl_minutes'),
            ],
        );
    }
}
