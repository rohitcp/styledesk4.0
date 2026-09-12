<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\BookingAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A time that has already been is not availability.
 *
 * The screen offered nine in the morning at nine at night, and a receptionist
 * working down a list has no reason to doubt it — so the booking was made,
 * sat in yesterday's half of the diary, and was found when the client did not
 * arrive for it.
 *
 * Every time here is measured on the branch's own clock. The server runs on
 * UTC and the desk does not, which is the whole difficulty: a salon in Austin
 * is still on the previous day for five hours of the server's morning.
 */
class PastSlotTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        /* 21:00 in Austin, which is 02:00 the NEXT day in UTC. Chosen so
           every assertion below fails if anything reaches for the server's
           clock or the server's date instead of the branch's. */
        Carbon::setTestNow('2026-09-12 02:00:00');

        $this->tenant = Tenant::create([
            'name' => 'Acme Salon', 'slug' => 'acme-past', 'country_code' => 'US',
            /* The business's own zone, which is what answers when no branch
               has been chosen yet. */
            'timezone' => 'America/Chicago',
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

        foreach (range(0, 6) as $day) {
            $this->location->hours()->create([
                'effective_from' => Location::EPOCH,
                'day_of_week' => $day, 'sort_order' => 0, 'is_open' => true,
                'opens_at' => '09:00', 'closes_at' => '22:00',
            ]);
        }

        $this->actingAs($this->owner());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function service(int $minutes = 30): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Beard trim', 'duration_minutes' => $minutes, 'is_active' => true,
        ]);

        $service->prices()->create(['currency_code' => 'USD', 'price_minor' => 2000]);

        return $service;
    }

    /** The branch's today, which is not the server's. */
    private const TODAY = '2026-09-11';

    // --------------------------------------------------------- the slot list

    public function test_this_mornings_slots_are_gone_by_the_evening(): void
    {
        $slots = BookingAvailability::for($this->location, self::TODAY, null, [$this->service()->id])['slots'];

        foreach (['09:00', '10:00', '12:00', '17:00', '20:30'] as $past) {
            $this->assertNotContains($past, $slots, $past.' has already been and gone.');
        }
    }

    public function test_the_times_still_to_come_today_are_offered(): void
    {
        $slots = BookingAvailability::for($this->location, self::TODAY, null, [$this->service()->id])['slots'];

        $this->assertContains('21:15', $slots);
        $this->assertContains('21:30', $slots);
        $this->assertNotEmpty($slots);
    }

    /**
     * The slot has to start in the future, not merely be the current one.
     *
     * At 21:07 the nine-o'clock slot has begun. An appointment cannot start
     * seven minutes ago.
     */
    public function test_the_slot_that_has_just_begun_is_no_longer_offered(): void
    {
        Carbon::setTestNow('2026-09-12 02:07:00');

        $slots = BookingAvailability::for($this->location, self::TODAY, null, [$this->service()->id])['slots'];

        $this->assertNotContains('21:00', $slots);
        $this->assertContains('21:15', $slots);
    }

    public function test_a_future_date_still_offers_the_whole_day(): void
    {
        $slots = BookingAvailability::for($this->location, '2026-09-12', null, [$this->service()->id])['slots'];

        $this->assertContains('09:00', $slots);
        $this->assertContains('12:00', $slots);
    }

    /**
     * A date wholly in the past is somebody writing up yesterday's walk-in.
     *
     * Deliberately untouched: refusing it would make the books unfixable, and
     * it is a different question from "today, but this morning".
     */
    public function test_a_past_date_is_left_alone(): void
    {
        $slots = BookingAvailability::for($this->location, '2026-09-10', null, [$this->service()->id])['slots'];

        $this->assertContains('09:00', $slots);
    }

    public function test_a_day_with_nothing_left_says_so_rather_than_saying_nothing_is_free(): void
    {
        /* 21:50, with a thirty-minute service and a 22:00 close: the day is
           over, which is a different fact from every slot being taken. */
        Carbon::setTestNow('2026-09-12 02:50:00');

        $answer = BookingAvailability::for($this->location, self::TODAY, null, [$this->service()->id]);

        $this->assertSame([], $answer['slots']);
        $this->assertSame('day_over', $answer['reason']);
        $this->assertFalse($answer['closed'], 'The branch is open; the day is simply spent.');
    }

    /** No branch chosen yet, so the fallback range is narrowed the same way. */
    public function test_the_fallback_range_drops_past_times_too(): void
    {
        $slots = BookingAvailability::for(null, self::TODAY, null, [$this->service()->id])['slots'];

        $this->assertNotContains('09:00', $slots);
    }

    // ------------------------------------------------------- over the wire

    public function test_the_availability_endpoint_hides_the_past(): void
    {
        $service = $this->service();

        $response = $this->getJson(route('bookings.availability', [
            'date' => self::TODAY,
            'location_id' => $this->location->id,
            'service_ids' => [$service->id],
        ]))->assertOk();

        $this->assertNotContains('09:00', $response->json('slots'));
        $this->assertContains('21:15', $response->json('slots'));
    }

    // ------------------------------------------------------------ on save

    /**
     * The screen is not the rule.
     *
     * A tab left open since this morning still holds this morning's list, and
     * the request can be made by hand.
     */
    public function test_a_time_that_has_passed_is_refused_on_save(): void
    {
        $service = $this->service();

        $this->post(route('bookings.store'), [
            'guest_name' => 'Tom Fletcher',
            'location_id' => $this->location->id,
            'date' => self::TODAY,
            'starts_at' => '09:00',
            'services' => [$service->id],
        ])->assertSessionHasErrors('starts_at');

        $this->assertSame(0, Booking::withoutGlobalScopes()->count());
    }

    public function test_the_refusal_says_what_to_do_about_it(): void
    {
        $service = $this->service();

        $this->post(route('bookings.store'), [
            'guest_name' => 'Tom Fletcher',
            'location_id' => $this->location->id,
            'date' => self::TODAY,
            'starts_at' => '09:00',
            'services' => [$service->id],
        ])->assertSessionHasErrors([
            'starts_at' => 'This appointment time has already passed. Please select a future time.',
        ]);
    }

    public function test_a_time_still_to_come_today_saves(): void
    {
        $service = $this->service();

        $this->post(route('bookings.store'), [
            'guest_name' => 'Tom Fletcher',
            'location_id' => $this->location->id,
            'date' => self::TODAY,
            'starts_at' => '21:30',
            'services' => [$service->id],
        ])->assertRedirect();

        $this->assertSame(1, Booking::withoutGlobalScopes()->count());
    }

    /**
     * Two branches, one instant, opposite answers.
     *
     * This is the whole point of the change, and the assertion that would
     * have caught the original bug: nothing here can be decided from the
     * server's clock, because at any single moment the two branches disagree
     * about both what time it is and what day it is.
     */
    public function test_the_branch_clock_decides_and_not_the_servers(): void
    {
        $service = $this->service();
        $london = $this->london();

        /* 02:00 UTC on the 12th is 21:00 on the 11th in Austin and 03:00 on
           the 12th in London. So the 11th is today-and-mostly-gone in Austin,
           and yesterday in London. */
        $this->assertNotContains('09:00', BookingAvailability::for($this->location, self::TODAY, null, [$service->id])['slots']);
        $this->assertContains('21:30', BookingAvailability::for($this->location, self::TODAY, null, [$service->id])['slots']);

        /* Yesterday in London, so left alone — somebody writing up a walk-in
           rather than a day being half-filtered against the wrong date. */
        $this->assertContains('09:00', BookingAvailability::for($london, self::TODAY, null, [$service->id])['slots']);

        /* Now noon UTC: 13:00 in London and 07:00 in Austin, on the same date
           in both. The same morning is over in one and still to come in the
           other, which no server clock could tell you. */
        Carbon::setTestNow('2026-09-12 12:00:00');

        $this->assertNotContains('09:00', BookingAvailability::for($london, '2026-09-12', null, [$service->id])['slots']);
        $this->assertContains('09:00', BookingAvailability::for($this->location, '2026-09-12', null, [$service->id])['slots']);
    }

    /** A second branch, five hours ahead of the first. */
    private function london(): Location
    {
        $london = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Soho', 'address_line1' => '2 Broadwick St', 'city' => 'London',
            'postal_code' => 'W1F', 'country' => 'GB', 'timezone' => 'Europe/London',
            'is_primary' => false,
        ]);

        foreach (range(0, 6) as $day) {
            $london->hours()->create([
                'effective_from' => Location::EPOCH,
                'day_of_week' => $day, 'sort_order' => 0, 'is_open' => true,
                'opens_at' => '09:00', 'closes_at' => '22:00',
            ]);
        }

        return $london;
    }
}
