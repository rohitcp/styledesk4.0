<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;

/**
 * `{{client.first_name}}` → "Sarah".
 *
 * One resolver for both kinds of template, because the difference between
 * them is only who started the email. A booking date formatted one way in a
 * confirmation and another in a follow-up is the kind of inconsistency a
 * client notices and a developer never does.
 *
 * The catalogue in config/email_templates.php is the same list the Insert
 * Variable menu offers, so a variable a person can insert is always one this
 * can resolve.
 */
class EmailVariables
{
    /**
     * Everything known about this email, flattened to `group.name` keys.
     *
     * Anything unanswerable resolves to an empty string and is therefore left
     * standing in the text by `render()` — see there for why.
     *
     * @return array<string, string>
     */
    public static function for(
        ?Client $client = null,
        ?Booking $booking = null,
        ?Tenant $tenant = null,
    ): array {
        $tenant ??= $client?->tenant ?? $booking?->tenant ?? tenant();
        $booking?->loadMissing(['services', 'staff', 'location', 'payments']);

        return array_map(
            fn ($value) => (string) ($value ?? ''),
            self::client($client)
                + self::business($tenant)
                + self::booking($booking)
                + self::service($booking)
                + self::staff($booking?->staff)
                + self::location($booking?->location)
                + self::payment($booking),
        );
    }

    /**
     * Swap the variables for their values.
     *
     * A variable with nothing behind it is left exactly as written rather than
     * blanked. "{{payment.balance_due}}" arriving in a client's inbox is a bug
     * somebody reports within the hour; a silently emptied sentence — "Your
     * balance is ." — is one nobody ever notices, and it goes out for months.
     *
     * Whitespace inside the braces is tolerated because people type it.
     *
     * @param  array<string, string>  $values
     */
    public static function render(?string $text, array $values): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        foreach ($values as $name => $value) {
            /* Scalars only. `__()` hands back an ARRAY when a lang key happens
               to be a group rather than a line, and one of those reaching
               str_replace kills the whole email at render time. A variable
               that cannot be turned into a word is left standing instead. */
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $value = (string) $value;

            $text = (string) preg_replace(
                '/\{\{\s*'.preg_quote($name, '/').'\s*\}\}/',
                str_replace('$', '\\$', $value),
                $text,
            );
        }

