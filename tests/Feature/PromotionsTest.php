<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Promotions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Coupons and offers.
 *
 * Mostly about the rules rather than the screens, because there are two
 * places a promotion gets applied — a client typing a code into online
 * booking, and the desk adding one at checkout — and a coupon the website
 * accepts and the till refuses is worse than one nobody can use.
 */
class PromotionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        /* A Tuesday, so the day rules have something to be right about. */
        Carbon::setTestNow('2026-10-13 11:00:00');

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function promotion(array $attributes = []): Promotion
    {
        return Promotion::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'New client 20%',
            'type' => 'coupon',
            'code' => 'WELCOME20',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'applies_to' => 'all_services',
            'location_mode' => 'all',
            'eligibility' => 'all',
            'starts_on' => now()->subWeek()->toDateString(),
            'ends_on' => null,
            'per_client_limit' => null,
        ]);
    }

    private function service(string $name, ?ServiceCategory $category = null): Service
    {
        return Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'duration_minutes' => 60, 'is_active' => true,
            'service_category_id' => $category?->id,
        ]);
    }

    private function category(string $name): ServiceCategory
    {
        return ServiceCategory::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'status' => ServiceCategory::STATUS_ACTIVE,
        ]);
    }

    private function client(): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
        ]);
    }

    /** @param  array<int, array{0: Service, 1: int}>  $items */
    private function lines(array $items): Collection
    {
        return collect($items)->map(fn (array $item) => [
            'service_id' => $item[0]->id,
            'category_id' => $item[0]->service_category_id,
            'price_minor' => $item[1],
        ]);
    }

    // ------------------------------------------------------------- status

    /**
     * Status is worked out, never stored: a column holding "active" would be
     * wrong every midnight until something rewrote it.
     */
    public function test_status_follows_the_dates(): void
    {
        $this->assertSame('active', $this->promotion()->status());

        $this->assertSame('scheduled', $this->promotion([
            'code' => 'LATER', 'starts_on' => now()->addWeek()->toDateString(),
        ])->status());

        $this->assertSame('expired', $this->promotion([
            'code' => 'GONE',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ])->status());

        $this->assertSame('draft', $this->promotion(['code' => 'DRAFT', 'is_draft' => true])->status());

        /* Disabled beats everything, whatever the dates say. */
        $this->assertSame('disabled', $this->promotion([
            'code' => 'OFF', 'is_disabled' => true, 'is_draft' => true,
        ])->status());
    }

    // ---------------------------------------------------------- what it takes

    public function test_a_percentage_comes_off_the_whole_bill(): void
    {
        $promotion = $this->promotion();
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $this->assertSame(2000, Promotions::discountMinor($promotion, $lines));
    }

    /**
     * The reason "20% off facials" is a different thing from "20% off the
     * bill": the percentage is taken against the facials, not against the
     * shampoo the client bought on the way out.
     */
    public function test_a_service_promotion_only_discounts_those_services(): void
    {
        $facial = $this->service('Facial');
        $massage = $this->service('Massage');

        $promotion = $this->promotion(['applies_to' => 'services']);
        $promotion->services()->sync([$facial->id]);
        $promotion->load('services');

        $lines = $this->lines([[$facial, 10000], [$massage, 10000]]);

        /* A fifth of the facial, not a fifth of the two hundred. */
        $this->assertSame(10000, Promotions::eligibleMinor($promotion, $lines));
        $this->assertSame(2000, Promotions::discountMinor($promotion, $lines));
    }

    public function test_a_category_promotion_discounts_that_category(): void
    {
        /* Names the seeded catalogue does not already use. */
        $hair = $this->category('Hair — promo test');
        $spa = $this->category('Spa — promo test');

        $cut = $this->service('Cut', $hair);
        $massage = $this->service('Massage', $spa);

        $promotion = $this->promotion(['applies_to' => 'categories']);
        $promotion->serviceCategories()->sync([$spa->id]);
        $promotion->load('serviceCategories');

        $lines = $this->lines([[$cut, 5000], [$massage, 10000]]);

        $this->assertSame(2000, Promotions::discountMinor($promotion, $lines));
    }

    /**
     * A £25 coupon against a £20 facial takes £20, not £25 with the
     * difference coming out of the rest of the bill.
     */
    public function test_a_fixed_discount_never_exceeds_what_it_applies_to(): void
    {
        $facial = $this->service('Facial');

        $promotion = $this->promotion([
            'discount_type' => 'fixed', 'discount_value' => 2500, 'applies_to' => 'services',
        ]);
        $promotion->services()->sync([$facial->id]);
        $promotion->load('services');

        $lines = $this->lines([[$facial, 2000], [$this->service('Massage'), 9000]]);

        $this->assertSame(2000, Promotions::discountMinor($promotion, $lines));
    }

    // ------------------------------------------------------------ the rules

    public function test_a_promotion_outside_its_dates_is_refused(): void
    {
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $early = $this->promotion(['code' => 'SOON', 'starts_on' => now()->addWeek()->toDateString()]);
        $late = $this->promotion([
            'code' => 'PAST',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame(__('promotions.refused.not_started'), Promotions::refusal($early, $lines));
        $this->assertSame(__('promotions.refused.expired'), Promotions::refusal($late, $lines));
    }

    /**
     * An empty Tuesday cannot be sold again on Wednesday, which is why day
     * restrictions earn their place in the MVP.
     */
    public function test_a_day_restricted_promotion_only_runs_on_those_days(): void
    {
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        /* Today is a Tuesday. */
        $tuesday = $this->promotion(['code' => 'TUE', 'days' => [2]]);
        $wednesday = $this->promotion(['code' => 'WED', 'days' => [3]]);

        $this->assertNull(Promotions::refusal($tuesday, $lines));
        $this->assertSame(__('promotions.refused.wrong_day'), Promotions::refusal($wednesday, $lines));
    }

    /** No days chosen means every day, not none. */
    public function test_an_empty_day_list_means_every_day(): void
    {
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $this->assertNull(Promotions::refusal($this->promotion(['days' => []]), $lines));
    }

    public function test_a_location_promotion_is_refused_elsewhere(): void
    {
        $other = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Uptown', 'address_line1' => '2 High St', 'city' => 'Hackensack',
            'postal_code' => '07601', 'country' => 'US', 'timezone' => 'America/New_York',
        ]);

        $promotion = $this->promotion(['location_mode' => 'selected']);
        $promotion->locations()->sync([$this->location->id]);
        $promotion->load('locations');

        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $this->assertNull(Promotions::refusal($promotion, $lines, null, $this->location->id));
        $this->assertSame(
            __('promotions.refused.wrong_location'),
            Promotions::refusal($promotion, $lines, null, $other->id),
        );
    }

    /**
     * "New" means nobody has ever finished an appointment for them. A
     * booking taken and cancelled does not make somebody an existing client.
     */
    public function test_a_new_client_offer_is_refused_once_they_have_been_in(): void
    {
        $client = $this->client();
        $promotion = $this->promotion(['eligibility' => 'new']);
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $this->assertNull(Promotions::refusal($promotion, $lines, $client));

        /* A cancelled booking is not a visit. */
        $this->booking($client, 'cancelled');
        $this->assertNull(Promotions::refusal($promotion, $lines, $client));

        $this->booking($client, 'completed');
        $this->assertSame(__('promotions.refused.new_only'), Promotions::refusal($promotion, $lines, $client->fresh()));
    }

    public function test_an_existing_client_offer_is_refused_for_a_first_timer(): void
    {
        $client = $this->client();
        $promotion = $this->promotion(['eligibility' => 'existing']);
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $this->assertSame(__('promotions.refused.existing_only'), Promotions::refusal($promotion, $lines, $client));
    }

    /** A walk-in cannot be shown to be new or returning. */
    public function test_a_client_only_promotion_needs_a_client(): void
    {
        $promotion = $this->promotion(['eligibility' => 'new']);
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $this->assertSame(__('promotions.refused.needs_a_client'), Promotions::refusal($promotion, $lines, null));
    }

    /** Stops £25 off being taken against a £30 booking. */
    public function test_a_minimum_spend_is_read_against_the_whole_bill(): void
    {
        $promotion = $this->promotion(['min_spend_minor' => 10000]);

        $this->assertNotNull(Promotions::refusal($promotion, $this->lines([[$this->service('Massage'), 5000]])));
        $this->assertNull(Promotions::refusal($promotion, $this->lines([[$this->service('Massage'), 12000]])));
    }

    public function test_a_total_limit_stops_it_once_it_is_used_up(): void
    {
        $promotion = $this->promotion(['total_limit' => 1]);
        $client = $this->client();
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $this->assertNull(Promotions::refusal($promotion, $lines, $client));

        Promotions::redeem($promotion, $this->booking($client), 2000);

        $this->assertSame(__('promotions.refused.fully_redeemed'), Promotions::refusal($promotion->fresh(), $lines, $client));
    }

    /** Once each is the usual answer, and it has to actually hold. */
    public function test_a_per_client_limit_stops_the_same_client_twice(): void
    {
        $promotion = $this->promotion(['per_client_limit' => 1]);
        $client = $this->client();
        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        Promotions::redeem($promotion, $this->booking($client), 2000);

        $this->assertSame(__('promotions.refused.client_limit'), Promotions::refusal($promotion->fresh(), $lines, $client));
    }

    /**
     * A client told a coupon "worked" and given nothing off would rightly
     * complain, so nothing eligible is a refusal rather than a nought.
     */
    public function test_a_promotion_covering_nothing_on_the_bill_is_refused(): void
    {
        $facial = $this->service('Facial');
        $promotion = $this->promotion(['applies_to' => 'services']);
        $promotion->services()->sync([$facial->id]);
        $promotion->load('services');

        $lines = $this->lines([[$this->service('Massage'), 10000]]);

        $this->assertSame(__('promotions.refused.no_eligible_services'), Promotions::refusal($promotion, $lines));
    }

    /** A code read off a poster is not proof-read. */
    public function test_a_code_is_found_whatever_the_case_or_spacing(): void
    {
        $this->promotion();

        $this->assertNotNull(Promotions::byCode('  welcome20 '));
        $this->assertNull(Promotions::byCode('NOPE'));
    }

    // ------------------------------------------------------------ the pages

    public function test_a_promotion_can_be_created_from_the_form(): void
    {
        $this->actingAs($this->owner)
            ->post(route('promotions.store'), [
                'name' => 'Slow Tuesday',
                'type' => 'offer',
                'discount_type' => 'percent',
                'discount_value' => '20',
                'applies_to' => 'all_services',
                'location_mode' => 'all',
                'eligibility' => 'all',
                'starts_on' => now()->toDateString(),
                'days' => [2],
            ])
            ->assertRedirect();

        $promotion = Promotion::withoutGlobalScopes()->where('name', 'Slow Tuesday')->firstOrFail();

        $this->assertSame('offer', $promotion->type);
        /* An offer applies itself, so it has no code. */
        $this->assertNull($promotion->code);
        $this->assertSame([2], $promotion->days);
    }

    /** Saved in capitals, because WELCOME20 and welcome20 are one code. */
    public function test_a_coupon_code_is_saved_in_capitals_and_must_be_unique(): void
    {
        $this->actingAs($this->owner)
            ->post(route('promotions.store'), $this->formPayload(['code' => 'welcome20']))
            ->assertRedirect();

        $this->assertSame('WELCOME20', Promotion::withoutGlobalScopes()->first()->code);

        $this->actingAs($this->owner)
            ->post(route('promotions.store'), $this->formPayload(['code' => 'WELCOME20', 'name' => 'Another']))
            ->assertSessionHasErrors('code');
    }

    /** Every day is stored as null: the two mean the same thing. */
    public function test_all_seven_days_is_stored_as_no_restriction(): void
    {
        $this->actingAs($this->owner)
            ->post(route('promotions.store'), $this->formPayload(['days' => [0, 1, 2, 3, 4, 5, 6]]));

        $this->assertNull(Promotion::withoutGlobalScopes()->first()->days);
    }

    /**
     * A copy that started running the moment it was made would be a second
     * promotion nobody had checked, sharing a name with the first.
     */
    public function test_duplicating_makes_a_draft_with_its_own_code(): void
    {
        $promotion = $this->promotion();

        $this->actingAs($this->owner)
            ->get(route('promotions.duplicate', $promotion))
            ->assertRedirect();

        $copy = Promotion::withoutGlobalScopes()->where('id', '!=', $promotion->id)->firstOrFail();

        $this->assertTrue($copy->is_draft);
        $this->assertNotSame($promotion->code, $copy->code);
        $this->assertSame(__('promotions.copy_of', ['name' => $promotion->name]), $copy->name);
    }

    /**
     * Switched off, never deleted: the bookings it discounted keep pointing
     * at it.
     */
    public function test_a_promotion_is_disabled_rather_than_removed(): void
    {
        $promotion = $this->promotion();

        $this->actingAs($this->owner)->patch(route('promotions.toggle', $promotion))->assertRedirect();

        $this->assertTrue($promotion->fresh()->is_disabled);
        $this->assertSame('disabled', $promotion->fresh()->status());

        $this->actingAs($this->owner)->patch(route('promotions.toggle', $promotion))->assertRedirect();

        $this->assertFalse($promotion->fresh()->is_disabled);
    }

    public function test_the_listing_shows_promotions_and_their_status(): void
    {
        $this->promotion();

        $this->actingAs($this->owner)
            ->get(route('promotions.index'))
            ->assertOk()
            ->assertSee(__('promotions.title'));

        $row = $this->actingAs($this->owner)->getJson(route('promotions.data'))->assertOk()->json('data.0');

        $this->assertSame('New client 20%', $row['name']);
        $this->assertSame('WELCOME20', $row['code']);
        $this->assertSame('20%', $row['discount']);
        $this->assertSame(__('promotions.statuses.active'), $row['status']);
    }

    /** What it has actually done, read from the redemptions. */
    public function test_the_report_counts_what_was_given_away(): void
    {
        $promotion = $this->promotion();
        $client = $this->client();

        Promotions::redeem($promotion, $this->booking($client), 2000);
        Promotions::redeem($promotion, $this->booking($client), 1500);

        $report = Promotions::report($promotion->fresh());

        $this->assertSame(2, $report['redemptions']);
        $this->assertSame(1, $report['clients']);
        $this->assertSame(3500, $report['discount_minor']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function formPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New client 20%',
            'type' => 'coupon',
            'code' => 'WELCOME20',
            'discount_type' => 'percent',
            'discount_value' => '20',
            'applies_to' => 'all_services',
            'location_mode' => 'all',
            'eligibility' => 'all',
            'starts_on' => now()->toDateString(),
        ];
    }

    private function booking(Client $client, string $status = 'completed'): Booking
    {
        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => now()->toDateString(),
            'starts_at' => '10:00', 'ends_at' => '11:00', 'minutes' => 60,
            'status' => $status, 'total_minor' => 10000, 'currency_code' => 'USD',
        ]);
    }
}
