<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\Booking;
use App\Support\TimeFormat;

/**
 * What a message says.
 *
 * One renderer, so the body a client receives and the body the SMS log shows
 * are the same string — read from the language files, which is what makes a
 * Spanish-speaking client's confirmation arrive in Spanish.
 *
 * The bodies are kept short deliberately. A text is charged by the segment,
 * 160 characters and then 153 for each one after, so every line here is one
 * somebody at the desk would actually read out.
 *
 * Editable templates per business are a screen that does not exist yet; when
 * it does, this is where a stored template would be rendered instead of the
 * default, and nothing above it changes.
 */
class SmsTemplates
{
    public static function render(string $type, mixed $subject): string
    {
        return match ($type) {
            'booking_confirmation' => self::bookingConfirmation($subject),
            default => '',
        };
    }

    /**
     * Every message names the business.
     *
     * One number carries every salon's texts, so "your appointment is
     * confirmed" arrives from a number the client has no reason to recognise
     * and no way to place. The name goes first, before anything else in the
     * message, because that is the word that makes the rest make sense.
     */
    private static function bookingConfirmation(Booking $booking): string
    {
        return __('bookings.sms.confirmation', [
            'name' => $booking->client?->preferred_name
                ?: $booking->client?->first_name
                ?: $booking->guest_name,
            'business' => tenant()?->name ?? config('app.name'),
            'date' => $booking->date->translatedFormat('j M'),
            'time' => TimeFormat::time($booking->startsAt()),
            'staff' => $booking->staff?->displayName() ?? __('bookings.any_staff'),
            'reference' => $booking->reference,
        ]);
    }
}
