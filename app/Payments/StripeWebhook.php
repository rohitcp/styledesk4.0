<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\TenantStripeAccount;
use Stripe\Webhook;

/**
 * What Stripe tells StyleDesk after the fact.
 *
 * Lives here rather than in a controller because it is part of the Stripe
 * adapter, not part of HTTP: verifying a signature and interpreting an event
 * are as processor-specific as charging a card, and the seam that keeps
 * Booking, Checkout, Sales and Client free of a vendor name only holds if the
 * vendor's own code stays on this side of it.
 *
 * Three things happen away from the browser and matter: an account finishes
 * (or fails) verification, a payment succeeds that StyleDesk did not start,
 * and money is refunded from Stripe's dashboard rather than from StyleDesk.
 * Without this the app would confidently describe a state that stopped being
 * true days ago.
 */
class StripeWebhook
{
    public function __construct(private readonly StripeConnect $connect) {}

    /**
     * Verify and act on one event.
     *
     * @return int The status the endpoint should answer with
     */
    public function handle(string $payload, ?string $signature): int
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            /* Not configured is not an error to shout about — a deployment
               without Stripe has nothing to verify — but nothing is trusted
               either. */
            return 404;
        }

        try {
            $event = Webhook::constructEvent($payload, (string) $signature, $secret);
        } catch (\Throwable) {
            /* Unsigned, mis-signed, or replayed past the tolerance. Refused
               without saying which, because an attacker learning why is an
               attacker learning how. */
            return 400;
        }

        match ($event->type) {
            'account.updated' => $this->accountUpdated($event->data->object->toArray()),
            'payment_intent.succeeded' => $this->paymentSucceeded($event->data->object->toArray()),
            'charge.refunded' => $this->charged($event->data->object->toArray()),
            default => null,
        };

        /* 200 whatever happened above. Stripe retries a non-2xx, and retrying
           an event this application has chosen to ignore is a retry loop. */
        return 200;
    }

    /**
     * Verification finished, or something new is outstanding.
     *
     * @param  array<string, mixed>  $account
     */
    private function accountUpdated(array $account): void
    {
        $stored = TenantStripeAccount::query()
            ->where('stripe_account_id', data_get($account, 'id'))
            ->first();

        $stored === null ? null : $this->connect->apply($stored, $account);
    }

    /**
     * A payment StyleDesk may not have recorded.
     *
     * A client paying through a payment link finishes at Stripe, not here, so
     * this is the only thing that knows the money arrived.
     *
     * @param  array<string, mixed>  $intent
     */
    private function paymentSucceeded(array $intent): void
    {
        $bookingId = (int) data_get($intent, 'metadata.booking_id');

        if ($bookingId === 0) {
            return;
        }

        /* Idempotent by the intent id: Stripe delivers at least once, and
           twice is normal. A second row would double the takings. */
        if (BookingPayment::withoutGlobalScopes()->where('reference', data_get($intent, 'id'))->exists()) {
            return;
        }

        $booking = Booking::withoutGlobalScopes()->find($bookingId);

        if ($booking === null) {
            return;
        }

        $tip = (int) data_get($intent, 'metadata.tip_minor', 0);

        $booking->payments()->create([
            'tenant_id' => $booking->tenant_id,
            'method' => 'card',
            'status' => 'paid',
            'amount_minor' => (int) data_get($intent, 'amount_received') - $tip,
            'tip_minor' => $tip,
            'currency_code' => mb_strtoupper((string) data_get($intent, 'currency')),
            'reference' => data_get($intent, 'id'),
            'paid_at' => now(),
        ]);

        $booking->load('payments');
        $booking->settlePaymentStatus();
    }

    /**
     * Money sent back from Stripe's own dashboard.
     *
     * @param  array<string, mixed>  $charge
     */
    private function charged(array $charge): void
    {
        $payment = BookingPayment::withoutGlobalScopes()
            ->where('reference', data_get($charge, 'payment_intent'))
            ->first();

        if ($payment === null || (int) data_get($charge, 'amount_refunded') === 0) {
            return;
        }

        $refunded = (int) data_get($charge, 'amount_refunded');

        /* Only the part StyleDesk has not already written down: a refund
           issued here arrives back as a webhook, and recording it twice would
           halve the takings on paper. */
        $known = (int) BookingPayment::withoutGlobalScopes()
            ->where('booking_id', $payment->booking_id)
            ->where('status', 'refunded')
            ->sum('amount_minor');

        if ($refunded <= $known) {
            return;
        }

        $payment->booking->payments()->create([
            'tenant_id' => $payment->tenant_id,
            'method' => $payment->method,
            'status' => 'refunded',
            'amount_minor' => $refunded - $known,
            'currency_code' => $payment->currency_code,
            'note' => __('payments.stripe.refunded_at_stripe'),
            'reference' => data_get($charge, 'payment_intent'),
            'paid_at' => now(),
        ]);

        $payment->booking->load('payments');
        $payment->booking->settlePaymentStatus();
    }
}
