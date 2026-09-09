<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\ResourceCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\ResourceAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What every room and chair is doing on one day.
 *
 * The operational screen, and the one a receptionist acts on with somebody in
 * front of them — so what is pinned here is that the picture agrees with the
 * booking engine. A chart that draws a room as free while the booking screen
 * refuses to book it is worse than no chart, because the disagreement is
 * invisible until a client has been promised the room.
 */
class ResourceAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Spa', 'slug' => 'nadia-avail', 'business_email' => 'hi@nadia.test',
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

        /* Nine to five, every day: eight hours, so the arithmetic below has a
           round number to divide into. */
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

    private function service(array $attributes = []): Service
    {
        $category = ServiceCategory::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Massage'],
            ['is_active' => true],
        );

        return Service::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'service_category_id' => $category->id,
            'name' => 'Deep Tissue Massage',
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    /** A booking with one service line in one room. */
    private function booking(Resource $room, string $date, string $at, int $minutes, array $attributes = []): Booking
    {
        $service = $attributes['service'] ?? null;
        unset($attributes['service']);

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
            'service_id' => $service?->id,
            'name' => $service?->name ?? 'Deep Tissue Massage',
            'minutes' => $minutes,
            'price_minor' => 12000,
            'sort_order' => 0,
        ]);

        return $booking;
    }

    /** The one row, for a day that is deliberately not today. */
    private function row(?string $date = null): array
    {
        return ResourceAvailability::forDay(Carbon::parse($date ?? $this->someDay()))['resources'][0];
    }

    /**
     * A day that is not today.
     *
     * Deliberate: "now" is a moving part, and a test that runs at ten past
     * five would otherwise disagree with the same test run at noon. What
     * "now" does has its own test, which pins the clock.
     */
    private function someDay(): string
    {
        return Carbon::today()->addWeek()->toDateString();
    }

    // ---------------------------------------------------------- the bands

    /**
     * Free time is drawn as free, and booked time as booked.
     *
     * The bands are the row's background and the whole reason the chart can
     * be read at a glance: a gap that says "you can book this" and a gap that
     * says nothing are the same pixels otherwise.
     */
    public function test_the_day_is_split_into_what_is_free_and_what_is_taken(): void
    {
        $room = $this->room();
        $this->booking($room, $this->someDay(), '11:00', 60);

        $bands = collect($this->row()['bands']);

        $this->assertSame(
            [['available', 540, 660], ['booked', 660, 720], ['available', 720, 1020]],
            $bands->map(fn (array $band) => [$band['status'], $band['from'], $band['to']])->all(),
        );
    }

    /**
     * The room is held for longer than the client is in it.
     *
     * Preparation and cleaning come from the booking engine's own occupancy
     * window, so a room offered here at eleven is a room the booking screen
     * would also offer at eleven. Drawn as their own pieces because "being
     * turned around" and "somebody is in it" are different facts.
     */
    public function test_preparation_and_cleaning_hold_the_room_around_the_appointment(): void
    {
        $room = $this->room();
        $service = $this->service(['preparation_minutes' => 15, 'cleanup_minutes' => 10]);

        $this->booking($room, $this->someDay(), '11:00', 60, ['service' => $service]);

        $row = $this->row();

        $this->assertSame(
            [['prep', 645, 660], ['booked', 660, 720], ['cleanup', 720, 730]],
            collect($row['blocks'])
                ->sortBy('from')
                ->map(fn (array $block) => [$block['kind'], $block['from'], $block['to']])
                ->values()
                ->all(),
        );

        /* The room is unbookable across all eighty-five minutes, and only the
           sixty in the middle count as used. */
        $this->assertSame(60, $row['used_minutes']);
        $this->assertSame(
            [['available', 540, 645], ['booked', 645, 730], ['available', 730, 1020]],
            collect($row['bands'])->map(fn (array $band) => [$band['status'], $band['from'], $band['to']])->all(),
        );
    }

    /** A room nobody came to was free, whatever the diary once said. */
    public function test_bookings_that_never_happened_hold_no_room(): void
    {
        $room = $this->room();

        foreach (['cancelled', 'declined', 'no-show', 'draft'] as $status) {
            $this->booking($room, $this->someDay(), '11:00', 60, ['status' => $status]);
        }

        $row = $this->row();

        $this->assertSame([], $row['blocks']);
        $this->assertSame(0, $row['used_minutes']);
        $this->assertSame([['available', 540, 1020]], collect($row['bands'])
            ->map(fn (array $band) => [$band['status'], $band['from'], $band['to']])->all());
    }

    /**
     * A room being repainted is not half available.
     *
     * A block takes the whole resource however many places it has, and it is
     * told apart from an appointment on the chart — "closed for cleaning" and
     * "somebody has booked it" are different problems.
     */
    public function test_a_block_closes_the_room_and_is_not_drawn_as_a_booking(): void
    {
        $room = $this->room(['capacity' => 2]);

        ResourceBlock::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'resource_id' => $room->id,
            'reason' => 'maintenance',
            'starts_at' => $this->someDay().' 13:00:00',
            'ends_at' => $this->someDay().' 15:00:00',
        ]);

        $row = $this->row();

        $this->assertSame([], $row['blocks']);
        $this->assertSame(
            [['available', 540, 780], ['blocked', 780, 900], ['available', 900, 1020]],
            collect($row['bands'])->map(fn (array $band) => [$band['status'], $band['from'], $band['to']])->all(),
        );
    }

    /**
     * A couple room with one client in it still has a place free.
     *
     * The rule the booking engine works to: capacity is a number, not a yes.
     * Drawing the room as full on one booking is how a couple gets turned
     * away at four o'clock.
     */
    public function test_a_room_that_holds_two_stays_available_with_one_booking_in_it(): void
    {
        $room = $this->room(['name' => 'Couple Room 01', 'capacity' => 2]);
        $this->booking($room, $this->someDay(), '11:00', 60);

        $row = $this->row();

        $this->assertSame([['available', 540, 1020]], collect($row['bands'])
            ->map(fn (array $band) => [$band['status'], $band['from'], $band['to']])->all());

        /* A second booking over the same hour fills it, and the two sit in
           their own lanes rather than on top of each other. */
        $this->booking($room, $this->someDay(), '11:00', 60);

        $row = $this->row();

        $this->assertSame([0, 1], collect($row['blocks'])->pluck('lane')->all());
        $this->assertSame(
            [['available', 540, 660], ['booked', 660, 720], ['available', 720, 1020]],
            collect($row['bands'])->map(fn (array $band) => [$band['status'], $band['from'], $band['to']])->all(),
        );
    }

    /**
     * A room with shorter hours than the branch says so.
     *
     * The ends of the day are drawn as closed rather than as free, because a
     * receptionist offering four o'clock in a room that shuts at three is the
     * mistake this column exists to prevent.
     */
    public function test_a_resource_with_its_own_hours_is_closed_outside_them(): void
    {
        /* A second room on the branch's own hours, so the chart still spans
           the whole nine-to-five day. The window is the earliest opening to
           the latest closing across everything on it — one room keeping
           short hours narrows itself, not the day. */
        $this->room(['name' => 'Chair 01', 'category' => 'Chairs']);

        $room = $this->room(['name' => 'Treatment Room']);

        foreach (range(0, 6) as $day) {
            $room->hours()->create([
                'day' => $day, 'is_available' => true,
                'starts_at' => '10:00', 'ends_at' => '15:00',
            ]);
        }

        $row = collect(ResourceAvailability::forDay(Carbon::parse($this->someDay()))['resources'])
            ->firstWhere('name', 'Treatment Room');

        $this->assertSame(
            [['closed', 540, 600], ['available', 600, 900], ['closed', 900, 1020]],
            collect($row['bands'])->map(fn (array $band) => [$band['status'], $band['from'], $band['to']])->all(),
        );

        $this->assertSame(300, $row['open_minutes']);
    }

    // ------------------------------------------------------------- the now

    /**
     * "Available now" is only ever said about now.
     *
     * A day that is not today has no now, so the column says what it can —
     * how much of the room went — rather than saying "available" about last
     * Tuesday. A reader who finds that column wrong once stops reading it.
     */
    public function test_a_day_that_is_not_today_makes_no_claim_about_right_now(): void
    {
        $room = $this->room();
        $this->booking($room, $this->someDay(), '11:00', 60);

        $day = ResourceAvailability::forDay(Carbon::parse($this->someDay()));

        $this->assertNull($day['resources'][0]['status']);
        $this->assertNull($day['now']);
        $this->assertSame(0, $day['summary']['available']);
        $this->assertSame(1, $day['summary']['bookings']);
    }

    /**
     * On today, where the room stands and when it comes back.
     *
     * The three questions the screen exists for, answered from one clock —
     * the server's, because a browser working "now" out for itself puts the
     * line in the wrong place for every salon in another timezone.
     */
    public function test_today_says_what_is_in_the_room_and_when_it_is_free(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 30));

        $room = $this->room();
        $this->booking($room, Carbon::today()->toDateString(), '11:00', 60);
        $this->booking($room, Carbon::today()->toDateString(), '14:00', 60);

        $day = ResourceAvailability::forDay(Carbon::today());
        $row = $day['resources'][0];

        $this->assertSame(690, $day['now']);
        $this->assertSame('in-use', $row['status']);
        $this->assertSame(660, $row['current']['from']);
        $this->assertSame(840, $row['next']['from']);
        /* In use, so there is nothing to be available until. */
        $this->assertNull($row['available_until']);
        $this->assertSame(1, $day['summary']['in_use']);

        /* Half an hour later the client has gone, and the answer is the one
           the receptionist actually wants: free, until two. */
        Carbon::setTestNow(Carbon::today()->setTime(12, 30));

        $row = ResourceAvailability::forDay(Carbon::today())['resources'][0];

        $this->assertSame('available', $row['status']);
        $this->assertSame(840, $row['available_until']);

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------ the page

    public function test_the_board_renders_for_somebody_who_may_see_the_rooms(): void
    {
        $this->room();

        $this->get(route('resources.availability', ['date' => $this->someDay()]))
            ->assertOk()
            ->assertSee('Resource availability')
            ->assertSee('data-vue-component="ResourceAvailability"', false);
    }

    public function test_the_day_can_be_asked_for_again_as_json(): void
    {
        $room = $this->room();
        $this->booking($room, $this->someDay(), '11:00', 60);

        $this->getJson(route('resources.availability.data', ['date' => $this->someDay()]))
            ->assertOk()
            ->assertJsonPath('date', $this->someDay())
            ->assertJsonPath('resources.0.name', 'Single Room 01')
            ->assertJsonPath('summary.bookings', 1);
    }

    /**
     * Seeing that a room is busy is the resource list's business; seeing
     * whose appointment it is is the diary's.
     */
    public function test_the_booking_panel_needs_permission_to_see_the_diary(): void
    {
        $room = $this->room();
        $booking = $this->booking($room, $this->someDay(), '11:00', 60);

        $this->getJson(route('resources.availability.booking', $booking))
            ->assertOk()
            ->assertJsonPath('reference', $booking->reference);

        $receptionist = User::create([
            'first_name' => 'Rae', 'last_name' => 'Ortiz',
            'email' => 'rae@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $receptionist->markEmailAsVerified();
        $receptionist->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($receptionist->fresh())
            ->getJson(route('resources.availability.booking', $booking))
            ->assertForbidden();
    }

    public function test_the_board_is_refused_to_somebody_who_may_not_see_the_rooms(): void
    {
        $stranger = User::create([
            'first_name' => 'Sam', 'last_name' => 'Doe',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $stranger->markEmailAsVerified();
        $stranger->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($stranger->fresh())
            ->get(route('resources.availability'))
            ->assertForbidden();
    }
}
