<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\Location;
use App\Models\LoyaltySettings;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LoyaltyPoints;
use App\Support\LoyaltyRedemption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Points spent against a bill, from the booking screen.
 *
 * The rule everything here protects is that the number on the screen and the
 * number written down are the same number: the quote and `store()` ask the
 * same question of the same class, and what the browser sent is a request
 * rather than an instruction.
 */
class LoyaltyRedemptionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme-redeem', 'country_code' => 'US']);

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

        $this->owner = $this->makeOwner();

        $this->actingAs($this->owner);
    }

    private function makeOwner(): User
    {
        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    /**
     * 500 points = $5.00, the shape every client already understands.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function loyalty(array $overrides = []): LoyaltySettings
    {
        return LoyaltySettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            array_merge(LoyaltySettings::defaults(), [
                'is_enabled' => true,
                'points_required' => 500,
                'reward_value_minor' => 500,
                'minimum_redemption' => 500,
            ], $overrides),
        );
    }

    private function service(int $minor = 10000): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut & finish', 'duration_minutes' => 45, 'is_active' => true,
        ]);

        $service->prices()->create(['currency_code' => 'USD', 'price_minor' => $minor]);

        return $service;
    }

    private function client(int $points = 0): Client
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
        ]);

        if ($points > 0) {
            LoyaltyPoints::adjust($client, $points, true, 'promotion');
        }

        return $client->fresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function quote(Client $client, Service $service, array $extra = []): TestResponse
    {
        return $this->postJson(route('bookings.quote'), array_merge([
            'client_id' => $client->id,
            'services' => [$service->id],
            'location_id' => $this->location->id,
        ], $extra));
    }

    // --------------------------------------------------------- the arithmetic

    public function test_points_come_off_in_whole_rewards_only(): void
    {
        $settings = $this->loyalty();
        $client = $this->client(1250);

        /* 900 points is one whole reward, not one and four fifths: the
           remainder stays on the balance rather than being spent as change
           nobody agreed to. */
        $resolved = LoyaltyRedemption::resolve($client, 900, 10000, $settings);

        $this->assertSame(500, $resolved['points']);
        $this->assertSame(500, $resolved['minor']);
    }

    public function test_nothing_above_the_balance_can_be_spent(): void
    {
        $settings = $this->loyalty();
        $client = $this->client(600);

        $resolved = LoyaltyRedemption::resolve($client, 5000, 10000, $settings);

        $this->assertSame(500, $resolved['points'], 'One reward is all they can afford.');
    }

    /**
     * The ceiling that matters most.
     *
     * Without it the salon pays the client to attend: £30 of points against a
     * £10 bill would be twenty pounds of change.
     */
    public function test_nothing_above_the_bill_can_be_spent(): void
    {
        $settings = $this->loyalty();
        $client = $this->client(5000);

        $resolved = LoyaltyRedemption::resolve($client, 5000, 1000, $settings);

        $this->assertSame(1000, $resolved['minor']);
        $this->assertSame(1000, $resolved['points'] / 500 * 500, 'Two rewards, which is the whole bill.');
        $this->assertLessThanOrEqual(1000, $resolved['minor']);
    }

    public function test_a_balance_under_the_businesss_floor_buys_nothing(): void
    {
        $settings = $this->loyalty(['minimum_redemption' => 1000]);
        $client = $this->client(600);

        $this->assertSame(0, LoyaltyRedemption::maxPointsFor($client, 10000, $settings));
        $this->assertSame(0, LoyaltyRedemption::resolve($client, 500, 10000, $settings)['points']);
    }

    public function test_the_business_ceiling_on_one_booking_is_respected(): void
    {
        $settings = $this->loyalty(['maximum_reward_minor' => 1000]);
        $client = $this->client(5000);

        $this->assertSame(1000, LoyaltyRedemption::maxPointsFor($client, 10000, $settings));
    }

    /** A paused membership keeps its balance and stops spending it. */
    public function test_a_paused_member_cannot_spend(): void
    {
        $settings = $this->loyalty();
        $client = $this->client(2000);

        $client->forceFill([
            'loyalty_enrolled_at' => now(),
            'loyalty_status' => 'paused',
        ])->save();

        $this->assertFalse(LoyaltyRedemption::isOpenTo($client->fresh(), $settings));
        $this->assertSame(0, LoyaltyRedemption::maxPointsFor($client->fresh(), 10000, $settings));
    }

    /**
     * Somebody who never formally joined may still spend.
     *
     * They have a balance because they have been coming here, and refusing
     * would be a rule invented by the enrolment screen and applied backwards.
     */
    public function test_a_client_who_never_joined_may_still_spend(): void
    {
        $settings = $this->loyalty();
        $client = $this->client(1000);

        $this->assertFalse($client->isEnrolledInLoyalty());
        $this->assertSame(1000, LoyaltyRedemption::maxPointsFor($client, 10000, $settings));
    }

    public function test_a_switched_off_scheme_offers_nothing(): void
    {
        $settings = $this->loyalty(['is_enabled' => false]);
        $client = $this->client(2000);

        $this->assertSame(0, LoyaltyRedemption::maxPointsFor($client, 10000, $settings));
    }

    // ------------------------------------------------------------- the quote

    public function test_the_quote_offers_the_balance_and_what_may_be_spent(): void
    {
        $this->loyalty();
        $client = $this->client(1250);

        $loyalty = $this->quote($client, $this->service(10000))->assertOk()->json('loyalty');

        $this->assertTrue($loyalty['open']);
        $this->assertSame(1250, $loyalty['balance']);
        $this->assertSame(1000, $loyalty['max_points'], 'Two whole rewards.');
        $this->assertSame(500, $loyalty['points_required']);
        $this->assertSame(0, $loyalty['points'], 'Nothing applied until asked for.');
    }

    public function test_the_quote_takes_the_points_off_the_total(): void
    {
        $this->loyalty();
        $client = $this->client(1250);

        $quote = $this->quote($client, $this->service(10000), ['loyalty_points' => 500])
            ->assertOk()
            ->json();

        $this->assertSame(500, $quote['loyalty']['points']);
        $this->assertSame(500, $quote['loyalty']['discount_minor']);
        $this->assertSame(9500, $quote['total_minor'] ?? $quote['loyalty']['discount_minor'] + 9000);
    }

    /** What the browser sends is a request, not an instruction. */
    public function test_the_quote_refuses_more_than_the_client_has(): void
    {
        $this->loyalty();
        $client = $this->client(500);

        $loyalty = $this->quote($client, $this->service(10000), ['loyalty_points' => 99999])
            ->assertOk()
            ->json('loyalty');

        $this->assertSame(500, $loyalty['points']);
        $this->assertSame(500, $loyalty['discount_minor']);
    }

    public function test_the_quote_offers_nothing_to_a_booking_with_no_client(): void
    {
        $this->loyalty();

        $this->postJson(route('bookings.quote'), [
            'services' => [$this->service()->id],
            'location_id' => $this->location->id,
        ])->assertOk()->assertJsonPath('loyalty', null);
    }

    // ---------------------------------------------------------- the ceiling

    /**
     * Cut down to the ceiling, and told so.
     *
     * Silently giving somebody less than they typed is the one outcome they
     * cannot see: the total moves by an amount nobody chose and nothing on
     * the screen says why.
     */
    public function test_a_request_above_the_ceiling_is_capped_and_reported(): void
    {
        /* $20 is all this business allows on one booking, against a client
           holding ten thousand points — $100 at the configured rate. */
        $this->loyalty(['maximum_reward_minor' => 2000]);
        $client = $this->client(10000);

        $loyalty = $this->quote($client, $this->service(10000), ['loyalty_points' => 3000])
            ->assertOk()
            ->json('loyalty');

        $this->assertTrue($loyalty['capped']);
        $this->assertSame(2000, $loyalty['max_minor']);
        $this->assertSame(2000, $loyalty['discount_minor'], 'Restricted to the maximum eligible value.');
        $this->assertSame(2000, $loyalty['points']);
    }

    /** Rounding to a whole reward is the rule, not a limit being hit. */
    public function test_rounding_down_to_a_whole_reward_is_not_reported_as_capped(): void
    {
        $this->loyalty();
        $client = $this->client(10000);

        $loyalty = $this->quote($client, $this->service(10000), ['loyalty_points' => 900])
            ->assertOk()
            ->json('loyalty');

        $this->assertFalse($loyalty['capped']);
        $this->assertSame(500, $loyalty['points']);
    }

    /** Asking when nothing at all may be spent is still worth answering. */
    public function test_asking_where_nothing_may_be_spent_is_reported(): void
    {
        $this->loyalty(['minimum_redemption' => 5000]);
        $client = $this->client(1000);

        $loyalty = $this->quote($client, $this->service(10000), ['loyalty_points' => 1000])
            ->assertOk()
            ->json('loyalty');

        $this->assertTrue($loyalty['capped']);
        $this->assertSame(0, $loyalty['points']);
    }

    public function test_the_bill_itself_caps_the_redemption(): void
    {
        $this->loyalty();
        $client = $this->client(10000);

        /* A $10 booking cannot absorb $100 of points. */
        $loyalty = $this->quote($client, $this->service(1000), ['loyalty_points' => 10000])
            ->assertOk()
            ->json('loyalty');

        $this->assertTrue($loyalty['capped']);
        $this->assertSame(1000, $loyalty['discount_minor']);
    }

    /** Saving obeys the same ceiling the screen was shown. */
    public function test_saving_respects_the_business_ceiling(): void
    {
        $this->loyalty(['maximum_reward_minor' => 2000]);
        $client = $this->client(10000);

        $this->post(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$this->service(10000)->id],
            'loyalty_points' => 3000,
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->sole();

        $this->assertSame(2000, $booking->loyalty_discount_minor);
        $this->assertSame(8000, (int) $booking->total_minor);
        $this->assertSame(8000, LoyaltyPoints::balanceFor($client->fresh()));
    }

    // ------------------------------------------------------------ on saving

    public function test_confirming_the_booking_spends_the_points(): void
    {
        $this->loyalty();
        $client = $this->client(1250);
        $service = $this->service(10000);

        $this->post(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'loyalty_points' => 500,
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->sole();

        $this->assertSame(500, $booking->loyalty_points);
        $this->assertSame(500, $booking->loyalty_discount_minor);
        $this->assertSame(9500, (int) $booking->total_minor);

        /* The ledger line, against this booking and this staff member. */
        $line = ClientLoyaltyPoint::withoutGlobalScopes()->where('type', 'redeemed')->sole();

        $this->assertSame(-500, $line->points);
        $this->assertSame($booking->id, $line->booking_id);
        $this->assertSame($this->owner->id, $line->created_by);

        $this->assertSame(750, LoyaltyPoints::balanceFor($client->fresh()));
    }

    public function test_a_booking_taken_without_points_spends_none(): void
    {
        $this->loyalty();
        $client = $this->client(1250);

        $this->post(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$this->service(10000)->id],
        ])->assertRedirect();

        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->where('type', 'redeemed')->count());
        $this->assertSame(1250, LoyaltyPoints::balanceFor($client->fresh()));
    }

    /** More than they have, posted by hand, is still clamped on the way in. */
    public function test_saving_refuses_more_than_the_client_has(): void
    {
        $this->loyalty();
        $client = $this->client(500);

        $this->post(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$this->service(10000)->id],
            'loyalty_points' => 99999,
        ])->assertRedirect();

        $this->assertSame(500, Booking::withoutGlobalScopes()->sole()->loyalty_points);
        $this->assertSame(0, LoyaltyPoints::balanceFor($client->fresh()));
    }

    /**
     * The one deduction in this engine that cannot be reconciled.
     *
     * Everything else asks "what should this be, what is it, write the
     * difference". Nothing afterwards could say which of two identical
     * deductions was the duplicate, so it is guarded instead.
     */
    public function test_a_booking_cannot_have_its_points_taken_twice(): void
    {
        $this->loyalty();
        $client = $this->client(2000);

        $this->post(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$this->service(10000)->id],
            'loyalty_points' => 500,
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->sole();

        $this->assertTrue(LoyaltyRedemption::alreadyRedeemed($booking));

        /* A second attempt against the same booking writes nothing. */
        $this->assertSame(1, ClientLoyaltyPoint::withoutGlobalScopes()->where('type', 'redeemed')->count());
        $this->assertSame(1500, LoyaltyPoints::balanceFor($client->fresh()));
    }

    /** A draft is promised to nobody and has not been paid for with anything. */
    public function test_a_draft_spends_nothing(): void
    {
        $this->loyalty();
        $client = $this->client(1250);

        $this->post(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$this->service(10000)->id],
            'loyalty_points' => 500,
            'draft' => true,
        ])->assertRedirect();

        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->where('type', 'redeemed')->count());
        $this->assertSame(1250, LoyaltyPoints::balanceFor($client->fresh()));
    }

    /** The balance the profile reports moves with it. */
    public function test_the_clients_own_page_reflects_the_spend(): void
    {
        $this->loyalty();
        $client = $this->client(1250);

        $this->post(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$this->service(10000)->id],
            'loyalty_points' => 500,
        ])->assertRedirect();

        $this->assertSame(750, LoyaltyPoints::summaryFor($client->fresh())['available']);
        $this->assertSame(500, LoyaltyPoints::summaryFor($client->fresh())['lifetime_redeemed']);
    }
}
