<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use App\Support\BookingTotals;
use App\Support\EmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your appointment is confirmed".
 *
 * Sent while somebody is still standing at the desk, which is why the
 * controller sends it rather than queueing it: "we have emailed that to you"
 * has to be true by the time they leave.
 *
 * The booking is passed whole and the money read from what was stored on it,
 * so the email quotes the same figures the receipt does — not the price list
 * as it stands when the mail is rendered.
 */
class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $businessName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('bookings.email.subject', [
                'business' => $this->businessName,
                'date' => $this->booking->date->translatedFormat('j M'),
            ]),
        );
    }

    public function content(): Content
    {
        $totals = BookingTotals::for($this->booking);

        return new Content(
            view: 'emails.booking-confirmation',
            with: [
                'booking' => $this->booking,
                'totals' => $totals,
                'businessName' => $this->businessName,
                'paid' => $totals->money($this->booking->paidMinor()),
                'due' => $totals->money($this->booking->dueMinor()),
                /* The salon's own colours and logo, resolved from the booking
                   rather than from the request: this mail is sometimes
                   rendered by a queue worker with no tenant in context. */
                'brand' => EmailBrand::for($this->booking->tenant, $this->businessName),
                /* Where they are going and how to reach it, if anybody asks
                   the email instead of ringing. */
                'footerLines' => array_filter([
                    $this->booking->location?->addressLine(),
                    $this->booking->location?->phone,
                ]),
            ],
        );
    }
}