        return $text;
    }

    /**
     * Resolve tokens that name one service: `{{service.42.name}}`.
     *
     * A second pass rather than more entries in the value map, because the
     * catalogue is per business and unbounded — building a map of every
     * service crossed with every field on every render would be hundreds of
     * strings to answer the two somebody actually used.
     *
     * A service that has since been deleted leaves its token standing, like
     * any other unanswerable variable: an owner who sees
     * "{{service.42.name}}" in a preview goes and fixes it, where a blank
     * would go out unnoticed.
     */
    public static function renderServiceTokens(string $text, ?Tenant $tenant = null): string
    {
        if (! str_contains($text, '{{service.')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*service\.(\d+)\.(name|duration|price)\s*\}\}/',
            function (array $match) use ($tenant): string {
                $service = Service::query()->find((int) $match[1]);

                if ($service === null) {
                    return $match[0];
                }

                return match ($match[2]) {
                    'name' => (string) $service->name,
                    'duration' => trans_choice('bookings.summary.minutes', (int) $service->duration_minutes, [
                        'count' => (int) $service->duration_minutes,
                    ]),
                    'price' => self::servicePrice($service, $tenant),
                };
            },
            $text,
        );
    }

    /**
     * What one service costs, as a reader would see it.
     *
     * Services can carry more than one price — card and cash — so this takes
     * the one the business leads with rather than inventing a total.
     */
    private static function servicePrice(Service $service, ?Tenant $tenant): string
    {
        $minor = (int) ($service->prices()->min('price_minor') ?? 0);

        /* Money::format takes major units, like every other call site in the
           app — passing minor turns a $75 service into $7,500. */
        return Money::format($minor / 100, (string) ($tenant?->currency_code ?: 'USD'));
    }

    /**
     * The menu, grouped and labelled.
     *
     * @return array<int, array{key: string, label: string, variables: array<int, array{token: string, label: string}>}>
     */
    public static function catalogue(): array
    {
        $groups = [];

        foreach (config('email_templates.variables') as $group => $names) {
            $groups[] = [
                'key' => $group,
                'label' => __('email_templates.variables.groups.'.$group),
                'variables' => array_map(fn (string $name) => [
                    'token' => '{{'.$group.'.'.$name.'}}',
                    'label' => __('email_templates.variables.names.'.$name),
                ], $names),
            ];
        }

        return $groups;
    }

    /**
     * Believable stand-ins, for the preview and the test send.
     *
     * A preview showing "Hi {{client.first_name}}" tells the reader nothing
     * about whether their email reads well; one showing "Hi Sarah" does.
     *
     * @return array<string, string>
     */
    public static function samples(?Tenant $tenant = null): array
    {
        $tenant ??= tenant();
        $money = fn (int $minor) => Money::format($minor, (string) ($tenant?->currency_code ?: 'USD'));

        /* The business's own details where it has them, believable stand-ins
           where it does not. A preview is for judging whether the email reads
           well, and a blank where a phone number belongs makes it impossible
           to tell a missing setting from a broken template. */
        $business = array_filter(self::business($tenant), fn ($value) => filled($value)) + [
            'business.name' => 'Serenity Spa',
            'business.phone' => '(704) 555-0198',
            'business.email' => 'hello@serenityspa.com',
            'business.website' => 'https://serenityspa.com',
        ];

        return array_map(fn ($value) => is_scalar($value) ? (string) $value : '', $business + [
            'client.first_name' => 'Sarah',
            'client.last_name' => 'Mitchell',
            'client.full_name' => 'Sarah Mitchell',
            'client.email' => 'sarah@example.com',
            'client.phone' => '(704) 555-0142',

            'booking.reference' => 'BK-20260908-00125',
            'booking.date' => now()->addDays(6)->translatedFormat('j F Y'),
            'booking.start_time' => TimeFormat::time('14:30'),
            'booking.end_time' => TimeFormat::time('15:30'),
            'booking.status' => __('bookings.statuses.confirmed.label'),

            'service.name' => 'Swedish Massage',
            'service.duration' => trans_choice('bookings.summary.minutes', 60, ['count' => 60]),
            'service.price' => $money(15000),

            'staff.first_name' => 'Jennifer',
            'staff.full_name' => 'Jennifer Smith',

            'location.name' => 'Downtown',
            'location.address' => '123 Main Street, Charlotte, NC 28202',
            'location.phone' => '(704) 555-0198',

            'payment.amount' => $money(18020),
            'payment.amount_paid' => $money(5000),
            'payment.balance_due' => $money(13020),
            /* A word, not a lang group: `bookings.methods.card` is an array
               in the catalogue, and asking __() for it returns the array. */
            'payment.method' => __('bookings.methods.card.name'),
            'payment.payment_link' => '#',

            /* Believable, and obviously not real: a preview must never show a
               code somebody could actually quote at the desk. */
            'coupon.code' => 'WELCOME20',
            'coupon.name' => 'Welcome Offer',
            'coupon.discount' => '20% off',
            'coupon.expires' => now()->addMonth()->translatedFormat('j F Y'),
        ]);
    }

    /**
     * What a chosen campaign puts into the text.
     *
     * Separate from `for()` because a coupon belongs to the template rather
     * than to the client or the booking — two emails to the same client can
     * carry different offers.
     *
     * @return array<string, string>
     */
    public static function couponValues(Promotion $coupon, ?Tenant $tenant = null): array
    {
        return [
            'coupon.code' => (string) $coupon->code,
            'coupon.name' => (string) $coupon->name,
            'coupon.discount' => $coupon->discountLabel($tenant?->currency_code),
            'coupon.expires' => $coupon->ends_on?->translatedFormat('j F Y') ?? '',
        ];
    }

    /* ------------------------------------------------------------ groups -- */

    /** @return array<string, ?string> */
    private static function client(?Client $client): array
    {
        return [
            'client.first_name' => $client?->first_name,
            'client.last_name' => $client?->last_name,
            'client.full_name' => $client?->displayName(),
            'client.email' => $client?->email,
            'client.phone' => $client?->mobile,
        ];
    }

    /** @return array<string, ?string> */
    private static function business(?Tenant $tenant): array
    {
        return [
            'business.name' => $tenant?->name,
            'business.phone' => $tenant?->business_phone,
            'business.email' => $tenant?->business_email,
            'business.website' => $tenant?->website,
        ];
    }

    /** @return array<string, ?string> */
    private static function booking(?Booking $booking): array
    {
        return [
            'booking.reference' => $booking?->reference,
            'booking.date' => $booking?->date?->translatedFormat('j F Y'),
            'booking.start_time' => $booking === null ? null : TimeFormat::time($booking->starts_at),
            'booking.end_time' => $booking === null ? null : TimeFormat::time($booking->endsAt()),
            'booking.status' => $booking === null ? null : __('bookings.statuses.'.$booking->status.'.label'),
        ];
    }

    /** @return array<string, ?string> */
    private static function service(?Booking $booking): array
    {
        $services = $booking?->services;

        return [
            /* Every service, not the first: a booking is often two, and naming
               one of them is worse than naming none. */
            'service.name' => $services?->pluck('name')->implode(', '),
            'service.duration' => $booking === null
                ? null
                : trans_choice('bookings.summary.minutes', (int) $booking->minutes, ['count' => (int) $booking->minutes]),
            'service.price' => $booking === null
                ? null
                : BookingTotals::for($booking)->money((int) $booking->subtotal_minor ?: (int) $booking->total_minor),
        ];
    }

    /** @return array<string, ?string> */
    private static function staff(?Staff $staff): array
    {
        return [
            'staff.first_name' => $staff?->first_name,
            'staff.full_name' => $staff?->displayName(),
        ];
    }

    /** @return array<string, ?string> */
    private static function location(?Location $location): array
    {
        return [
            'location.name' => $location?->name,
            'location.address' => $location === null ? null : collect([
                $location->address_line1, $location->city, $location->state, $location->postal_code,
            ])->filter()->implode(', '),
            'location.phone' => $location?->phone,
        ];
    }

    /** @return array<string, ?string> */
    private static function payment(?Booking $booking): array
    {
        if ($booking === null) {
            return [
                'payment.amount' => null, 'payment.amount_paid' => null,
                'payment.balance_due' => null, 'payment.method' => null,
                'payment.payment_link' => null,
            ];
        }

        $totals = BookingTotals::for($booking);

        return [
            'payment.amount' => $totals->money((int) $booking->total_minor),
            'payment.amount_paid' => $totals->money($booking->paidMinor()),
            'payment.balance_due' => $totals->money($booking->dueMinor()),
            'payment.method' => $booking->payments->last()?->methodLabel(),
            /* The live link if one was sent, and nothing if not — a "pay now"
               button pointing at a spent link is worse than no button. Built
               from the token, because the URL is a route rather than a column. */
            'payment.payment_link' => ($token = $booking->paymentLinks
                ->first(fn ($link) => $link->currentStatus() === 'sent')?->token)
                    ? route('booking.pay-link', ['token' => $token])
                    : null,
        ];
    }
}
