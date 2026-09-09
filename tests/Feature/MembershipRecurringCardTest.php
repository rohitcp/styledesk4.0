<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPaymentMethod;
use App\Models\Location;
use App\Models\MembershipPlan;
use App\Models\MembershipSettings;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Selling a membership that renews, and the card it renews on.
 *
 * The rule underneath all of it: a subscription with no card is one the
 * client believes they have and the business cannot charge for. It is refused
 * at the point of sale rather than discovered on the first billing date.
 *
 * And the card is always a reference. Nothing in this file — or in the
 * request it describes — carries a card number, because the number never
 * reaches StyleDesk.
 */
class MembershipRecurringCardTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-09';

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY.' 10:00:00');

        $this->tenant = Tenant::create(['name' => 'Acme Spa', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
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
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
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

    private function service(): Service
    {
        return Service::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Swedish Massage'],
            ['duration_minutes' => 60, 'is_active' => true],
        );
    }

    private function settings(): MembershipSettings
    {
        return MembershipSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            ['is_enabled' => true] + MembershipSettings::defaults(),
        );
    }

    private function plan(array $overrides = []): MembershipPlan
    {
        $plan = MembershipPlan::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'recurring',
            'name' => 'Monthly Massage Membership',
            'price_minor' => 7900,
            'billing_frequency' => 'monthly',
            'location_mode' => 'all',
            'sell_in_store' => true,
            'is_draft' => false,
        ]);

        $plan->planServices()->create([
            'service_id' => $this->service()->id, 'quantity' => 1, 'position' => 0,
        ]);

        return $plan->fresh('planServices');
    }

    /** A card already in the vault — a reference, never a number. */
    private function card(array $overrides = []): ClientPaymentMethod
    {
        return ClientPaymentMethod::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $this->client()->id,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test',
            'gateway_payment_method_id' => 'pm_'.uniqid(),
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 8,
            'exp_year' => 2029,
            'status' => 'active',
        ]);
    }

    private function payload(MembershipPlan $plan, array $overrides = []): array
    {
        return $overrides + [
            'membership_plan_id' => $plan->id,
            'client_id' => $this->client()->id,
            'location_id' => $this->location->id,
            'starts_on' => self::TODAY,
            'payment_method' => 'card',
        ];
    }

    private function sold(): ClientMembership
    {
        return ClientMembership::withoutGlobalScopes()->firstOrFail();
    }

    // -------------------------------------------------------- the renewal

    public function test_a_recurring_membership_is_sold_renewing_on_the_chosen_card(): void
    {
        $this->settings();
        $plan = $this->plan();
        $card = $this->card();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, [
                'auto_renew' => 1,
                'card_id' => $card->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $membership = $this->sold();

        $this->assertTrue((bool) $membership->auto_renew);
        $this->assertSame($card->id, $membership->payment_method_id);
        $this->assertSame('2026-10-09', $membership->next_billing_on->toDateString());
    }

    /* A subscription with no card is one the client believes they have and
       the business cannot charge for. */
    public function test_a_renewing_membership_without_a_card_is_refused(): void
    {
        $this->settings();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['auto_renew' => 1]))
            ->assertSessionHasErrors('card_id');

        $this->assertSame(0, ClientMembership::withoutGlobalScopes()->count());
    }

    public function test_a_recurring_plan_can_be_sold_as_a_one_off(): void
    {
        $this->settings();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['auto_renew' => 0]))
            ->assertSessionHasNoErrors();

        $membership = $this->sold();

        $this->assertFalse((bool) $membership->auto_renew);
        $this->assertNull($membership->payment_method_id);
        /* Nothing renews, so there is no next billing date — not "none yet",
           a question that no longer applies. */
        $this->assertNull($membership->next_billing_on);
    }

    /* A package with auto-renew on is a subscription to something that has
       already finished. */
    public function test_a_package_never_renews_whatever_arrives(): void
    {
        $this->settings();
        $plan = $this->plan(['type' => 'package', 'name' => 'Massage Package', 'billing_frequency' => null]);
        $card = $this->card();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, [
                'auto_renew' => 1,
                'card_id' => $card->id,
                'payment_method' => 'cash',
            ]))
            ->assertSessionHasNoErrors();

        $membership = $this->sold();

        $this->assertFalse((bool) $membership->auto_renew);
        $this->assertNull($membership->payment_method_id);
        $this->assertNull($membership->next_billing_on);
    }

    // ----------------------------------------------------------- the card

    /* An id in a form is a number a reader can change. */
    public function test_a_membership_cannot_renew_on_another_clients_card(): void
    {
        $this->settings();
        $plan = $this->plan();
        $theirs = $this->card(['client_id' => $this->client('other@example.test')->id]);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, [
                'auto_renew' => 1,
                'card_id' => $theirs->id,
            ]))
            ->assertSessionHasErrors('card_id');

        $this->assertSame(0, ClientMembership::withoutGlobalScopes()->count());
    }

    /* A renewal on an expired card fails on the day it matters, so the sale
       is refused on the day it can still be fixed. */
    public function test_an_expired_card_cannot_be_sold_against(): void
    {
        $this->settings();
        $plan = $this->plan();
        $expired = $this->card(['exp_month' => 8, 'exp_year' => 2026]);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, [
                'auto_renew' => 1,
                'card_id' => $expired->id,
            ]))
            ->assertSessionHasErrors('card_id');
    }

    public function test_a_removed_card_cannot_be_sold_against(): void
    {
        $this->settings();
        $plan = $this->plan();
        $card = $this->card();
        $card->markRemoved();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, [
                'auto_renew' => 1,
                'card_id' => $card->id,
            ]))
            ->assertSessionHasErrors('card_id');
    }

    // ------------------------------------------------------ what the screen sees

    public function test_the_screen_is_told_there_is_nowhere_to_keep_a_card(): void
    {
        $this->settings();
        $this->plan();

        $content = $this->actingAs($this->owner())
            ->get(route('bookings.create'))
            ->assertOk()
            ->getContent();

        preg_match('/data-props=\'(.*?)\'/s', $content, $matches);
        $props = json_decode(html_entity_decode($matches[1] ?? '{}'), true);

        /* No processor connected in this business, so Card on File says so
           rather than offering a button that fails when pressed. */
        $this->assertFalse($props['cardVault']['available']);
        $this->assertNull($props['cardVault']['gateway']);
    }

    public function test_a_client_with_no_processor_is_offered_no_cards(): void
    {
        $this->settings();
        $this->card();

        $context = $this->actingAs($this->owner())
            ->getJson(route('bookings.clients.context', ['client' => $this->client()->id]))
            ->assertOk()
            ->json();

        /* There is nowhere to charge them again, so offering the card would
           be offering a renewal that cannot run. */
        $this->assertSame([], $context['cards']);
    }

    public function test_saving_a_card_needs_a_processor(): void
    {
        $this->actingAs($this->owner())
            ->postJson(route('client-cards.setup', ['client' => $this->client()->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', __('payments.methods_list.no_vault'));
    }
}
