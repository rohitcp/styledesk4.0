<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Calendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The day, drawn against the clock.
 *
 * What these guard is the difference between the calendar and the listing:
 * the listing is a set of rows and this is a picture of a working day, so
 * what matters is that a block lands at the right minute in the right
 * person's column, that the parts nobody is working are marked as such, and
 * that the figures above it describe the day actually on screen.
 *
 * The availability rules are deliberately BookingAvailability's. A slot the
 * calendar draws as free and the booking screen then refuses is worse than no
 * calendar at all.
 */
class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-10-12';

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 11:20:00');

        $this->tenant = Tenant::create(['name' => 'Acme Spa', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'UTC',
            'is_primary' => true,
        ]);

        /* The day is built directly in several of these rather than through a
           request, and a client's own display name asks the tenant how it
           likes names formatted. Outside a request nothing has resolved one,
           so it is resolved here — the same state every one of these screens
           runs in. */
        tenancy()->initialize($this->tenant);

        $this->location->allHours()->create([
            'effective_from' => '2000-01-01',
            'day_of_week' => (int) Carbon::parse(self::DATE)->dayOfWeek,
            'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00', 'sort_order' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    // ------------------------------------------------------------- the day

    public function test_the_timeline_runs_to_the_business_hours(): void
    {
        $this->staff();

        $day = Calendar::day($this->location, self::DATE);

        $this->assertSame(9 * 60, $day['opens']);
        $this->assertSame(18 * 60, $day['closes']);
        $this->assertSame(15, $day['interval']);
    }

    /**
     * An appointment outside the hours widens the day rather than being cut.
     *
     * A booking taken out of hours is exactly the one somebody needs to see,
     * and a calendar that cropped it would hide it.
     */
    public function test_a_booking_past_closing_widens_the_timeline(): void
    {
        $staff = $this->staff();
        $this->booking($staff, '18:30', '19:30');

        $day = Calendar::day($this->location, self::DATE);

        $this->assertSame(20 * 60, $day['closes']);
    }

    public function test_a_block_lands_at_the_minute_it_starts(): void
    {
        $staff = $this->staff();
        $this->booking($staff, '10:30', '11:30');

        $day = Calendar::day($this->location, self::DATE);
        $card = $day['bookings'][0];

        $this->assertSame(630, $card['start']);
        $this->assertSame(690, $card['end']);
        $this->assertSame((int) $staff->id, $card['staff_id']);
    }

    /**
     * A draft holds nothing, so it draws nothing.
     *
     * The booking screen saves one as the receptionist types, and a block on
     * the calendar for a call that was abandoned is a slot nobody can have.
     */
    public function test_a_draft_is_not_on_the_calendar(): void
    {
        $staff = $this->staff();
        $this->booking($staff, '10:00', '11:00', ['status' => 'draft']);

        $day = Calendar::day($this->location, self::DATE);

        $this->assertSame([], $day['bookings']);
        $this->assertSame(0, $day['summary']['total']);
    }

    // ------------------------------------------------------- who is working

    /**
     * The rota is the column.
     *
     * Somebody rostered ten to four is available for those hours and greyed
     * out either side of them — an empty slot means "free to book", and a
     * slot outside a shift is not free.
     */
    public function test_a_rostered_shift_becomes_the_working_window(): void
    {
        $staff = $this->staff();

        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $staff->id, 'location_id' => $this->location->id,
            'date' => self::DATE, 'starts_at' => '10:00', 'ends_at' => '16:00',
            'break_minutes' => 30, 'type' => 'regular', 'status' => 'scheduled',
        ]);

        $column = Calendar::day($this->location, self::DATE)['staff'][0];

        $this->assertTrue($column['rostered']);
        $this->assertSame([['opens' => 600, 'closes' => 960]], $column['windows']);
        $this->assertSame(30, $column['break_minutes']);
    }

    /**
     * Nobody rostered is not the same as nobody available.
     *
     * A salon that keeps no rota would otherwise show every column greyed
     * out, so an empty rota means the location's hours stand — which is
     * exactly how the booking screen reads it.
     */
    public function test_an_empty_rota_falls_back_to_the_business_hours(): void
    {
        $this->staff();

        $column = Calendar::day($this->location, self::DATE)['staff'][0];

        $this->assertFalse($column['rostered']);
        $this->assertSame([['opens' => 540, 'closes' => 1080]], $column['windows']);
    }

    /** A cancelled shift is not a shift. */
    public function test_a_cancelled_shift_is_ignored(): void
    {
        $staff = $this->staff();

        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $staff->id, 'location_id' => $this->location->id,
            'date' => self::DATE, 'starts_at' => '10:00', 'ends_at' => '16:00',
            'type' => 'regular', 'status' => 'cancelled',
        ]);

        $this->assertFalse(Calendar::day($this->location, self::DATE)['staff'][0]['rostered']);
    }

    /** Somebody who performs no services cannot be booked, so has no column. */
    public function test_only_people_who_perform_services_get_a_column(): void
    {
        $this->staff();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Bea', 'last_name' => 'Nolan', 'email' => 'bea@acme.test',
            'role' => 'front-desk', 'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => false,
        ]);

        $this->assertCount(1, Calendar::day($this->location, self::DATE)['staff']);
    }

    // ------------------------------------------------------------- the line

    public function test_the_now_line_follows_the_location_clock(): void
    {
        $this->staff();

        $day = Calendar::day($this->location, self::DATE);

        $this->assertTrue($day['is_today']);
        $this->assertSame(11 * 60 + 20, $day['now']);
    }

    /** A "now" marker on next Tuesday is pointing at nothing. */
    public function test_another_day_has_no_now_line(): void
    {
        $this->staff();

        $day = Calendar::day($this->location, '2026-10-13');

        $this->assertFalse($day['is_today']);
        $this->assertNull($day['now']);
    }

    // ---------------------------------------------------------- the figures

    public function test_the_summary_counts_the_day_on_screen(): void
    {
        $staff = $this->staff();

        $this->booking($staff, '09:00', '10:00', ['status' => 'arrived']);
        $this->booking($staff, '10:00', '11:00', ['status' => 'completed']);
        $this->booking($staff, '11:00', '12:00', ['status' => 'cancelled']);

        $summary = Calendar::day($this->location, self::DATE)['summary'];

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['arrived']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(1, $summary['cancelled']);
        /* Revenue nobody is going to see is not a forecast, so the cancelled
           one is left out of the takings. */
        $this->assertSame(16000, $summary['revenue_minor']);
    }

    // ------------------------------------------------------- week and month

    /**
     * A week is seven columns, whatever the reader clicked.
     *
     * Monday first: a salon's week runs Monday to Sunday whatever the month
     * grid does, and a rota starting on Sunday would put the quietest day at
     * the front.
     */
    public function test_the_week_runs_monday_to_sunday_around_the_date(): void
    {
        $staff = $this->staff();
        $this->booking($staff, '10:00', '11:00');

        /* The 12th is a Monday; the date asked for is the Thursday after. */
        $week = Calendar::week($this->location, self::DATE);

        $this->assertSame('week', $week['view']);
        $this->assertCount(7, $week['days']);
        $this->assertSame('2026-10-12', $week['days'][0]['date']);
        $this->assertSame('2026-10-18', $week['days'][6]['date']);
        $this->assertSame('2026-10-05', $week['previous']);
        $this->assertSame('2026-10-19', $week['next']);
    }

    /** Each day holds its own, and the figures cover the whole week. */
    public function test_the_week_files_each_booking_under_its_day(): void
    {
        $staff = $this->staff();

        $this->booking($staff, '10:00', '11:00');
        $this->booking($staff, '14:00', '15:00', ['date' => '2026-10-15']);

        $week = Calendar::week($this->location, self::DATE);

        $this->assertCount(1, $week['days'][0]['bookings']);
        $this->assertCount(1, $week['days'][3]['bookings']);
        $this->assertSame(2, $week['summary']['total']);
    }

    /**
     * The line belongs in one column.
     *
     * Drawn across all seven it would say today is every day of the week.
     */
    public function test_the_week_says_which_column_now_belongs_in(): void
    {
        $this->staff();

        $week = Calendar::week($this->location, self::DATE);

        /* The 12th is the Monday, and the clock is stopped on it. */
        $this->assertSame(0, $week['now']['day']);
        $this->assertSame(11 * 60 + 20, $week['now']['minute']);
    }

    public function test_a_week_without_today_has_no_line(): void
    {
        $this->staff();

        $this->assertNull(Calendar::week($this->location, '2026-11-02')['now']);
    }

    /** The grid covers whole weeks, with the days either side marked. */
    public function test_the_month_grid_covers_whole_weeks(): void
    {
        $this->staff();

        $month = Calendar::month($this->location, self::DATE);

        $this->assertSame('month', $month['view']);
        /* The first of the month either side, not the same date shifted:
           "a month before the 31st" is a question with no good answer. */
        $this->assertSame('2026-09-01', $month['previous']);
        $this->assertSame('2026-11-01', $month['next']);

        $days = collect($month['weeks'])->flatten(1);

        $this->assertSame(0, $days->count() % 7);
        $this->assertTrue($days->first()['date'] < '2026-10-01');
        $this->assertFalse($days->first()['in_month']);
        $this->assertTrue($days->firstWhere('date', self::DATE)['in_month']);
    }

    /**
     * A cell names a handful and counts the rest.
     *
     * A cell with eleven chips in it is a cell nobody reads.
     */
    public function test_a_busy_month_cell_counts_what_it_cannot_name(): void
    {
        $staff = $this->staff();

        foreach (['09:00', '10:00', '11:00', '12:00', '13:00'] as $at) {
            $this->booking($staff, $at, '14:00');
        }

        $cell = collect(Calendar::month($this->location, self::DATE)['weeks'])
            ->flatten(1)
            ->firstWhere('date', self::DATE);

        $this->assertSame(5, $cell['count']);
        $this->assertCount(3, $cell['bookings']);
        $this->assertSame(2, $cell['more']);
    }

    // ------------------------------------------------------------ the page

    public function test_the_calendar_opens_on_the_day_view(): void
    {
        $this->staff();

        $this->actingAs($this->owner())
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertSee(__('calendar.title'));
    }

    /** A view nobody recognises is the day, which is what the desk wants. */
    public function test_an_unknown_view_falls_back_to_the_day(): void
    {
        $this->staff();

        $this->actingAs($this->owner())
            ->getJson(route('calendar.data', ['date' => self::DATE, 'view' => 'decade']))
            ->assertOk()
            ->assertJsonPath('view', 'day');
    }

    public function test_the_week_and_month_are_served_by_the_same_endpoint(): void
    {
        $this->staff();

        foreach (['week', 'month'] as $view) {
            $this->actingAs($this->owner())
                ->getJson(route('calendar.data', ['date' => self::DATE, 'view' => $view]))
                ->assertOk()
                ->assertJsonPath('view', $view);
        }
    }

    public function test_an_unreadable_date_is_simply_today(): void
    {
        $this->staff();

        $this->actingAs($this->owner())
            ->getJson(route('calendar.data', ['date' => 'not-a-date']))
            ->assertOk()
            ->assertJsonPath('date', Carbon::today()->toDateString());
    }

    /** An endpoint that hands out a day's appointments is the page. */
    public function test_the_day_is_behind_the_calendar_permission(): void
    {
        $this->staff();

        $this->actingAs($this->userWithout())
            ->getJson(route('calendar.data', ['date' => self::DATE]))
            ->assertForbidden();

        $this->actingAs($this->userWithout())
            ->get(route('calendar.index'))
            ->assertForbidden();
    }

    /**
     * The filters narrow the day, and a service filter reads the lines.
     *
     * A booking is its services, so "show me today's facials" means the
     * appointments that contain one — not only the ones that are nothing
     * else.
     */
    public function test_the_service_filter_keeps_only_the_bookings_that_contain_it(): void
    {
        $staff = $this->staff();
        $massage = $this->service();
        $facial = $this->service('Facial');

        $this->booking($staff, '09:00', '10:00');
        $wanted = $this->booking($staff, '11:00', '12:00', [], $facial);

        $day = Calendar::day($this->location, self::DATE, null, null, $facial->id);

        $this->assertCount(1, $day['bookings']);
        $this->assertSame((int) $wanted->id, $day['bookings'][0]['id']);
        $this->assertSame(1, $day['summary']['total']);
        $this->assertNotNull($massage);
    }

    public function test_the_staff_filter_keeps_one_column(): void
    {
        $susan = $this->staff();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Tom', 'last_name' => 'Reid', 'email' => 'tom@acme.test',
            'role' => 'service-provider', 'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);

        $day = Calendar::day($this->location, self::DATE, (int) $susan->id);

        $this->assertCount(1, $day['staff']);
        $this->assertSame((int) $susan->id, $day['staff'][0]['id']);
    }

    // --------------------------------------------------------- what is drawn

    /** Quarter hours by default; half hours where the desk asks for them. */
    public function test_the_ruler_can_be_drawn_in_half_hours(): void
    {
        $this->staff();

        $this->assertSame(15, Calendar::day($this->location, self::DATE)['interval']);
        $this->assertSame(30, Calendar::day($this->location, self::DATE, interval: 30)['interval']);
        /* A step the ruler cannot be drawn in is simply the default. */
        $this->assertSame(15, Calendar::day($this->location, self::DATE, interval: 7)['interval']);
    }

    /** A booking with something written on it says so. */
    public function test_a_booking_with_a_note_is_marked(): void
    {
        $staff = $this->staff();

        $this->booking($staff, '10:00', '11:00');
        $this->booking($staff, '12:00', '13:00', ['notes' => 'Sensitive scalp.']);

        $cards = collect(Calendar::day($this->location, self::DATE)['bookings'])->keyBy('start');

        $this->assertFalse($cards[600]['has_note']);
        $this->assertTrue($cards[720]['has_note']);
    }

    /** Every branch at once, where somebody asks for it. */
    public function test_all_locations_shows_every_branch(): void
    {
        $staff = $this->staff();
        $this->booking($staff, '10:00', '11:00');

        $elsewhere = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Northside', 'address_line1' => '2 North St', 'city' => 'Austin',
            'postal_code' => '78702', 'country' => 'US', 'timezone' => 'UTC',
        ]);

        $this->booking($staff, '14:00', '15:00', ['location_id' => $elsewhere->id]);

        $this->assertCount(1, Calendar::day($this->location, self::DATE)['bookings']);
        $this->assertCount(2, Calendar::day(null, self::DATE)['bookings']);

        $this->actingAs($this->owner())
            ->getJson(route('calendar.data', ['date' => self::DATE, 'location_id' => 'all']))
            ->assertOk()
            ->assertJsonCount(2, 'bookings');
    }

    // ------------------------------------------------------- whose calendar

    /**
     * A service provider sees their own diary.
     *
     * Applied on the server, not by hiding the filter: the staff id arrives
     * in a query string that a person can edit, and a calendar that only
     * looked private would be one somebody could page past.
     */
    public function test_a_provider_without_the_permission_sees_only_their_own(): void
    {
        $susan = $this->staff();

        $other = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Tom', 'last_name' => 'Reid', 'email' => 'tom@acme.test',
            'role' => 'service-provider', 'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);

        $this->booking($susan, '10:00', '11:00');
        $this->booking($other, '14:00', '15:00');

        $provider = $this->providerFor($susan);

        $day = $this->actingAs($provider)
            ->getJson(route('calendar.data', ['date' => self::DATE]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $day['staff']);
        $this->assertSame((int) $susan->id, $day['staff'][0]['id']);
        $this->assertCount(1, $day['bookings']);

        /* And asking for somebody else's changes nothing. */
        $asked = $this->actingAs($provider)
            ->getJson(route('calendar.data', ['date' => self::DATE, 'staff_id' => $other->id]))
            ->assertOk()
            ->json();

        $this->assertSame((int) $susan->id, $asked['staff'][0]['id']);
    }

    /** The filter is not offered to somebody who may not use it. */
    public function test_the_page_says_when_the_staff_filter_is_locked(): void
    {
        $susan = $this->staff();

        $this->actingAs($this->providerFor($susan))
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertViewHas('lockedToOwnStaff', true);

        $this->actingAs($this->owner())
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertViewHas('lockedToOwnStaff', false);
    }

    // ----------------------------------------------------------- the way in

    /**
     * A slot already answered three of the questions.
     *
     * The booking screen opens on the branch, the day and the hour the click
     * decided, so the desk only has to name the client and the service.
     */
    public function test_a_clicked_slot_opens_the_booking_screen_on_it(): void
    {
        $staff = $this->staff();

        $this->actingAs($this->owner())
            ->get(route('bookings.create', [
                'date' => self::DATE,
                'starts_at' => '14:30',
                'staff_id' => $staff->id,
                'location_id' => $this->location->id,
            ]))
            ->assertOk()
            ->assertViewHas('opening', [
                'date' => self::DATE,
                'starts_at' => '14:30',
                'staff_id' => $staff->id,
                'location_id' => $this->location->id,
            ]);
    }

    /** Anything unreadable is left for the desk rather than guessed at. */
    public function test_a_mangled_opening_is_ignored(): void
    {
        $this->actingAs($this->owner())
            ->get(route('bookings.create', ['date' => 'soon', 'starts_at' => '2pm']))
            ->assertOk()
            ->assertViewHas('opening', null);
    }

    // ------------------------------------------------------------- fixtures

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

    /** Somebody with a role that does not include the calendar. */
    private function userWithout(): User
    {
        if ($existing = User::where('email', 'robin@styledesk.test')->first()) {
            return $existing;
        }

        $user = User::create([
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'limited', 'name' => 'Limited',
        ]);

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id, 'role_id' => $role->id,
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'role' => 'front-desk',
            'location_id' => $this->location->id, 'is_active' => true,
        ]);

        return $user->fresh();
    }

    /** Somebody who may see the calendar, but only their own column of it. */
    private function providerFor(Staff $member): User
    {
        $user = User::create([
            'first_name' => 'Susan', 'last_name' => 'Pena',
            'email' => 'provider@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'provider', 'name' => 'Provider',
        ]);
        $role->permissions()->create(['permission' => 'calendar.view', 'scope' => 'own']);

        $member->forceFill(['user_id' => $user->id, 'role_id' => $role->id])->save();

        return $user->fresh();
    }

    private function staff(): Staff
    {
        return Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Susan', 'last_name' => 'Pena', 'email' => 'susan@acme.test',
            'role' => 'service-provider', 'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);
    }

    private function service(string $name = 'Massage'): Service
    {
        $service = Service::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => $name],
            ['duration_minutes' => 60, 'is_active' => true, 'color' => '#0d9488'],
        );

        ServicePrice::withoutGlobalScopes()->firstOrCreate(
            ['service_id' => $service->id, 'currency_code' => 'USD'],
            ['price_minor' => 8000, 'cash_price_minor' => 8000],
        );

        return $service;
    }

    /** @param  array<string, mixed>  $overrides */
    private function booking(Staff $staff, string $from, string $to, array $overrides = [], ?Service $service = null): Booking
    {
        $client = Client::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'email' => 'sarah@example.test'],
            [
                'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
                'first_name' => 'Sarah', 'last_name' => 'Johnson', 'status' => 'active',
            ],
        );

        $service ??= $this->service();

        $booking = Booking::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => 'BK-'.uniqid(),
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'date' => self::DATE,
            'starts_at' => $from,
            'ends_at' => $to,
            'minutes' => 60,
            'status' => 'confirmed',
            'subtotal_minor' => 8000,
            'total_minor' => 8000,
            'currency_code' => 'USD',
            'payment_status' => 'unpaid',
        ]);

        $booking->services()->create([
            'service_id' => $service->id,
            'name' => $service->name,
            'minutes' => 60,
            'price_minor' => 8000,
            'sort_order' => 0,
        ]);

        return $booking;
    }
}
