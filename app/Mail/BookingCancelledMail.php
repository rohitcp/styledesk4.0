<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use App\Support\EmailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your appointment has been cancelled".
 *
 * Unlike the confirmation, this one carries no Blade view of its own: the
 * subject and the body arrive already rendered, because the business writes
 * them. Settings → Email Templates → Booking Cancelled is the source, run
 * through `EmailRenderer` with this booking's values substituted in.
 *
 * That is the whole reason it takes strings rather than a Booking. Rendering
 * inside the mailable would mean a queue worker resolving the template with
 * no tenant in context, and a salon's own wording quietly replaced by
 * StyleDesk's default at the moment it was sent.
 *
 * The same template goes to all three recipients. What differs between them
 * is who it is addressed to, not what the business decided to say about a
 * cancelled appointment.
 */
class BookingCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $renderedSubject,
        public string $renderedHtml,
        public ?Tenant $tenant = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            /* The business's own sender name and reply-to. A client hitting
               Reply on "your appointment is cancelled" is answering the
               salon, not StyleDesk's noreply. */
            from: EmailSender::fromAddress($this->tenant),
            replyTo: EmailSender::replyTo($this->tenant),
            subject: $this->renderedSubject,
        );
    }

    public function content(): Content
    {
        /* Already whole HTML, brand and footer included — `EmailRenderer`
           builds the same wrapper every other templated email uses. */
        return new Content(htmlString: $this->renderedHtml);
    }
}
