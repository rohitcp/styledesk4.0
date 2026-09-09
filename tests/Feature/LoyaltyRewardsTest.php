<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\Location;
use App\Models\LoyaltySettings;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LoyaltyPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Points earned, spent, taken back and given by hand.
 *
 * What these are really guarding is the two ways a loyalty scheme loses a
 * business money: awarding twice for one visit, and leaving points on a
 * booking that was refunded. Both are failures of the same thing — the engine
 * reconciles rather than accumulates — so most of what follows calls it more
 * than once and checks the balance did not move.
 */
class LoyaltyRewardsTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-10-12';

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 14:00:00');
        Queue::fake();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
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

    // ----------------------------------------------------------- the set-up

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

    private function loyaltyOn(array $overrides = []): LoyaltySettings
    {
        return LoyaltySettings::create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'is_enabled' => true,
            'program_name' => 'Glow Rewards',
            'spend_amount' => 1,
            'points_earned' => 1,
            'eligible_purchases' => ['services'],
            'points_required' => 500,
            'reward_value_minor' => 500,
            'minimum_redemption' => 500,
            'expiry' => 'never',
        ]);
    }

    private function client(): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
            'email' => 'mia@example.test', 'comm_email' => true,
        ]);
    }

    /** A $100 appointment, priced the way the booking screen prices one. */
    private function booking(Client $client, string $status = 'arrived', array $overrides = []): Booking
    {
        $staff = Staff::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'email' => 'susan@acme.test'],
            [
                'first_name' => 'Susan', 'last_name' => 'Pena', 'role' => 'service-provider',
                'location_id' => $this->location->id, 'is_active' => true, 'provides_services' => true,
            ],
        );

        $service = Service::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Cut'],
            ['duration_minutes' => 60, 'is_active' => true],
        );

        $booking = Booking::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'date' => self::DATE,
            'starts_at' => '11:30', 'ends_at' => '12:30', 'minutes' => 60,
            'status' => $status,
            'subtotal_minor' => 10000, 'total_minor' => 10000, 'currency_code' => 'USD',
        ]);

        $booking->services()->create([
            'service_id' => $service->id, 'name' => 'Cut',
            'minutes' => 60, 'price_minor' => 10000,
        ]);

        return $booking->fresh();
    }

    /** Money in the drawer, without going through a gateway. */
    private function pay(Booking $booking, int $amountMinor, string $status = 'paid'): void
    {
        $booking->payments()->create([
            'tenant_id' => $booking->tenant_id,
            'method' => 'cash',
            'status' => $status,
            'amount_minor' => $amountMinor,
            'currency_code' => 'USD',
            'paid_at' => now(),
        ]);

        $booking->load('payments');
        $booking->settlePaymentStatus();
    }

    private function balance(Client $client): int
    {
        return (int) ClientLoyaltyPoint::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->sum('points');
    }

    // ------------------------------------------------------------- earning

    public function test_a_completed_and_paid_appointment_earns_points(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'arrived');

        $this->actingAs($this->owner())
            ->post(route('bookings.complete', $booking))
            ->assertRedirect();

        $this->pay($booking->fresh(), 10000);

        $this->assertSame(100, $this->balance($client));

        $line = ClientLoyaltyPoint::withoutGlobalScopes()->firstWhere('client_id', $client->id);
        $this->assertSame('earned', $line->type);
        $this->assertSame(100, $line->balance_after);
        $this->assertSame($booking->id, $line->booking_id);
        $this->assertSame($this->location->id, $line->location_id);
    }

    public function test_a_completed_appointment_nobody_has_paid_for_earns_nothing(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'arrived');

        $this->actingAs($this->owner())->post(route('bookings.complete', $booking));

        /* The visit happened. The money did not, and §6 wants both. */
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(0, $this->balance($client));
    }

    public function test_money_taken_before_the_visit_is_finished_earns_nothing_until_it_is(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'arrived');

        $this->pay($booking, 10000);
        $this->assertSame(0, $this->balance($client), 'A deposit is not a delivered service.');

        $this->actingAs($this->owner())->post(route('bookings.complete', $booking));

        $this->assertSame(100, $this->balance($client));
    }

    public function test_a_part_paid_visit_earns_in_proportion_and_tops_up_when_the_rest_lands(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'arrived');

        $this->actingAs($this->owner())->post(route('bookings.complete', $booking));

        $this->pay($booking->fresh(), 4000);
        $this->assertSame(40, $this->balance($client));

        $this->pay($booking->fresh(), 6000);
        $this->assertSame(100, $this->balance($client));

        /* Two lines, not two full awards: the second wrote the difference. */
        $this->assertSame(2, ClientLoyaltyPoint::withoutGlobalScopes()->where('client_id', $client->id)->count());
    }

    public function test_settling_twice_does_not_award_twice(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'completed');

        $this->pay($booking, 10000);
        $this->assertSame(100, $this->balance($client));

        LoyaltyPoints::settle($booking->fresh());
        LoyaltyPoints::settle($booking->fresh());

        $this->assertSame(100, $this->balance($client));
        $this->assertSame(1, ClientLoyaltyPoint::withoutGlobalScopes()->where('client_id', $client->id)->count());
    }

    public function test_the_rate_and_the_discount_both_decide_what_a_visit_is_worth(): void
    {
        /* $5 spent earns 1 point, and the coupon comes off first: $100 less a
           $10 coupon is $90, which is 18 points and not 20. */
        $this->loyaltyOn(['spend_amount' => 5, 'points_earned' => 1]);
        $client = $this->client();
        $booking = $this->booking($client, 'completed', ['discount_minor' => 1000, 'total_minor' => 9000]);

        $this->pay($booking, 9000);

        $this->assertSame(18, $this->balance($client));
    }

    public function test_tips_and_taxes_earn_only_where_the_business_said_so(): void
    {
        $this->loyaltyOn(['eligible_purchases' => ['services', 'tips', 'taxes']]);
        $client = $this->client();
        $booking = $this->booking($client, 'completed', [
            'tax_minor' => 800, 'tip_minor' => 2000, 'total_minor' => 12800,
        ]);

        $this->pay($booking, 12800);

        $this->assertSame(128, $this->balance($client));
    }

    public function test_nothing_is_earned_while_the_scheme_is_off(): void
    {
        $client = $this->client();
        $booking = $this->booking($client, 'arrived');

        $this->actingAs($this->owner())->post(route('bookings.complete', $booking));
        $this->pay($booking->fresh(), 10000);

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(0, $this->balance($client));
    }

    // ------------------------------------------------------------- refunds

    public function test_a_full_refund_takes_every_point_back(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'completed');

        $this->pay($booking, 10000);
        $this->assertSame(100, $this->balance($client));

        $this->pay($booking->fresh(), 10000, 'refunded');

        $this->assertSame(0, $this->balance($client));

        $reversal = ClientLoyaltyPoint::withoutGlobalScopes()
            ->where('client_id', $client->id)->latest('id')->first();

        $this->assertSame('refund_adjustment', $reversal->type);
        $this->assertSame(-100, $reversal->points);
        $this->assertSame(0, $reversal->balance_after);
    }

    public function test_a_partial_refund_takes_the_same_proportion_back(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'completed');

        $this->pay($booking, 10000);
        $this->pay($booking->fresh(), 3000, 'refunded');

        /* $70 of $100 kept, so 70 of the 100 points stay. */
        $this->assertSame(70, $this->balance($client));
    }

    public function test_cancelling_a_finished_appointment_gives_the_points_back(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'completed');

        $this->pay($booking, 10000);
        $this->assertSame(100, $this->balance($client));

        $booking->fresh()->update(['status' => 'cancelled']);
        LoyaltyPoints::settle($booking->fresh());

        $this->assertSame(0, $this->balance($client));
        $this->assertSame(
            'cancellation_adjustment',
            ClientLoyaltyPoint::withoutGlobalScopes()->where('client_id', $client->id)->latest('id')->first()->type,
        );
    }

    // ------------------------------------------------------- pending points

    public function test_pending_points_are_read_from_the_diary_rather_than_written_down(): void
    {
        $settings = $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'confirmed');

        $this->assertSame(100, LoyaltyPoints::pendingFor($client, $settings));
        /* A forecast is not a ledger line. */
        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());

        $booking->update(['status' => 'cancelled']);

        $this->assertSame(0, LoyaltyPoints::pendingFor($client, $settings));
    }

    // -------------------------------------------------- manual adjustments

    public function test_a_manual_addition_is_recorded_against_the_person_who_made_it(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('clients.rewards.adjust', $client), [
                'direction' => 'add',
                'points' => 100,
                'reason' => 'customer_service',
                'note' => 'Kept waiting forty minutes.',
            ])
            ->assertRedirect();

        $line = ClientLoyaltyPoint::withoutGlobalScopes()->firstWhere('client_id', $client->id);

        $this->assertSame('manual_add', $line->type);
        $this->assertSame(100, $line->points);
        $this->assertSame('customer_service', $line->reason);
        $this->assertSame('Kept waiting forty minutes.', $line->note);
        $this->assertSame($owner->id, $line->created_by);
    }

    public function test_removing_points_is_stored_as_a_deduction_however_the_number_arrives(): void
    {
        $this->loyaltyOn();
        $client = $this->client();

        $this->actingAs($this->owner())
            ->post(route('clients.rewards.adjust', $client), [
                'direction' => 'remove',
                'points' => 40,
                'reason' => 'duplicate',
            ])
            ->assertRedirect();

        $line = ClientLoyaltyPoint::withoutGlobalScopes()->firstWhere('client_id', $client->id);

        $this->assertSame('manual_deduct', $line->type);
        $this->assertSame(-40, $line->points);
        /* A balance pushed under zero is nothing left, not a debt. */
        $this->assertSame(0, $line->balance_after);
    }

    public function test_points_cannot_be_adjusted_while_the_scheme_is_off(): void
    {
        $client = $this->client();

        $this->actingAs($this->owner())
            ->post(route('clients.rewards.adjust', $client), [
                'direction' => 'add', 'points' => 100, 'reason' => 'promotion',
            ])
            ->assertForbidden();

        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    // ----------------------------------------------------------- expiry

    public function test_expired_points_stop_counting_without_anything_sweeping_them(): void
    {
        $this->loyaltyOn(['expiry' => '6m']);
        $client = $this->client();
        $booking = $this->booking($client, 'completed');

        $this->pay($booking, 10000);
        $this->assertSame(100, LoyaltyPoints::balanceFor($client->fresh()));

        Carbon::setTestNow(now()->addMonths(7));

        $this->assertSame(0, LoyaltyPoints::balanceFor($client->fresh()));
        /* The line is still there. It was earned, and "lifetime earned" is a
           history rather than a balance. */
        $this->assertSame(1, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    // ----------------------------------------------------------- the screens

    public function test_the_settings_screen_saves_the_two_rules(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.loyalty.update'), [
                'is_enabled' => 1,
                'program_name' => 'Glow Rewards',
                'description' => 'Earn on every visit.',
                'spend_amount' => 5,
                'points_earned' => 2,
                'eligible_purchases' => ['tips'],
                'points_required' => 400,
                'reward_value' => '7.50',
                'minimum_redemption' => 400,
                'maximum_reward' => '25',
                'expiry' => '12m',
            ])
            ->assertRedirect();

        $settings = LoyaltySettings::withoutGlobalScopes()->first();

        $this->assertTrue($settings->is_enabled);
        $this->assertSame('Glow Rewards', $settings->program_name);
        $this->assertSame(5, $settings->spend_amount);
        $this->assertSame(2, $settings->points_earned);
        $this->assertSame(750, $settings->reward_value_minor);
        $this->assertSame(2500, $settings->maximum_reward_minor);
        $this->assertSame('12m', $settings->expiry);
        /* Services are always on: a scheme switched on with nothing earning
           reads as a bug rather than as a choice. */
        $this->assertContains('services', $settings->eligible_purchases);
        $this->assertContains('tips', $settings->eligible_purchases);
    }

    public function test_the_settings_screen_refuses_a_purchase_type_nothing_can_sell_yet(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.loyalty.update'), [
                'is_enabled' => 1,
                'program_name' => 'Rewards',
                'spend_amount' => 1,
                'points_earned' => 1,
                'eligible_purchases' => ['gift_cards'],
                'points_required' => 500,
                'reward_value' => '5',
                'minimum_redemption' => 500,
                'expiry' => 'never',
            ])
            ->assertSessionHasErrors('eligible_purchases.0');
    }

    public function test_the_client_profile_shows_the_balance_and_the_next_reward(): void
    {
        $this->loyaltyOn();
        $client = $this->client();
        $booking = $this->booking($client, 'completed');

        $this->pay($booking, 10000);

        $this->actingAs($this->owner())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee(__('clients.module.workspace.tabs.rewards'))
            ->assertSee(__('loyalty.client.available'))
            ->assertSee(__('loyalty.client.to_go', ['points' => '400', 'value' => '$5.00']));
    }
}
