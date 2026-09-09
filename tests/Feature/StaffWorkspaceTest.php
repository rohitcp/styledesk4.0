<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Service;
use App\Models\ShiftRule;
use App\Models\Staff;
use App\Models\StaffNote;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The staff member's workspace: one header, four tabs.
 *
 * What makes it a workspace rather than four pages about the same person is
 * that the header is the same on every tab and each tab is its own address —
 * so a schedule can be bookmarked and the back button means what it says.
 * Those two facts are what most of these tests are about.
 */
class StaffWorkspaceTest extends TestCase
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
            'first_name' => 'Amara', 'last_name' => 'Person',
            'email' => 'amara@acme.test', 'role' => 'service-provider',
            'job_title' => 'Massage Therapist',
            'employee_ref' => 'EMP-014',
            'location_id' => $this->location->id,
        ]);
    }

    private function service(string $name = 'Swedish Massage'): Service
    {
        return Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'duration_minutes' => 60,
        ]);
    }

    // ------------------------------------------------------------- the tabs

    public function test_every_tab_is_its_own_address_and_keeps_the_header(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        foreach (['show', 'schedule', 'services', 'notes'] as $tab) {
            $url = $tab === 'show'
                ? route('staff.show', $member)
                : route('staff.'.$tab, $member);

            $page = $this->actingAs($owner)->get($url)->assertOk();

            /* The header is the same on all four — a summary that shifted as
               the reader moved between tabs would read as a different
               person's page. */
            $page->assertSee('Amara Person')
                ->assertSee('Massage Therapist')
                /* The staff ID, which is what tells two people of the same
                   name apart. */
                ->assertSee('EMP-014')
                ->assertSee('Overview')
                ->assertSee('Schedule')
                ->assertSee('Services')
                ->assertSee('Notes');
        }
    }

    /** The same workspace from both doors, because both render one view. */
    public function test_the_tabs_exist_in_both_sections(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)->get(route('staff.services', $member))->assertOk();
        $this->actingAs($owner)->get(route('settings.staff.services', $member))->assertOk();

        /* And each links to its own section's tabs, not the other's. */
        $this->actingAs($owner)->get(route('staff.notes', $member))
            ->assertSee(route('staff.schedule', $member), false)
            ->assertDontSee(route('settings.staff.schedule', $member), false);
    }

    // ----------------------------------------------------------- overview

    /**
     * Only metrics the data supports. The appointment figures need a booking
     * module, and a card reading "—" teaches the reader to ignore the row.
     */
    public function test_the_overview_reports_on_shifts_and_hours(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'date' => now()->startOfWeek()->addDay()->toDateString(),
            'starts_at' => '09:00', 'ends_at' => '17:00', 'break_minutes' => 60,
        ]);

        $this->actingAs($owner)->get(route('staff.show', $member))
            ->assertOk()
            ->assertSee('Shifts this week')
            ->assertSee('Hours this week')
            /* Eight hours less the hour of break. */
            ->assertSee('7');
    }

    // ----------------------------------------------------------- services

    public function test_services_can_be_added_and_removed(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $service = $this->service();

        $this->actingAs($owner)->get(route('staff.services', $member))
            ->assertOk()
            ->assertSee('No services assigned');

        $this->actingAs($owner)
            ->post(route('staff.services.attach', $member), ['service_ids' => [$service->id]])
            ->assertRedirect();

        $this->assertSame([$service->id], $member->refresh()->services->pluck('id')->all());

        $this->actingAs($owner)->get(route('staff.services', $member))
            ->assertOk()
            ->assertSee('Swedish Massage');

        $this->actingAs($owner)
            ->delete(route('staff.services.detach', [$member, $service]))
            ->assertRedirect();

        $this->assertCount(0, $member->refresh()->services);
        /* Removing takes the service off the person; the service itself is
           untouched. */
        $this->assertSame(1, Service::withoutGlobalScopes()->count());
    }

    /**
     * The form posts what is being added, not the whole list — sync would take
     * away everything it did not mention.
     */
    public function test_adding_services_does_not_drop_the_existing_ones(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $first = $this->service('Swedish Massage');
        $second = $this->service('Deep Tissue');

        $member->services()->sync([$first->id]);

        $this->actingAs($owner)
            ->post(route('staff.services.attach', $member), ['service_ids' => [$second->id]]);

        $this->assertSame(
            [$first->id, $second->id],
            $member->refresh()->services->pluck('id')->sort()->values()->all(),
        );
    }

    // ----------------------------------------------------------- schedule

    public function test_the_schedule_tab_shows_the_rule_and_the_shifts(): void
    {
        $owner = $this->owner();

        $rule = ShiftRule::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Standard Full-Time',
        ]);

        $member = $this->member(['shift_rule_id' => $rule->id]);

        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00', 'ends_at' => '16:00',
        ]);

        $this->actingAs($owner)->get(route('staff.schedule', $member))
            ->assertOk()
            /* The rule is reached through the action, not a card of its own:
               the page is read for the working schedule. */
            ->assertDontSee('Assigned Shift Rule')
            ->assertSee('Current Shift Rule')
            ->assertSee('Standard Full-Time')
            ->assertSee('Remove Shift Rule')
            /* The dated rows themselves live in the listing grid, which
               fetches them from its own endpoint. */
            ->assertSee('data-grid', false);
    }

    /**
     * Two actions in one row, in the order they are reached for. The Shift
     * Rule one is offered only when there is something to choose from — or
     * when this person is already on a rule, so an assignment made before a
     * rule was retired can still be taken off.
     */
    public function test_the_schedule_tab_offers_the_shift_rule_and_assign_actions(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        /* No rules at all: nothing to assign, so no Shift Rule action. */
        $this->actingAs($owner)->get(route('staff.schedule', $member))
            ->assertOk()
            ->assertDontSee('data-shift-rule-panel', false)
            ->assertSee('Assign Schedule')
            /* A one-off shift is added from the shifts screen, not from
               here: this page is for the working schedule. */
            ->assertDontSee(route('shifts.create'), false);

        $rule = ShiftRule::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Standard Full-Time',
        ]);

        $this->actingAs($owner)->get(route('staff.schedule', $member))
            ->assertOk()
            ->assertSee('data-shift-rule-panel', false)
            ->assertSee('No Shift Rule assigned.')
            /* Nothing to remove until something is assigned. */
            ->assertDontSee('Remove Shift Rule');

        /* Feature off: the action goes, rule or no rule. */
        $member->forceFill(['shift_rule_id' => $rule->id])->save();
        $this->tenant->forceFill(['shift_rules_enabled' => false])->save();

        $this->actingAs($owner->fresh())->get(route('staff.schedule', $member))
            ->assertOk()
            ->assertDontSee('data-shift-rule-panel', false);
    }

    public function test_a_shift_rule_can_be_assigned_and_cleared_from_the_tab(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $rule = ShiftRule::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Standard Full-Time',
        ]);

        /* Asked for in a dialog, so the schedule underneath is not pushed
           down the page to make room for a question about the pattern. */
        $this->actingAs($owner)->get(route('staff.schedule', $member))
            ->assertOk()
            ->assertSee('styledesk_modal__panel', false)
            ->assertSee('data-shift-rule-panel', false);

        /* Saving answers on the page it came from, which is the only place
           the change is visible. */
        $this->actingAs($owner)
            ->patch(route('staff.shift-rule', $member), ['shift_rule_id' => $rule->id])
            ->assertRedirect()
            ->assertSessionHas('toast.message', 'Put on Standard Full-Time.');

        $this->assertSame($rule->id, $member->refresh()->shift_rule_id);

        $this->actingAs($owner)
            ->patch(route('staff.shift-rule', $member), ['shift_rule_id' => ''])
            ->assertSessionHas('toast.type', 'success');

        $this->assertNull($member->refresh()->shift_rule_id);
    }

    /**
     * The month being read is stated, and changed from behind the pencil.
     *
     * A page opened without a period is already showing this month, so the
     * controls for changing it are one click away rather than a card of their
     * own above the schedule.
     */
    public function test_the_schedule_tab_opens_on_the_current_month_and_offers_a_month_editor(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $thisMonth = CarbonImmutable::now()->startOfMonth();

        $this->actingAs($owner)->get(route('staff.schedule', $member))
            ->assertOk()
            ->assertSee($thisMonth->translatedFormat('j M Y').' – '.$thisMonth->endOfMonth()->translatedFormat('j M Y'))
            ->assertSee('data-period-toggle', false)
            ->assertSee('schedulePeriodPanel', false)
            /* The filter card above the summary is gone: the same choice is
               made in one place now. */
            ->assertDontSee('data-schedule-filters', false);

        $next = $thisMonth->addMonth();

        $this->actingAs($owner)
            ->get(route('staff.schedule', $member).'?period=month&year='.$next->year.'&month='.$next->month)
            ->assertOk()
            ->assertSee($next->translatedFormat('j M Y').' – '.$next->endOfMonth()->translatedFormat('j M Y'))
            ->assertDontSee($thisMonth->translatedFormat('j M Y').' – '.$thisMonth->endOfMonth()->translatedFormat('j M Y'));
    }

    public function test_the_grid_reaches_a_shift_through_the_period_filter(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'date' => now()->addWeeks(3)->toDateString(),
            'starts_at' => '10:00', 'ends_at' => '16:00',
        ]);

        $date = now()->addWeeks(3);
        $rows = fn (string $query) => collect($this->actingAs($owner)
            ->getJson(route('staff.schedule.data', $member).'?'.$query)
            ->assertOk()
            ->json('data'));

        /* This month may not reach it; the year always does — on the page
           the date falls on, since the grid pages over the dates themselves
           and a year is more of them than one page holds. */
        $page = (int) ceil($date->dayOfYear / 50);

        $this->assertSame(
            '10:00 AM – 4:00 PM',
            $rows('period=year&year='.$date->year.'&page='.$page)->firstWhere('id', $date->toDateString())['time'],
        );

        $this->assertNull(
            $rows('period=month&year='.$date->copy()->subMonths(2)->year.'&month='.$date->copy()->subMonths(2)->month)
                ->firstWhere('id', $date->toDateString()),
        );
    }

    // -------------------------------------------------------------- notes

    public function test_a_note_can_be_added_and_deleted(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)->get(route('staff.notes', $member))
            ->assertOk()->assertSee('No notes yet.');

        $this->actingAs($owner)
            ->post(route('staff.notes.store', $member), ['body' => 'Prefers earlier starts.'])
            ->assertRedirect();

        $note = StaffNote::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($owner->id, $note->created_by);

        $this->actingAs($owner)->get(route('staff.notes', $member))
            ->assertOk()
            ->assertSee('Prefers earlier starts.')
            ->assertSee('Nadia Khan');

        $this->actingAs($owner)
            ->delete(route('staff.notes.destroy', [$member, $note]))
            ->assertRedirect();

        $this->assertSame(0, StaffNote::withoutGlobalScopes()->count());
    }

    public function test_an_empty_note_is_refused(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)
            ->from(route('staff.notes', $member))
            ->post(route('staff.notes.store', $member), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, StaffNote::withoutGlobalScopes()->count());
    }

    /** A note belongs to the person it was written about, and nobody else. */
    public function test_a_note_cannot_be_deleted_through_another_staff_member(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $other = $this->member(['first_name' => 'Priya', 'email' => 'priya@acme.test', 'employee_ref' => 'EMP-015']);

        $note = StaffNote::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'created_by' => $owner->id,
            'body' => 'Prefers earlier starts.',
        ]);

        $this->actingAs($owner)
            ->delete(route('staff.notes.destroy', [$other, $note]))
            ->assertForbidden();

        $this->assertSame(1, StaffNote::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------- more actions

    public function test_the_header_can_set_somebody_on_leave(): void
    {
        $owner = $this->owner();
        $member = $this->member(['is_active' => true]);

        $this->actingAs($owner)
            ->from(route('staff.show', $member))
            ->patch(route('staff.status', $member), ['to' => 'on-leave'])
            ->assertRedirect();

        $member->refresh();

        /* Away, not gone: not available to be booked, but the reason is kept
           so a rota can tell them from somebody who has left. */
        $this->assertSame('on-leave', $member->status());
        $this->assertFalse($member->is_active);

        /* And coming back lands on active rather than back on leave. */
        $this->actingAs($owner)
            ->from(route('staff.show', $member))
            ->patch(route('staff.status', $member));

        $this->assertSame('active', $member->refresh()->status());
    }

    /**
     * The same header the client workspace uses, on every tab.
     *
     * Two record pages in one application that lay their headers out
     * differently read as two applications — so this one is the client
     * identity block with staff facts in it, not a second arrangement of the
     * same information.
     */
    public function test_every_tab_uses_the_shared_identity_header(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        foreach (['show', 'schedule', 'services', 'notes'] as $tab) {
            $url = $tab === 'show' ? route('staff.show', $member) : route('staff.'.$tab, $member);

            $this->actingAs($owner)->get($url)
                ->assertOk()
                ->assertSee('styledesk_identity', false)
                ->assertSee('styledesk_identity__actions', false)
                ->assertSee('styledesk_avatar--identity', false)
                ->assertSee('styledesk_metachip', false)
                /* The labelled facts strip and the breadcrumb it sat under
                   are gone: the chips beside the name say the same things. */
                ->assertDontSee('styledesk_profile__facts', false)
                ->assertDontSee('aria-label="Breadcrumb"', false);
        }
    }

    /**
     * The same full-width container the clients screens use.
     *
     * A workspace that sat in its own narrow column while every other screen
     * filled the window would read as a different application.
     */
    public function test_every_tab_fills_the_content_width(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        foreach (['show', 'schedule', 'services', 'notes'] as $tab) {
            $url = $tab === 'show' ? route('staff.show', $member) : route('staff.'.$tab, $member);

            $this->actingAs($owner)->get($url)
                ->assertOk()
                /* pt-4, the same top padding the client workspace uses:
                   the two record pages open at the same height. */
                ->assertSee('w-full px-6 lg:px-8 pt-4 pb-[100px]', false)
                ->assertDontSee('max-w-[1080px]', false);
        }
    }
}
