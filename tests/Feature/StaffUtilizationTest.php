<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\StaffUtilization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * How much of each person's bookable day is actually booked.
 *
 * What is pinned here is the denominator, because that is the whole claim the
 * screen makes. A utilization figure measured against the hours the branch is
 * open — rather than the hours somebody was rostered to take clients, less
 * their breaks and anything unbookable — says the whole team is idle, and a
 * manager who reads that once and finds it wrong never reads it again.
 */
class StaffUtilizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Spa', 'slug' => 'nadia-staff-util', 'business_email' => 'hi@nadia.test',
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

        $this->actingAs($this->owner);
    }

    // ------------------------------------------------------------ helpers

    private function member(array $attributes = []): Staff
    {
        return Staff::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'location_id' => $this->location->id,
            'first_name' => 'Sarah', 'last_name' => 'Johnson',
            'is_active' => true,
            'provides_services' => true,
        ]);
    }

    /** A rostered day: eight hours with an hour's break makes seven bookable. */
    private function shift(Staff $member, string $date, string $from = '09:00', string $until = '17:00', array $attributes = []): StaffShift
    {
        return StaffShift::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'location_id' => $this->location->id,
            'date' => $date,
            'starts_at' => $from,
            'ends_at' => $until,
            'break_minutes' => 60,
            'type' => 'regular',
            'status' => 'scheduled',
        ]);
    }

    private function booking(Staff $member, string $date, string $at, int $minutes, array $attributes = []): Booking
    {
        $booking = Booking::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'location_id' => $this->location->id,
            'staff_id' => $member->id,
            'reference' => Booking::nextReference(),
            'date' => $date,
            'starts_at' => $at,
            'ends_at' => Carbon::parse($at)->addMinutes($minutes)->format('H:i'),
            'minutes' => $minutes,
            'total_minor' => 12000,
            'status' => 'confirmed',
        ]);

        $booking->services()->create([
            'name' => 'Deep Tissue Massage',
            'minutes' => $minutes,
            'price_minor' => 12000,
            'sort_order' => 0,
        ]);

        return $booking;
    }

    /**
     * A login for a member of staff, in a given role.
     *
     * A user's role is read through their staff record rather than off a
     * column on `users` — which is the same path the application takes, so a
     * test that set one directly would be testing something the app cannot
     * produce.
     */
    private function userFor(Staff $member, string $email, string $roleKey): User
    {
        $user = User::create([
            'first_name' => $member->first_name, 'last_name' => $member->last_name,
            'email' => $email, 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $member->forceFill([
            'user_id' => $user->id,
            'role_id' => Role::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant->getTenantKey())
                ->where('key', $roleKey)
                ->value('id'),
        ])->save();

        return $user->fresh();
    }

    private function today(): Carbon
    {
        return Carbon::today();
    }

    /** @return array<string, mixed> */
    private function row(?Carbon $day = null): array
    {
        $day ??= $this->today();

        return StaffUtilization::forRange($day, $day)[0];
    }

    // -------------------------------------------------------- the denominator

    /**
     * The sum the whole screen is: booked over bookable.
     *
     * Eight hours rostered with an hour's break is seven bookable, and five
     * and a quarter booked into it is seventy-five per cent — the worked
     * example from the specification, which is the one an owner will check.
     */
    public function test_utilization_is_booked_time_over_bookable_time(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString());
        $this->booking($member, $this->today()->toDateString(), '09:00', 315);

        $row = $this->row();

        $this->assertSame(480, $row['scheduled_minutes']);
        $this->assertSame(60, $row['break_minutes']);
        $this->assertSame(420, $row['available_minutes']);
        $this->assertSame(315, $row['booked_minutes']);
        $this->assertSame(105, $row['idle_minutes']);
        $this->assertSame(75, $row['utilization']);
        $this->assertSame(1, $row['bookings']);
        $this->assertSame(12000, $row['revenue_minor']);
        $this->assertSame('on_target', $row['status']);
    }

    /**
     * A break is not capacity.
     *
     * The difference between measuring against the shift and measuring
     * against the bookable part of it: the same appointments read 66% against
     * one and 75% against the other, and only the second is a figure anybody
     * can act on.
     */
    public function test_a_break_comes_out_of_the_capacity(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString(), '09:00', '17:00', ['break_minutes' => 0]);
        $this->booking($member, $this->today()->toDateString(), '09:00', 315);

        $this->assertSame(480, $this->row()['available_minutes']);
        $this->assertSame(66, $this->row()['utilization']);
    }

    /**
     * Time at work that no client can book is not capacity either.
     *
     * A training day counts as scheduled — the business is paying for it —
     * and comes straight back out of what could be booked. Counting it as
     * capacity says somebody was idle when they were in a classroom.
     */
    public function test_a_training_day_is_scheduled_but_not_bookable(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString(), '09:00', '17:00', ['type' => 'training']);

        $row = $this->row();

        $this->assertSame(480, $row['scheduled_minutes']);
        $this->assertSame(420, $row['blocked_minutes']);
        $this->assertSame(0, $row['available_minutes']);
        $this->assertSame('unscheduled', $row['status']);
    }

    /**
     * Somebody with no rota has no percentage.
     *
     * Not 0%. A day off is not a day wasted, and a bubble at nought for
     * somebody on annual leave is an accusation — as well as a figure that
     * drags the team's average down for a reason that is nobody's problem.
     */
    public function test_somebody_who_was_not_rostered_has_no_capacity(): void
    {
        $this->member();

        $row = $this->row();

        $this->assertSame(0, $row['available_minutes']);
        $this->assertSame(0, $row['utilization']);
        $this->assertFalse($row['scheduled']);
        $this->assertSame('unscheduled', $row['status']);
    }

    /** A shift that was called off is not capacity anybody had. */
    public function test_a_cancelled_shift_is_not_capacity(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString(), '09:00', '17:00', ['status' => 'cancelled']);

        $this->assertSame(0, $this->row()['available_minutes']);
    }

    /** An appointment nobody came to filled nobody's day. */
    public function test_bookings_that_never_happened_fill_nobody(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString());

        foreach (['cancelled', 'declined', 'no-show', 'draft'] as $status) {
            $this->booking($member, $this->today()->toDateString(), '09:00', 120, ['status' => $status]);
        }

        $row = $this->row();

        $this->assertSame(0, $row['booked_minutes']);
        $this->assertSame(0, $row['bookings']);
        $this->assertSame(0, $row['utilization']);
    }

    /**
     * The bands the screen colours by.
     *
     * Named answers rather than a gradient, because a manager is asking who
     * needs doing something about. High is its own band and not a better
     * on-target: somebody at 95% has no room left for an appointment that
     * overruns.
     */
    public function test_the_status_bands_are_the_ones_the_screen_colours_by(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString(), '09:00', '17:00', ['break_minutes' => 0]);

        foreach ([
            [456, 'high'],
            [384, 'on_target'],
            [312, 'near_target'],
            [216, 'low'],
            [48, 'very_low'],
        ] as [$minutes, $expected]) {
            Booking::withoutGlobalScopes()->where('staff_id', $member->id)->forceDelete();
            $this->booking($member, $this->today()->toDateString(), '09:00', $minutes);

            $this->assertSame(
                $expected,
                $this->row()['status'],
                "{$minutes} minutes of 480 should read as {$expected}",
            );
        }
    }

    // ------------------------------------------------------------- the day

    /**
     * A person's day as bands, and the gaps worth selling.
     *
     * The part of the drill-down a manager acts on: not another percentage,
     * but where the holes are and what would fit in them.
     */
    public function test_the_day_shows_where_the_sellable_gaps_are(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString(), '09:00', '17:00', ['break_minutes' => 0]);
        $this->booking($member, $this->today()->toDateString(), '09:00', 120);
        $this->booking($member, $this->today()->toDateString(), '14:00', 60);

        $detail = StaffUtilization::detail($member, $this->today(), $this->today());

        $this->assertSame(
            [['booked', 540, 660], ['available', 660, 840], ['booked', 840, 900], ['available', 900, 1020]],
            collect($detail['timeline']['bands'])
                ->map(fn (array $band) => [$band['status'], $band['from'], $band['to']])
                ->all(),
        );

        $this->assertSame(
            [[660, 840, 180], [900, 1020, 120]],
            collect($detail['gaps'])
                ->map(fn (array $gap) => [$gap['from'], $gap['to'], $gap['minutes']])
                ->all(),
        );
    }

    /**
     * A hole too short to sell is arithmetic, not an opportunity.
     *
     * Listing a nine-minute gap between two appointments teaches whoever
     * reads the list to stop reading it.
     */
    public function test_a_gap_too_short_to_sell_is_not_listed(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString(), '09:00', '11:00', ['break_minutes' => 0]);
        $this->booking($member, $this->today()->toDateString(), '09:00', 55);
        $this->booking($member, $this->today()->toDateString(), '10:00', 60);

        $detail = StaffUtilization::detail($member, $this->today(), $this->today());

        $this->assertSame([], $detail['gaps']);
    }

    // ---------------------------------------------------------- the screen

    public function test_the_board_renders_for_somebody_who_may_see_the_team(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString());

        $this->get(route('staff.utilization'))
            ->assertOk()
            ->assertSee('Staff utilization')
            ->assertSee('data-vue-component="StaffUtilization"', false);
    }

    public function test_the_rows_come_back_for_the_listing_grid(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString());
        $this->booking($member, $this->today()->toDateString(), '09:00', 315);

        $this->getJson(route('staff.utilization.rows'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.utilization', '75%')
            ->assertJsonPath('data.0.variance', 'On target')
            ->assertJsonPath('data.0.booked', '5.3');
    }

    /**
     * A service provider sees their own day and nobody else's.
     *
     * The scope is what makes this screen safe to put in front of the team at
     * all, and it is enforced in the query rather than in the browser —
     * because a filter applied in the browser is one that can be undone by
     * editing a URL.
     */
    public function test_somebody_with_only_their_own_scope_sees_only_themselves(): void
    {
        $mine = $this->member(['first_name' => 'Sarah']);
        $theirs = $this->member(['first_name' => 'Andrew', 'last_name' => 'Bell']);

        $this->shift($mine, $this->today()->toDateString());
        $this->shift($theirs, $this->today()->toDateString());

        $provider = $this->userFor($mine, 'sarah@styledesk.test', 'service-provider');

        $response = $this->actingAs($provider)
            ->getJson(route('staff.utilization.rows'))
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->assertSame('Sarah Johnson', $response->json('data.0.name'));

        /* And not by changing the id in the URL either. */
        $this->actingAs($provider)
            ->getJson(route('staff.utilization.show', $theirs))
            ->assertForbidden();
    }

    /**
     * Seeing the rota is not seeing the takings.
     *
     * Its own permission, and a sales one — a business can perfectly well
     * want its receptionist to know who is busy without knowing who earns.
     */
    public function test_revenue_is_absent_for_somebody_who_may_not_see_it(): void
    {
        $member = $this->member();
        $this->shift($member, $this->today()->toDateString());
        $this->booking($member, $this->today()->toDateString(), '09:00', 315);

        $receptionist = $this->userFor(
            $this->member(['first_name' => 'Rae', 'last_name' => 'Ortiz']),
            'rae@styledesk.test',
            'front-desk',
        );

        $this->actingAs($receptionist)
            ->getJson(route('staff.utilization.rows'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.revenue');

        /* The owner, on the same request, does get it. */
        $this->actingAs($this->owner)
            ->getJson(route('staff.utilization.rows'))
            ->assertOk()
            ->assertJsonPath('data.0.utilization', '75%')
            ->assertJsonStructure(['data' => [['revenue']]]);
    }

    public function test_the_board_is_refused_to_somebody_who_may_not_see_staff(): void
    {
        $stranger = User::create([
            'first_name' => 'Sam', 'last_name' => 'Doe',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $stranger->markEmailAsVerified();
        $stranger->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($stranger->fresh())
            ->get(route('staff.utilization'))
            ->assertForbidden();
    }
}
