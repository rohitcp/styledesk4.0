<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Database\Seeders\SmileSpaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smile Spa's rooms, chairs and price list.
 *
 * The point of these is not that forty-four rows appeared. It is the shape of
 * the mapping: a service is attached to a pool of resources in order of
 * preference, never to one room. That is what makes "a couple room may take a
 * single massage when no single room is spare" true without a line of code
 * saying so, and what lets a seventh room be added without editing a service.
 */
class SmileSpaSeederTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::create([
            'first_name' => 'Emma', 'last_name' => 'Martin',
            'email' => 'emma.martin3@example.com', 'password' => 'Str0ng!Pass',
        ]);
        $owner->markEmailAsVerified();

        $this->tenant = Tenant::create([
            'name' => 'Velvet Wellness Studio', 'slug' => 'velvet',
            'currency_code' => 'USD', 'owner_user_id' => $owner->id,
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->seed(SmileSpaSeeder::class);
    }

    private function service(string $name): Service
    {
        return Service::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('name', $name)
            ->firstOrFail();
    }

    /** @return array<int, string> resource codes, first choice first */
    private function pool(string $service): array
    {
        return $this->service($service)->resourcesByPreference()->pluck('code')->all();
    }

    public function test_the_business_is_smile_spa_at_the_hackensack_address(): void
    {
        $this->assertSame('Smile Spa', $this->tenant->fresh()->name);

        $location = $this->tenant->fresh()->locations()->where('is_primary', true)->firstOrFail();

        $this->assertSame('336A Main Street', $location->address_line1);
        $this->assertSame('Hackensack', $location->city);
        $this->assertSame('07601', $location->postal_code);
    }

    /** Two chairs, six single rooms, two couple rooms. */
    public function test_ten_resources_are_created(): void
    {
        $resources = Resource::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())->get();

        $this->assertCount(10, $resources);
        $this->assertEqualsCanonicalizing(
            ['CHAIR-01', 'CHAIR-02', 'ROOM-S01', 'ROOM-S02', 'ROOM-S03',
                'ROOM-S04', 'ROOM-S05', 'ROOM-S06', 'ROOM-C01', 'ROOM-C02'],
            $resources->pluck('code')->all(),
        );
        $this->assertTrue($resources->every(fn (Resource $r) => $r->is_active));
    }

    /**
     * A couple room holds two people. That is what capacity says — not that
     * it can carry two bookings.
     */
    public function test_a_couple_room_holds_two_and_a_single_room_holds_one(): void
    {
        $codes = Resource::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())->get()->keyBy('code');

        $this->assertSame(2, $codes['ROOM-C01']->capacity);
        $this->assertSame(2, $codes['ROOM-C02']->capacity);
        $this->assertSame(1, $codes['ROOM-S01']->capacity);
        $this->assertSame(1, $codes['CHAIR-01']->capacity);
    }

    public function test_the_price_list_is_categorised(): void
    {
        $categories = ServiceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->pluck('name');

        foreach (['Body Massage', 'Couple Massage', 'Therapeutic Massage',
            'Aromatherapy & Hot Stone', 'Head / Neck / Shoulder Massage',
            'Foot & Reflexology', 'Combination Treatments', 'Add-On Treatments'] as $name) {
            $this->assertContains($name, $categories->all());
        }
    }

    public function test_a_service_carries_its_duration_and_its_price(): void
    {
        $service = $this->service('Deep Tissue Massage — 90 Minutes');

        $this->assertSame(90, $service->duration_minutes);
        $this->assertSame(9000, (int) $service->prices()->where('currency_code', 'USD')->value('price_minor'));
        $this->assertTrue($service->requires_resource);
        $this->assertTrue($service->is_active);
    }

    // ------------------------------------------------------------- the pools

    /**
     * A single massage is offered every single room first and the couple
     * rooms only behind them — which is the whole of the fallback rule.
     */
    public function test_a_single_massage_prefers_a_single_room_and_falls_back_to_a_couple_room(): void
    {
        $pool = $this->pool('Deep Tissue Massage — 60 Minutes');

        $this->assertSame(
            ['ROOM-S01', 'ROOM-S02', 'ROOM-S03', 'ROOM-S04', 'ROOM-S05', 'ROOM-S06', 'ROOM-C01', 'ROOM-C02'],
            $pool,
        );
    }

    /**
     * A couple massage has nowhere else to go. Two beds and two therapists
     * are the booking, so a single room could not carry it however free.
     */
    public function test_a_couple_massage_can_only_use_a_couple_room(): void
    {
        foreach (['60', '80', '90'] as $length) {
            $this->assertSame(
                ['ROOM-C01', 'ROOM-C02'],
                $this->pool('Couple Massage — '.$length.' Minutes'),
            );
        }
    }

    /**
     * Seated work is offered a chair first and a bed behind it, for a client
     * who would rather lie down.
     */
    public function test_seated_work_prefers_a_chair_and_falls_back_to_a_room(): void
    {
        foreach (['Foot Reflexology — 30 Minutes', 'Head & Scalp Massage', 'Hand Massage',
            'Shoulder & Neck Massage — 30 Minutes'] as $name) {
            $pool = $this->pool($name);

            $this->assertSame(['CHAIR-01', 'CHAIR-02'], array_slice($pool, 0, 2), $name);
            $this->assertContains('ROOM-S01', $pool, $name);
            /* A couple room is on the list, and last on it: a twenty-minute
               scalp massage should only ever reach one when the chairs and
               every bed are taken. */
            $this->assertSame('ROOM-C02', $pool[count($pool) - 1], $name);
        }
    }

    /**
     * An hour of neck and shoulder is lying-down work, whatever the
     * half-hour version is — so it is not on the chair list.
     */
    public function test_the_hour_long_neck_and_shoulder_is_a_room_service(): void
    {
        $pool = $this->pool('Shoulder & Neck Massage — 60 Minutes');

        $this->assertSame('ROOM-S01', $pool[0]);
        $this->assertNotContains('CHAIR-01', $pool);
    }

    /**
     * The preference is on the pairing, not on the room: the same room is a
     * body massage's first choice and a reflexology's second, and one number
     * on the room could only ever be one of those.
     */
    public function test_the_same_room_ranks_differently_for_two_services(): void
    {
        $body = $this->service('Deep Tissue Massage — 60 Minutes')
            ->resources()->where('code', 'ROOM-S01')->first();
        $feet = $this->service('Foot Reflexology — 30 Minutes')
            ->resources()->where('code', 'ROOM-S01')->first();

        /* Priority 1 is the preferred resource, 2 the fallback, 3 the last
           resort — the same numbering the spec uses. */
        $this->assertSame(1, (int) $body->pivot->priority);
        $this->assertSame(2, (int) $feet->pivot->priority);
    }

    /** Run it twice and nothing is duplicated. */
    public function test_seeding_again_changes_nothing(): void
    {
        $services = Service::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())->count();

        $this->seed(SmileSpaSeeder::class);

        $this->assertSame(10, Resource::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())->count());
        $this->assertSame($services, Service::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())->count());
        $this->assertCount(8, $this->pool('Deep Tissue Massage — 60 Minutes'));
    }

    // ---------------------------------------------------------------- team

    /** Ten therapists and a front desk, beside the owner who was already there. */
    public function test_ten_staff_members_are_created(): void
    {
        $staff = Staff::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('email', 'like', '%@smilespa.test')
            ->get();

        $this->assertCount(10, $staff);
        $this->assertTrue($staff->every(fn (Staff $member) => $member->is_active));
        $this->assertTrue($staff->every(fn (Staff $member) => $member->employee_ref !== null));
    }

    /**
     * Not ten of the same person.
     *
     * A team where everybody can do everything makes the booking screen's
     * staff filter meaningless — every name would come back for every
     * service.
     */
    public function test_therapists_work_on_their_own_part_of_the_list(): void
    {
        $reflexologist = $this->staff('yuki.tanaka@smilespa.test');
        $services = $reflexologist->services->pluck('name');

        $this->assertContains('Foot Reflexology — 60 Minutes', $services->all());
        $this->assertNotContains('Deep Tissue Massage — 60 Minutes', $services->all());
    }

    /**
     * A couple massage takes two therapists at once, so a team with only one
     * who can do it could never carry a single couple booking.
     */
    public function test_at_least_two_people_can_take_a_couple_massage(): void
    {
        $couple = $this->service('Couple Massage — 60 Minutes');

        $able = Staff::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->whereHas('services', fn ($query) => $query->whereKey($couple->id))
            ->count();

        $this->assertGreaterThanOrEqual(2, $able);
    }

    /**
     * The desk books everybody in and does none of it — which is why the flag
     * is off rather than the list being empty. An empty list on somebody who
     * provides services reads as "not set up yet".
     */
    public function test_the_front_desk_does_not_provide_services(): void
    {
        $desk = $this->staff('grace.bennett@smilespa.test');

        $this->assertFalse($desk->provides_services);
        $this->assertCount(0, $desk->services);
    }

    /** Re-running should not renumber the team. */
    public function test_seeding_again_keeps_the_same_staff_numbers(): void
    {
        $before = $this->staff('mai.nguyen@smilespa.test')->employee_ref;

        $this->seed(SmileSpaSeeder::class);

        $this->assertSame($before, $this->staff('mai.nguyen@smilespa.test')->employee_ref);
        $this->assertSame(10, Staff::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('email', 'like', '%@smilespa.test')->count());
    }

    private function staff(string $email): Staff
    {
        return Staff::withoutGlobalScopes()
            ->with('services')
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('email', $email)
            ->firstOrFail();
    }
}
