<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Support\BookingAvailability;
use App\Support\ResourceAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Which room or chair an appointment goes in.
 *
 * The rule these are all about is the one that is easy to get wrong in the
 * obvious direction: a couple room can take a single client, which makes it
 * the most useful room in the building and the one most easily wasted. A
 * twenty-minute scalp massage put in ROOM-C02 because it happened to be free
 * is a couple turned away at four o'clock. So preference is not a nicety —
 * "any free one" is a bug.
 */
class ResourceAllocationTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-10-12';

    private Tenant $tenant;

    private Location $location;

    /** @var array<string, resource> */
    private array $rooms = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 09:00:00');

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

        $this->location->allHours()->create([
            'effective_from' => '2000-01-01',
            'day_of_week' => (int) Carbon::parse(self::DATE)->dayOfWeek,
            'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00', 'sort_order' => 0,
        ]);

        /* Two single rooms and one couple room is enough to show every rule;
           six of them would only make the assertions longer. */
        $this->rooms['ROOM-S01'] = $this->resource('ROOM-S01', 'massage-room', 1, 1);
        $this->rooms['ROOM-S02'] = $this->resource('ROOM-S02', 'massage-room', 1, 2);
        $this->rooms['ROOM-C01'] = $this->resource('ROOM-C01', 'couples-massage-room', 2, 10);
        $this->rooms['CHAIR-01'] = $this->resource('CHAIR-01', 'massage-chair', 1, 20);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function resource(string $code, string $categoryKey, int $capacity, int $position): Resource
    {
        $category = ResourceCategory::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'key' => $categoryKey],
            ['name' => $categoryKey, 'group' => 'rooms', 'default_capacity' => $capacity]
        );

        return Resource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'resource_category_id' => $category->id,
            'location_id' => $this->location->id,
            'code' => $code, 'name' => $code,
            'capacity' => $capacity, 'position' => $position,
            'is_active' => true, 'availability_status' => 'available',
        ]);
    }

    /**
     * A service and the pool it may use, in order of preference.
     *
     * @param  array<string, int>  $pool  resource code => priority
     */
    private function service(string $name, int $minutes, array $pool): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'duration_minutes' => $minutes,
            'is_active' => true, 'requires_resource' => true,
        ]);

        $service->resources()->sync(collect($pool)
            ->mapWithKeys(fn (int $priority, string $code) => [
                $this->rooms[$code]->id => ['priority' => $priority],
            ])->all());

        return $service;
    }

    /** A single massage: single rooms first, the couple room behind them. */
    private function massage(int $minutes = 60): Service
    {
        return $this->service('Deep Tissue Massage', $minutes, [
            'ROOM-S01' => 1, 'ROOM-S02' => 1, 'ROOM-C01' => 2,
        ]);
    }

    private function coupleMassage(): Service
    {
        return $this->service('Couple Massage', 60, ['ROOM-C01' => 1]);
    }

    /** Chair first, then a bed, then the couple room. */
    private function reflexology(): Service
    {
        return $this->service('Foot Reflexology', 30, [
            'CHAIR-01' => 1, 'ROOM-S01' => 2, 'ROOM-S02' => 2, 'ROOM-C01' => 3,
        ]);
    }

    private function booking(Service $service, string $startsAt, string $endsAt, string $code, string $status = 'confirmed'): Booking
    {
        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'location_id' => $this->location->id,
            'resource_id' => $this->rooms[$code]->id,
            'date' => self::DATE,
            'starts_at' => $startsAt, 'ends_at' => $endsAt,
            'minutes' => (int) $service->duration_minutes,
            'status' => $status, 'total_minor' => 0, 'currency_code' => 'USD',
        ]);

        $booking->services()->create([
            'service_id' => $service->id, 'name' => $service->name,
            'minutes' => (int) $service->duration_minutes, 'price_minor' => 0,
        ]);

        return $booking;
    }

    private function assign(Service $service, string $startsAt, ?int $ignore = null): ?string
    {
        return ResourceAllocator::assign(
            [$service->id], self::DATE, $startsAt, (int) $service->duration_minutes, $ignore
        )?->code;
    }

    // ------------------------------------------------------------ preference

    /** Nothing booked: the first single room, not whichever row came back. */
    public function test_an_empty_day_gives_the_first_preferred_resource(): void
    {
        $this->assertSame('ROOM-S01', $this->assign($this->massage(), '10:00'));
    }

    /**
     * ROOM-S01 booked, ROOM-S02 free, ROOM-C01 free.
     * The answer is S02 — the couple room is not reached while a single is
     * free, which is the protection rule in one line.
     */
    public function test_it_moves_to_the_next_single_room_before_the_couple_room(): void
    {
        $massage = $this->massage();
        $this->booking($massage, '10:00', '11:00', 'ROOM-S01');

        $this->assertSame('ROOM-S02', $this->assign($massage, '10:00'));
    }

    /** Only when every single room is taken does a couple room get used. */
    public function test_the_couple_room_takes_a_single_massage_when_no_single_room_is_free(): void
    {
        $massage = $this->massage();
        $this->booking($massage, '10:00', '11:00', 'ROOM-S01');
        $this->booking($massage, '10:00', '11:00', 'ROOM-S02');

        $this->assertSame('ROOM-C01', $this->assign($massage, '10:00'));
    }

    /**
     * A chair service takes the second chair over a free room — the spec's
     * own example, and the reason "any free one" is wrong.
     */
    public function test_a_chair_service_prefers_a_chair_over_a_free_room(): void
    {
        $reflexology = $this->reflexology();

        /* The chair is free, and so is every room. */
        $this->assertSame('CHAIR-01', $this->assign($reflexology, '14:00'));

        $this->booking($reflexology, '14:00', '14:30', 'CHAIR-01');

        /* Chair taken: now a bed, and still not the couple room. */
        $this->assertSame('ROOM-S01', $this->assign($reflexology, '14:00'));
    }

    /** Everything full is a real answer, and it is null. */
    public function test_nothing_is_assigned_when_everything_is_taken(): void
    {
        $massage = $this->massage();
        foreach (['ROOM-S01', 'ROOM-S02', 'ROOM-C01'] as $code) {
            $this->booking($massage, '10:00', '11:00', $code);
        }

        $this->assertNull($this->assign($massage, '10:00'));
    }

    // -------------------------------------------------------------- the room

    /** A couple massage has nowhere else to go, however free the beds are. */
    public function test_a_couple_massage_only_ever_gets_a_couple_room(): void
    {
        $this->assertSame('ROOM-C01', $this->assign($this->coupleMassage(), '13:00'));
    }

    /**
     * A couple booking takes the room, not a bed. There is no second
     * appointment to squeeze into the other half, because the other half is
     * where the second client is lying.
     */
    public function test_a_couple_booking_leaves_no_bed_for_anybody_else(): void
    {
        $couple = $this->coupleMassage();
        $massage = $this->massage();

        $this->booking($couple, '13:00', '14:30', 'ROOM-C01');

        /* Both single rooms full as well, and until three o'clock — so at
           half two the couple room is the only thing left, and it is not
           available. */
        $this->booking($massage, '13:00', '15:00', 'ROOM-S01');
        $this->booking($massage, '13:00', '15:00', 'ROOM-S02');

        $this->assertNull($this->assign($massage, '13:00'));
        $this->assertNull($this->assign($massage, '14:00'));

        /* And free again the moment the couple leave. */
        $this->assertSame('ROOM-C01', $this->assign($massage, '14:30'));
    }

    /** Back-to-back is how a busy room is run, not a clash. */
    public function test_a_room_is_free_the_minute_the_last_appointment_ends(): void
    {
        $massage = $this->massage();
        $this->booking($massage, '10:00', '11:00', 'ROOM-S01');

        $this->assertSame('ROOM-S01', $this->assign($massage, '11:00'));
    }

    /**
     * A cancelled appointment is not still holding a room, and neither is one
     * nobody arrived for.
     */
    public function test_a_settled_booking_releases_its_room(): void
    {
        $massage = $this->massage();

        foreach (['cancelled', 'declined', 'no-show', 'draft'] as $status) {
            Booking::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())->forceDelete();
            $this->booking($massage, '10:00', '11:00', 'ROOM-S01', $status);

            $this->assertSame('ROOM-S01', $this->assign($massage, '10:00'), $status);
        }
    }

    /** An appointment must not be found to clash with where it already is. */
    public function test_a_booking_does_not_block_itself(): void
    {
        $massage = $this->massage();
        $booking = $this->booking($massage, '10:00', '11:00', 'ROOM-S01');

        $this->assertSame('ROOM-S01', $this->assign($massage, '10:00', $booking->id));
    }

    // ------------------------------------------------------ several services

    /**
     * A cupping added to a massage happens in the room the client is already
     * lying in. One place for the appointment, not one per line on it.
     */
    public function test_an_add_on_shares_the_room_rather_than_taking_a_second(): void
    {
        $massage = $this->massage();
        $cupping = $this->service('Cupping', 15, ['ROOM-S01' => 1, 'ROOM-S02' => 1, 'ROOM-C01' => 2]);

        $resource = ResourceAllocator::assign(
            [$massage->id, $cupping->id], self::DATE, '10:00', 75
        );

        $this->assertSame('ROOM-S01', $resource->code);
    }

    /**
     * Every service, not any of them: a chair that can take the reflexology
     * but not the massage cannot take an appointment that is both.
     */
    public function test_a_mixed_appointment_needs_somewhere_that_can_do_all_of_it(): void
    {
        $massage = $this->massage();
        $reflexology = $this->reflexology();

        $eligible = ResourceAllocator::eligibleFor([$massage->id, $reflexology->id])->pluck('code');

        $this->assertNotContains('CHAIR-01', $eligible->all());
        $this->assertSame(['ROOM-S01', 'ROOM-S02', 'ROOM-C01'], $eligible->all());
    }

    // -------------------------------------------------------- availability

    /**
     * The reader offers a time only while something is free to do it in —
     * however free the therapist is.
     */
    public function test_a_time_is_not_offered_once_every_room_is_taken(): void
    {
        $massage = $this->massage();

        $this->assertContains('10:00', $this->slots($massage));

        foreach (['ROOM-S01', 'ROOM-S02', 'ROOM-C01'] as $code) {
            $this->booking($massage, '10:00', '11:00', $code);
        }

        $slots = $this->slots($massage);

        $this->assertNotContains('10:00', $slots);
        /* Still bookable either side of it. */
        $this->assertContains('11:00', $slots);
        $this->assertContains('09:00', $slots);
    }

    /** One free room of three is a bookable appointment. */
    public function test_a_time_is_still_offered_while_one_room_is_free(): void
    {
        $massage = $this->massage();
        $this->booking($massage, '10:00', '11:00', 'ROOM-S01');
        $this->booking($massage, '10:00', '11:00', 'ROOM-S02');

        $this->assertContains('10:00', $this->slots($massage));
    }

    /** A business that maps no rooms is not one with no rooms free. */
    public function test_a_service_mapped_to_nothing_is_unaffected(): void
    {
        $service = $this->service('Consultation', 30, []);

        $this->assertContains('10:00', $this->slots($service));
    }

    /** @return array<int, string> */
    private function slots(Service $service): array
    {
        return BookingAvailability::for(
            $this->location, self::DATE, null, [$service->id]
        )['slots'];
    }

    // ------------------------------------------------- per-service mapping

    /**
     * A room is held for longer than the client is in it.
     *
     * A sixty-minute massage with ten minutes of preparation and ten of
     * cleaning occupies the room for eighty. A room offered to somebody else
     * the moment the previous client stood up is a room being cleaned around
     * them.
     */
    public function test_the_room_is_held_for_preparation_and_cleanup_too(): void
    {
        $service = $this->service('Deep Tissue Massage', 60, [
            'ROOM-S01' => 1, 'ROOM-S02' => 1, 'ROOM-C01' => 2,
        ]);

        $service->forceFill(['preparation_minutes' => 10, 'cleanup_minutes' => 10])->save();

        $window = ResourceAllocator::occupancyFor($service->fresh());

        $this->assertSame(10, $window['lead']);
        $this->assertSame(60, $window['body']);
        $this->assertSame(10, $window['trail']);
    }

    /**
     * Which is the whole point of holding it: an appointment starting the
     * minute the last one ended would be booked into a room still being
     * turned round.
     */
    public function test_a_room_is_not_free_during_the_cleanup_of_the_last_booking(): void
    {
        $service = $this->service('Deep Tissue Massage', 60, ['ROOM-S01' => 1]);
        $service->forceFill(['preparation_minutes' => 10, 'cleanup_minutes' => 10])->save();
        $service = $service->fresh();

        /* Ten o'clock to eleven, so the room is held 09:50–11:10. */
        $booking = $this->booking($service, '10:00', '11:00', 'ROOM-S01');
        $booking->services()->update([
            'resource_id' => $this->rooms['ROOM-S01']->id,
        ]);

        /* Eleven is inside the cleanup. */
        $this->assertNull(ResourceAllocator::assignForService(
            $service, self::DATE, '11:00', $this->location->id
        ));

        /* Nor is ten past: the *next* appointment needs its own ten minutes
           of preparation, so its window would open at eleven — inside the
           cleanup of the one before. Two appointments in one room are
           separated by the cleanup of the first and the preparation of the
           second, which is twenty minutes here and not ten. */
        $this->assertNull(ResourceAllocator::assignForService(
            $service, self::DATE, '11:10', $this->location->id
        ));

        /* Twenty past is: its window opens at ten past, exactly where the
           previous booking's hold ends. Back-to-back is not a clash. */
        $this->assertSame('ROOM-S01', ResourceAllocator::assignForService(
            $service, self::DATE, '11:20', $this->location->id
        )?->code);
    }

    /** A room at another branch is not a room this appointment can use. */
    public function test_a_room_at_another_location_is_not_offered(): void
    {
        $elsewhere = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Uptown', 'address_line1' => '2 High St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
        ]);

        $service = $this->massage();

        $this->assertNotEmpty(ResourceAllocator::eligibleFor([$service->id], $this->location->id));
        $this->assertTrue(ResourceAllocator::eligibleFor([$service->id], $elsewhere->id)->isEmpty());
    }

    /**
     * Every room the service could use, with whether it is free — the taken
     * ones included, because somebody hunting for Single Room 01 and not
     * finding it will assume the mapping is wrong.
     */
    public function test_the_selector_lists_taken_rooms_as_unavailable(): void
    {
        $massage = $this->massage();
        $this->booking($massage, '10:00', '11:00', 'ROOM-S01');

        $options = ResourceAllocator::optionsForService(
            $massage, self::DATE, '10:00', $this->location->id
        );

        $this->assertSame('ROOM-S01', $options[0]['code']);
        $this->assertFalse($options[0]['available']);
        $this->assertTrue($options[1]['available']);
        /* Capacity travels with it, so the selector can show it. */
        $this->assertSame(1, $options[0]['capacity']);
    }

    /**
     * Each service on a booking is answered on its own: a massage at ten and
     * a reflexology at eleven are two rooms.
     */
    public function test_two_services_on_one_booking_get_their_own_rooms(): void
    {
        $massage = $this->massage();
        $reflexology = $this->reflexology();

        $this->assertSame('ROOM-S01', ResourceAllocator::assignForService(
            $massage, self::DATE, '10:00', $this->location->id
        )?->code);

        $this->assertSame('CHAIR-01', ResourceAllocator::assignForService(
            $reflexology, self::DATE, '11:00', $this->location->id
        )?->code);
    }
}
