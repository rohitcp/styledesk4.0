<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPaymentMethod;
use App\Models\Location;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Payments\ManualGateway;
use App\Payments\PaymentGatewayManager;
use App\Payments\StripeGateway;
use App\Payments\VaultsCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The card vault.
 *
 * The rule everything here turns on: StyleDesk stores payment REFERENCES, not
 * cards. The card lives at the gateway, which is PCI-compliant and whose
 * business that is; what StyleDesk keeps is the token it was handed and the
 * four digits a receptionist says out loud.
 *
 * The first test is the one that matters most. Adding a raw card column later
 * would move this business inside PCI scope in a single migration, and a test
 * that names the forbidden columns is what makes that a failing build rather
 * than a quiet Tuesday.
 */
class ClientPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-09 10:00:00');

        $this->tenant = Tenant::create(['name' => 'Acme Spa', 'slug' => 'acme']);
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

    private function client(string $email = 'sarah@example.test'): Client
    {
        return Client::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'email' => $email],
            [
                'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
                'first_name' => 'Sarah', 'last_name' => 'Johnson', 'status' => 'active',
            ],
        );
    }

    private function card(array $overrides = []): ClientPaymentMethod
    {
        return ClientPaymentMethod::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $this->client()->id,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_'.fake()->bothify('##########'),
            'gateway_payment_method_id' => 'pm_'.fake()->bothify('##########'),
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 8,
            'exp_year' => 2029,
            'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------- the vault

    /**
     * The columns that must never exist.
     *
     * Named explicitly rather than checked by shape: a migration that adds
     * `card_number` should fail this build, and the failure should say why.
     */
    public function test_the_table_cannot_hold_a_card(): void
    {
        $columns = Schema::getColumnListing('client_payment_methods');

        foreach (['card_number', 'number', 'cvv', 'cvc', 'pin', 'security_code', 'track_data', 'expiry_full'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $columns,
                "client_payment_methods must never hold [{$forbidden}] — the card lives at the gateway.",
            );
        }

        /* What it does hold: a reference, and enough to recognise the card. */
        foreach (['gateway', 'gateway_customer_id', 'gateway_payment_method_id', 'brand', 'last4'] as $expected) {
            $this->assertContains($expected, $columns);
        }
    }

    public function test_a_card_reads_as_a_person_would_say_it(): void
    {
        $card = $this->card();

        $this->assertSame('Visa •••• 4242', $card->label());
        $this->assertSame('08/29', $card->expiryLabel());
    }

    // ------------------------------------------------------------- expiry

    /* A card expires at the END of its month — one dated 08/29 works
       throughout August. */
    public function test_a_card_is_good_until_the_end_of_its_month(): void
    {
        $card = $this->card(['exp_month' => 9, 'exp_year' => 2026]);

        Carbon::setTestNow('2026-09-30 23:00:00');
        $this->assertFalse($card->isExpired());
        $this->assertTrue($card->isChargeable());

        Carbon::setTestNow('2026-10-01 00:30:00');
        $this->assertTrue($card->isExpired());
        $this->assertFalse($card->isChargeable());
    }

    public function test_a_card_running_out_soon_is_flagged_before_it_fails(): void
    {
        /* This month and next: a renewal three weeks out on a card that dies
           in two is a payment nobody has to lose. */
        $this->assertTrue($this->card(['exp_month' => 9, 'exp_year' => 2026])->isExpiringSoon());
        $this->assertTrue($this->card(['exp_month' => 10, 'exp_year' => 2026])->isExpiringSoon());
        $this->assertFalse($this->card(['exp_month' => 12, 'exp_year' => 2026])->isExpiringSoon());
        /* Already gone is not "soon" — it is a different problem. */
        $this->assertFalse($this->card(['exp_month' => 8, 'exp_year' => 2026])->isExpiringSoon());
    }

    // ------------------------------------------------------------ default

    public function test_exactly_one_card_is_the_default(): void
    {
        $first = $this->card();
        $second = $this->card();

        $first->makeDefault();
        $second->makeDefault();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, ClientPaymentMethod::withoutGlobalScopes()->where('is_default', true)->count());
    }

    public function test_the_default_is_the_one_renewals_reach_for(): void
    {
        $this->card();
        $chosen = $this->card();
        $chosen->makeDefault();

        $this->assertSame($chosen->id, ClientPaymentMethod::defaultFor($this->client()->id)?->id);
    }

    /* A client whose default was removed falls to the card they added most
       recently, which is the one they are most likely to have meant. */
    public function test_removing_the_default_falls_to_the_newest_card(): void
    {
        $old = $this->card();
        $newest = $this->card();
        $old->makeDefault();

        $old->markRemoved();

        $this->assertSame($newest->id, ClientPaymentMethod::defaultFor($this->client()->id)?->id);
    }

    /* A membership renewed on this card last month still points at it, and a
       row that vanished would leave that payment unexplained. */
    public function test_a_removed_card_is_kept_and_not_erased(): void
    {
        $card = $this->card();
        $card->markRemoved();

        $card = $card->fresh();

        $this->assertNotNull($card);
        $this->assertTrue($card->isRemoved());
        $this->assertFalse($card->is_default);
        $this->assertNotNull($card->removed_at);
        $this->assertFalse($card->isChargeable());
        $this->assertNull(ClientPaymentMethod::defaultFor($this->client()->id));
    }

    public function test_one_clients_cards_are_not_anothers(): void
    {
        $mine = $this->card();
        $theirs = $this->card(['client_id' => $this->client('other@example.test')->id]);

        $this->assertSame(
            [$mine->id],
            ClientPaymentMethod::withoutGlobalScopes()
                ->forClient($this->client()->id)->pluck('id')->all(),
        );
        $this->assertNotSame($mine->client_id, $theirs->client_id);
    }

    // --------------------------------------------------- which gateways vault

    /* Cash cannot be charged again next month, and neither can a card taken
       on the terminal beside the till. */
    public function test_a_recorder_is_not_a_vault(): void
    {
        $this->assertNotInstanceOf(VaultsCards::class, app(ManualGateway::class));
        $this->assertInstanceOf(VaultsCards::class, app(StripeGateway::class));
    }

    public function test_a_business_with_no_processor_has_nowhere_to_keep_a_card(): void
    {
        /* Every screen that offers Card on File has to be able to find this
           out rather than failing at the last step. */
        $this->assertNull(app(PaymentGatewayManager::class)->vault($this->tenant));
    }

    // -------------------------------------------------------- what uses it

    public function test_a_membership_points_at_the_card_that_renews_it(): void
    {
        $location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'recurring', 'name' => 'Monthly Massage Membership',
            'price_minor' => 7900, 'billing_frequency' => 'monthly',
            'location_mode' => 'all', 'sell_in_store' => true, 'is_draft' => false,
        ]);

        $card = $this->card();

        $membership = ClientMembership::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $this->client()->id,
            'membership_plan_id' => $plan->id,
            'location_id' => $location->id,
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

        $this->assertSame($card->id, $membership->payment_method_id);
        $this->assertTrue($card->memberships()->whereKey($membership->id)->exists());

        /* Losing the card must not take the membership with it — it must make
           the next renewal ask for a new one. */
        $card->delete();

        $this->assertNotNull($membership->fresh());
        $this->assertNull($membership->fresh()->payment_method_id);
    }
}
