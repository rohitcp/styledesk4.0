<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Booking;
use App\Models\Location;
use App\Models\LocationHour;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\ResourceUtilization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * How much of the day each room and chair was actually working.
 *
 * One sentence, and every figure on the screen is part of it: the place was
 * open for so many hours, this room was used for so many of them, and the
 * rest it sat empty. What is pinned here is the arithmetic behind that
 * sentence — because a percentage an owner cannot trust is worse than no
 * percentage at all.
 */
class ResourceUtilizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Spa', 'slug' => 'nadia-util', 'business_email' => 'hi@nadia.test',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->owner = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();
        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();
        $this->owner = $this->owner->fresh();

        app(ProvisionSystemRoles::class)->forTenant($this->tenant);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);

        /* Open nine to five, every day, so the arithmetic below has a round
           number to divide into: eight hours is 480 minutes. */
        foreach (range(0, 6) as $day) {
            $this->location->hours()->create([
                'day_of_week' => $day, 'is_open' => true,
                'opens_at' => '09:00', 'closes_at' => '17:00', 'sort_order' => 0,
            ]);
        }

        $this->actingAs($this->owner);
    }

    // ------------------------------------------------------------ helpers

    private function room(array $attributes = []): Resource
    {
        $category = ResourceCategory::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => $attributes['category'] ?? 'Massage room'],
            ['is_active' => true],
        );

        unset($attributes['category']);

        return Resource::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'resource_category_id' => $category->id,
            'location_id' => $this->location->id,
            'name' => 'Single Room 01',
            'capacity' => 1,
            'is_active' => true,
        ]);
    }

    /** A booking with one service line in one room. */
    private function booking(Resource $room, string $date, string $at, int $minutes, array $attributes = []): Booking
    {
        $service = $attributes['service_name'] ?? 'Deep Tissue Massage';
        unset($attributes['service_name']);

        $booking = Booking::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'location_id' => $this->location->id,
            'reference' => Booking::nextReference(),
            'date' => $date,
            'starts_at' => $at,
            'ends_at' => Carbon::parse($at)->addMinutes($minutes)->format('H:i'),
            'status' => 'confirmed',
        ]);

        $booking->services()->create([
            'resource_id' => $room->id,
            'name' => $service,
            'minutes' => $minutes,
            'price_minor' => 12000,
            'sort_order' => 0,
        ]);

        return $booking;
    }

    private function today(): Carbon
    {
        return Carbon::today();
    }

    // ------------------------------------------------------------ the sum

    /**
     * Used over open, as a percentage, in the owner's own words.
     *
     * Four hours booked into a room that stood open eight is half the space
     * used and half of it empty — and those three numbers have to agree,
     * because the screen prints all three next to each other.
     */
    public function test_the_percentage_is_the_hours_used_over_the_hours_open(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->toDateString(), '09:00', 240);

        $figures = ResourceUtilization::forRange($this->today(), $this->today())[0];

        $this->assertSame(480, $figures['open_minutes']);
        $this->assertSame(480, $figures['available_minutes']);
        $this->assertSame(240, $figures['used_minutes']);
        $this->assertSame(240, $figures['idle_minutes']);
        $this->assertSame(50, $figures['utilization']);
        $this->assertSame(1, $figures['bookings']);
        $this->assertSame(12000, $figures['revenue_minor']);
    }

    /**
     * A room that seats two has twice the hours to sell.
     *
     * A couple room open eight hours holds sixteen resource-hours. One
     * booking filling half of them is half used — which is the honest
     * reading, and not what a per-room clock would say.
     */
    public function test_a_room_that_holds_two_has_twice_the_hours_to_sell(): void
    {
        $room = $this->room(['name' => 'Couple Room 01', 'capacity' => 2]);
        $this->booking($room, $this->today()->toDateString(), '09:00', 480);

        $figures = ResourceUtilization::forRange($this->today(), $this->today())[0];

        $this->assertSame(480, $figures['open_minutes']);
        $this->assertSame(960, $figures['available_minutes']);
        $this->assertSame(50, $figures['utilization']);
    }

    /**
     * A room nobody came to was empty, whatever the diary once said.
     */
    public function test_bookings_that_never_happened_do_not_fill_a_room(): void
    {
        $room = $this->room();

        foreach (['cancelled', 'declined', 'no-show', 'draft'] as $status) {
            $this->booking($room, $this->today()->toDateString(), '09:00', 120, ['status' => $status]);
        }

        $figures = ResourceUtilization::forRange($this->today(), $this->today())[0];

        $this->assertSame(0, $figures['used_minutes']);
        $this->assertSame(0, $figures['utilization']);
        $this->assertSame(0, $figures['bookings']);
    }

    /**
     * Read from the service lines, not the booking's own room.
     *
     * An appointment of a massage then a facial is two rooms at two times;
     * the booking header holds only the principal one, so counting from
     * there would credit the whole visit to one of them.
     */
    public function test_each_service_line_is_counted_against_its_own_room(): void
    {
        $first = $this->room(['name' => 'Single Room 01']);
        $second = $this->room(['name' => 'Single Room 02']);

        $booking = $this->booking($first, $this->today()->toDateString(), '09:00', 60);

        $booking->services()->create([
            'resource_id' => $second->id,
            'name' => 'Hydrating Facial',
            'minutes' => 120,
            'price_minor' => 9000,
            'sort_order' => 1,
        ]);

        $figures = collect(ResourceUtilization::forRange($this->today(), $this->today()))->keyBy('name');

        $this->assertSame(60, $figures['Single Room 01']['used_minutes']);
        $this->assertSame(120, $figures['Single Room 02']['used_minutes']);
    }

    /**
     * Three services in one room is one visit, not three.
     *
     * Counting lines would tell the owner the room is busier than it is.
     */
    public function test_a_visit_of_several_services_in_one_room_counts_once(): void
    {
        $room = $this->room();
        $booking = $this->booking($room, $this->today()->toDateString(), '09:00', 60);

        foreach (['Facial', 'Scalp'] as $index => $name) {
            $booking->services()->create([
                'resource_id' => $room->id,
                'name' => $name, 'minutes' => 30, 'price_minor' => 5000,
                'sort_order' => $index + 1,
            ]);
        }

        $figures = ResourceUtilization::forRange($this->today(), $this->today())[0];

        $this->assertSame(1, $figures['bookings']);
        $this->assertSame(120, $figures['used_minutes']);
    }

    /** Never past a hundred: a room reading 140% is a number nobody believes. */
    public function test_the_percentage_is_capped_at_a_hundred(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->toDateString(), '09:00', 900);

        $this->assertSame(100, ResourceUtilization::forRange($this->today(), $this->today())[0]['utilization']);
    }

    /** A resource nothing is open for reads nought rather than dividing by it. */
    public function test_a_resource_with_no_open_hours_is_not_divided_by_zero(): void
    {
        LocationHour::withoutGlobalScopes()
            ->where('location_id', $this->location->id)->delete();
        $this->room();

        $figures = ResourceUtilization::forRange($this->today(), $this->today())[0];

        $this->assertSame(0, $figures['available_minutes']);
        $this->assertSame(0, $figures['utilization']);
        $this->assertSame('closed', $figures['status']);
    }

    // ------------------------------------------------------------ the detail

    /**
     * The drill-down survives a booking that actually has a time.
     *
     * `bookings.date` is cast to a datetime, so the naive
     * "date . ' ' . starts_at" produced "2026-09-01 00:00:00 09:00:00" — two
     * times in one string, which Carbon refuses. It only ever showed on a
     * resource that had bookings, which is every resource an owner would
     * actually click.
     */
    public function test_the_detail_reads_the_time_of_a_booked_service(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->toDateString(), '10:00', 90);

        $detail = ResourceUtilization::detail($room, $this->today(), $this->today());

        $this->assertSame(90, $detail['used_minutes']);
        $this->assertCount(1, $detail['timeline']['blocks']);
        $this->assertSame('10:00', $detail['timeline']['blocks'][0]['from']);
        $this->assertSame('11:30', $detail['timeline']['blocks'][0]['to']);

        /* The hour breakdown puts the minutes where they happened: an hour
           of the ten o'clock and half of the eleven. */
        $byHour = collect($detail['by_hour'])->keyBy('hour');
        $this->assertSame(60, $byHour[10]['minutes']);
        $this->assertSame(30, $byHour[11]['minutes']);
        $this->assertSame(0, $byHour[9]['minutes']);
    }

    /** What was actually done in here, most minutes first. */
    public function test_the_detail_lists_the_services_performed(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->toDateString(), '09:00', 30, ['service_name' => 'Scalp']);
        $this->booking($room, $this->today()->toDateString(), '11:00', 120, ['service_name' => 'Deep Tissue']);

        $services = ResourceUtilization::detail($room, $this->today(), $this->today())['services'];

        $this->assertSame('Deep Tissue', $services[0]['name']);
        $this->assertSame(120, $services[0]['minutes']);
        $this->assertSame('Scalp', $services[1]['name']);
    }

    // ------------------------------------------------------------ the screen

    /** The page opens filled, so the bubbles have something to draw. */
    public function test_the_page_carries_the_figures_it_draws(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->toDateString(), '09:00', 240);

        $this->get(route('resources.utilization'))
            ->assertOk()
            ->assertSee(__('resources.utilization.subtitle'))
            ->assertViewHas('resources', fn (array $rows) => count($rows) === 1 && $rows[0]['utilization'] === 50);
    }

    /**
     * "utilization" is a page, not a resource called utilization.
     *
     * `resources/{resource}` binds its parameter to a model, so declared in
     * the wrong order this route would 404 on the lookup instead of opening.
     */
    public function test_the_page_is_not_mistaken_for_a_resource_id(): void
    {
        $this->get('/resources/utilization')->assertOk();
    }

    /**
     * A date range is a different question, so the server answers it again —
     * and it answers it with the app's own range vocabulary.
     *
     * The presets are SalesPeriod's, not a list invented for this screen:
     * one place decides what "last 7 days" means, so the Sales page and this
     * one cannot come to disagree about it.
     */
    public function test_the_range_filter_asks_a_different_window(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->copy()->subDays(3)->toDateString(), '09:00', 240);

        /* Three days back: inside the last seven, outside the last three,
           and nothing at all today. */
        $this->getJson(route('resources.utilization.data', ['range' => 'today']))
            ->assertOk()
            ->assertJsonPath('resources.0.used_minutes', 0);

        $this->getJson(route('resources.utilization.data', ['range' => 'last_3']))
            ->assertOk()
            ->assertJsonPath('resources.0.used_minutes', 0);

        $this->getJson(route('resources.utilization.data', ['range' => 'last_7']))
            ->assertOk()
            ->assertJsonPath('resources.0.used_minutes', 240);
    }

    /** Yesterday is yesterday, not "the last day with anything on it". */
    public function test_yesterday_is_its_own_day(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->copy()->subDay()->toDateString(), '09:00', 120);

        $this->getJson(route('resources.utilization.data', ['range' => 'yesterday']))
            ->assertOk()
            ->assertJsonPath('resources.0.used_minutes', 120);

        $this->getJson(route('resources.utilization.data', ['range' => 'today']))
            ->assertOk()
            ->assertJsonPath('resources.0.used_minutes', 0);
    }

    /**
     * The screen offers its own list, in its own order.
     *
     * Sales is read a month at a time; this is read a few days at a time,
     * because "is that room busy" is a question about this week.
     */
    public function test_the_screen_offers_the_ranges_an_owner_asks_for(): void
    {
        $this->get(route('resources.utilization'))
            ->assertOk()
            ->assertViewHas('presets', ['today', 'yesterday', 'last_3', 'last_7', 'custom']);
    }

    /**
     * A custom range that makes no sense falls back to the screen's default.
     *
     * SalesPeriod's rule, inherited rather than reinvented: a board that
     * showed nothing because somebody typed the dates backwards would look
     * like a salon with no bookings. Falling back to today shows something
     * true instead.
     */
    public function test_a_custom_range_entered_backwards_falls_back_to_today(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->toDateString(), '09:00', 60);

        $this->getJson(route('resources.utilization.data', [
            'range' => 'custom',
            'from' => $this->today()->toDateString(),
            'until' => $this->today()->copy()->subDays(4)->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('from', $this->today()->toDateString())
            ->assertJsonPath('resources.0.used_minutes', 60);
    }

    /** A custom range that does make sense is honoured. */
    public function test_a_custom_range_is_honoured(): void
    {
        $room = $this->room();
        $this->booking($room, $this->today()->copy()->subDays(2)->toDateString(), '09:00', 60);

        $this->getJson(route('resources.utilization.data', [
            'range' => 'custom',
            'from' => $this->today()->copy()->subDays(4)->toDateString(),
            'until' => $this->today()->toDateString(),
        ]))->assertOk()->assertJsonPath('resources.0.used_minutes', 60);
    }

    /** Reading how busy the rooms are is reading the rooms. */
    public function test_somebody_who_may_not_see_resources_is_refused(): void
    {
        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->firstOrFail()
            ->permissions()->where('permission', 'resources.view')->delete();

        $user = User::create([
            'first_name' => 'Dana', 'last_name' => 'Person',
            'email' => 'dana@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'user_id' => $user->id,
            'first_name' => 'Dana', 'last_name' => 'Person',
            'email' => $user->email, 'role' => 'front-desk',
        ]);

        $this->actingAs($user->fresh())
            ->get(route('resources.utilization'))
            ->assertStatus(403);
    }
}
