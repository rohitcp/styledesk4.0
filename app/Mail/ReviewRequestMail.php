<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BookingReview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "How was your visit?"
 *
 * One question, one button, and the stars are in the email itself so that a
 * client who has already decided can answer without reading anything. §33 is
 * the whole design brief: open link, tap rating, done — every extra sentence
 * here costs response rate.
 *
 * The link is the credential, so it is the only thing in this mail that
 * identifies the appointment. Nothing about the client or the booking id
 * appears in the URL.
 */
class ReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingReview $review,
        public string $businessName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('reviews.email.subject', ['business' => $this->businessName]),
        );
    }

    public function content(): Content
    {
        $booking = $this->review->booking;
        $url = route('reviews.show', ['token' => $this->review->token]);

        return new Content(
            view: 'emails.review-request',
            with: [
                'review' => $this->review,
                'booking' => $booking,
                'businessName' => $this->businessName,
                'clientName' => $this->review->client?->first_name ?? $booking->clientName(),
                'url' => $url,
                /* Five links rather than one button, so the first tap is the
                   answer. A client who opens the page still gets the same
                   choice; this only saves the ones who had already made it. */
                'starUrls' => collect(config('reviews.ratings'))
                    ->mapWithKeys(fn (int $rating) => [
                        $rating => route('reviews.show', ['token' => $this->review->token, 'rating' => $rating]),
                    ])
                    ->all(),
            ],
        );
    }
}
