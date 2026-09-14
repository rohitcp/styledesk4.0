<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BookingPaymentLink;
use App\Support\BookingTotals;
use App\Support\EmailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Here is how to pay for your appointment".
 *
 * Sent while the receptionist is still on the call, for the reason the
 * confirmation is: "I have just emailed you a link" has to be true by the
 * time they hang up.
 *
 * The amount is the link's own, not the booking's. A link sent for a $50
 * deposit keeps asking for $50 after somebody adds a service — what was
 * asked for is not rewritten by what changed afterwards.
 */
class BookingPaymentLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingPaymentLink $link,
        public string $businessName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            /* The business's own sender name and reply-to, which
               were configured in App Settings and reaching nothing
               but manually written client mail until now. */
            from: EmailSender::fromAddress($this->booking->tenant),
            replyTo: EmailSender::replyTo($this->booking->tenant),
            subject: __('bookings.email.link_subject', [
                'business' => $this->businessName,
                'date' => $this->link->booking->date->translatedFormat('j M'),
            ]),
        );
    }

    public function content(): Content
    {
        $totals = BookingTotals::for($this->link->booking);

        return new Content(
            view: 'emails.booking-payment-link',
            with: [
                'link' => $this->link,
                'booking' => $this->link->booking,
                'businessName' => $this->businessName,
                'amount' => $totals->money((int) $this->link->amount_minor),
                'url' => route('booking.pay-link', ['token' => $this->link->token]),
            ],
        );
    }
}
