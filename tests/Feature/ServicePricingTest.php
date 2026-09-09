<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\TipSettings;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Card and cash prices.
 *
 * Two explicit amounts rather than one and a discount off it: a business that
 * charges the same either way, or more for cash, is not doing anything wrong
 * and a stored percentage could not say so.
 *
 * The rule that matters most is what a blank cash price means. It means "the
 * same as card" — every service priced before there were two prices has one,
 * and reading a blank as nought would make them all free for anybody paying
 * cash.
 */
class ServicePricingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private User $owner;

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
            'name' => 'Main Location', 'address_line1' => '1 Main St', 'city' => 'Hackensack',
            'postal_code' => '07601', 'country' => 'US', 'timezone' => 'America/New_York',
            'is_primary' => true,
        ]);

        $this->owner = User::create([
            'first_name' => 'Emma', 'last_name' => 'Martin',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();
        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();
        $this->owner = $this->owner->fresh();
    }

    private function service(int $cardMinor, ?int $cashMinor = null): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Swedish Massage — 60 Minutes',
            'duration_minutes' => 60, 'is_active' => true,
        ]);

        $service->prices()->create([
            'currency_code' => 'USD',
            'price_minor' => $cardMinor,
            'cash_price_minor' => $cashMinor,
        ]);

        return $service->fresh()->load('prices');
    }

    // ------------------------------------------------------------ the price

    public function test_a_service_can_cost_a_different_amount_in_cash(): void
    {
        $service = $this->service(10500, 10000);

        $this->assertSame(10500, $service->priceMinorFor('USD', 'card'));
        $this->assertSame(10000, $service->priceMinorFor('USD', 'cash'));
        $this->assertTrue($service->hasTwoPricesIn('USD'));
    }

    /**
     * A blank cash price means "the same as card", not nothing. Every service
     * priced before there were two prices has one, and should keep charging
     * it whichever way somebody pays.
     */
    public function test_no_cash_price_means_the_same_as_card(): void
    {
        $service = $this->service(6500);

        $this->assertSame(6500, $service->priceMinorFor('USD', 'card'));
        $this->assertSame(6500, $service->priceMinorFor('USD', 'cash'));
        $this->assertFalse($service->hasTwoPricesIn('USD'));
    }

    /** The business decides its own prices; the form does not argue. */
    public function test_cash_may_cost_more_than_card(): void
    {
        $service = $this->service(10000, 11000);

        $this->assertSame(11000, $service->priceMinorFor('USD', 'cash'));
        $this->assertTrue($service->hasTwoPricesIn('USD'));
    }

    /**
     * One column with both stacked, and just the amount where they are the
     * same — "Card $65 · Cash $65" on every row would be a column of noise.
     */
    public function test_the_listing_shows_both_prices_only_when_they_differ(): void
    {
        $this->assertSame('Card $105.00 · Cash $100.00', $this->service(10500, 10000)->pricingLabel('USD'));
        $this->assertStringNotContainsString('Cash', (string) $this->service(6500)->pricingLabel('USD'));
    }

    /**
     * The listing asks for the two prices separately.
     *
     * A service charging the same either way still answers the cash question
     * with a price. A blank cell there would read as "no cash price", which
     * is the one thing it does not mean.
     */
    public function test_each_price_is_offered_on_its_own_for_the_listing(): void
    {
        $two = $this->service(10500, 10000);

        $this->assertSame('$105.00', $two->priceLabel('USD'));
        $this->assertSame('$100.00', $two->cashPriceLabel('USD'));

        $one = $this->service(6500);

        $this->assertSame('$65.00', $one->priceLabel('USD'));
        $this->assertSame('$65.00', $one->cashPriceLabel('USD'));
    }

    // ------------------------------------------------------------- the form

    public function test_both_prices_are_saved_from_the_form(): void
    {
        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Swedish Massage',
                'duration_minutes' => 60,
                'locations' => [$this->location->id],
                'price' => ['USD' => '105.00'],
                'cash_price' => ['USD' => '100.00'],
            ])
            ->assertRedirect();

        $service = Service::withoutGlobalScopes()->where('name', 'Swedish Massage')->with('prices')->firstOrFail();

        $this->assertSame(10500, (int) $service->prices->first()->price_minor);
        $this->assertSame(10000, (int) $service->prices->first()->cash_price_minor);
    }

    /** Left blank, it stays null rather than becoming a free service. */
    public function test_a_blank_cash_price_is_stored_as_nothing_rather_than_nought(): void
    {
        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Deep Tissue',
                'duration_minutes' => 60,
                'locations' => [$this->location->id],
                'price' => ['USD' => '65.00'],
                'cash_price' => ['USD' => ''],
            ])
            ->assertRedirect();

        $service = Service::withoutGlobalScopes()->where('name', 'Deep Tissue')->with('prices')->firstOrFail();

        $this->assertNull($service->prices->first()->cash_price_minor);
        $this->assertSame(6500, $service->priceMinorFor('USD', 'cash'));
    }

    // ---------------------------------------------------------- the booking

    /**
     * Card unless somebody says the client is paying cash. The screen has to
     * quote a price before anybody has said how they are paying, and quoting
     * the lower one and then charging more is the version a client complains
     * about.
     */
    public function test_a_booking_is_priced_at_the_card_price_by_default(): void
    {
        $service = $this->service(10500, 10000);

        $this->actingAs($this->owner)->post(route('bookings.store'), [
            'guest_name' => 'Someone',
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->with('services')->firstOrFail();

        $this->assertSame('card', $booking->priced_for);
        $this->assertSame(10500, (int) $booking->total_minor);
        $this->assertSame(10500, (int) $booking->services->first()->price_minor);
    }

    public function test_a_cash_booking_is_priced_at_the_cash_price(): void
    {
        $service = $this->service(10500, 10000);

        $this->actingAs($this->owner)->post(route('bookings.store'), [
            'guest_name' => 'Someone',
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->with('services')->firstOrFail();

        $this->assertSame('cash', $booking->priced_for);
        $this->assertSame(10000, (int) $booking->total_minor);
    }

    /**
     * The till is told which price list the booking was totalled against.
     *
     * A cash booking settled on a card collects the cash total for a card
     * sale, and the salon is short the difference on every service that
     * charges two prices — so the payment panel holds the method to cash,
     * and this is what it reads to know.
     */
    public function test_the_payment_panel_is_told_the_booking_was_priced_in_cash(): void
    {
        $service = $this->service(10500, 10000);

        $this->actingAs($this->owner)->post(route('bookings.store'), [
            'guest_name' => 'Someone',
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        $panel = $this->actingAs($this->owner)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->viewData('panel');

        $this->assertSame('cash', $panel['priced_for']);
        $this->assertSame(10000, $panel['collect_minor']);
    }

    /**
     * Both prices are kept on the line. Prices change; last March's booking
     * has to keep saying what last March's prices were.
     */
    public function test_the_booking_keeps_both_prices_as_they_were(): void
    {
        $service = $this->service(10500, 10000);

        $this->actingAs($this->owner)->post(route('bookings.store'), [
            'guest_name' => 'Someone',
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
        ]);

        $line = Booking::withoutGlobalScopes()->with('services')->firstOrFail()->services->first();

        $this->assertSame(10500, (int) $line->card_price_minor);
        $this->assertSame(10000, (int) $line->cash_price_minor);

        /* The service is repriced afterwards; the booking does not move. */
        $service->prices->first()->forceFill(['price_minor' => 20000, 'cash_price_minor' => 19000])->save();

        $this->assertSame(10500, (int) $line->fresh()->card_price_minor);
        $this->assertSame(10000, (int) $line->fresh()->cash_price_minor);
        $this->assertSame(10500, (int) $line->fresh()->price_minor);
    }

    /** The booking screen is handed both, so it can requote itself. */
    public function test_the_booking_screen_receives_both_prices(): void
    {
        $this->service(10500, 10000);

        $this->actingAs($this->owner)
            ->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('"cash_price_minor":10000', false)
            ->assertSee('"two_prices":true', false);
    }

    // ------------------------------------------------------------- the quote

    /** @param  array<string, mixed>  $payload */
    private function quote(array $payload): array
    {
        return $this->actingAs($this->owner)
            ->postJson(route('bookings.quote'), $payload)
            ->assertOk()
            ->json();
    }

    /**
     * The order the total is worked out in, which every other order gets
     * wrong: price, subtotal, discount, tip, tax, total.
     */
    public function test_the_total_is_built_in_the_stated_order(): void
    {
        $service = $this->service(10000);

        $settings = TipSettings::forTenant($this->tenant);
        $settings->update(['is_enabled' => true]);

        $promotion = Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Ten off', 'type' => 'coupon', 'code' => 'SPA10',
            'discount_type' => 'percent', 'discount_value' => 10,
            'applies_to' => 'all_services', 'location_mode' => 'all',
            'eligibility' => 'all', 'starts_on' => now()->subDay()->toDateString(),
            'per_client_limit' => null,
        ]);

        $quote = $this->quote([
            'services' => [$service->id],
            'payment_method' => 'card',
            'coupon' => 'SPA10',
            'tip_percent' => 15,
        ]);

        $this->assertSame(10000, $quote['subtotal_minor']);
        $this->assertSame(1000, $quote['discount_minor']);
        /* Fifteen per cent of the ninety left after the discount, not of the
           hundred: a client who used a coupon did not buy more work. */
        $this->assertSame(1350, $quote['tip_minor']);
        $this->assertSame(10350, $quote['total_minor']);
        $this->assertSame($promotion->code, $quote['coupon']['code']);
    }

    /**
     * Ten per cent of the card price and ten per cent of the cash price are
     * different amounts, and the client is owed the one they are paying.
     */
    public function test_the_coupon_is_taken_off_the_price_for_the_chosen_method(): void
    {
        $service = $this->service(10500, 10000);

        Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Ten off', 'type' => 'coupon', 'code' => 'SPA10',
            'discount_type' => 'percent', 'discount_value' => 10,
            'applies_to' => 'all_services', 'location_mode' => 'all',
            'eligibility' => 'all', 'starts_on' => now()->subDay()->toDateString(),
            'per_client_limit' => null,
        ]);

        $card = $this->quote(['services' => [$service->id], 'payment_method' => 'card', 'coupon' => 'SPA10']);
        $cash = $this->quote(['services' => [$service->id], 'payment_method' => 'cash', 'coupon' => 'SPA10']);

        $this->assertSame(1050, $card['discount_minor']);
        $this->assertSame(9450, $card['total_minor']);

        $this->assertSame(1000, $cash['discount_minor']);
        $this->assertSame(9000, $cash['total_minor']);
    }

    /** A refused coupon says why, and leaves the total alone. */
    public function test_an_unusable_coupon_does_not_change_the_total(): void
    {
        $service = $this->service(10000);

        $quote = $this->quote([
            'services' => [$service->id],
            'payment_method' => 'card',
            'coupon' => 'NOSUCHCODE',
        ]);

        $this->assertSame(__('promotions.refused.unknown_code'), $quote['coupon_error']);
        $this->assertSame(0, $quote['discount_minor']);
        $this->assertSame(10000, $quote['total_minor']);
        $this->assertNull($quote['coupon']);
    }

    /**
     * A percentage is a share and moves with what it is a share of; an
     * amount somebody typed is a decision and does not.
     */
    public function test_a_custom_tip_is_kept_while_a_percentage_is_recalculated(): void
    {
        $service = $this->service(10500, 10000);
        TipSettings::forTenant($this->tenant)->update(['is_enabled' => true]);

        $card = $this->quote(['services' => [$service->id], 'payment_method' => 'card', 'tip_percent' => 20]);
        $cash = $this->quote(['services' => [$service->id], 'payment_method' => 'cash', 'tip_percent' => 20]);

        $this->assertSame(2100, $card['tip_minor']);
        $this->assertSame(2000, $cash['tip_minor']);

        /* The typed amount is the same either way. */
        foreach (['card', 'cash'] as $method) {
            $quote = $this->quote(['services' => [$service->id], 'payment_method' => $method, 'tip_amount' => '20.00']);

            $this->assertSame(2000, $quote['tip_minor'], $method);
            $this->assertNull($quote['tip_percent'], $method);
        }
    }

    /** A business that does not take tips is not asked about them. */
    public function test_no_tip_is_added_where_tipping_is_switched_off(): void
    {
        $service = $this->service(10000);

        $quote = $this->quote(['services' => [$service->id], 'tip_percent' => 20]);

        $this->assertFalse($quote['tips_enabled']);
        $this->assertSame(0, $quote['tip_minor']);
        $this->assertSame(10000, $quote['total_minor']);
    }

    /** Each service on the bill at the price for how it is being paid. */
    public function test_several_services_each_use_their_own_price_for_the_method(): void
    {
        $first = $this->service(10500, 10000);

        $second = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Facial', 'duration_minutes' => 45, 'is_active' => true,
        ]);
        $second->prices()->create(['currency_code' => 'USD', 'price_minor' => 9000, 'cash_price_minor' => 8000]);

        $cash = $this->quote(['services' => [$first->id, $second->id], 'payment_method' => 'cash']);

        $this->assertSame(18000, $cash['subtotal_minor']);
    }

    // --------------------------------------------------------- the till

    /**
     * The tip agreed while taking the booking is kept, so the counter opens
     * on it. A receptionist who settled fifteen per cent with the client
     * should not have to remember it at the till.
     */
    public function test_the_tip_agreed_on_the_booking_screen_is_kept(): void
    {
        $service = $this->service(10000);
        TipSettings::forTenant($this->tenant)->update(['is_enabled' => true]);

        $this->actingAs($this->owner)->post(route('bookings.store'), [
            'guest_name' => 'Someone',
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'tip_percent' => 15,
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(15, (int) $booking->tip_percent);
        $this->assertSame(1500, (int) $booking->tip_minor);
    }

    /**
     * A draft being confirmed goes through update() rather than store(), and
     * must keep the same answers — this is the path the booking screen
     * actually takes once it has autosaved.
     */
    public function test_confirming_a_draft_keeps_the_tip_and_the_coupon(): void
    {
        $service = $this->service(10000);
        TipSettings::forTenant($this->tenant)->update(['is_enabled' => true]);

        Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Ten off', 'type' => 'coupon', 'code' => 'SPA10',
            'discount_type' => 'percent', 'discount_value' => 10,
            'applies_to' => 'all_services', 'location_mode' => 'all',
            'eligibility' => 'all', 'starts_on' => now()->subDay()->toDateString(),
            'per_client_limit' => null,
        ]);

        $this->actingAs($this->owner)->post(route('bookings.store'), [
            'guest_name' => 'Someone',
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'draft' => 1,
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner)->patch(route('bookings.update', $booking), [
            'guest_name' => 'Someone',
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'coupon' => 'SPA10',
            'tip_percent' => 15,
        ])->assertOk();

        $booking = $booking->fresh();

        $this->assertSame(15, (int) $booking->tip_percent);
        $this->assertSame(1000, (int) $booking->discount_minor);
        /* The tip is a share of what is owed after the discount. */
        $this->assertSame(1350, (int) $booking->tip_minor);
        $this->assertNotNull($booking->promotion_id);
    }

    /** The till opens on what was agreed rather than on nothing. */
    public function test_the_payment_panel_is_handed_the_agreed_tip(): void
    {
        $service = $this->service(10000);
        TipSettings::forTenant($this->tenant)->update(['is_enabled' => true]);

        $this->actingAs($this->owner)->post(route('bookings.store'), [
            'guest_name' => 'Someone',
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'tip_percent' => 20,
        ]);

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('"chosen_percent":20', false);
    }

    /**
     * Both deposit fields are offered, and each wears its own unit.
     *
     * An amount is money and wears the currency symbol; a percentage is not,
     * and wears a per-cent sign. A reader who has just chosen "percentage"
     * and sees a $ in the box will type dollars. Both are rendered, and the
     * script shows whichever the type calls for, so neither needs a round
     * trip to appear.
     */
    public function test_the_deposit_field_offers_both_units(): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('services.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('services.deposit_amount'), $html);
        $this->assertStringContainsString(__('services.deposit_percent'), $html);

        /* The per-cent sign sits in the box; the currency does not — the
           amount field's label names the money, the way the price fields
           beneath it do. */
        $this->assertStringContainsString('styledesk_input__suffix', $html);
        $this->assertStringContainsString('data-deposit-percent-field', $html);
        $this->assertStringContainsString('data-deposit-amount-fields', $html);
        $this->assertStringContainsString('data-deposit-type', $html);
    }

    /**
     * The deposit is asked for once, in its own card, above the prices.
     *
     * It used to be a switch on every price, which asks a business pricing
     * in three currencies the same question three times.
     */
    public function test_the_deposit_is_asked_once_above_the_prices(): void
    {
        $currency = Currencies::primaryFor($this->tenant);

        $html = $this->actingAs($this->owner)
            ->get(route('services.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-deposit-card', $html);
        /* Once as the switch itself. The pair — a hidden 0 beside the
           checkbox — is how every toggle in the app posts an "off". */
        $this->assertSame(1, substr_count($html, 'name="deposit_required" value="1"'));
        $this->assertStringNotContainsString('name="deposit['.$currency.'][required]"', $html);

        /* And it comes before the prices it governs. */
        $this->assertLessThan(
            strpos($html, __('services.section.price')),
            strpos($html, __('services.section.deposit')),
        );
    }

    /** The switch carries no card of its own inside the one it sits in. */
    public function test_the_deposit_toggle_is_bare(): void
    {
        $this->actingAs($this->owner)
            ->get(route('services.create'))
            ->assertOk()
            ->assertSee('styledesk_toggle--bare', false);
    }
}
