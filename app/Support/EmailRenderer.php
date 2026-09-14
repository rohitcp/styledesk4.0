<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * A template plus a booking becomes a finished email.
 *
 * The one place that turns stored wording into HTML, for both kinds of
 * template — a booking confirmation the system fired and a follow-up a
 * receptionist chose go through exactly this. Two renderers would be two
 * places for the date format to drift.
 */
class EmailRenderer
{
    /**
     * Subject and HTML, ready to send.
     *
     * @return array{subject: string, html: string, heading: string}
     */
    public static function render(
        EmailTemplate $template,
        ?Tenant $tenant,
        ?Client $client = null,
        ?Booking $booking = null,
        array $extra = [],
    ): array {
        $values = EmailVariables::for($client, $booking, $tenant, $extra);

        return self::compose($template, $tenant, $values, $booking);
    }

    /**
     * The same email, drawn with believable stand-ins.
     *
     * For the preview and the test send: a preview reading "Hi
     * {{client.first_name}}" tells an owner nothing about whether their email
     * reads well, and one reading "Hi Sarah" tells them everything.
     *
     * @return array{subject: string, html: string, heading: string}
     */
    public static function preview(EmailTemplate $template, ?Tenant $tenant): array
    {
        return self::compose($template, $tenant, EmailVariables::samples($tenant), null, sample: true);
    }

    /**
     * @param  array<string, string>  $values
     * @return array{subject: string, html: string, heading: string}
     */
    private static function compose(
        EmailTemplate $template,
        ?Tenant $tenant,
        array $values,
        ?Booking $booking,
        bool $sample = false,
    ): array {
        /* Resolved before the variables, because a chosen campaign puts its
           code into `{{coupon.code}}` — an owner who wants the code in a
           sentence rather than in the block should get it. */
        $coupon = $template->couponToShow();

        if ($coupon !== null) {
            $values += EmailVariables::couponValues($coupon, $tenant);
        }

        /* A template that names services describes those, not the booking's:
           aftercare for a Swedish Massage says Swedish Massage whichever
           appointment it was sent about. */
        $named = $template->services();

        if ($named->isNotEmpty()) {
            $values['service.name'] = $named->pluck('name')->implode(', ');
        }

        /* Two passes: the fixed catalogue, then the tokens that name one
           service by id — `{{service.42.name}}` — which cannot live in a
           value map because the catalogue is unbounded. */
        $fill = fn (?string $text) => EmailVariables::renderServiceTokens(
            EmailVariables::render($text, $values),
            $tenant,
        );

        $heading = $fill($template->heading) ?: $fill($template->subject);

        $html = view('emails.standard', [
            'template' => $template,
            'brand' => EmailBrand::for($tenant),
            'heading' => $heading,
            'preheader' => Str::limit($fill($template->intro), 120),
            'intro' => $fill($template->intro),
            'supporting' => $fill($template->supporting_message),
            'ctaLabel' => $template->cta_enabled ? $fill($template->cta_label) : null,
            'ctaUrl' => $template->cta_enabled ? self::ctaUrl($template, $booking, $sample) : null,
            'bookingRows' => self::bookingRows($template, $values),
            'paymentRows' => self::paymentRows($template, $values),
            'paymentTotals' => self::paymentTotals($template, $values),
            'contactLines' => self::contactLines($values),
            'coupon' => $coupon,
            'currency' => $tenant?->currency_code,
        ])->render();

        return [
            'subject' => $fill($template->subject),
            'heading' => $heading,
            'html' => $html,
        ];
    }

    /**
     * Where the button goes.
     *
     * StyleDesk owns the destinations: an owner writes the words and picks
     * from actions the product can perform. A free-text URL field is how a
     * transactional email ends up pointing at a page that no longer exists.
     *
     * A sample preview gets `#` — a preview is not a live link, and a test
     * email that navigates somewhere real is a test that changes something.
     */
    private static function ctaUrl(EmailTemplate $template, ?Booking $booking, bool $sample): ?string
    {
        if ($sample) {
            return '#';
        }

        return match ($template->cta_action) {
            'view_booking', 'manage_booking' => $booking === null
                ? null
                : route('bookings.confirmation', $booking),
            'view_receipt' => $booking === null ? null : route('bookings.receipt', $booking),
            'make_payment' => $booking?->paymentLinks
                ->first(fn ($link) => $link->currentStatus() === 'sent')?->token
                    ? route('booking.pay-link', [
                        'token' => $booking->paymentLinks
                            ->first(fn ($link) => $link->currentStatus() === 'sent')->token,
                    ])
                    : null,
            /* Not built yet. Null rather than a guess: the shell drops a button
               with nowhere to go, which is the right answer until forms exist. */
            'complete_form' => null,
            'contact_business' => filled($email = tenant()?->business_email) ? 'mailto:'.$email : null,
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private static function bookingRows(EmailTemplate $template, array $values): array
    {
        $rows = [
            'service' => [__('email_templates.rows.service'), $values['service.name'] ?? ''],
            'date' => [__('email_templates.rows.date'), $values['booking.date'] ?? ''],
            'time' => [__('email_templates.rows.time'), trim(($values['booking.start_time'] ?? '').' – '.($values['booking.end_time'] ?? ''), ' –')],
            'staff' => [__('email_templates.rows.staff'), $values['staff.full_name'] ?? ''],
            'location' => [__('email_templates.rows.location'), trim(($values['location.name'] ?? '').' · '.($values['location.address'] ?? ''), ' ·')],
            'reference' => [__('email_templates.rows.reference'), $values['booking.reference'] ?? ''],
            'price' => [__('email_templates.rows.price'), $values['service.price'] ?? ''],
            'amount_paid' => [__('email_templates.rows.amount_paid'), $values['payment.amount_paid'] ?? ''],
            'balance_due' => [__('email_templates.rows.balance_due'), $values['payment.balance_due'] ?? ''],
        ];

        $shown = [];

        foreach ($rows as $field => [$label, $value]) {
            if ($template->showsDetail($field)) {
                $shown[$label] = $value;
            }
        }

        return $shown;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private static function paymentRows(EmailTemplate $template, array $values): array
    {
        return [
            __('email_templates.rows.amount') => $values['payment.amount'] ?? '',
            __('email_templates.rows.amount_paid') => $values['payment.amount_paid'] ?? '',
        ];
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private static function paymentTotals(EmailTemplate $template, array $values): array
    {
        return [__('email_templates.rows.balance_due') => $values['payment.balance_due'] ?? ''];
    }

    /**
     * @param  array<string, string>  $values
     * @return array<int, string>
     */
    private static function contactLines(array $values): array
    {
        return array_values(array_filter([
            $values['location.address'] ?? null,
            $values['business.phone'] ?? null,
            $values['business.email'] ?? null,
            $values['business.website'] ?? null,
        ]));
    }
}
