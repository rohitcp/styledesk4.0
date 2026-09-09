<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPaymentMethod;
use App\Models\Location;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\TenantStripeAccount;
use App\Models\User;
use App\Support\PaymentCapabilities;
use App\Support\PaymentFees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What a business's payments may do, what each one cost, and the cards behind
 * them.
 *
 * The thread running through all of it: a screen must never offer something
 * that cannot work. A payment link on a salon taking cash is a button with
 * nothing at the other end, and a fee of zero on a card charge Stripe has not
 * settled yet is a number the receipt cannot support.
 */
class PaymentCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-09 10:00:00');

        $this->tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function owner(): User
    {
        if ($existing = User::where('email', 'owner@styledesk.test')->first()) {
            return $existing;
        }

        $user = User::create([
            'first_name' => 'Emma', 'last_name' => 'Martin',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    /** A connected, charge-ready Stripe account. */
    private function connectStripe(): TenantStripeAccount
    {
        $this->tenant->forceFill(['payment_gateway' => 'stripe'])->save();

        return TenantStripeAccount::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'mode' => TenantStripeAccount::MODE_OWN,
            'stripe_account_id' => 'acct_test',
            'api_key' => 'sk_test_abc',
            'charges_enabled' => true,
            'details_submitted' => true,
            'connected_at' => now(),
        ]);
    }

    private function client(): Client
    {
        return Client::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'email' => 'sarah@example.test'],
            [
                'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
                'first_name' => 'Sarah', 'last_name' => 'Johnson', 'status' => 'active',
            ],
        );
    }

    // -------------------------------------------------------- capabilities

    /* A salon writing down cash cannot take a payment link — there is
       nothing at the other end of it. */
    public function test_a_recorder_cannot_do_what_needs_a_processor(): void
    {
        $this->assertFalse(PaymentCapabilities::allows($this->tenant, 'payment_link'));
        $this->assertFalse(PaymentCapabilities::allows($this->tenant, 'card_on_file'));
        /* Taking a card at the terminal beside the till needs nobody. */
        $this->assertTrue(PaymentCapabilities::allows($this->tenant, 'card'));
    }

    /**
     * Asserted through the screen rather than in isolation.
     *
     * Whether a gateway is ready depends on tenancy, which is resolved by
     * middleware — so a bare call outside a request sees no processor and
     * would pass for the wrong reason.
     */
    public function test_a_connected_processor_opens_the_rest(): void
    {
        $this->connectStripe();

        $this->actingAs($this->owner())
            ->get(route('settings.payments.show'))
            ->assertOk()
            /* Nothing is blocked for wanting a processor any more. What
               remains blocked is only what is not built yet. */
            ->assertDontSee(__('payments.capabilities.blocked.no_processor'))
            ->assertSee(__('payments.capabilities.blocked.unbuilt'));
    }

    /* The screen should say what is planned rather than offer a switch that
       does nothing. */
    public function test_a_capability_that_is_not_built_is_never_allowed(): void
    {
        $this->connectStripe();

        $this->assertFalse(PaymentCapabilities::allows($this->tenant->fresh(), 'tap_to_pay'));
    }

    /* A screen asking about a capability nobody defined has a bug in it, and
       answering yes is how that bug reaches a client. */
    public function test_an_unknown_capability_is_refused(): void
    {
        $this->assertFalse(PaymentCapabilities::allows($this->tenant, 'teleportation'));
    }

    /* A business that has never opened the screen gets the defaults, not
       everything switched off. */
    public function test_a_business_that_never_chose_gets_the_defaults(): void
    {
        $this->assertContains('booking_deposit', PaymentCapabilities::enabled($this->tenant));
        $this->assertNotContains('tap_to_pay', PaymentCapabilities::enabled($this->tenant));
    }

    public function test_switching_one_off_is_respected(): void
    {
        $this->connectStripe();

        $this->actingAs($this->owner())
            ->patch(route('settings.payments.update'), [
                'payments_enabled' => 1,
                'payment_gateway' => 'stripe',
                'capabilities' => ['card', 'booking_deposit'],
            ])
            ->assertRedirect();

        $tenant = $this->tenant->fresh();

        $this->assertTrue(PaymentCapabilities::allows($tenant, 'booking_deposit'));
        /* Unticked means unticked — not "fall back to the defaults". */
        $this->assertFalse(PaymentCapabilities::allows($tenant, 'payment_link'));
    }

    public function test_a_capability_nobody_built_cannot_be_switched_on_by_hand(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.payments.update'), [
                'payments_enabled' => 1,
                'capabilities' => ['tap_to_pay'],
            ])
            ->assertSessionHasErrors('capabilities.0');
    }

    public function test_the_settings_screen_says_why_something_is_closed(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertSee(__('payments.capabilities.title'))
            /* No processor connected, so the features that need one say so
               rather than offering a switch that would do nothing. */
            ->assertSee(__('payments.capabilities.blocked.no_processor'))
            ->assertSee(__('payments.capabilities.blocked.unbuilt'));
    }

    // ---------------------------------------------------------------- fees

    /** A booking for the payment to hang off. */
    private function booking(): Booking
    {
        $location = Location::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Main'],
            [
                'address_line1' => '1 Main St', 'city' => 'Hackensack',
                'postal_code' => '07601', 'country' => 'US',
                'timezone' => 'America/New_York', 'is_primary' => true,
            ],
        );

        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $this->client()->id,
            'location_id' => $location->id,
            'date' => '2026-09-15', 'starts_at' => '11:00', 'ends_at' => '12:00',
            'minutes' => 60, 'status' => 'confirmed',
            'subtotal_minor' => 10000, 'total_minor' => 10000, 'currency_code' => 'USD',
        ]);
    }

    private function payment(array $overrides = []): BookingPayment
    {
        return BookingPayment::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'booking_id' => $this->booking()->id,
            'method' => 'card',
            'status' => 'paid',
            'amount_minor' => 10000,
            'tip_minor' => 0,
            'currency_code' => 'USD',
            'paid_at' => now(),
        ]);
    }

    /* Nobody knows the fee at the moment of charge — Stripe settles it
       afterwards — and writing zero would be a claim the receipt cannot
       support. */
    public function test_an_unsettled_payment_has_no_fee_rather_than_a_fee_of_nothing(): void
    {
        $payment = $this->payment();

        $this->assertNull($payment->processor_fee_minor);
        $this->assertNull($payment->net_minor);
        $this->assertNull(PaymentFees::breakdown($payment));
    }

    public function test_recording_the_fee_works_out_what_landed(): void
    {
        $payment = $this->payment(['tip_minor' => 1500]);

        PaymentFees::record($payment, 350, 50, 'txn_123');

        $payment = $payment->fresh();

        $this->assertSame(350, $payment->processor_fee_minor);
        $this->assertSame(50, $payment->platform_fee_minor);
        /* The tip settles on the same card and is paid out in the same
           batch, so it is part of what landed. */
        $this->assertSame(11100, $payment->net_minor);
        $this->assertSame('txn_123', $payment->balance_transaction_id);
    }

    /* "What did Stripe cost me" and "what did StyleDesk charge me" are
       questions a salon owner asks separately. */
    public function test_the_two_fees_are_reported_apart(): void
    {
        $payment = $this->payment();

        PaymentFees::record($payment, 320, 50);

        $breakdown = PaymentFees::breakdown($payment->fresh());

        $this->assertSame('$3.20', $breakdown['processor']);
        $this->assertSame('$0.50', $breakdown['platform']);
        $this->assertSame('$96.30', $breakdown['net']);
    }

    public function test_recording_the_same_settlement_twice_changes_nothing(): void
    {
        $payment = $this->payment();

        PaymentFees::record($payment, 320, 50);
        PaymentFees::record($payment->fresh(), 320, 50);

        $this->assertSame(9630, $payment->fresh()->net_minor);
    }

    // ------------------------------------------------------------ statuses

    /* A payment the client has queried is not a refund: Stripe holds the
       money while the case runs, and nothing has gone back yet. */
    public function test_the_statuses_a_dispute_can_reach_are_labelled(): void
    {
        foreach (['authorized', 'disputed', 'chargeback', 'cancelled'] as $status) {
            $this->assertNotSame(
                'bookings.payment_statuses.'.$status.'.label',
                __('bookings.payment_statuses.'.$status.'.label'),
                "[{$status}] has no label, so the badge would print its own key.",
            );

            $this->assertNotNull(config('bookings.payment_statuses.'.$status.'.class'));
        }
    }

    // -------------------------------------------------- the client's cards

    private function card(array $overrides = []): ClientPaymentMethod
    {
        return ClientPaymentMethod::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $this->client()->id,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test',
            'gateway_payment_method_id' => 'pm_'.uniqid(),
            'brand' => 'visa', 'last4' => '4242',
            'exp_month' => 8, 'exp_year' => 2029,
            'status' => 'active',
        ]);
    }

    public function test_the_tab_is_absent_where_there_is_nowhere_to_keep_a_card(): void
    {
        $this->card();

        $this->actingAs($this->owner())
            ->get(route('clients.show', $this->client()))
            ->assertOk()
            ->assertDontSee('data-tab="payments"', false);
    }

    public function test_the_tab_lists_the_cards_without_the_card(): void
    {
        $this->connectStripe();
        $card = $this->card();
        $card->makeDefault();

        $response = $this->actingAs($this->owner())
            ->get(route('clients.show', $this->client()))
            ->assertOk();

        $response->assertSee('data-tab="payments"', false)
            ->assertSee('Visa •••• 4242')
            ->assertSee(__('payments.methods_list.default'))
            /* Everything StyleDesk holds is on that line. There is nothing
               else to show, because there is nothing else stored. */
            ->assertDontSee($card->gateway_payment_method_id);
    }

    /* The alternative is a subscription whose next payment silently fails
       and a client who finds out when their credits stop. */
    public function test_a_card_a_membership_renews_on_cannot_be_removed(): void
    {
        $this->connectStripe();
        $card = $this->card();

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'recurring', 'name' => 'Monthly Massage Membership',
            'price_minor' => 7900, 'billing_frequency' => 'monthly',
            'location_mode' => 'all', 'sell_in_store' => true, 'is_draft' => false,
        ]);

        ClientMembership::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $this->client()->id,
            'membership_plan_id' => $plan->id,
            'payment_method_id' => $card->id,
            'auto_renew' => true,
            'status' => 'active',
            'starts_on' => '2026-09-09',
            'type' => 'recurring',
            'price_minor' => 7900,
            'currency_code' => 'USD',
            'billing_frequency' => 'monthly',
            'next_billing_on' => '2026-10-09',
        ]);

        $this->actingAs($this->owner())
            ->delete(route('client-cards.destroy', ['client' => $this->client(), 'method' => $card]))
            ->assertSessionHasErrors('payment_method');

        $this->assertFalse($card->fresh()->isRemoved());
    }

    public function test_a_card_nothing_depends_on_can_be_removed(): void
    {
        $this->connectStripe();
        $card = $this->card();

        $this->actingAs($this->owner())
            ->delete(route('client-cards.destroy', ['client' => $this->client(), 'method' => $card]))
            ->assertSessionHasNoErrors();

        /* Kept, not erased: a membership renewed on it last month still
           points at it. */
        $this->assertTrue($card->fresh()->isRemoved());
        $this->assertNotNull($card->fresh());
    }
}
