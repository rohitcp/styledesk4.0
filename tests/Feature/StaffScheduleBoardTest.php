<?php

namespace Tests\Feature;

use App\Http\Controllers\StaffScheduleBoardController;
use App\Models\Location;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The team's rota board: a row per person, a column per month.
 *
 * The per-person schedule screen answers "what is this one person working";
 * this one answers the question a manager opens the app with — "whose month
 * is still empty". These tests are about the three states a cell can be in
 * and where each one leads, because that is the whole screen.
 */
class StaffScheduleBoardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
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

    private function member(array $attributes = []): Staff
    {
        return Staff::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Susan', 'last_name' => 'Pena',
            'email' => 'susan@acme.test', 'role' => 'service-provider',
            'location_id' => $this->location->id,
            'is_active' => true,
        ]);
    }

    private function shift(Staff $member, string $date, array $overrides = []): StaffShift
    {
        return StaffShift::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'location_id' => $this->location->id,
            'date' => $date,
            'starts_at' => '09:00', 'ends_at' => '17:00',
        ]);
    }

    /** One row's cell for the given month, as the grid reads it. */
    private function cell(array $row, CarbonImmutable $month): array
    {
        return $row[StaffScheduleBoardController::field($month->format('Y-n'))];
    }

    /**
     * The board opens on the month the manager is standing in.
     *
     * With months behind it as well as ahead: "who did we forget last month"
     * is asked as often as "who is not covered next month".
     */
    public function test_the_board_opens_on_the_current_month(): void
    {
        $owner = $this->owner();
        $this->member();

        $thisMonth = CarbonImmutable::now()->startOfMonth();

        $this->actingAs($owner)->get(route('staff.schedules'))
            ->assertOk()
            /* The rows come from the shared listing grid, which fetches them
               from its own endpoint — the page describes the table. */
            ->assertSee('data-grid', false)
            ->assertSee(route('staff.schedules.data'), false)
            ->assertSee($thisMonth->translatedFormat('M Y'))
            ->assertSee($thisMonth->subMonths(3)->translatedFormat('M Y'))
            ->assertSee($thisMonth->addMonths(8)->translatedFormat('M Y'))
            /* The current column is marked, not merely present: a row of
               twelve months that all look alike is one nobody can find their
               place in. */
            ->assertSee('This month');
    }

    /**
     * The three states, told apart the same way the per-person screen tells
     * them apart — every shift sent means published, and a month sent once
     * and edited since is neither published nor a fresh draft.
     */
    public function test_a_cell_reports_the_state_of_that_persons_month(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $month = CarbonImmutable::now()->startOfMonth();
        $row = fn () => $this->actingAs($owner)
            ->getJson(route('staff.schedules.data'))->assertOk()->json('data.0');

        $this->assertSame('Not Scheduled', $this->cell($row(), $month)['label']);
        $this->assertSame('none', $this->cell($row(), $month)['tone']);

        $this->shift($member, $month->addDays(1)->toDateString());

        $this->assertSame('Draft', $this->cell($row(), $month)['label']);
        $this->assertSame(1, $this->cell($row(), $month)['shifts']);

        StaffShift::withoutGlobalScopes()->update([
            'publish_status' => 'published',
            'published_at' => now(),
            'published_by' => $owner->id,
        ]);

        $this->assertSame('Published', $this->cell($row(), $month)['label']);
        $this->assertSame('published', $this->cell($row(), $month)['tone']);

        /* Published once and edited since is a draft to the person holding
           it, and the board says so rather than inventing a fourth colour. */
        StaffShift::withoutGlobalScopes()->update(['publish_status' => 'draft']);

        $this->assertSame('Changes pending', $this->cell($row(), $month)['label']);
        $this->assertSame('draft', $this->cell($row(), $month)['tone']);
    }

    /**
     * A cancelled shift is a fact about the week, not cover for it. A month
     * whose only shift was called off has nobody working it.
     */
    public function test_a_cancelled_shift_does_not_count_as_a_schedule(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $month = CarbonImmutable::now()->startOfMonth();

        $this->shift($member, $month->addDays(1)->toDateString(), ['status' => 'cancelled']);

        $row = $this->actingAs($owner)->getJson(route('staff.schedules.data'))->assertOk()->json('data.0');

        $this->assertSame('Not Scheduled', $this->cell($row, $month)['label']);
    }

    /**
     * Where a cell leads: the month itself where there is one to read, and
     * the flow that would create it where there is not.
     */
    public function test_a_cell_links_to_the_month_or_to_the_flow_that_creates_it(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $month = CarbonImmutable::now()->startOfMonth();
        $days = (int) $month->diffInDays($month->endOfMonth()) + 1;

        $row = fn () => $this->actingAs($owner)
            ->getJson(route('staff.schedules.data'))->assertOk()->json('data.0');

        /* Nothing planned: the cell starts the assign flow for that person
           and that whole month, and comes back to the board. */
        $empty = $this->cell($row(), $month)['url'];

        $this->assertStringContainsString('days='.$days.'&from='.$month->toDateString(), $empty);
        $this->assertStringContainsString(
            urlencode(route('staff.schedules').'?year='.$month->year.'&month='.$month->month),
            $empty,
        );

        $this->shift($member, $month->addDays(1)->toDateString());

        $this->assertSame(
            route('staff.schedule', $member).'?period=month&year='.$month->year.'&month='.$month->month,
            $this->cell($row(), $month)['url'],
        );
    }

    /** The status filter is a statement about the cells, so it narrows rows. */
    public function test_the_status_filter_narrows_the_staff_shown(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $this->member(['first_name' => 'Marco', 'last_name' => 'Diaz', 'email' => 'marco@acme.test']);

        $this->shift($member, CarbonImmutable::now()->startOfMonth()->addDays(1)->toDateString(), [
            'publish_status' => 'published',
            'published_at' => now(),
            'published_by' => $owner->id,
        ]);

        $names = fn (string $query) => collect($this->actingAs($owner)
            ->getJson(route('staff.schedules.data').$query)->assertOk()->json('data'))
            ->pluck('name')->all();

        $this->assertSame(['Susan Pena'], $names('?status=published'));

        /* Marco has nothing anywhere, and Susan has eleven months with
           nothing in them, so both are unscheduled somewhere in the window. */
        $this->assertSame(['Marco Diaz', 'Susan Pena'], $names('?status=not-scheduled'));
        $this->assertSame(['Susan Pena'], $names('?status=scheduled'));
    }

    /** The coverage filter is a statement about the months, so it narrows columns. */
    public function test_the_coverage_filter_narrows_the_months_shown(): void
    {
        $owner = $this->owner();
        $this->member();

        $thisMonth = CarbonImmutable::now()->startOfMonth();
        $field = fn (CarbonImmutable $month) => StaffScheduleBoardController::field(
            $month->format('Y-n'),
        );

        $row = $this->actingAs($owner)
            ->getJson(route('staff.schedules.data').'?coverage=future')->assertOk()->json('data.0');

        $this->assertArrayHasKey($field($thisMonth->addMonth()), $row);
        $this->assertArrayNotHasKey($field($thisMonth), $row);
        $this->assertArrayNotHasKey($field($thisMonth->subMonth()), $row);

        $this->actingAs($owner)->get(route('staff.schedules').'?coverage=future')
            ->assertOk()
            ->assertDontSee('This month');
    }

    /** Each row offers the month in focus, to read or to plan. */
    public function test_each_row_carries_its_own_actions(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $row = $this->actingAs($owner)
            ->getJson(route('staff.schedules.data').'?month=11&year=2026')->assertOk()->json('data.0');

        $this->assertSame(
            ['View November 2026 schedule', 'Assign November 2026 schedule'],
            collect($row['menu'])->pluck('label')->all(),
        );

        $this->assertStringContainsString(
            route('staff.schedule', $member).'?period=month&year=2026&month=11',
            $row['url'],
        );
    }

    /**
     * A published month, read without leaving the board.
     *
     * Every day of it, worked or not: a list of only the working days hides
     * the gaps, and the gaps are half of what a rota is read for.
     */
    public function test_a_published_month_can_be_read_from_the_board(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $month = CarbonImmutable::now()->startOfMonth();

        $this->shift($member, $month->addDays(1)->toDateString(), [
            'break_minutes' => 30,
            'publish_status' => 'published',
            'published_at' => now(),
            'published_by' => $owner->id,
        ]);

        $month = $this->actingAs($owner)
            ->getJson(route('staff.schedules.month', $member).'?year='.$month->year.'&month='.$month->month)
            ->assertOk()
            ->json();

        $this->assertSame('Susan Pena', $month['staff']);
        $this->assertTrue($month['published']);
        $this->assertSame('Published', $month['state_label']);
        $this->assertCount(CarbonImmutable::now()->daysInMonth, $month['days']);

        $worked = collect($month['days'])->firstWhere('working', true);

        $this->assertSame('9:00 AM', $worked['shifts'][0]['start']);
        $this->assertSame('5:00 PM', $worked['shifts'][0]['end']);
        $this->assertSame('30 min', $worked['shifts'][0]['break']);
        $this->assertSame(__('schedule.working'), $worked['label']);

        /* Days off are rows too, and say so. */
        $this->assertNotNull(collect($month['days'])->firstWhere('working', false));

        /* And the way out of the dialog is the flow that would change it. */
        $this->assertStringContainsString('/schedule/assign', $month['assign_url']);
    }

    /**
     * Assign Schedule needs a person and a month before the flow that already
     * exists can take over, so that is all the action does.
     */
    public function test_assign_schedule_redirects_into_the_assign_flow(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)
            ->get(route('staff.schedules.start').'?staff='.$member->id.'&month=11&year=2026')
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('staff.schedules.start').'?staff='.$member->id.'&month=11&year=2026')
            ->assertRedirectContains('days=30&from=2026-11-01');
    }

    /**
     * The board is staff data, so it is behind the staff policy rather than
     * merely behind being signed in.
     */
    public function test_the_board_is_not_open_to_somebody_outside_the_business(): void
    {
        $outsider = User::create([
            'first_name' => 'Ida', 'last_name' => 'Ruiz',
            'email' => 'ida@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $outsider->markEmailAsVerified();

        $this->actingAs($outsider)->get(route('staff.schedules'))
            ->assertRedirect()
            ->assertSessionMissing('nothing');
    }
}
