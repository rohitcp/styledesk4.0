<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Tenant;

/**
 * The starting points a message can be written from, and the values that fill
 * them in.
 *
 * Templates are scaffolding, not envelopes: the drawer drops one into the
 * subject and body, the sender edits it, and what is stored is what was
 * actually sent. A template edited next March must never change what a client
 * was told in February, which is why nothing here is referenced at read time.
 */
class ClientEmailTemplates
{
    /**
     * Every template, rendered against this client and booking.
     *
     * @return array<int, array{key: string, name: string, subject: string, body: string}>
     */
    public static function all(Client $client, ?Booking $booking = null): array
    {
        $values = self::values($client, $booking);

        return array_map(fn (string $key) => [
            'key' => $key,
            'name' => __('client_email.templates.'.$key.'.name'),
            'subject' => self::render(__('client_email.templates.'.$key.'.subject'), $values),
            'body' => self::render(__('client_email.templates.'.$key.'.body'), $values),
        ], config('client_email.templates'));
    }

    /**
     * Swap `{{ variable }}` for its value.
     *
     * Only the variables the catalogue names, and only ones we have an answer
     * for. Anything else is left exactly as written: "{{ balance_due }}"
     * arriving in a client's inbox is a bug somebody reports within the hour,
     * where a silently blanked sentence is one nobody ever notices.
     *
     * Whitespace inside the braces is tolerated because people type it.
     *
     * @param  array<string, string>  $values
     */
    public static function render(string $text, array $values): string
    {
        foreach ($values as $name => $value) {
            if ($value === '') {
                continue;
            }

            $text = (string) preg_replace(
                '/\{\{\s*'.preg_quote($name, '/').'\s*\}\}/',
                str_replace('$', '\\$', $value),
                $text
            );
        }

        return $text;
    }

    /**
     * What each variable stands for, for this client and this booking.
     *
     * A variable with nothing behind it resolves to an empty string and is
     * therefore left in the text by render() — see above. That is deliberate:
     * a payment reminder with no balance to name is a message that should not
     * have been sent from that template, and it should be obvious.
     *
     * @return array<string, string>
     */
    public static function values(Client $client, ?Booking $booking = null): array
    {
        $tenant = $client->tenant ?? tenant();

        return [
            'client_first_name' => (string) $client->first_name,
            'client_last_name' => (string) $client->last_name,
            'business_name' => (string) ($tenant?->name ?? ''),
            'booking_date' => $booking?->date?->translatedFormat('j M Y') ?? '',
            'booking_time' => $booking === null ? '' : (string) TimeFormat::time($booking->starts_at),
            'booking_reference' => (string) ($booking?->reference ?? ''),
            'service_name' => $booking?->services->pluck('name')->implode(', ') ?? '',
            'staff_name' => (string) ($booking?->staff?->displayName() ?? ''),
            'balance_due' => $booking === null
                ? ''
                : BookingTotals::for($booking)->money($booking->dueMinor()),
        ];
    }

    /**
     * How the business signs its mail, and where a reply goes.
     *
     * The sender name falls back to the business's own name: a salon that
     * never opened the setting still signs as itself rather than as nothing.
     *
     * @return array{name: string, from: string, reply_to: ?string}
     */
    public static function sender(Tenant $tenant): array
    {
        return [
            'name' => (string) ($tenant->email_sender_name ?: $tenant->name),
            'from' => (string) config('mail.from.address'),
            'reply_to' => $tenant->email_reply_to ?: null,
        ];
    }
}
