<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\Tenant;
use App\Models\TenantStripeAccount;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe Connect.
 *
 * A client pays; Stripe settles into the salon's own connected account; Stripe
 * pays the salon's bank. StyleDesk never holds or redistributes the money,
 * which is the point of Connect rather than one shared merchant account.
 *
 * The client is injected rather than constructed, so this class can be
 * exercised without a network — and so a future platform-fee change is a
 * parameter here rather than a rewrite of the checkout.
 *
 * StyleDesk stores references and never card data: no number, no CVV, no
 * stripe. What comes back is an id, and an id cannot move money without the
 * platform secret, which lives in the environment.
 */
class StripeGateway implements PaymentGateway, VaultsCards
{
    public function __construct(private readonly StripeClientFactory $clients) {}

    /**
     * The client for the business being served.
     *
     * Resolved per call rather than injected, because whose key authorises it
     * depends on how that business connected — StyleDesk's platform key, or
     * their own.
     */
    private function client(?Tenant $tenant): StripeClient
    {
        $client = $this->clients->for($tenant);

        if ($client === null) {
            throw new PaymentFailed(__('payments.stripe.not_ready'));
        }

        return $client;
    }

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return __('payments.gateways.stripe.name');
    }

    /**
     * Configured on this deployment, and connected by this business.
     *
     * Both halves matter and they fail differently: no keys is StyleDesk's
     * problem, no connected account is the salon's, and an account that has
     * not passed verification is Stripe's. The settings screen tells them
     * apart; this only answers "can money move right now".
     */
    public function isReady(): bool
    {
        $account = self::accountFor(tenant());

        if ($account === null || ! $account->canCharge()) {
            return false;
        }

        /* On its own key a business needs nothing from the platform — which is
           the point of offering the choice. On the platform's, it needs the
           platform to have one. */
        return $account->usesOwnKeys() || filled(config('services.stripe.secret'));
    }

    /**
     * Whether Stripe can be offered on this deployment at all.
     *
     * True where StyleDesk holds platform keys, and also where the business
     * being served has supplied its own — a salon with its own Stripe account
     * does not need StyleDesk to have one.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'))
            || (self::accountFor(tenant())?->usesOwnKeys() ?? false);
    }

    public static function accountFor(?Tenant $tenant): ?TenantStripeAccount
    {
        return $tenant === null
            ? null
            : TenantStripeAccount::query()->where('tenant_id', $tenant->getTenantKey())->first();
    }

    public function processes(): bool
    {
        return true;
    }

    /**
     * Everything the recorder takes, plus the wallets only a processor can.
     *
     * A salon with Stripe connected still takes cash, so this is the manual
     * list widened rather than a different one.
     *
     * @return array<int, string>
     */
    public function methods(): array
    {
        return array_keys(config('payments.methods'));
    }

    /**
     * Take the money.
     *
     * A payment method token means Stripe charges a card; anything else is
     * money that arrived another way and is recorded exactly as the manual
     * gateway records it. One method rather than two, because "what did we
     * take for this booking" must have a single answer whatever moved it.
     *
     * @throws PaymentFailed
     */
    public function charge(Booking $booking, PaymentRequest $request): BookingPayment
    {
        /* Cash on a Stripe business is still cash. Nothing to charge, so
           nothing to fail — recorded, not processed. */
        if ($request->paymentMethodToken === null) {
            return app(ManualGateway::class)->charge($booking, $request);
        }

        $account = self::accountFor($booking->tenant);

        if ($account === null || ! $account->canCharge()) {
            throw new PaymentFailed(__('payments.stripe.not_ready'));
        }

        try {
            /*
             * Charged on the connected account, not the platform's.
             *
             * `stripe_account` puts the money in the salon's account from the
             * outset. Taking it into StyleDesk's and transferring afterwards
             * would make StyleDesk a money transmitter, which is a different
             * business with different licensing.
             */
            $intent = $this->client($booking->tenant)->paymentIntents->create([
                'amount' => $request->totalMinor(),
                'currency' => mb_strtolower((string) $booking->currency_code),
                'payment_method' => $request->paymentMethodToken,
                'confirm' => true,
                'off_session' => true,
                'description' => $booking->reference,
                'metadata' => [
                    'booking_id' => (string) $booking->id,
                    'booking_reference' => (string) $booking->reference,
                    'tenant_id' => (string) $booking->tenant_id,
                    /* The tip travels with the charge so a payout can be
                       reconciled against what the salon owes its staff. */
                    'tip_minor' => (string) $request->tipMinor,
                ],
            ] + $this->platformFee($request, $account), $this->clients->options($account) + [
                /* One charge per attempt. Without this a retried request —
                   a double-click, a dropped connection — charges twice. */
                'idempotency_key' => 'bk_'.$booking->id.'_'.$request->totalMinor().'_'.substr(md5($request->paymentMethodToken.now()->format('YmdHi')), 0, 16),
            ]);
        } catch (ApiErrorException $e) {
            /* Recorded as failed rather than swallowed: a payment that did not
               go through is one the desk has to know about, and a row written
               only on success leaves them believing a client was charged. */
            $this->record($booking, $request, 'failed', null, $e->getMessage());

            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        return $this->record($booking, $request, 'paid', $intent->id);
    }

    /**
     * Give some of it back, through Stripe.
     *
     * The original charge is refunded rather than a new payment made, so
     * Stripe's own records, the salon's payout and StyleDesk's history agree.
     *
     * @throws PaymentFailed
     */
    public function refund(BookingPayment $payment, int $amountMinor, ?string $reason = null, ?string $note = null): BookingPayment
    {
        $account = self::accountFor($payment->booking->tenant);

        /* Money StyleDesk only recorded cannot be sent back by Stripe — there
           is nothing at Stripe to reverse. It is handed back the way it came
           and written down, which is what the manual gateway does. */
        if ($payment->reference === null || $account === null) {
            return app(ManualGateway::class)->refund($payment, $amountMinor, $reason, $note);
        }

        try {
            $this->client($payment->booking->tenant)->refunds->create([
                'payment_intent' => $payment->reference,
                'amount' => $amountMinor,
                'metadata' => array_filter(['reason' => $reason, 'note' => $note]),
            ], $this->clients->options($account));
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        return app(ManualGateway::class)->refund($payment, $amountMinor, $reason, $note);
    }

    /**
     * A page the client can pay on.
     *
     * Stripe hosts it, so the card never touches StyleDesk and the salon's
     * branding still fronts it.
     */
    public function paymentLink(Booking $booking, int $amountMinor): ?string
    {
        $account = self::accountFor($booking->tenant);

        if ($account === null || ! $account->canCharge()) {
            return null;
        }

        try {
            $session = $this->client($booking->tenant)->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => mb_strtolower((string) $booking->currency_code),
                        'unit_amount' => $amountMinor,
                        'product_data' => ['name' => $booking->reference],
                    ],
                ]],
                'success_url' => route('bookings.confirmation', $booking),
                'cancel_url' => route('bookings.confirmation', $booking),
                'metadata' => ['booking_id' => (string) $booking->id],
            ], $this->clients->options($account));
        } catch (ApiErrorException) {
            return null;
        }

        return $session->url;
    }

    /**
     * StyleDesk's cut, when there is one.
     *
     * Nothing is taken today. The shape is here so enabling it later is a
     * config change rather than a migration and a re-reconciliation of every
     * transaction ever recorded.
     *
     * @return array<string, int>
     */
    /* ------------------------------------------------------- the card vault */

    /**
     * Start a card being added, and hand back what the browser needs.
     *
     * A SetupIntent rather than a charge: the client is authorising future
     * billing, not paying for anything today. The client secret it returns is
     * what Stripe's own Payment Element uses to collect the card — the number
     * goes from the browser to Stripe and never touches this application.
     *
     * `off_session` usage is what tells Stripe the card will be charged with
     * nobody present. Stripe collects the extra authentication now, while the
     * client is here to give it, rather than failing the first renewal.
     */
    public function startCardSetup(Client $client): array
    {
        $account = self::accountFor($client->tenant);

        if ($account === null || ! $account->canCharge()) {
            throw new PaymentFailed(__('payments.stripe.not_ready'));
        }

        $stripe = $this->client($client->tenant);
        $options = $this->clients->options($account);

        try {
            $customerId = $this->customerFor($client, $stripe, $options);

            $intent = $stripe->setupIntents->create([
                'customer' => $customerId,
                'usage' => 'off_session',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'client_id' => (string) $client->id,
                    'tenant_id' => (string) $client->tenant_id,
                ],
            ], $options);
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        return [
            'client_secret' => (string) $intent->client_secret,
            'customer_id' => $customerId,
            'publishable_key' => $account->publishable_key,
        ];
    }

    /**
     * Record a card Stripe has already stored.
     *
     * The brand, the last four and the expiry are read back from Stripe
     * rather than accepted from the browser. What a page claims a card is and
     * what Stripe will actually charge have to be the same thing, and only
     * one of the two is trustworthy.
     */
    public function rememberCard(Client $client, string $gatewayPaymentMethodId, string $gatewayCustomerId): ClientPaymentMethod
    {
        $account = self::accountFor($client->tenant);

        if ($account === null) {
            throw new PaymentFailed(__('payments.stripe.not_ready'));
        }

        try {
            $method = $this->client($client->tenant)->paymentMethods->retrieve(
                $gatewayPaymentMethodId, [], $this->clients->options($account),
            );
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        $card = $method->card ?? null;

        return ClientPaymentMethod::updateOrCreate(
            ['gateway' => $this->key(), 'gateway_payment_method_id' => $gatewayPaymentMethodId],
            [
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->id,
                'gateway_customer_id' => $gatewayCustomerId,
                'brand' => $card?->brand,
                'last4' => $card?->last4,
                'exp_month' => $card?->exp_month,
                'exp_year' => $card?->exp_year,
                'status' => 'active',
                'removed_at' => null,
                'added_by' => auth()->id(),
            ],
        );
    }

    /**
     * Charge a saved card with nobody present.
     *
     * `off_session` twice over: once to tell Stripe the client is not here,
     * and once because a card saved for off-session use is the only kind that
     * can be charged this way without the bank asking for a code nobody is
     * standing there to give.
     *
     * A refusal is thrown with Stripe's own reason rather than a generic
     * failure — "insufficient funds" and "card expired" need different things
     * done about them, and the desk is the one who has to do them.
     */
    public function chargeSavedCard(ClientPaymentMethod $method, int $amountMinor, string $currency, string $description): array
    {
        $tenant = $method->client?->tenant;
        $account = self::accountFor($tenant);

        if ($account === null || ! $account->canCharge()) {
            throw new PaymentFailed(__('payments.stripe.not_ready'));
        }

        try {
            $intent = $this->client($tenant)->paymentIntents->create([
                'amount' => $amountMinor,
                'currency' => mb_strtolower($currency),
                'customer' => $method->gateway_customer_id,
                'payment_method' => $method->gateway_payment_method_id,
                'confirm' => true,
                'off_session' => true,
                'description' => $description,
                'metadata' => [
                    'client_id' => (string) $method->client_id,
                    'tenant_id' => (string) $method->tenant_id,
                    'payment_method_id' => (string) $method->id,
                ],
            ], $this->clients->options($account) + [
                /* One charge per renewal. A retried request — a dropped
                   connection, a scheduler that ran twice — must not take the
                   money twice. */
                'idempotency_key' => 'cpm_'.$method->id.'_'.$amountMinor.'_'.substr(md5($description), 0, 16),
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        /* Anything but a settled charge is a failure here. A payment that is
           "processing" has not paid for the month, and issuing credits
           against it would be giving away a massage on a promise. */
        if ($intent->status !== 'succeeded') {
            throw new PaymentFailed(__('payments.stripe.not_settled'), (string) $intent->status);
        }

        return ['reference' => (string) $intent->id, 'status' => 'paid'];
    }

    /** Tell Stripe to let the card go. */
    public function forgetCard(ClientPaymentMethod $method): void
    {
        $account = self::accountFor($method->client?->tenant);

        if ($account === null) {
            return;
        }

        try {
            $this->client($method->client?->tenant)->paymentMethods->detach(
                $method->gateway_payment_method_id, [], $this->clients->options($account),
            );
        } catch (ApiErrorException) {
            /* The card is out of use in StyleDesk either way. A gateway that
               has already forgotten it, or is briefly unreachable, is not a
               reason to refuse the receptionist. */
        }
    }

    /**
     * Who this client is to Stripe, making them if they are not one yet.
     *
     * The id is kept on the client's existing cards rather than in a column
     * of its own: a client with a saved card already has one, and a client
     * with none does not need one until the moment they are given a card.
     */
    private function customerFor(Client $client, StripeClient $stripe, array $options): string
    {
        $existing = ClientPaymentMethod::query()
            ->forClient($client->id)
            ->where('gateway', $this->key())
            ->orderByDesc('id')
            ->value('gateway_customer_id');

        if ($existing !== null) {
            return $existing;
        }

        try {
            $customer = $stripe->customers->create([
                'name' => $client->displayName(),
                'email' => $client->email,
                'metadata' => [
                    'client_id' => (string) $client->id,
                    'tenant_id' => (string) $client->tenant_id,
                ],
            ], $options);
        } catch (ApiErrorException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getStripeCode());
        }

        return (string) $customer->id;
    }

    private function platformFee(PaymentRequest $request, TenantStripeAccount $account): array
    {
        $fee = config('payments.platform_fee');

        /* Nothing to take on a business's own account. An application fee is
           the platform's share of a charge it authorised, and StyleDesk
           authorised nothing here — Stripe would refuse it, and rightly. */
        if (! ($fee['enabled'] ?? false) || $account->usesOwnKeys()) {
            return [];
        }

        $amount = (int) $fee['fixed_minor'] + (int) round($request->amountMinor * ((float) $fee['percent'] / 100));

        /* Never on the tip. It is owed to whoever did the work, and a platform
           taking a slice of it would be taking it from them. */
        return $amount > 0 ? ['application_fee_amount' => $amount] : [];
    }

    /** The transaction row, whatever the outcome. */
    private function record(
        Booking $booking,
        PaymentRequest $request,
        string $status,
        ?string $reference,
        ?string $note = null,
    ): BookingPayment {
        return DB::transaction(function () use ($booking, $request, $status, $reference, $note) {
            $payment = $booking->payments()->create([
                'tenant_id' => $booking->tenant_id,
                'method' => $request->method,
                'status' => $status,
                'amount_minor' => $request->amountMinor,
                'tip_minor' => $request->tipMinor,
                'currency_code' => $booking->currency_code,
                'reference' => $reference,
                'note' => $note ?? $request->note,
                'paid_at' => $status === 'paid' ? now() : null,
                'recorded_by' => $request->userId,
            ]);

            $booking->load('payments');
            $booking->settlePaymentStatus();

            return $payment;
        });
    }
}
