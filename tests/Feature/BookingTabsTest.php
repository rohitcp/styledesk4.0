<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The bookings page as a working view rather than a table of everything.
 *
 * The question a front desk opens this page with is "who is coming in", not
 * "show me every appointment ever taken" — so the tabs are the feature, and
 * the one that matters most is the queue: today's confirmed bookings, sorted
 * by time, with how late each one is.
 */
class BookingTabsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        /* Mid-morning, so "late" and "early" both have something to mean. */
        Carbon::setTestNow('2026-10-12 11:00:00');

        $this->tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);
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

        $this->owner = $this->makeOwner();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeOwner(): User
    {
        $user = User::create([
            'first_name' => 'Emma', 'last_name' => 'Martin',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    private function booking(string $date, string $startsAt, string $status = 'confirmed', string $name = 'Mia'): Booking
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => $name, 'last_name' => 'Baker', 'status' => 'active',
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Deep Tissue Massage', 'duration_minutes' => 60, 'is_active' => true,
        ]);

        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Mai', 'last_name' => 'Nguyen',
            'email' => 'mai'.uniqid().'@smilespa.test', 'role' => 'service-provider',
            'location_id' => $this->location->id, 'is_active' => true, 'provides_services' => true,
        ]);

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'date' => $date,
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addHour()->format('H:i'),
            'minutes' => 60,
            'status' => $status, 'total_minor' => 6500, 'currency_code' => 'USD',
        ]);

        $booking->services()->create([
            'service_id' => $service->id, 'name' => $service->name,
            'minutes' => 60, 'price_minor' => 6500,
        ]);

        return $booking;
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(array $params = []): array
    {
        return $this->actingAs($this->owner)
            ->getJson(route('bookings.data', $params))
            ->assertOk()
            ->json('data');
    }

    // ------------------------------------------------------------ the tabs

    /**
     * The question a front desk opens this page with is "who is coming in",
     * so that is what it opens on.
     */
    public function test_the_page_opens_on_today(): void
    {
        $this->booking('2026-10-12', '14:00');
        $this->booking('2026-10-20', '14:00', 'confirmed', 'Later');

        $rows = $this->rows();

        $this->assertCount(1, $rows);
        $this->assertSame('Mia Baker', $rows[0]['name']);
    }

    public function test_next_three_days_leaves_out_today_and_the_fourth_day(): void
    {
        $this->booking('2026-10-12', '14:00', 'confirmed', 'Today');
        $this->booking('2026-10-13', '14:00', 'confirmed', 'Tomorrow');
        $this->booking('2026-10-15', '14:00', 'confirmed', 'Third');
        $this->booking('2026-10-16', '14:00', 'confirmed', 'Fourth');

        $names = collect($this->rows(['tab' => 'next-3']))->pluck('name');

        $this->assertEqualsCanonicalizing(['Tomorrow Baker', 'Third Baker'], $names->all());
    }

    public function test_the_month_tab_shows_the_month_it_is_asked_for(): void
    {
        $this->booking('2026-10-02', '14:00', 'confirmed', 'October');
        $this->booking('2026-11-02', '14:00', 'confirmed', 'November');

        $this->assertSame(
            ['October Baker'],
            collect($this->rows(['tab' => 'month', 'month' => '2026-10']))->pluck('name')->all(),
        );

        $this->assertSame(
            ['November Baker'],
            collect($this->rows(['tab' => 'month', 'month' => '2026-11']))->pluck('name')->all(),
        );
    }

    /**
     * The queue is today's confirmed bookings, because checking somebody in
     * is what moves them off it.
     */
    public function test_the_queue_is_todays_confirmed_bookings_only(): void
    {
        $this->booking('2026-10-12', '10:00', 'confirmed', 'Waiting');
        $this->booking('2026-10-12', '10:00', 'arrived', 'Here');
        $this->booking('2026-10-12', '10:00', 'completed', 'Done');
        $this->booking('2026-10-13', '10:00', 'confirmed', 'Tomorrow');

        $names = collect($this->rows(['tab' => 'check-in']))->pluck('name');

        $this->assertSame(['Waiting Baker'], $names->all());
    }

    /** Earliest first: a queue read in any other order is not a queue. */
    public function test_the_queue_is_ordered_by_appointment_time(): void
    {
        $this->booking('2026-10-12', '15:00', 'confirmed', 'Third');
        $this->booking('2026-10-12', '09:00', 'confirmed', 'First');
        $this->booking('2026-10-12', '12:00', 'confirmed', 'Second');

        $this->assertSame(
            ['First Baker', 'Second Baker', 'Third Baker'],
            collect($this->rows(['tab' => 'check-in']))->pluck('name')->all(),
        );
    }

    public function test_the_more_tabs_each_show_their_own_status(): void
    {
        $this->booking('2026-10-01', '10:00', 'completed', 'Done');
        $this->booking('2026-10-02', '10:00', 'cancelled', 'Called off');
        $this->booking('2026-10-03', '10:00', 'no-show', 'Absent');
        $this->booking('2026-10-04', '10:00', 'declined', 'Refused');

        foreach ([
            'completed' => 'Done Baker',
            'cancelled' => 'Called off Baker',
            'no-shows' => 'Absent Baker',
            'declined' => 'Refused Baker',
        ] as $tab => $name) {
            $this->assertSame([$name], collect($this->rows(['tab' => $tab]))->pluck('name')->all(), $tab);
        }

        $this->assertCount(4, $this->rows(['tab' => 'all']));
    }

    /** A tab nobody asked for is Today, not an error and not everything. */
    public function test_an_unknown_tab_falls_back_to_today(): void
    {
        $this->booking('2026-10-12', '14:00', 'confirmed', 'Today');
        $this->booking('2026-10-20', '14:00', 'confirmed', 'Later');

        $this->assertSame(['Today Baker'], collect($this->rows(['tab' => 'whatever']))->pluck('name')->all());
    }

    // --------------------------------------------------------- the arrival

    /**
     * "12 min late" is what somebody at the desk acts on. The scheduled time
     * alone makes them read a clock and do the arithmetic themselves.
     */
    public function test_the_queue_says_how_late_each_client_is(): void
    {
        $this->booking('2026-10-12', '10:48', 'confirmed', 'Late');

        $row = collect($this->rows(['tab' => 'check-in']))->firstWhere('name', 'Late Baker');

        $this->assertSame(__('bookings.tabs.arrival.late', ['count' => 12]), $row['arrival']);
    }

    public function test_a_client_due_within_the_hour_is_shown_as_early(): void
    {
        $this->booking('2026-10-12', '11:30', 'confirmed', 'Early');

        $row = collect($this->rows(['tab' => 'check-in']))->firstWhere('name', 'Early Baker');

        $this->assertSame(__('bookings.tabs.arrival.early', ['count' => 30]), $row['arrival']);
    }

    /**
     * Somebody due at eight is not "266 min early" at half three. They are
     * simply later, and a column of four-figure numbers is one nobody reads.
     */
    public function test_an_appointment_hours_away_is_later_rather_than_early(): void
    {
        $this->booking('2026-10-12', '17:00', 'confirmed', 'Evening');

        $row = collect($this->rows(['tab' => 'check-in']))->firstWhere('name', 'Evening Baker');

        $this->assertSame(__('bookings.tabs.arrival.later'), $row['arrival']);
    }

    /** A completed appointment being nine minutes late is not news. */
    public function test_a_settled_booking_has_no_arrival_indicator(): void
    {
        $this->booking('2026-10-12', '09:00', 'completed', 'Done');

        $row = collect($this->rows(['tab' => 'today']))->firstWhere('name', 'Done Baker');

        $this->assertNull($row['arrival']);
        $this->assertSame(__('bookings.tabs.arrival.done'), $row['checkin']);
    }

    // ------------------------------------------------------------ filtering

    /**
     * A filter narrows the tab rather than replacing it: choosing a therapist
     * on Today should not throw the reader into every booking ever taken.
     */
    public function test_a_filter_narrows_the_tab_rather_than_replacing_it(): void
    {
        $today = $this->booking('2026-10-12', '14:00', 'confirmed', 'Today');
        $this->booking('2026-10-20', '14:00', 'confirmed', 'Later');

        $rows = $this->rows(['tab' => 'today', 'staff' => $today->staff_id]);

        $this->assertCount(1, $rows);
        $this->assertSame('Today Baker', $rows[0]['name']);
    }

    public function test_bookings_can_be_filtered_by_service_and_payment(): void
    {
        $booking = $this->booking('2026-10-12', '14:00');
        $other = $this->booking('2026-10-12', '16:00', 'confirmed', 'Other');
        $booking->update(['payment_status' => 'paid']);

        $serviceId = $booking->services->first()->service_id;

        $this->assertSame(
            ['Mia Baker'],
            collect($this->rows(['tab' => 'today', 'service' => $serviceId]))->pluck('name')->all(),
        );

        $this->assertSame(
            ['Mia Baker'],
            collect($this->rows(['tab' => 'today', 'payment' => 'paid']))->pluck('name')->all(),
        );
    }

    // ------------------------------------------------------------ the page

    public function test_the_page_renders_the_tabs_and_the_summary(): void
    {
        $this->booking('2026-10-12', '14:00');

        $this->actingAs($this->owner)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee(__('bookings.tabs.today'))
            ->assertSee(__('bookings.tabs.next-3'))
            ->assertSee(__('bookings.tabs.month'))
            ->assertSee(__('bookings.tabs.check-in'))
            ->assertSee(__('bookings.tabs.more'))
            /* The five numbers above today's table. Asserted on this one
               rather than the queue card: "Check-in pending" is also the
               tab's own label, so it proves nothing about the cards. */
            ->assertSee(__('bookings.tabs.summary.total'));
    }

    /** Five numbers above a table nobody works in all day is noise. */
    public function test_the_summary_cards_are_only_on_today(): void
    {
        $this->booking('2026-10-12', '14:00');

        $this->actingAs($this->owner)
            ->get(route('bookings.index', ['tab' => 'month']))
            ->assertOk()
            ->assertDontSee(__('bookings.tabs.summary.total'));
    }

    /**
     * Only actions that mean something from where the booking has got to —
     * an action offered here and refused on the booking page would be a menu
     * that lies.
     */
    public function test_a_row_offers_only_the_actions_its_status_allows(): void
    {
        $this->booking('2026-10-12', '14:00', 'confirmed', 'Open');
        $this->booking('2026-10-12', '09:00', 'completed', 'Done');

        $rows = collect($this->rows(['tab' => 'today']))->keyBy('name');

        $labels = fn (string $name) => collect($rows[$name]['menu'])->pluck('label')->filter()->all();

        $this->assertContains(__('bookings.status.check-in.action'), $labels('Open Baker'));
        $this->assertContains(__('bookings.status.cancelled.action'), $labels('Open Baker'));

        $this->assertNotContains(__('bookings.status.check-in.action'), $labels('Done Baker'));
        $this->assertNotContains(__('bookings.status.cancelled.action'), $labels('Done Baker'));
    }
}
