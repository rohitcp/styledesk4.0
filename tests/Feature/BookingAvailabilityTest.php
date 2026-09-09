<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\BookingAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Which times a booking can actually start at.
 *
 * The screen used to offer 6am to 9pm every day, whatever was chosen, so
 * every reason a slot was impossible — a closed branch, a stylist not on
 * shift, an hour's work offered at half five — was found out afterwards, by
 * the person the client was on the phone to.
 */
class BookingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    /** A Wednesday, so weekday hours are the ordinary case. */
    private const DATE = '2026-09-09';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Acme Salon', 'slug' => 'acme-availability',
            'default_appointment_interval' => 30,
        ]);

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

        $this->actingAs($this->owner());
    }

    private function owner(): User
    {
        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    /** Nine to six on the day under test. */
    private function opensNineToSix(string $opens = '09:00', string $closes = '18:00'): void
    {
        $this->location->allHours()->create([
            'effective_from' => '2000-01-01',
            'day_of_week' => (int) Carbon::parse(self::DATE)->dayOfWeek,
            'is_open' => true, 'opens_at' => $opens, 'closes_at' => $closes, 'sort_order' => 0,
        ]);
    }

    private function service(int $minutes, array $extra = []): Service
    {
        return Service::withoutGlobalScopes()->create($extra + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut', 'duration_minutes' => $minutes, 'is_active' => true,
        ]);
    }

    private function staff(): Staff
    {
        return Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Susan', 'last_name' => 'Pena',
            'email' => 'susan@acme.test', 'role' => 'service-provider',
            'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);
    }

    private function slots(array $serviceIds = [], ?int $staffId = null, string $date = self::DATE): array
    {
        return BookingAvailability::for($this->location, $date, $staffId, $serviceIds)['slots'];
    }

    // ------------------------------------------------------- location hours

    /**
     * The example from the spec: nine to six, an hour's work, and the last
     * slot is five — not half past, which would finish at half six with the
     * door locked.
     */
    public function test_the_last_slot_leaves_room_for_the_whole_appointment(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);

        $slots = $this->slots([$service->id]);

        $this->assertSame('09:00', $slots[0]);
        $this->assertContains('17:00', $slots);
        $this->assertNotContains('17:30', $slots);
        $this->assertNotContains('08:30', $slots);
    }

    public function test_a_booking_cannot_start_before_the_branch_opens(): void
    {
        $this->opensNineToSix('10:00', '16:00');
        $service = $this->service(30);

        $slots = $this->slots([$service->id]);

        $this->assertNotContains('09:30', $slots);
        $this->assertSame('10:00', $slots[0]);
        $this->assertSame('15:30', end($slots));
    }

    /**
     * A day with no hours at all is a day the branch is shut, and the screen
     * says which of the possible nothings this is.
     */
    public function test_a_closed_day_is_named_as_closed(): void
    {
        $service = $this->service(30);

        $answer = BookingAvailability::for($this->location, self::DATE, null, [$service->id]);

        $this->assertSame([], $answer['slots']);
        $this->assertTrue($answer['closed']);
        $this->assertSame('closed_date', $answer['reason']);
    }

    /**
     * Open, but not for long enough — a different fact from being closed.
     */
    public function test_an_appointment_longer_than_the_day_is_not_offered(): void
    {
        $this->opensNineToSix('09:00', '10:00');
        $service = $this->service(120);

        $answer = BookingAvailability::for($this->location, self::DATE, null, [$service->id]);

        $this->assertSame([], $answer['slots']);
        $this->assertFalse($answer['closed']);
        $this->assertSame('too_long', $answer['reason']);
    }

    /**
     * The appointment is as long as everything it occupies, not as long as
     * the service is billed for. A chair held for ninety minutes and quoted
     * as sixty is how a day silently overbooks.
     */
    public function test_the_time_around_a_service_counts_towards_its_length(): void
    {
        $this->opensNineToSix();

        $service = $this->service(60, ['preparation_minutes' => 15, 'cleanup_minutes' => 15]);

        $this->assertSame(90, BookingAvailability::durationFor([$service->id]));

        $slots = $this->slots([$service->id]);

        $this->assertContains('16:30', $slots);
        $this->assertNotContains('17:00', $slots);
    }

    public function test_two_services_are_added_together(): void
    {
        $this->opensNineToSix();

        $cut = $this->service(60);
        $colour = $this->service(90);

        $this->assertSame(150, BookingAvailability::durationFor([$cut->id, $colour->id]));

        $this->assertNotContains('16:00', $this->slots([$cut->id, $colour->id]));
        $this->assertContains('15:30', $this->slots([$cut->id, $colour->id]));
    }

    // -------------------------------------------------------------- the rota

    public function test_only_the_hours_a_chosen_staff_member_works_are_offered(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);
        $staff = $this->staff();

        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $staff->id, 'location_id' => $this->location->id,
            'date' => self::DATE, 'starts_at' => '12:00', 'ends_at' => '16:00',
            'status' => 'scheduled', 'publish_status' => 'published',
        ]);

        $slots = $this->slots([$service->id], $staff->id);

        $this->assertNotContains('09:00', $slots);
        $this->assertContains('12:00', $slots);
        $this->assertContains('15:00', $slots);
        $this->assertNotContains('15:30', $slots);
    }

    /**
     * A business that keeps no rota is not a business where nobody works.
     */
    public function test_a_staff_member_with_no_shift_falls_back_to_the_branch_hours(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);
        $staff = $this->staff();

        $slots = $this->slots([$service->id], $staff->id);

        $this->assertContains('09:00', $slots);
        $this->assertContains('17:00', $slots);
    }

    /**
     * A cancelled shift is not a shift.
     */
    public function test_a_cancelled_shift_does_not_narrow_the_day(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);
        $staff = $this->staff();

        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $staff->id, 'location_id' => $this->location->id,
            'date' => self::DATE, 'starts_at' => '12:00', 'ends_at' => '16:00',
            'status' => 'cancelled', 'publish_status' => 'published',
        ]);

        $this->assertContains('09:00', $this->slots([$service->id], $staff->id));
    }

    // ------------------------------------------------------------- conflicts

    public function test_a_time_somebody_is_already_booked_over_is_not_offered(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);
        $staff = $this->staff();

        Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => 'BK-1', 'guest_name' => 'Walk in',
            'staff_id' => $staff->id, 'location_id' => $this->location->id,
            'date' => self::DATE, 'starts_at' => '11:00', 'ends_at' => '12:00',
            'minutes' => 60, 'status' => 'confirmed', 'currency_code' => 'USD',
        ]);

        $slots = $this->slots([$service->id], $staff->id);

        $this->assertNotContains('11:00', $slots);
        /* Half ten runs to half eleven, into the booking. */
        $this->assertNotContains('10:30', $slots);
        /* Back to back is how a busy chair is run, so noon is still free. */
        $this->assertContains('12:00', $slots);
        $this->assertContains('10:00', $slots);
    }

    public function test_a_cancelled_booking_frees_its_time_again(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);
        $staff = $this->staff();

        Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => 'BK-2', 'guest_name' => 'Walk in',
            'staff_id' => $staff->id, 'location_id' => $this->location->id,
            'date' => self::DATE, 'starts_at' => '11:00', 'ends_at' => '12:00',
            'minutes' => 60, 'status' => 'cancelled', 'currency_code' => 'USD',
        ]);

        $this->assertContains('11:00', $this->slots([$service->id], $staff->id));
    }

    /**
     * A draft holds nothing.
     *
     * The booking screen saves one as the receptionist types, so a slot
     * reserved by a call that was abandoned is a slot nobody could ever have.
     * It becomes real when somebody confirms it.
     */
    public function test_a_draft_does_not_reserve_the_time_it_names(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);
        $staff = $this->staff();

        Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => 'BK-20260909-00001', 'guest_name' => 'Walk in',
            'staff_id' => $staff->id, 'location_id' => $this->location->id,
            'date' => self::DATE, 'starts_at' => '11:00', 'ends_at' => '12:00',
            'minutes' => 60, 'status' => 'draft', 'currency_code' => 'USD',
        ]);

        $this->assertContains('11:00', $this->slots([$service->id], $staff->id));
    }

    /**
     * Somebody else's diary is not this stylist's.
     */
    public function test_another_staff_members_booking_does_not_block_this_one(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);
        $susan = $this->staff();

        $other = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Ben', 'last_name' => 'Ali', 'email' => 'ben@acme.test',
            'role' => 'service-provider', 'is_active' => true,
        ]);

        Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => 'BK-3', 'guest_name' => 'Walk in',
            'staff_id' => $other->id, 'location_id' => $this->location->id,
            'date' => self::DATE, 'starts_at' => '11:00', 'ends_at' => '12:00',
            'minutes' => 60, 'status' => 'confirmed', 'currency_code' => 'USD',
        ]);

        $this->assertContains('11:00', $this->slots([$service->id], $susan->id));
    }

    // ------------------------------------------------------------ the endpoint

    public function test_the_endpoint_answers_the_times_and_the_reason(): void
    {
        $this->opensNineToSix();
        $service = $this->service(60);

        $this->getJson(route('bookings.availability', [
            'date' => self::DATE,
            'location_id' => $this->location->id,
            'service_ids' => [$service->id],
        ]))
            ->assertOk()
            ->assertJsonPath('closed', false)
            ->assertJsonPath('minutes', 60)
            ->assertJsonFragment(['17:00']);
    }

    public function test_the_endpoint_says_why_a_closed_day_is_empty(): void
    {
        $service = $this->service(60);

        $this->getJson(route('bookings.availability', [
            'date' => self::DATE,
            'location_id' => $this->location->id,
            'service_ids' => [$service->id],
        ]))
            ->assertOk()
            ->assertJsonPath('closed', true)
            ->assertJsonPath('slots', [])
            ->assertJsonPath('message', __('bookings.when.closed_date'));
    }

    /**
     * The times come from the server, so a client asking about a date it has
     * no business seeing gets the same answer as anybody else — nothing about
     * another salon's day is reachable through it.
     */
    public function test_another_businesss_location_is_not_readable(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-availability']);
        $theirs = Location::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'name' => 'Theirs', 'address_line1' => '2 Elm', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
        ]);

        $this->getJson(route('bookings.availability', [
            'date' => self::DATE,
            'location_id' => $theirs->id,
        ]))->assertStatus(422);
    }
}
