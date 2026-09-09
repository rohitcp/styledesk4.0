<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\TenantStripeAccount;
use App\Models\User;
use App\Payments\ManualGateway;
use App\Payments\PaymentFailed;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentRequest;
use App\Payments\StripeClientFactory;
use App\Payments\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Stripe\Account;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Stripe Connect.
 *
 * The Stripe client is faked throughout: these are about StyleDesk's half —
 * what it records, what it refuses, and what it does when Stripe says no —
 * and a test that reached the network would be testing Stripe.
 *
 * The rule underneath all of it: money belongs to the salon. StyleDesk charges
 * on the connected account, never on the platform's, and never holds funds.
 */
class StripeConnectTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.secret' => 'sk_test_fake']);

        $this->tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile-stripe']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->owner = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();
        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();
        $this->owner = $this->owner->fresh();

        tenancy()->initialize($this->tenant);
    }

    private function account(array $attributes = []): TenantStripeAccount
    {
        return TenantStripeAccount::create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'stripe_account_id' => 'acct_test123',
            'business_name' => 'Smile Spa',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'connected_at' => now(),
        ]);
    }

    /** A factory that hands back one prepared client, whoever asks. */
    private function factory(StripeClient $client): StripeClientFactory
    {
        return new class($client) extends StripeClientFactory
        {
            public function __construct(private readonly StripeClient $fake) {}

            public function for(?Tenant $tenant): ?StripeClient
            {
                return $this->fake;
            }

            public function platform(): ?StripeClient
            {
                return $this->fake;
            }

            public function make(string $key): ?StripeClient
            {
                return $this->fake;
            }
        };
    }

    private function booking(int $totalMinor = 20000): Booking
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Sarah', 'last_name' => 'Mitchell',
        ]);

        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $client->id,
            'date' => now()->toDateString(),
            'starts_at' => '10:00', 'minutes' => 60, 'status' => 'confirmed',
            'total_minor' => $totalMinor, 'currency_code' => 'USD',
        ]);
    }

    // ------------------------------------------------------- availability

    /** No keys on this deployment means the processor does not exist here. */
    public function test_stripe_is_not_offered_without_platform_keys(): void
    {
        config(['services.stripe.secret' => null]);

        $this->assertFalse(StripeGateway::isConfigured());
        $this->assertNull((new PaymentGatewayManager)->make('stripe'));
    }

    /** Configured but unconnected still falls back to recording. */
    public function test_a_business_that_has_not_connected_still_records_payments(): void
    {
        $this->tenant->forceFill(['payment_gateway' => 'stripe'])->save();

        $this->assertInstanceOf(
            ManualGateway::class,
            (new PaymentGatewayManager)->for($this->tenant),
        );
    }

    /**
     * Connected is not ready.
     *
     * Stripe verifies over hours or days, and offering an unverified account
     * fails at the moment a client tries to pay.
     */
    public function test_an_unverified_account_cannot_take_card_payments(): void
    {
        $this->account(['charges_enabled' => false]);
        $this->tenant->forceFill(['payment_gateway' => 'stripe'])->save();

        $this->assertInstanceOf(
            ManualGateway::class,
            (new PaymentGatewayManager)->for($this->tenant->fresh()),
        );
    }

    public function test_a_verified_account_takes_card_payments(): void
    {
        $this->account();
        $this->tenant->forceFill(['payment_gateway' => 'stripe'])->save();

        $this->app->instance(StripeClientFactory::class, $this->factory(Mockery::mock(StripeClient::class)));

        $this->assertInstanceOf(
            StripeGateway::class,
            (new PaymentGatewayManager)->for($this->tenant->fresh()),
        );
    }

    // ------------------------------------------------------------ charging

    /**
     * The charge goes on the salon's own account.
     *
     * Taking it into StyleDesk's and transferring afterwards would make
     * StyleDesk a money transmitter, which is a different business with
     * different licensing.
     */
    public function test_a_card_is_charged_on_the_connected_account(): void
    {
        $account = $this->account();
        $booking = $this->booking(20000);

        $intents = Mockery::mock();
        $intents->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $params) => $params['amount'] === 22000
                    && $params['currency'] === 'usd'
                    && $params['metadata']['tip_minor'] === '2000'),
                Mockery::on(fn (array $options) => $options['stripe_account'] === $account->stripe_account_id
                    /* One charge per attempt: without this a double-click
                       charges twice. */
                    && isset($options['idempotency_key'])),
            )
            ->andReturn((object) ['id' => 'pi_test_123']);

        $client = Mockery::mock(StripeClient::class);
        $client->paymentIntents = $intents;

        $payment = (new StripeGateway($this->factory($client)))->charge($booking, new PaymentRequest(
            amountMinor: 20000,
            method: 'card',
            tipMinor: 2000,
            paymentMethodToken: 'pm_card_visa',
        ));

        $this->assertSame('paid', $payment->status);
        $this->assertSame('pi_test_123', $payment->reference);
        /* The tip stays beside the bill, never folded into it. */
        $this->assertSame(20000, $payment->amount_minor);
        $this->assertSame(2000, $payment->tip_minor);
    }

    /** Cash on a Stripe business is still cash — recorded, not charged. */
    public function test_cash_is_recorded_rather_than_sent_to_stripe(): void
    {
        $this->account();
        $booking = $this->booking(20000);

        /* Nothing is stubbed: touching the client at all would fail here,
           which is the assertion. */
        $payment = (new StripeGateway($this->factory(Mockery::mock(StripeClient::class))))
            ->charge($booking, new PaymentRequest(amountMinor: 20000, method: 'cash'));

        $this->assertSame('paid', $payment->status);
        $this->assertNull($payment->reference);
    }

    /**
     * A refusal is recorded, not swallowed.
     *
     * A row written only on success leaves the desk believing a client was
     * charged when they were not.
     */
    public function test_a_declined_card_is_recorded_as_failed(): void
    {
        $this->account();
        $booking = $this->booking(20000);

        $intents = Mockery::mock();
        $intents->shouldReceive('create')->once()->andThrow(
            new CardException('Your card was declined.'),
        );

        $client = Mockery::mock(StripeClient::class);
        $client->paymentIntents = $intents;

        try {
            (new StripeGateway($this->factory($client)))->charge($booking, new PaymentRequest(
                amountMinor: 20000,
                method: 'card',
                paymentMethodToken: 'pm_card_declined',
            ));

            $this->fail('A declined card must not report success.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('declined', $e->getMessage());
        }

        $payment = BookingPayment::withoutGlobalScopes()->first();

        $this->assertSame('failed', $payment->status);
        $this->assertNull($payment->paid_at);
        /* And the booking is not marked paid on the strength of it. */
        $this->assertNotSame('paid', $booking->fresh()->payment_status);
    }

    public function test_charging_without_a_ready_account_is_refused(): void
    {
        $booking = $this->booking(20000);

        $this->expectException(PaymentFailed::class);

        (new StripeGateway($this->factory(Mockery::mock(StripeClient::class))))->charge($booking, new PaymentRequest(
            amountMinor: 20000,
            method: 'card',
            paymentMethodToken: 'pm_card_visa',
        ));
    }

    // -------------------------------------------------- bring your own key

    /**
     * A key is verified before it is trusted.
     *
     * Storing an unchecked one leaves a salon believing it can take payments
     * until the first client tries — the worst possible moment to find out.
     */
    public function test_a_business_can_connect_its_own_stripe_account(): void
    {
        $accounts = Mockery::mock();
        $accounts->shouldReceive('retrieve')->once()->andReturn(
            Account::constructFrom([
                'id' => 'acct_theirs',
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'details_submitted' => true,
                'business_profile' => ['name' => 'Their Spa'],
                'country' => 'US',
                'default_currency' => 'usd',
            ]),
        );

        $client = Mockery::mock(StripeClient::class);
        $client->accounts = $accounts;
        $this->app->instance(StripeClientFactory::class, $this->factory($client));

        $this->actingAs($this->owner)
            ->post(route('settings.payments.stripe.keys'), [
                'api_key' => 'sk_test_theirown',
                'publishable_key' => 'pk_test_theirown',
            ])
            ->assertRedirect(route('settings.payments.show'))
            ->assertSessionHasNoErrors();

        $account = TenantStripeAccount::first();

        $this->assertSame(TenantStripeAccount::MODE_OWN, $account->mode);
        $this->assertSame('acct_theirs', $account->stripe_account_id);
        $this->assertTrue($account->charges_enabled);

        /* Connecting is choosing. */
        $this->assertSame('stripe', $this->tenant->fresh()->payment_gateway);
    }

    /** A key Stripe refuses is not stored. */
    public function test_a_key_stripe_rejects_is_not_saved(): void
    {
        $accounts = Mockery::mock();
        $accounts->shouldReceive('retrieve')->once()->andThrow(
            new AuthenticationException('Invalid API Key provided'),
        );

        $client = Mockery::mock(StripeClient::class);
        $client->accounts = $accounts;
        $this->app->instance(StripeClientFactory::class, $this->factory($client));

        $this->actingAs($this->owner)
            ->post(route('settings.payments.stripe.keys'), ['api_key' => 'sk_test_wrong'])
            ->assertSessionHasErrors('api_key');

        $this->assertSame(0, TenantStripeAccount::count());
    }

    /** A publishable key in the secret box is the likeliest mistake. */
    public function test_a_publishable_key_is_refused_as_a_secret(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.payments.stripe.keys'), ['api_key' => 'pk_test_notasecret'])
            ->assertSessionHasErrors('api_key');

        $this->assertSame(0, TenantStripeAccount::count());
    }

    /** The key is encrypted at rest — the database never holds it in the clear. */
    public function test_the_secret_key_is_encrypted_in_the_database(): void
    {
        $this->account(['mode' => TenantStripeAccount::MODE_OWN, 'api_key' => 'sk_test_secret_value']);

        $raw = DB::table('tenant_stripe_accounts')->value('api_key');

        $this->assertNotSame('sk_test_secret_value', $raw);
        $this->assertStringNotContainsString('sk_test_secret_value', (string) $raw);

        /* And it decrypts back through the model. */
        $this->assertSame('sk_test_secret_value', TenantStripeAccount::first()->api_key);
    }

    /** The key never reaches the page, in any form. */
    public function test_the_secret_key_is_never_rendered(): void
    {
        $this->account(['mode' => TenantStripeAccount::MODE_OWN, 'api_key' => 'sk_test_secret_value']);

        $this->actingAs($this->owner)
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertDontSee('sk_test_secret_value')
            /* Only the last four, so an owner with two accounts can tell them
               apart. */
            ->assertSee('••••alue');
    }

    /**
     * On its own key the account IS the account.
     *
     * Sending `stripe_account` would ask their account to act on behalf of
     * another, which Stripe refuses — and getting this backwards is the
     * expensive mistake in the whole feature.
     */
    public function test_an_own_key_charge_does_not_impersonate_a_connected_account(): void
    {
        $account = $this->account(['mode' => TenantStripeAccount::MODE_OWN, 'api_key' => 'sk_test_theirs']);

        $this->assertSame([], (new StripeClientFactory)->options($account));

        /* Where a business is on the platform, the option is required. */
        $account->forceFill(['mode' => TenantStripeAccount::MODE_PLATFORM])->save();

        $this->assertSame(
            ['stripe_account' => 'acct_test123'],
            (new StripeClientFactory)->options($account->fresh()),
        );
    }

    /** A business on its own key needs nothing from the platform. */
    public function test_own_keys_work_without_platform_keys(): void
    {
        config(['services.stripe.secret' => null]);

        $this->account(['mode' => TenantStripeAccount::MODE_OWN, 'api_key' => 'sk_test_theirs']);

        $this->assertTrue(StripeGateway::isConfigured());
    }

    /** StyleDesk takes no cut of a charge it did not authorise. */
    public function test_no_platform_fee_is_taken_on_a_businesss_own_account(): void
    {
        config(['payments.platform_fee' => ['enabled' => true, 'fixed_minor' => 25, 'percent' => 1.0]]);

        $this->account(['mode' => TenantStripeAccount::MODE_OWN, 'api_key' => 'sk_test_theirs']);
        $booking = $this->booking(20000);

        $intents = Mockery::mock();
        $intents->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $params) => ! array_key_exists('application_fee_amount', $params)),
                Mockery::on(fn (array $options) => ! array_key_exists('stripe_account', $options)),
            )
            ->andReturn((object) ['id' => 'pi_own_1']);

        $client = Mockery::mock(StripeClient::class);
        $client->paymentIntents = $intents;

        $payment = (new StripeGateway($this->factory($client)))->charge($booking, new PaymentRequest(
            amountMinor: 20000,
            method: 'card',
            paymentMethodToken: 'pm_card_visa',
        ));

        /* The Mockery expectations above are the real assertion — they refuse
           the call if a fee or an account is sent. This states the outcome so
           the test is not counted as making none. */
        $this->assertSame('paid', $payment->status);
        $this->assertSame('pi_own_1', $payment->reference);
    }

    // ------------------------------------------------------------- webhook

    public function test_an_unsigned_webhook_is_refused(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $this->postJson(route('webhooks.stripe'), ['type' => 'payment_intent.succeeded'])
            ->assertStatus(400);
    }

    /** A deployment with no webhook secret trusts nothing. */
    public function test_a_webhook_is_ignored_when_no_secret_is_configured(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $this->postJson(route('webhooks.stripe'), ['type' => 'account.updated'])
            ->assertStatus(404);
    }

    // ------------------------------------------------------- the settings

    public function test_the_settings_screen_offers_connect_when_nothing_is_connected(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertSee(__('payments.stripe.connect'))
            ->assertSee(__('payments.stripe.not_connected'))
            ->assertSee(__('payments.stripe.money_note'));
    }

    public function test_the_settings_screen_shows_the_connected_account(): void
    {
        $this->account(['payout_last4' => '4582']);

        $this->actingAs($this->owner)
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertSee('Smile Spa')
            ->assertSee(__('payments.stripe.payout_account', ['last4' => '4582']))
            ->assertSee(__('payments.stripe.statuses.connected'));
    }

    /** What Stripe is still waiting for, in its own words. */
    public function test_an_account_needing_attention_says_what_is_missing(): void
    {
        $this->account([
            'charges_enabled' => false,
            'requirements' => ['currently_due' => ['individual.id_number']],
        ]);

        $this->actingAs($this->owner)
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertSee('individual.id_number');
    }

    /** Disconnecting must not stop the desk taking cash. */
    public function test_disconnecting_falls_back_to_recording(): void
    {
        $this->account();
        $this->tenant->forceFill(['payment_gateway' => 'stripe'])->save();

        $this->actingAs($this->owner)
            ->delete(route('settings.payments.stripe.disconnect'))
            ->assertRedirect(route('settings.payments.show'));

        $this->assertSame(0, TenantStripeAccount::count());
        $this->assertNull($this->tenant->fresh()->payment_gateway);
        $this->assertInstanceOf(ManualGateway::class, (new PaymentGatewayManager)->for($this->tenant->fresh()));
    }

    public function test_connect_is_unreachable_without_platform_keys(): void
    {
        config(['services.stripe.secret' => null]);

        $this->actingAs($this->owner)
            ->get(route('settings.payments.stripe.connect'))
            ->assertNotFound();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
