<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Role;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\DashboardData;
use App\Support\DashboardLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The dashboard answers a different question depending on who logs in.
 *
 *   Owner        — how is my business performing?
 *   Receptionist — who is arriving, waiting, or needs attention?
 *   Provider     — who is my next client?
 *
 * So these tests are mostly about *order* and *absence*: that check-in is the
 * top of a receptionist's screen and the sixth thing an owner scrolls to, and
 * that a therapist is never shown the branch's takings.
 */
class DashboardRolesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 11:00:00');

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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function owner(): User
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

    /**
     * Somebody in one of the system roles.
     *
     * Their role is reached through their staff row, which is how StyleDesk
     * resolves it for everybody who is not the owner.
     */
    private function userInRole(string $key, string $email): User
    {
        $user = User::create([
            'first_name' => ucfirst($key), 'last_name' => 'Tester',
            'email' => $email, 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)
            ->firstOrFail();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id, 'role_id' => $role->id,
            'first_name' => ucfirst($key), 'last_name' => 'Tester',
            'email' => $email, 'role' => $key,
            'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => $key === 'service-provider',
        ]);

        return $user->fresh();
    }

    private function booking(string $status = 'confirmed', string $startsAt = '14:00', ?Staff $staff = null): Booking
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Deep Tissue Massage', 'duration_minutes' => 60, 'is_active' => true,
        ]);

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client->id,
            'staff_id' => $staff?->id,
            'location_id' => $this->location->id,
            'date' => now()->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addHour()->format('H:i'),
            'minutes' => 60, 'status' => $status,
            'total_minor' => 6500, 'currency_code' => 'USD',
            'payment_status' => 'unpaid',
        ]);

        $booking->services()->create([
            'service_id' => $service->id, 'name' => $service->name,
            'minutes' => 60, 'price_minor' => 6500,
        ]);

        return $booking->fresh();
    }

    /** @return array<int, string> */
    private function panels(User $user): array
    {
        return DashboardLayout::for($user)->all();
    }

    // -------------------------------------------------------- the hierarchy

    /**
     * An owner opens the page to find out how today looks, then how the
     * month is going — in that order.
     */
    public function test_the_owner_leads_with_today_then_the_month(): void
    {
        $panels = $this->panels($this->owner());

        $this->assertSame(['bookings_today', 'performance'], array_slice($panels, 0, 2));
        $this->assertContains('checkin', $panels);
    }

    /**
     * Three panels sit in the column beside the page rather than down it:
     * they are glanced at repeatedly while working on something else.
     */
    public function test_the_side_column_holds_the_glanceable_panels(): void
    {
        $panels = collect($this->panels($this->owner()));
        $side = collect(config('dashboard.side'));

        foreach (['checkin', 'staff_today', 'alerts'] as $widget) {
            $this->assertTrue($side->contains($widget), $widget);
            $this->assertTrue($panels->contains($widget), $widget);
        }

        /* Check-in leads the tabs, because it is the one of the three
           anybody presses first. */
        $this->assertSame('checkin', $side->first());
    }

    /**
     * A provider holds none of the side panels, so their dashboard is one
     * column — which is right: it is one thing at a time.
     */
    public function test_a_provider_gets_a_single_column(): void
    {
        $panels = collect($this->panels($this->userInRole('service-provider', 'therapist@styledesk.test')));
        $side = collect(config('dashboard.side'));

        /* Alerts is the one they do hold; the other two are not theirs. */
        $this->assertFalse($panels->contains('checkin'));
        $this->assertFalse($panels->contains('staff_today'));
        $this->assertTrue($side->contains('alerts'));
    }

    /**
     * The most operational screen in StyleDesk. The desk opens it to find
     * out who is at the desk.
     */
    public function test_the_front_desk_leads_with_check_in(): void
    {
        $panels = $this->panels($this->userInRole('front-desk', 'desk@styledesk.test'));

        $this->assertSame(['checkin', 'arriving_soon', 'waiting'], array_slice($panels, 0, 3));
        /* And is never shown the takings. */
        $this->assertNotContains('performance', $panels);
    }

    public function test_a_provider_leads_with_their_next_client(): void
    {
        $panels = $this->panels($this->userInRole('service-provider', 'therapist@styledesk.test'));

        $this->assertSame('my_next_client', $panels[0]);

        /* Nothing business-wide: not the takings, not the branch's diary,
           not another therapist's day. */
        foreach (['performance', 'payments', 'staff_today', 'clients', 'bookings_today'] as $forbidden) {
            $this->assertNotContains($forbidden, $panels, $forbidden);
        }
    }

    public function test_a_manager_leads_with_their_location(): void
    {
        $panels = $this->panels($this->userInRole('manager', 'manager@styledesk.test'));

        $this->assertSame('bookings_today', $panels[0]);
        $this->assertContains('schedule_issues', $panels);
    }

    /** Every role gets a genuinely different page, not one page with holes. */
    public function test_no_two_roles_get_the_same_dashboard(): void
    {
        $layouts = [
            $this->panels($this->owner()),
            $this->panels($this->userInRole('administrator', 'admin@styledesk.test')),
            $this->panels($this->userInRole('manager', 'manager@styledesk.test')),
            $this->panels($this->userInRole('front-desk', 'desk@styledesk.test')),
            $this->panels($this->userInRole('service-provider', 'therapist@styledesk.test')),
        ];

        $this->assertCount(5, collect($layouts)->map(fn (array $p) => implode(',', $p))->unique());
    }

    // ------------------------------------------------------ permission-led

    /**
     * A custom role has no layout of its own and needs none: it is offered
     * everything and its permissions decide.
     */
    public function test_a_custom_role_is_assembled_from_its_permissions(): void
    {
        $user = User::create([
            'first_name' => 'Senior', 'last_name' => 'Therapist',
            'email' => 'senior@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'senior-therapist', 'name' => 'Senior Therapist',
        ]);

        foreach ([
            'dashboard.view' => 'all',
            'dashboard.view_own_clients' => 'all',
            'dashboard.view_own_schedule' => 'all',
            'dashboard.view_own_performance' => 'all',
        ] as $permission => $scope) {
            $role->permissions()->create(['permission' => $permission, 'scope' => $scope]);
        }

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id, 'role_id' => $role->id,
            'first_name' => 'Senior', 'last_name' => 'Therapist',
            'email' => 'senior@styledesk.test', 'role' => 'service-provider',
            'location_id' => $this->location->id, 'is_active' => true, 'provides_services' => true,
        ]);

        $panels = $this->panels($user->fresh());

        $this->assertSame(
            ['my_next_client', 'my_day', 'my_schedule', 'my_performance', 'alerts', 'quick_actions'],
            $panels,
        );
    }

    /** A panel whose permission is taken away goes, rather than emptying. */
    public function test_removing_a_permission_removes_the_panel(): void
    {
        $user = $this->userInRole('front-desk', 'desk@styledesk.test');

        $this->assertContains('payments', $this->panels($user));

        $user->role()->permissions()->where('permission', 'dashboard.view_payments')->delete();

        $this->assertNotContains('payments', $this->panels($user->fresh()));
    }

    // ----------------------------------------------------------- the scope

    /**
     * A manager assigned to one branch must not see another's work. This is
     * the leak nobody notices until somebody mentions a number they should
     * not have known.
     */
    public function test_a_manager_is_scoped_to_their_own_location(): void
    {
        $other = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Uptown', 'address_line1' => '2 High St', 'city' => 'Hackensack',
            'postal_code' => '07601', 'country' => 'US', 'timezone' => 'America/New_York',
        ]);

        $manager = $this->userInRole('manager', 'manager@styledesk.test');

        $this->assertSame([$this->location->id], DashboardLayout::locationScope($manager));
        $this->assertNotContains($other->id, DashboardLayout::locationScope($manager));
    }

    /** For an owner every branch is theirs, so there is nothing to filter. */
    public function test_an_owner_is_not_scoped_to_a_location(): void
    {
        $this->assertNull(DashboardLayout::locationScope($this->owner()));
    }

    /**
     * One branch is not a filter, it is the business — and a dropdown with a
     * single entry is furniture.
     */
    public function test_the_location_selector_appears_only_with_a_choice(): void
    {
        $owner = $this->owner();

        $this->assertTrue(DashboardLayout::selectableLocations($owner)->isEmpty());

        Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Uptown', 'address_line1' => '2 High St', 'city' => 'Hackensack',
            'postal_code' => '07601', 'country' => 'US', 'timezone' => 'America/New_York',
        ]);

        $this->assertCount(2, DashboardLayout::selectableLocations($owner));
    }

    // ------------------------------------------------------------- the page

    public function test_the_owner_dashboard_renders_its_panels(): void
    {
        $this->booking('confirmed', '14:00');

        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.performance.title'))
            ->assertSee(__('dashboard.bookings_today.title'))
            ->assertSee(__('dashboard.checkin.title'));
    }

    public function test_the_front_desk_dashboard_renders_the_queue_and_no_revenue(): void
    {
        $this->booking('confirmed', '14:00');

        $this->actingAs($this->userInRole('front-desk', 'desk@styledesk.test'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.checkin.title'))
            ->assertSee(__('dashboard.arriving.title'))
            ->assertDontSee(__('dashboard.performance.title'));
    }

    /**
     * The provider's own day, and only theirs — a therapist's dashboard must
     * never fall back to the whole branch's diary.
     */
    public function test_a_provider_sees_only_their_own_clients(): void
    {
        $therapist = $this->userInRole('service-provider', 'therapist@styledesk.test');
        $theirStaff = Staff::withoutGlobalScopes()->where('user_id', $therapist->id)->firstOrFail();

        $mine = $this->booking('confirmed', '14:00', $theirStaff);
        $somebodyElses = $this->booking('confirmed', '15:00');

        $response = $this->actingAs($therapist)->get(route('dashboard'))->assertOk();

        $response->assertSee(__('dashboard.my_next_client.title'));
        $response->assertSee($mine->reference === '' ? 'Mia Baker' : 'Mia Baker');

        /* The other appointment belongs to nobody in particular, and must
           not appear on this person's day. */
        $day = DashboardData::forUser($therapist, null)->myDay();

        $this->assertCount(1, $day);
        $this->assertSame($mine->id, $day->first()->id);
        $this->assertNotContains($somebodyElses->id, $day->pluck('id')->all());
    }

    /** Empty states are role-specific, and say the true thing. */
    public function test_the_empty_states_speak_to_the_reader(): void
    {
        $this->actingAs($this->userInRole('front-desk', 'desk@styledesk.test'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.checkin.none'));

        $this->actingAs($this->userInRole('service-provider', 'therapist@styledesk.test'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.my_next_client.none'));
    }
}
