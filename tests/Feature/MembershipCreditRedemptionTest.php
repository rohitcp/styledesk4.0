<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Location;
use App\Models\MembershipCredit;
use App\Models\MembershipCreditRedemption;
use App\Models\MembershipPlan;
use App\Models\MembershipSettings;
use App\Models\ReasonCode;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\MembershipCredits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Spending a membership credit on a booking.
 *
 * What these guard is that a credit is a promise both ways. The client is not
 * charged for work they already paid for; the business does not hand out a
 * credit that was never there, nor keep one for a visit it never delivered.
 *
 * The arithmetic order matters and is checked: credits come off before the
 * coupon, because a percentage taken off work the client is not paying for is
 * a discount on nothing.
 */
class MembershipCreditRedemptionTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-09';

    private Tenant $tenant;

    private Location $location;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY.' 09:00:00');
        Queue::fake();

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

        $this->staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Susan', 'last_name' => 'Pena', 'email' => 'susan@acme.test',
            'role' => 'service-provider', 'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------ fixtures

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

    /** An $80 service, priced the way the booking screen prices one. */
    private function service(string $name = 'Massage', int $priceMinor = 8000): Service
    {
        $service = Service::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => $name],
            ['duration_minutes' => 60, 'is_active' => true],
        );

        /* One row per currency, holding both prices: the card price and the
           cash price are two columns, not two rows. */
        ServicePrice::withoutGlobalScopes()->firstOrCreate(
            ['service_id' => $service->id, 'currency_code' => 'USD'],
            ['price_minor' => $priceMinor, 'cash_price_minor' => $priceMinor],
        );

        return $service->fresh('prices');
    }

    /** A client holding `$quantity` credits for a service. */
    private function membershipFor(Service $service, int $quantity = 1, array $creditOverrides = []): ClientMembership
    {
        MembershipSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            ['is_enabled' => true] + MembershipSettings::defaults(),
        );

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'recurring', 'name' => 'Monthly Massage Membership',
            'price_minor' => 7900, 'billing_frequency' => 'monthly',
            'location_mode' => 'all', 'sell_in_store' => true, 'is_draft' => false,
        ]);

        $membership = ClientMembership::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $this->client()->id,
            'membership_plan_id' => $plan->id,
            'location_id' => $this->location->id,
            'status' => 'active',
            'starts_on' => self::TODAY,
            'type' => 'recurring',
            'price_minor' => 7900,
            'currency_code' => 'USD',
            'billing_frequency' => 'monthly',
        ]);

        MembershipCredit::withoutGlobalScopes()->create($creditOverrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_membership_id' => $membership->id,
            'service_id' => $service->id,
            'quantity_granted' => $quantity,
            'quantity_used' => 0,
        ]);

        return $membership->fresh('credits');
    }

    /** Everything a booking posts. */
    private function bookingPayload(array $serviceIds, array $overrides = []): array
    {
        return $overrides + [
            'client_id' => $this->client()->id,
            'staff_id' => $this->staff->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-15',
            'starts_at' => '11:00',
            'services' => $serviceIds,
            'payment_method' => 'card',
            'duplicate_ack' => true,
        ];
    }

    private function credit(): MembershipCredit
    {
        return MembershipCredit::withoutGlobalScopes()->firstOrFail();
    }

    // ------------------------------------------------------------- the quote

    public function test_the_quote_offers_a_credit_the_client_holds(): void
    {
        $service = $this->service();
        $this->membershipFor($service);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$service->id],
                'client_id' => $this->client()->id,
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey($service->id, $quote['membership_offers']);
        $this->assertSame(1, $quote['membership_offers'][$service->id]['remaining']);
        $this->assertSame('Monthly Massage Membership', $quote['membership_offers'][$service->id]['membership_name']);
        /* Offered, not applied. Whether to spend it today is the client's
           decision, and the desk asks it out loud. */
        $this->assertSame(0, $quote['membership_credit_minor']);
        $this->assertSame(8000, $quote['total_minor']);
    }

    public function test_applying_a_credit_takes_the_whole_line_off_the_bill(): void
    {
        $service = $this->service();
        $this->membershipFor($service);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$service->id],
                'client_id' => $this->client()->id,
                'membership_credits' => [$service->id],
            ])
            ->assertOk()
            ->json();

        /* Service $80, membership credit −$80, amount due $0. */
        $this->assertSame(8000, $quote['subtotal_minor']);
        $this->assertSame(8000, $quote['membership_credit_minor']);
        $this->assertSame(0, $quote['total_minor']);
    }

    public function test_a_credit_the_client_does_not_hold_is_ignored(): void
    {
        $service = $this->service();

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$service->id],
                'client_id' => $this->client()->id,
                'membership_credits' => [$service->id],
            ])
            ->assertOk()
            ->json();

        $this->assertSame([], $quote['membership_covered']);
        $this->assertSame(0, $quote['membership_credit_minor']);
        $this->assertSame(8000, $quote['total_minor']);
    }

    /* Only the line it was asked for. A client holding two credits booking
       one massage spends one; the other stays on their membership. */
    public function test_a_second_credit_is_not_spent_on_a_line_that_is_not_there(): void
    {
        $service = $this->service();
        $this->membershipFor($service, quantity: 2);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$service->id],
                'client_id' => $this->client()->id,
                'membership_credits' => [$service->id],
            ])
            ->assertOk()
            ->json();

        $this->assertSame(8000, $quote['membership_credit_minor']);
        $this->assertSame(0, $quote['total_minor']);

        $this->actingAs($this->owner())
            ->post(route('bookings.store'), $this->bookingPayload([$service->id], [
                'membership_credits' => [$service->id],
            ]));

        $this->assertSame(1, $this->credit()->quantity_used);
        $this->assertSame(1, $this->credit()->remaining());
    }

    /* One credit covers one service; the rest of the booking is charged. */
    public function test_only_the_covered_service_leaves_the_bill(): void
    {
        $massage = $this->service();
        $facial = $this->service('Facial', 10000);
        $this->membershipFor($massage);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$massage->id, $facial->id],
                'client_id' => $this->client()->id,
                'membership_credits' => [$massage->id],
            ])
            ->assertOk()
            ->json();

        $this->assertSame(18000, $quote['subtotal_minor']);
        $this->assertSame(8000, $quote['membership_credit_minor']);
        $this->assertSame(10000, $quote['total_minor']);
    }

    /* A percentage taken off work the client is not paying for is a discount
       on nothing, so the credits come off first. */
    public function test_a_coupon_applies_only_to_what_is_left_to_pay(): void
    {
        $massage = $this->service();
        $facial = $this->service('Facial', 10000);
        $this->membershipFor($massage);

        $this->actingAs($this->owner())->post(route('promotions.store'), [
            'name' => 'Ten off', 'type' => 'coupon', 'code' => 'TEN',
            'discount_type' => 'percent', 'discount_value' => 10,
            'applies_to' => 'all_services', 'location_mode' => 'all',
            'eligibility' => 'all', 'starts_on' => self::TODAY,
            'no_expiry' => 1,
        ]);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$massage->id, $facial->id],
                'client_id' => $this->client()->id,
                'membership_credits' => [$massage->id],
                'coupon' => 'TEN',
            ])
            ->assertOk()
            ->json();

        $this->assertSame(18000, $quote['subtotal_minor']);
        $this->assertSame(8000, $quote['membership_credit_minor']);
        /* Ten per cent of the £100 facial, not of the £180 booking. */
        $this->assertSame(1000, $quote['discount_minor']);
        $this->assertSame(9000, $quote['total_minor']);
    }

    // ------------------------------------------------------------- the save

    public function test_confirming_the_booking_holds_the_credit_down(): void
    {
        $service = $this->service();
        $this->membershipFor($service);

        $this->actingAs($this->owner())
            ->post(route('bookings.store'), $this->bookingPayload([$service->id], [
                'membership_credits' => [$service->id],
            ]))
            ->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(8000, $booking->subtotal_minor);
        $this->assertSame(8000, $booking->membership_credit_minor);
        /* A coupon is the business giving money away and a credit is the
           client spending something they already bought. */
        $this->assertSame(0, $booking->discount_minor);
        $this->assertSame(0, $booking->total_minor);

        $this->assertSame(1, $this->credit()->quantity_used);
        $this->assertSame(0, $this->credit()->remaining());

        $redemption = MembershipCreditRedemption::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($booking->id, $redemption->booking_id);
        $this->assertSame(8000, $redemption->value_minor);
        $this->assertNull($redemption->released_at);
    }

    public function test_a_credit_spent_between_the_quote_and_the_save_is_simply_paid_for(): void
    {
        $service = $this->service();
        $this->membershipFor($service);

        /* Somebody else used it while this booking was being filled in. */
        $this->credit()->update(['quantity_used' => 1]);

        $this->actingAs($this->owner())
            ->post(route('bookings.store'), $this->bookingPayload([$service->id], [
                'membership_credits' => [$service->id],
            ]))
            ->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        /* The appointment is the thing that matters; the line is charged. */
        $this->assertSame(0, $booking->membership_credit_minor);
        $this->assertSame(8000, $booking->total_minor);
        $this->assertSame(0, MembershipCreditRedemption::withoutGlobalScopes()->count());
    }

    public function test_a_draft_holds_no_credit(): void
    {
        $service = $this->service();
        $this->membershipFor($service);

        $this->actingAs($this->owner())
            ->post(route('bookings.store'), $this->bookingPayload([$service->id], [
                'membership_credits' => [$service->id],
                'draft' => 1,
            ]));

        /* A booking promised to nobody has not used anything yet, and a
           credit held against one somebody abandons is a massage the client
           cannot book. */
        $this->assertSame(0, $this->credit()->quantity_used);
        $this->assertSame(0, MembershipCreditRedemption::withoutGlobalScopes()->count());
    }

    // ---------------------------------------------------------- giving back

    public function test_cancelling_the_visit_gives_the_credit_back(): void
    {
        $service = $this->service();
        $this->membershipFor($service);

        $this->actingAs($this->owner())
            ->post(route('bookings.store'), $this->bookingPayload([$service->id], [
                'membership_credits' => [$service->id],
            ]));

        $booking = Booking::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(1, $this->credit()->quantity_used);

        $reason = ReasonCode::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'booking-cancellation', 'key' => 'client-cancelled',
            'name' => 'Client cancelled', 'is_active' => true,
        ]);

        $this->actingAs($this->owner())
            ->post(route('bookings.cancel', $booking), ['reason_code_id' => $reason->id])
            ->assertSessionHasNoErrors();

        /* A client whose visit was called off has not used their massage. */
        $this->assertSame(0, $this->credit()->quantity_used);
        $this->assertNotNull(MembershipCreditRedemption::withoutGlobalScopes()->firstOrFail()->released_at);
    }

    public function test_releasing_twice_does_not_hand_the_credit_back_twice(): void
    {
        $service = $this->service();
        $this->membershipFor($service);

        $this->actingAs($this->owner())
            ->post(route('bookings.store'), $this->bookingPayload([$service->id], [
                'membership_credits' => [$service->id],
            ]));

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(1, MembershipCredits::release($booking));
        /* The status handlers this hangs off can fire more than once. */
        $this->assertSame(0, MembershipCredits::release($booking));
        $this->assertSame(0, $this->credit()->quantity_used);
    }

    // -------------------------------------------------------- what is offered

    public function test_an_expired_credit_is_not_offered(): void
    {
        $service = $this->service();
        $this->membershipFor($service, creditOverrides: ['expires_on' => '2026-09-01']);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$service->id],
                'client_id' => $this->client()->id,
            ])
            ->json();

        $this->assertSame([], $quote['membership_offers']);
    }

    public function test_a_scheduled_membership_offers_nothing_until_it_starts(): void
    {
        $service = $this->service();
        $membership = $this->membershipFor($service);
        $membership->update(['status' => 'scheduled', 'starts_on' => '2026-10-01']);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$service->id],
                'client_id' => $this->client()->id,
            ])
            ->json();

        /* Its credits do not exist until the start date arrives. */
        $this->assertSame([], $quote['membership_offers']);
    }

    public function test_a_cancelled_membership_offers_nothing(): void
    {
        $service = $this->service();
        $membership = $this->membershipFor($service);
        $membership->update(['status' => 'cancelled']);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), [
                'services' => [$service->id],
                'client_id' => $this->client()->id,
            ])
            ->json();

        $this->assertSame([], $quote['membership_offers']);
    }

    public function test_a_walk_in_with_no_client_is_offered_nothing(): void
    {
        $service = $this->service();
        $this->membershipFor($service);

        $quote = $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), ['services' => [$service->id]])
            ->json();

        $this->assertSame([], $quote['membership_offers']);
    }
}
