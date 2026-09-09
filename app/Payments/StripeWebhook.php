<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ClientPaymentMethod;
use App\Models\StripeWebhookEvent;
use App\Models\TenantStripeAccount;
use App\Support\PaymentFees;
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

        /* Written down before it is acted on, and recognised if it has been
           seen before. Stripe delivers at least once and retries on anything
           that is not a 2xx, so the same event id arriving twice is normal —
           and an event already settled must not be replayed. */
        $record = StripeWebhookEvent::begin(
            (string) $event->id,
            (string) $event->type,
            $event->account ?? null,
        );

        if ($record->attempts > 1 && $record->processed_at !== null && $record->status === 'processing') {
            /* A retry of something that finished. Nothing to redo. */
            return 200;
        }

        try {
            $handled = match ($event->type) {
                'account.updated' => $this->accountUpdated($event->data->object->toArray()),
                'payment_intent.succeeded' => $this->paymentSucceeded($event->data->object->toArray()),
                'payment_intent.payment_failed' => $this->paymentFailed($event->data->object->toArray()),
                'charge.refunded' => $this->charged($event->data->object->toArray()),
                /* What the money cost, which Stripe only knows once it has
                   settled — minutes or hours after the payment itself. */
                'charge.succeeded', 'charge.updated' => $this->chargeSettled($event->data->object->toArray()),
                'charge.dispute.created' => $this->disputed($event->data->object->toArray()),
                'charge.dispute.closed' => $this->disputeClosed($event->data->object->toArray()),
                /* A card the client removed at the gateway, or one Stripe
                   detached itself. StyleDesk keeps its row and stops
                   reaching for it. */
                'payment_method.detached' => $this->cardDetached($event->data->object->toArray()),
                default => null,
            };

            /* Nothing to do with it is an outcome, not a failure. Stripe
               sends plenty StyleDesk has no opinion about, and logging those
               as errors buries the ones that matter. */
            $handled === null
                ? $record->ignore(__('payments.webhooks.unhandled'))
                : $record->complete($handled);
        } catch (\Throwable $e) {
            /* Recorded and swallowed. The log is what makes a failure
               visible; answering non-2xx would have Stripe retry an event
               that will fail the same way every time. */
            $record->fail($e->getMessage());

            report($e);
        }

        /* 200 whatever happened above. Stripe retries a non-2xx, and retrying
           an event this application has chosen to ignore is a retry loop. */
        return 200;
    }

    /**
     * Verification finished, or something new is outstanding.
     *
     * @param  array<string, mixed>  $account
     */
    private function accountUpdated(array $account): ?string
    {
        $stored = TenantStripeAccount::query()
            ->where('stripe_account_id', data_get($account, 'id'))
            ->first();

        if ($stored === null) {
            return null;
        }

        $this->connect->apply($stored, $account);

        return (string) $stored->tenant_id;
    }

    /**
     * A payment StyleDesk may not have recorded.
     *
     * A client paying through a payment link finishes at Stripe, not here, so
     * this is the only thing that knows the money arrived.
     *
     * @param  array<string, mixed>  $intent
     */
    private function paymentSucceeded(array $intent): ?string
    {
        $bookingId = (int) data_get($intent, 'metadata.booking_id');

        if ($bookingId === 0) {
            return null;
        }

        /* Idempotent by the intent id: Stripe delivers at least once, and
           twice is normal. A second row would double the takings. */
        if ($known = BookingPayment::withoutGlobalScopes()->where('reference', data_get($intent, 'id'))->first()) {
            /* Already recorded, and that is a completed outcome rather than
               nothing to do — the money did arrive. */
            return (string) $known->tenant_id;
        }

        $booking = Booking::withoutGlobalScopes()->find($bookingId);

        if ($booking === null) {
            return null;
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

        return (string) $booking->tenant_id;
    }

    /**
     * A card that was refused.
     *
     * Recorded rather than swallowed. A payment the desk believes went
     * through and did not is the single most expensive thing this
     * integration can get wrong, and a failed link or an off-session renewal
     * is exactly the case nobody is standing there to see.
     *
     * @param  array<string, mixed>  $intent
     */
    private function paymentFailed(array $intent): ?string
    {
        $bookingId = (int) data_get($intent, 'metadata.booking_id');

        if ($bookingId === 0) {
            return null;
        }

        $booking = Booking::withoutGlobalScopes()->find($bookingId);

        if ($booking === null) {
            return null;
        }

        /* Idempotent by the intent id, like every other handler here. */
        if (BookingPayment::withoutGlobalScopes()
            ->where('reference', data_get($intent, 'id'))
            ->where('status', 'failed')
            ->exists()) {
            return (string) $booking->tenant_id;
        }

        $booking->payments()->create([
            'tenant_id' => $booking->tenant_id,
            'method' => 'card',
            'status' => 'failed',
            'amount_minor' => (int) data_get($intent, 'amount'),
            'currency_code' => mb_strtoupper((string) data_get($intent, 'currency')),
            'reference' => data_get($intent, 'id'),
            /* Stripe's own wording, kept for the desk. It is never shown to
               a client: a raw decline code describes our integration, not
               their card. */
            'note' => (string) data_get($intent, 'last_payment_error.message'),
        ]);

        return (string) $booking->tenant_id;
    }

    /**
     * What the payment actually cost, once Stripe has settled it.
     *
     * The fee is not known at the moment of charge — Stripe works it out on
     * a balance transaction afterwards — so this is the only thing that can
     * tell a salon what landed in their account.
     *
     * @param  array<string, mixed>  $charge
     */
    private function chargeSettled(array $charge): ?string
    {
        $payment = BookingPayment::withoutGlobalScopes()
            ->where('reference', data_get($charge, 'payment_intent'))
            ->first();

        $fee = data_get($charge, 'balance_transaction.fee');

        if ($payment === null || $fee === null) {
            return null;
        }

        PaymentFees::record(
            $payment,
            (int) $fee,
            (int) data_get($charge, 'application_fee_amount', 0),
            (string) data_get($charge, 'balance_transaction.id'),
        );

        return (string) $payment->tenant_id;
    }

    /**
     * A client has disputed a charge.
     *
     * Marked, never reversed. The money has not gone back yet — Stripe holds
     * it while the case runs — and a booking whose payment silently became a
     * refund would take the appointment's takings with it before anybody had
     * decided anything.
     *
     * @param  array<string, mixed>  $dispute
     */
    private function disputed(array $dispute): ?string
    {
        $payment = BookingPayment::withoutGlobalScopes()
            ->where('reference', data_get($dispute, 'payment_intent'))
            ->first();

        if ($payment === null) {
            return null;
        }

        $payment->forceFill([
            'status' => 'disputed',
            'note' => trim($payment->note.' '.__('payments.stripe.disputed', [
                'reason' => (string) data_get($dispute, 'reason'),
            ])),
        ])->save();

        return (string) $payment->tenant_id;
    }

    /**
     * The case finished.
     *
     * Lost is a chargeback — the money has genuinely gone — and won puts the
     * payment back where it was. Both are outcomes worth writing down: a
     * payment stuck on "disputed" forever is one nobody can reconcile.
     *
     * @param  array<string, mixed>  $dispute
     */
    private function disputeClosed(array $dispute): ?string
    {
        $payment = BookingPayment::withoutGlobalScopes()
            ->where('reference', data_get($dispute, 'payment_intent'))
            ->first();

        if ($payment === null) {
            return null;
        }

        $payment->forceFill([
            'status' => data_get($dispute, 'status') === 'lost' ? 'chargeback' : 'paid',
        ])->save();

        return (string) $payment->tenant_id;
    }

    /**
     * A saved card that is no longer at the gateway.
     *
     * StyleDesk keeps its own row — a membership renewed on this card last
     * month still points at it — and stops reaching for it. The next renewal
     * asks for a new card rather than failing at the till.
     *
     * @param  array<string, mixed>  $method
     */
    private function cardDetached(array $method): ?string
    {
        $card = ClientPaymentMethod::withoutGlobalScopes()
            ->where('gateway_payment_method_id', data_get($method, 'id'))
            ->first();

        if ($card === null) {
            return null;
        }

        $card->markRemoved();

        return (string) $card->tenant_id;
    }

    /**
     * Money sent back from Stripe's own dashboard.
     *
     * @param  array<string, mixed>  $charge
     */
    private function charged(array $charge): ?string
    {
        $payment = BookingPayment::withoutGlobalScopes()
            ->where('reference', data_get($charge, 'payment_intent'))
            ->first();

        if ($payment === null || (int) data_get($charge, 'amount_refunded') === 0) {
            return null;
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
            /* Already accounted for. The event was handled the first time. */
            return (string) $payment->tenant_id;
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

        return (string) $payment->tenant_id;
    }
}
