<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ClientEmailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message the salon wrote to one of its clients.
 *
 * The business's own branding, not StyleDesk's: as far as the client is
 * concerned this is a letter from their salon, and a StyleDesk-liveried email
 * about their haircut would be a stranger writing to them.
 *
 * Built from the stored row rather than from the client and the template,
 * because the row is what was actually sent — the client's address can change
 * and the template can be rewritten, and neither may rewrite history.
 */
class ClientMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ClientEmailMessage $email) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            /* "Smile Spa via StyleDesk". The address is StyleDesk's because
               StyleDesk is what is putting it on the wire — claiming to send
               from the salon's own domain without being authorised to would
               land the message in spam, if it left at all. */
            from: new Address(
                $this->email->sender_email,
                __('client_email.send.from_via', ['name' => $this->email->sender_name]),
            ),
            subject: $this->email->subject,
            /* Where Reply goes: the salon's own inbox, when they have set
               one. Without it the client answers a mailbox nobody reads —
               which is why the settings screen asks for it plainly rather
               than burying it. */
            replyTo: array_filter([$this->email->tenant?->email_reply_to]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-message',
            with: [
                'email' => $this->email,
                /* Not 'message': Laravel injects the Illuminate\Mail\Message
                   being built into every mail view under that exact name, so a
                   payload key called `message` is silently replaced by an
                   object and the template dies escaping it. The team
                   invitation learned this the same way. */
                'bodyText' => $this->email->message,
            ],
        );
    }
}
