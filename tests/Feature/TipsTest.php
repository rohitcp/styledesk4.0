<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\TipSettings;
use App\Models\User;
use App\Support\Tips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tipping.
 *
 * The rule everything here turns on: a tip is offered on the work, not on the
 * bill. A booking with a massage and a bottle of oil on it suggests a tip on
 * the massage, because twenty per cent of the oil is money nobody earned —
 * and a till that quietly includes it is one the client will eventually
 * notice and stop trusting.
 */
class TipsTest extends TestCase
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
            'name' => 'Main Location', 'address_line1' => '336A Main Street', 'city' => 'Hackensack',
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

    private function settings(array $attributes = []): TipSettings
    {
        $settings = TipSettings::forTenant($this->tenant);
        $settings->update($attributes + ['is_enabled' => true]);

        return $settings->fresh();
    }

    private function service(string $name, int $priceMinor, array $tips = []): Service
    {
        return Service::withoutGlobalScopes()->create($tips + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'duration_minutes' => 60, 'is_active' => true,
        ]);
    }

    /** @param  array<int, array{0: Service, 1: int}>  $lines */
    private function booking(array $lines): Booking
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
        ]);

        $total = collect($lines)->sum(fn (array $line) => $line[1]);

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->toDateString(),
            'starts_at' => '10:00', 'ends_at' => '11:00', 'minutes' => 60,
            'status' => 'confirmed',
            'subtotal_minor' => $total, 'total_minor' => $total, 'currency_code' => 'USD',
            'payment_status' => 'unpaid', 'paid_minor' => 0,
        ]);

        foreach ($lines as [$service, $price]) {
            $booking->services()->create([
                'service_id' => $service->id, 'name' => $service->name,
                'minutes' => 60, 'price_minor' => $price,
            ]);
        }

        return $booking->fresh();
    }

    // ------------------------------------------------------------- the switch

    /** A salon that has never thought about tipping is not asked about it. */
    public function test_tips_are_off_until_somebody_turns_them_on(): void
    {
        $settings = TipSettings::forTenant($this->tenant);

        $this->assertFalse($settings->is_enabled);

        $service = $this->service('Massage', 10000);
        $booking = $this->booking([[$service, 10000]]);

        $this->assertFalse(Tips::panel($booking, $settings)['enabled']);
    }

    /**
     * Switching tips off hides the question; it does not erase the answers.
     * A salon that turns tipping off for the winter should find its services
     * exactly as they left them in the spring.
     */
    public function test_switching_tips_off_keeps_the_service_settings(): void
    {
        $this->settings();
        $service = $this->service('Massage', 10000, ['accepts_tips' => true, 'tip_value' => 25]);

        $this->actingAs($this->owner)
            ->patch(route('settings.tips.update'), [
                'is_enabled' => '0',
                'default_tip_type' => 'percent',
                'default_tip_value' => 20,
            ])
            ->assertRedirect();

        $this->assertFalse(TipSettings::forTenant($this->tenant)->is_enabled);
        $this->assertTrue((bool) $service->fresh()->accepts_tips);
        $this->assertSame(25, (int) $service->fresh()->tip_value);
    }

    /** The form is six boxes; a business that wants four leaves two empty. */
    public function test_blank_percentage_boxes_are_dropped_rather_than_refused(): void
    {
        $this->settings();

        $this->actingAs($this->owner)
            ->patch(route('settings.tips.update'), [
                'is_enabled' => '1',
                'default_tip_type' => 'percent',
                'default_tip_value' => 20,
                'percentages' => ['10', '15', '', null, '', ''],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame([10, 15], TipSettings::forTenant($this->tenant)->percentages);
    }

    // ------------------------------------------------------- what is tipped

    /**
     * Twenty per cent of a bottle of oil is money the therapist did not
     * earn.
     */
    public function test_only_tipped_services_count_towards_the_tip(): void
    {
        $settings = $this->settings();

        $massage = $this->service('Massage', 12000, ['accepts_tips' => true]);
        $retail = $this->service('Massage oil', 3000, ['accepts_tips' => false]);

        $booking = $this->booking([[$massage, 12000], [$retail, 3000]]);

        $this->assertSame(12000, Tips::eligibleMinor($booking, $settings));
    }

    /** A bill of nothing but retail has no tip to ask about. */
    public function test_a_booking_with_nothing_tipped_shows_no_tip_section(): void
    {
        $settings = $this->settings();
        $retail = $this->service('Massage oil', 3000, ['accepts_tips' => false]);

        $this->assertFalse(Tips::panel($this->booking([[$retail, 3000]]), $settings)['enabled']);
    }

    /**
     * Null on the service means "whatever the business says" rather than a
     * value of its own, so changing the default changes every service that
     * never disagreed.
     */
    public function test_a_service_with_no_opinion_follows_the_business(): void
    {
        $settings = $this->settings(['default_tip_value' => 15]);
        $service = $this->service('Massage', 10000);
        $booking = $this->booking([[$service, 10000]]);

        $this->assertSame(1500, Tips::panel($booking, $settings)['default_minor']);

        $settings->update(['default_tip_value' => 25]);

        $this->assertSame(2500, Tips::panel($booking->fresh(), $settings->fresh())['default_minor']);
    }

    public function test_the_suggestions_are_worked_out_from_the_tipped_part(): void
    {
        $settings = $this->settings(['percentages' => [15, 20]]);

        $massage = $this->service('Massage', 10000, ['accepts_tips' => true]);
        $retail = $this->service('Massage oil', 5000, ['accepts_tips' => false]);

        $panel = Tips::panel($this->booking([[$massage, 10000], [$retail, 5000]]), $settings);

        $this->assertSame(10000, $panel['eligible_minor']);
        /* Of the hundred, not the hundred and fifty. */
        $this->assertSame(1500, $panel['suggested'][0]['minor']);
        $this->assertSame(2000, $panel['suggested'][1]['minor']);
    }

    /** A flat tip larger than the work it is on is a typo, not a decision. */
    public function test_a_fixed_default_never_exceeds_the_tipped_amount(): void
    {
        $settings = $this->settings(['default_tip_type' => 'fixed', 'default_tip_value' => 80]);
        $service = $this->service('Head massage', 2500, ['accepts_tips' => true]);

        $this->assertSame(2500, Tips::panel($this->booking([[$service, 2500]]), $settings)['default_minor']);
    }

    /**
     * A booking containing one service that insists on an answer is a
     * booking that insists on one — the alternative is a rule that depends
     * on which line happens to come first.
     */
    public function test_one_insistent_service_makes_the_whole_booking_ask(): void
    {
        $settings = $this->settings(['require_selection' => false]);

        $relaxed = $this->service('Massage', 5000, ['accepts_tips' => true, 'tip_required' => false]);
        $strict = $this->service('Facial', 5000, ['accepts_tips' => true, 'tip_required' => true]);

        $panel = Tips::panel($this->booking([[$relaxed, 5000], [$strict, 5000]]), $settings);

        $this->assertTrue($panel['require_selection']);
    }

    /** One service that forbids declining takes the option off the bill. */
    public function test_no_tip_is_offered_only_where_every_line_allows_it(): void
    {
        $settings = $this->settings();

        $open = $this->service('Massage', 5000, ['accepts_tips' => true, 'allow_no_tip' => true]);
        $closed = $this->service('Facial', 5000, ['accepts_tips' => true, 'allow_no_tip' => false]);

        $this->assertTrue(Tips::panel($this->booking([[$open, 5000]]), $settings)['allow_no_tip']);
        $this->assertFalse(Tips::panel($this->booking([[$open, 5000], [$closed, 5000]]), $settings)['allow_no_tip']);
    }

    // ------------------------------------------------------------- the till

    /**
     * The tip is kept apart from the bill: it is owed to whoever did the
     * work, and a total that has absorbed it can never be taken apart again.
     */
    public function test_a_tip_is_recorded_beside_the_payment_not_inside_it(): void
    {
        $this->settings();
        $service = $this->service('Massage', 10000, ['accepts_tips' => true]);
        $booking = $this->booking([[$service, 10000]]);

        $this->actingAs($this->owner)
            ->postJson(route('bookings.pay', $booking), [
                'method' => 'cash',
                'amount' => '100.00',
                'received' => '130.00',
                'tip' => '20.00',
                'manual' => true,
            ])
            ->assertCreated();

        $payment = $booking->fresh()->payments->first();

        $this->assertSame(10000, (int) $payment->amount_minor);
        $this->assertSame(2000, (int) $payment->tip_minor);
        /* The bill is settled by the amount alone — the tip is not the
           salon's money in the same way and must not pay anything off. */
        $this->assertSame(0, $booking->fresh()->dueMinor());
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    /** Change is against the whole handover, tip included. */
    public function test_change_is_worked_out_after_the_tip(): void
    {
        $this->settings();
        $service = $this->service('Massage', 10000, ['accepts_tips' => true]);
        $booking = $this->booking([[$service, 10000]]);

        $this->actingAs($this->owner)
            ->postJson(route('bookings.pay', $booking), [
                'method' => 'cash', 'amount' => '100.00', 'received' => '130.00',
                'tip' => '20.00', 'manual' => true,
            ])->assertCreated();

        $this->assertSame(1000, (int) $booking->fresh()->payments->first()->change_minor);
    }

    /** Handing over less than the bill and the tip together is short. */
    public function test_cash_short_of_the_bill_and_the_tip_is_refused(): void
    {
        $this->settings();
        $service = $this->service('Massage', 10000, ['accepts_tips' => true]);
        $booking = $this->booking([[$service, 10000]]);

        $this->actingAs($this->owner)
            ->postJson(route('bookings.pay', $booking), [
                'method' => 'cash', 'amount' => '100.00', 'received' => '110.00',
                'tip' => '20.00', 'manual' => true,
            ])
            ->assertStatus(422);
    }

    /** A tip on a bill where nothing is tipped is money nobody can account for. */
    public function test_a_tip_is_refused_where_nothing_on_the_bill_is_tipped(): void
    {
        $this->settings();
        $retail = $this->service('Massage oil', 3000, ['accepts_tips' => false]);
        $booking = $this->booking([[$retail, 3000]]);

        $this->actingAs($this->owner)
            ->postJson(route('bookings.pay', $booking), [
                'method' => 'cash', 'amount' => '30.00', 'tip' => '5.00', 'manual' => true,
            ])
            ->assertStatus(422);
    }

    /**
     * "Must choose" is a prompt, not a charge: declining is answering, and
     * a payment that never mentions the tip has skipped the question.
     */
    public function test_where_an_answer_is_required_a_silent_payment_is_refused(): void
    {
        $this->settings(['require_selection' => true, 'allow_no_tip' => true]);
        $service = $this->service('Massage', 10000, ['accepts_tips' => true]);
        $booking = $this->booking([[$service, 10000]]);

        $this->actingAs($this->owner)
            ->postJson(route('bookings.pay', $booking), [
                'method' => 'cash', 'amount' => '100.00', 'manual' => true,
            ])
            ->assertStatus(422);

        /* Nought is an answer where declining is on offer. */
        $this->actingAs($this->owner)
            ->postJson(route('bookings.pay', $booking), [
                'method' => 'cash', 'amount' => '100.00', 'tip' => '0', 'manual' => true,
            ])
            ->assertCreated();
    }

    /** Where declining is not on offer, nought is not an answer either. */
    public function test_where_no_tip_is_not_offered_a_zero_tip_is_refused(): void
    {
        $this->settings(['require_selection' => true, 'allow_no_tip' => false]);
        $service = $this->service('Massage', 10000, ['accepts_tips' => true]);
        $booking = $this->booking([[$service, 10000]]);

        $this->actingAs($this->owner)
            ->postJson(route('bookings.pay', $booking), [
                'method' => 'cash', 'amount' => '100.00', 'tip' => '0', 'manual' => true,
            ])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------ the pages

    /**
     * Two tabs once tipping is on, and none before: what to suggest and
     * which services it applies to are only questions worth asking when the
     * answer will be used.
     */
    public function test_the_tabs_appear_only_once_tipping_is_on(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.tips.index'))
            ->assertOk()
            ->assertSee(__('tips.enable'))
            /* Asserted on this tab alone: "Services" is also the sidebar's
               own word and the module description's, so its absence would
               prove nothing. */
            ->assertDontSee(__('tips.tabs.suggest'));

        $this->settings();

        $this->actingAs($this->owner)
            ->get(route('settings.tips.index'))
            ->assertOk()
            ->assertSee(__('tips.tabs.suggest'))
            ->assertSee(__('tips.tabs.services'));
    }

    /** The page opens on the suggestions; the table is its own address. */
    public function test_the_services_table_is_on_its_own_tab(): void
    {
        $this->settings();
        $this->service('Deep Tissue Massage', 6500, ['accepts_tips' => true]);

        $this->actingAs($this->owner)
            ->get(route('settings.tips.index'))
            ->assertOk()
            ->assertSee(__('tips.defaults'))
            ->assertDontSee('Deep Tissue Massage');

        $this->actingAs($this->owner)
            ->get(route('settings.tips.index', ['tab' => 'services']))
            ->assertOk()
            ->assertSee('Deep Tissue Massage');
    }

    /**
     * The switch posts on its own, and must not take the settings with it —
     * a business that turns tipping off and on again should find their
     * percentages exactly as they left them.
     */
    public function test_switching_tipping_on_keeps_the_percentages(): void
    {
        $this->settings(['percentages' => [10, 12]]);

        $this->actingAs($this->owner)
            ->patch(route('settings.tips.update'), [
                'is_enabled' => '0',
                'default_tip_type' => 'percent',
                'default_tip_value' => 20,
                'percentages' => ['10', '12'],
            ])->assertRedirect();

        $this->actingAs($this->owner)
            ->patch(route('settings.tips.update'), [
                'is_enabled' => '1',
                'default_tip_type' => 'percent',
                'default_tip_value' => 20,
                'percentages' => ['10', '12'],
            ])->assertRedirect();

        $settings = TipSettings::forTenant($this->tenant);

        $this->assertTrue($settings->is_enabled);
        $this->assertSame([10, 12], $settings->percentages);
    }

    public function test_one_services_tip_settings_can_be_changed(): void
    {
        $this->settings();
        $service = $this->service('Massage oil', 3000);

        $this->actingAs($this->owner)
            ->patch(route('settings.tips.service', $service), ['accepts_tips' => '0'])
            ->assertRedirect();

        $this->assertFalse((bool) $service->fresh()->accepts_tips);
    }

    /**
     * A card asking how much to suggest, in a salon that has never tipped
     * anybody, is a question with no answer.
     */
    public function test_the_service_form_shows_the_tips_card_only_when_tips_are_on(): void
    {
        $service = $this->service('Massage', 10000);

        $this->actingAs($this->owner)
            ->get(route('services.edit', $service))
            ->assertOk()
            ->assertDontSee(__('tips.accepts'));

        $this->settings();

        $this->actingAs($this->owner)
            ->get(route('services.edit', $service))
            ->assertOk()
            ->assertSee(__('tips.accepts'));
    }
}
