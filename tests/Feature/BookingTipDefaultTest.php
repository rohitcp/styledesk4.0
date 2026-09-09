<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\TipSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tip the booking screen opens on.
 *
 * A business that configured 15/18/20/25 with 15 as its default meant the
 * screen to open on 15. Opening on nothing is the screen choosing No Tip on
 * the client's behalf — which is the one answer nobody asked for, and the bug
 * these guard.
 *
 * The other half is that a default is only a default: once somebody answers,
 * including by answering "none", their answer stands and is not quietly
 * overwritten by the next recalculation.
 */
class BookingTipDefaultTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile', 'currency_code' => 'USD']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Main', 'address_line1' => '1 Main St', 'city' => 'Hackensack',
            'postal_code' => '07601', 'country' => 'US', 'timezone' => 'America/New_York',
            'is_primary' => true,
        ]);
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

    /** A $100 service, so every percentage is a round number. */
    private function service(): Service
    {
        $service = Service::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Swedish Massage'],
            ['duration_minutes' => 60, 'is_active' => true],
        );

        ServicePrice::withoutGlobalScopes()->firstOrCreate(
            ['service_id' => $service->id, 'currency_code' => 'USD'],
            ['price_minor' => 10000, 'cash_price_minor' => 10000],
        );

        return $service->fresh('prices');
    }

    private function tips(array $overrides = []): TipSettings
    {
        return TipSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            $overrides + [
                'is_enabled' => true,
                'percentages' => [15, 18, 20, 25],
                'default_tip_type' => 'percent',
                'default_tip_value' => 15,
                'require_selection' => false,
                'allow_no_tip' => true,
            ],
        );
    }

    /** The quote as the screen asks for it. */
    private function quote(array $payload = []): array
    {
        return $this->actingAs($this->owner())
            ->postJson(route('bookings.quote'), $payload + [
                'services' => [$this->service()->id],
            ])
            ->assertOk()
            ->json();
    }

    // --------------------------------------------------------- the default

    public function test_the_configured_default_is_applied_before_anybody_answers(): void
    {
        $this->tips(['default_tip_value' => 15]);

        $quote = $this->quote();

        $this->assertSame(15, $quote['tip_percent']);
        $this->assertSame(1500, $quote['tip_minor']);
        /* And the total is the one the desk reads out. */
        $this->assertSame(11500, $quote['total_minor']);
    }

    public function test_the_screen_is_told_what_the_default_is(): void
    {
        $this->tips(['default_tip_value' => 18]);

        $quote = $this->quote();

        $this->assertSame(18, $quote['default_tip_percent']);
        $this->assertSame(1800, $quote['default_tip_minor']);
        $this->assertSame([15, 18, 20, 25], $quote['tip_percentages']);
        $this->assertFalse($quote['tip_chosen']);
    }

    public function test_a_fixed_default_comes_back_as_an_amount_with_no_percentage(): void
    {
        $this->tips(['default_tip_type' => 'fixed', 'default_tip_value' => 7]);

        $quote = $this->quote();

        /* Nothing to light a chip with; it belongs in the custom box. */
        $this->assertNull($quote['default_tip_percent']);
        $this->assertSame(700, $quote['default_tip_minor']);
        $this->assertSame(700, $quote['tip_minor']);
    }

    /* A flat tip larger than the work it is on is somebody's typo. */
    public function test_a_fixed_default_never_exceeds_the_bill(): void
    {
        $this->tips(['default_tip_type' => 'fixed', 'default_tip_value' => 500]);

        $this->assertSame(10000, $this->quote()['tip_minor']);
    }

    public function test_nothing_is_tipped_where_the_business_does_not_take_tips(): void
    {
        $this->tips(['is_enabled' => false]);

        $quote = $this->quote();

        $this->assertFalse($quote['tips_enabled']);
        $this->assertNull($quote['tip_percent']);
        $this->assertSame(0, $quote['tip_minor']);
        $this->assertSame(10000, $quote['total_minor']);
    }

    // ------------------------------------------------------- once answered

    public function test_a_chosen_percentage_beats_the_default(): void
    {
        $this->tips(['default_tip_value' => 15]);

        $quote = $this->quote(['tip_chosen' => true, 'tip_percent' => 20]);

        $this->assertSame(20, $quote['tip_percent']);
        $this->assertSame(2000, $quote['tip_minor']);
        $this->assertTrue($quote['tip_chosen']);
    }

    /* The whole reason the flag exists: "none" and "not asked" arrive as the
       same empty field otherwise, and the default would overwrite the
       client's answer on every recalculation. */
    public function test_choosing_no_tip_is_respected_and_not_overwritten(): void
    {
        $this->tips(['default_tip_value' => 15]);

        $quote = $this->quote(['tip_chosen' => true, 'tip_percent' => 0]);

        $this->assertSame(0, $quote['tip_percent']);
        $this->assertSame(0, $quote['tip_minor']);
        $this->assertSame(10000, $quote['total_minor']);
    }

    public function test_a_typed_amount_beats_the_default(): void
    {
        $this->tips(['default_tip_value' => 15]);

        $quote = $this->quote(['tip_chosen' => true, 'tip_amount' => '6.50']);

        $this->assertNull($quote['tip_percent']);
        $this->assertSame(650, $quote['tip_minor']);
    }

    /* A tip is a share of the work, and a client who used a coupon did not
       buy more work — so the default follows the discounted bill. */
    public function test_the_default_is_worked_out_after_the_discount(): void
    {
        $this->tips(['default_tip_value' => 20]);

        $this->actingAs($this->owner())->post(route('promotions.store'), [
            'name' => 'Half off', 'type' => 'coupon', 'code' => 'HALF',
            'discount_type' => 'percent', 'discount_value' => 50,
            'applies_to' => 'all_services', 'location_mode' => 'all',
            'eligibility' => 'all', 'starts_on' => now()->toDateString(),
            'no_expiry' => 1,
        ]);

        $quote = $this->quote(['coupon' => 'HALF']);

        $this->assertSame(5000, $quote['discount_minor']);
        /* Twenty per cent of the $50 they are paying, not of the $100. */
        $this->assertSame(1000, $quote['tip_minor']);
    }

    /* Pressing Custom takes the previous tip off the bill. The screen sends
       an answered-but-empty tip, and the quote has to come back at nought
       rather than leaving the percentage that was pressed a moment ago. */
    public function test_an_empty_custom_amount_recalculates_to_no_tip(): void
    {
        $this->tips(['default_tip_value' => 15]);

        $quote = $this->quote(['tip_chosen' => true, 'tip_percent' => null]);

        $this->assertNull($quote['tip_percent']);
        $this->assertSame(0, $quote['tip_minor']);
        $this->assertSame(10000, $quote['total_minor']);
    }

    /* And pressing a percentage over a typed amount replaces it — the two
       are one answer, not two that add up. */
    public function test_a_percentage_replaces_a_typed_amount(): void
    {
        $this->tips(['default_tip_value' => 15]);

        $typed = $this->quote(['tip_chosen' => true, 'tip_amount' => '12.50']);
        $this->assertSame(1250, $typed['tip_minor']);

        $pressed = $this->quote(['tip_chosen' => true, 'tip_percent' => 18]);

        $this->assertSame(18, $pressed['tip_percent']);
        $this->assertSame(1800, $pressed['tip_minor']);
        $this->assertSame(11800, $pressed['total_minor']);
    }

    // ------------------------------------------------- carrying it forward

    public function test_the_default_tip_is_written_onto_the_booking(): void
    {
        $this->tips(['default_tip_value' => 15]);

        $this->actingAs($this->owner())
            ->post(route('bookings.store'), [
                'client_id' => null,
                'guest_name' => 'Walk In',
                'location_id' => $this->location->id,
                'date' => now()->addDay()->toDateString(),
                'starts_at' => '11:00',
                'services' => [$this->service()->id],
                'payment_method' => 'card',
                'duplicate_ack' => true,
                /* What the screen now sends, because the screen now shows it. */
                'tip_percent' => 15,
            ])
            ->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(15, $booking->tip_percent);
        $this->assertSame(1500, $booking->tip_minor);
    }

    public function test_the_till_opens_on_what_was_agreed(): void
    {
        $this->tips(['default_tip_value' => 18]);

        /* The screen posts in JSON and gets the payment panel's payload back
           without leaving the page — which is the moment the till is handed
           what was agreed. */
        $panel = $this->actingAs($this->owner())
            ->postJson(route('bookings.store'), [
                'guest_name' => 'Walk In',
                'location_id' => $this->location->id,
                'date' => now()->addDay()->toDateString(),
                'starts_at' => '11:00',
                'services' => [$this->service()->id],
                'payment_method' => 'card',
                'duplicate_ack' => true,
                'tip_percent' => 18,
            ])
            ->assertCreated();

        /* The payment panel reads chosen_percent to decide which chip opens
           lit — a receptionist who settled 18% should not have to remember
           it at the counter. */
        $this->assertSame(18, $panel->json('booking.tips.chosen_percent'));
        /* And default_minor is what it falls back to for a booking where
           nothing was agreed, so the till never opens on No Tip either. */
        $this->assertSame(1800, $panel->json('booking.tips.default_minor'));
    }
}
