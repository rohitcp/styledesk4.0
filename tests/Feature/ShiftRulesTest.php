<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\ShiftRule;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App Settings → Staff → Shift Rules.
 *
 * A shift rule is a reusable working pattern. The tests are written against
 * what makes it its own record: it is a template, it keeps no hours of its
 * own — the business's working hours are the one source of those — and the
 * rules that stop somebody writing an impossible pattern need more than one
 * answer to decide.
 */
class ShiftRulesTest extends TestCase
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

    /**
     * The business's own working week — Monday to Friday, nine to five.
     *
     * On the location, not on the rule: a shift rule keeps no hours, and this
     * is the one place the week is written.
     *
     * @param  array<int, array<int, array<string, string>>>  $overrides
     */
    private function businessWeek(array $overrides = []): void
    {
        $week = [];

        foreach ([1, 2, 3, 4, 5] as $day) {
            $week[$day] = [['opens_at' => '09:00', 'closes_at' => '17:00']];
        }

        foreach (array_replace($week, $overrides) as $day => $periods) {
            foreach (array_values($periods) as $order => $period) {
                $this->location->hours()->create([
                    'effective_from' => Location::EPOCH,
                    'day_of_week' => $day,
                    'sort_order' => $order,
                    'is_open' => true,
                    'opens_at' => $period['opens_at'],
                    'closes_at' => $period['closes_at'],
                ]);
            }
        }
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Standard Full-Time',
            'location_scope' => 'all',
            'status' => 'active',
            'break_type' => 'none',
            'allow_adjustment' => '1',
        ], $overrides);
    }

    private function rule(array $overrides = []): ShiftRule
    {
        return ShiftRule::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Standard Full-Time',
        ], $overrides));
    }

    // -------------------------------------------------------------- create

    /**
     * The rule reads the business's week rather than holding one.
     *
     * This replaces the tests that asserted a rule stored its own periods.
     * That behaviour was removed on purpose: working hours are one fact, held
     * in the business's own configuration and read by scheduling, booking,
     * resources and the calendar alike, and a copy on the rule would be a
     * second answer free to drift. The rules that guarded the rule's own week
     * — overlapping periods, an end before a start, a second block without
     * split shifts — now live where the week is written, and are covered by
     * the business-hours tests.
     */
    public function test_a_rule_reads_the_business_week_rather_than_holding_one(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.store'), $this->payload())
            ->assertRedirect(route('settings.shift-rules.index'))
            ->assertSessionHasNoErrors();

        $rule = ShiftRule::withoutGlobalScopes()->firstOrFail();

        $this->assertSame([1, 2, 3, 4, 5], $rule->workingDays());
        $this->assertSame('Mon–Fri', $rule->workingDaysLabel());
        $this->assertSame('9:00 AM – 5:00 PM', $rule->defaultHoursLabel());
        $this->assertSame(2400, $rule->weeklyMinutes());
    }

    /**
     * Change the business's Monday and every rule follows, with nothing to
     * migrate — which is the whole point of holding the hours once.
     */
    public function test_changing_the_business_hours_changes_what_every_rule_reads(): void
    {
        $this->businessWeek();
        $rule = $this->rule();

        $this->assertSame('9:00 AM – 5:00 PM', $rule->defaultHoursLabel());

        /* Through the rows themselves: hours() picks the current schedule
           with a correlated subquery over location_hours, and MySQL refuses an
           UPDATE whose own subquery reads the table being written. */
        foreach ($this->location->hours()->get()->where('day_of_week', 1) as $monday) {
            $monday->forceFill(['closes_at' => '19:00'])->save();
        }

        /* Varies, because Monday now differs from the rest — the label says
           so rather than picking one day and calling it the week. */
        $this->assertSame('Varies by day', $rule->fresh()->defaultHoursLabel());
    }

    public function test_a_split_business_day_is_read_as_two_periods(): void
    {
        $this->businessWeek([
            1 => [
                ['opens_at' => '09:00', 'closes_at' => '13:00'],
                ['opens_at' => '14:00', 'closes_at' => '18:00'],
            ],
        ]);

        $rule = $this->rule();

        $this->assertCount(2, $rule->openHours()->where('day_of_week', 1));
        $this->assertSame('Varies by day', $rule->defaultHoursLabel());
    }

    /**
     * The form shows the week and offers the way to change it at its source —
     * it does not ask for one.
     */
    public function test_the_form_shows_the_business_week_read_only(): void
    {
        $this->businessWeek();

        $page = $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index', ['add' => 1]))
            ->assertOk();

        $page->assertSee('Business working hours are used across scheduling and booking. Changing them here changes them everywhere.')
            ->assertSee('Edit Business Working Hours')
            ->assertSee(route('settings.hours.edit', $this->location), false)
            /* Sunday is not a working day, and the table says so rather than
               leaving a gap that reads as missing data. */
            ->assertSee('Closed');

        /* No week editor: the island, its day toggles and its copy action are
           all gone from this screen. */
        $page->assertDontSee('data-vue-component="BusinessHours"', false)
            ->assertDontSee('name="hours[1][is_open]"', false)
            ->assertDontSee('Copy Monday');
    }

    public function test_save_and_add_another_returns_to_an_empty_form(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.store'), $this->payload(['after_save' => 'add_another']))
            ->assertRedirect(route('settings.shift-rules.index', ['add' => 1]));
    }

    // ---------------------------------------------------------- validation

    /**
     * Measured against the business's shortest open day, not a copy on the
     * rule: the hours are one fact, and checking against a second would
     * eventually pass a break the real week cannot hold.
     */
    public function test_a_break_longer_than_the_shortest_business_day_is_refused(): void
    {
        /* A four-hour Monday, so five hours of break cannot fit. */
        $this->businessWeek([1 => [['opens_at' => '09:00', 'closes_at' => '13:00']]]);

        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->payload([
                'break_type' => 'duration',
                'break_minutes' => 300,
            ]))
            ->assertSessionHasErrors('break_minutes');
    }

    public function test_a_break_that_fits_the_shortest_day_is_accepted(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.store'), $this->payload([
                'break_type' => 'duration',
                'break_minutes' => 60,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_minimum_shift_longer_than_the_maximum_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->payload([
                'min_hours_per_shift' => 8,
                'max_hours_per_shift' => 4,
            ]))
            ->assertSessionHasErrors('min_hours_per_shift');
    }

    public function test_effective_until_cannot_precede_effective_from(): void
    {
        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->payload([
                'effective_from' => '2026-08-01',
                'effective_until' => '2026-06-01',
            ]))
            ->assertSessionHasErrors('effective_until');
    }

    public function test_specific_locations_needs_a_location(): void
    {
        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->payload(['location_scope' => 'specific']))
            ->assertSessionHasErrors('locations');
    }

    public function test_two_rules_cannot_share_a_name(): void
    {
        $this->rule();

        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->payload())
            ->assertSessionHasErrors('name');
    }

    // ---------------------------------------------------------- housekeeping

    /**
     * "Everywhere" and "these two branches" are different answers, so the
     * list is emptied when the scope goes back to all — a dormant list is a
     * second answer waiting to contradict the first.
     */
    public function test_going_back_to_all_locations_empties_the_list(): void
    {
        $rule = $this->rule(['location_scope' => 'specific']);
        $rule->locations()->sync([$this->location->id]);

        $this->actingAs($this->owner())
            ->patch(route('settings.shift-rules.update', $rule), $this->payload(['location_scope' => 'all']))
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $rule->refresh()->locations);
    }

    public function test_switching_overtime_off_clears_its_figures(): void
    {
        $rule = $this->rule([
            'allow_overtime' => true, 'overtime_after_hours' => 40, 'max_overtime_hours' => 8,
        ]);

        $this->actingAs($this->owner())
            ->patch(route('settings.shift-rules.update', $rule), $this->payload())
            ->assertSessionHasNoErrors();

        $rule->refresh();

        $this->assertFalse($rule->allow_overtime);
        $this->assertNull($rule->overtime_after_hours);
        $this->assertNull($rule->max_overtime_hours);
    }

    // ------------------------------------------------------------- actions

    /**
     * The copy opens for editing rather than landing on the listing: it
     * exists to be changed, and two identically-shaped rules with near
     * identical names says nothing about which is which.
     */
    public function test_duplicating_opens_the_copy_for_editing(): void
    {
        $rule = $this->rule();

        $response = $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.duplicate', $rule));

        $copy = ShiftRule::withoutGlobalScopes()->where('id', '!=', $rule->id)->firstOrFail();

        $response->assertRedirect(route('settings.shift-rules.index', ['edit' => $copy->id]));

        $this->assertSame('Standard Full-Time (copy)', $copy->name);
        /* A draft until somebody has renamed it. */
        $this->assertFalse($copy->isActive());
        /* And it reads the same business week the original does, because
           neither of them holds one. */
        $this->assertSame($rule->workingDays(), $copy->workingDays());
    }

    public function test_a_rule_can_be_deactivated_and_activated(): void
    {
        $owner = $this->owner();
        $rule = $this->rule();

        $this->actingAs($owner)
            ->from(route('settings.shift-rules.index'))
            ->patch(route('settings.shift-rules.status', $rule))
            ->assertRedirect(route('settings.shift-rules.index'));

        $this->assertFalse($rule->refresh()->isActive());

        $this->actingAs($owner)
            ->from(route('settings.shift-rules.index'))
            ->patch(route('settings.shift-rules.status', $rule));

        $this->assertTrue($rule->refresh()->isActive());
    }

    public function test_an_unused_rule_can_be_deleted(): void
    {
        $rule = $this->rule();

        $this->actingAs($this->owner())
            ->delete(route('settings.shift-rules.destroy', $rule))
            ->assertRedirect(route('settings.shift-rules.index'));

        $this->assertSame(0, ShiftRule::withoutGlobalScopes()->count());
    }

    // ---------------------------------------------------------- the listing

    /**
     * One page for the whole feature. The DataGrid this screen used to mount
     * is gone on purpose: a rule is half a dozen facts of different shapes,
     * and a table forces each into a column width decided by the widest.
     */
    public function test_the_page_lists_the_rules_as_cards(): void
    {
        $this->businessWeek();
        $this->rule();

        $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('Standard Full-Time')
            ->assertSee('Enable Shift Rules')
            ->assertSee(route('settings.shift-rules.index', ['add' => 1]), false)
            ->assertDontSee('data-grid', false);
    }

    public function test_a_card_summarises_the_rule(): void
    {
        $this->businessWeek();
        $this->rule(['max_hours_per_week' => 40]);

        $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('Standard Full-Time')
            ->assertSee('Active')
            ->assertSee('All locations')
            ->assertSee('Mon–Fri')
            ->assertSee('9:00 AM – 5:00 PM')
            ->assertSee('40 hours / week')
            /* The three things you can do to it, plus the copy action. */
            ->assertSee('Edit')
            ->assertSee('Deactivate')
            ->assertSee('Delete');
    }

    /**
     * A switched-off rule stays on the page saying so, which is the whole
     * difference between deactivating one and deleting it.
     */
    public function test_an_inactive_rule_stays_on_the_page(): void
    {
        $this->businessWeek();
        $this->rule(['status' => 'inactive']);

        $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('Standard Full-Time')
            ->assertSee('Inactive')
            /* And the way to bring it back. */
            ->assertSee('Activate');
    }

    /**
     * A rule that applies everywhere applies at that branch too, so filtering
     * by branch must not hide it — the reader asked which rules are in force
     * there, not which ones name it.
     */
    public function test_rules_for_every_branch_and_for_one_are_both_listed(): void
    {
        $this->rule(['name' => 'Everywhere rule']);

        $specific = $this->rule(['name' => 'Riverside only', 'location_scope' => 'specific']);
        $specific->locations()->sync([$this->location->id]);

        /* Both are listed. The branch filter this used to assert went with
           the DataGrid — the page shows every rule now, which at the scale a
           business writes them is the whole list on one screen. */
        $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('Everywhere rule')
            ->assertSee('Riverside only');
    }

    public function test_a_business_with_no_rules_sees_the_empty_state(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('No shift rules yet')
            ->assertDontSee('data-grid', false);
    }

    // -------------------------------------------------------- permissions

    /**
     * Owner and Administrator only. A manager meets a shift rule on the Staff
     * Schedule screen, where they pick one — never here, where they are
     * written.
     */
    public function test_a_manager_cannot_reach_shift_rules(): void
    {
        $this->owner();

        $manager = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'manager@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $manager->markEmailAsVerified();
        $manager->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $manager->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $manager->email, 'role' => 'manager',
        ]);

        $this->actingAs($manager->fresh())
            ->get(route('settings.shift-rules.index'))
            ->assertRedirect();
    }

    public function test_another_business_rules_are_never_listed(): void
    {
        $this->rule();

        $other = Tenant::create(['name' => 'Elsewhere', 'slug' => 'elsewhere']);
        ShiftRule::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(), 'name' => 'Theirs',
        ]);

        $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('Standard Full-Time')
            ->assertDontSee('Theirs');
    }

    // ------------------------------------------------------- split shifts

    /** @param array<int, array<string, mixed>> $periods */
    private function splitPayload(array $periods, array $overrides = []): array
    {
        return $this->payload(array_merge([
            'allow_split_shift' => '1',
            'periods' => $periods,
        ], $overrides));
    }

    /**
     * Three separate ideas, and the point of the feature is that they stay
     * separate: the business's hours say when it is open, the periods say how
     * that day is divided, and allowing one person on several says whether
     * anybody may work more than one of them.
     */
    public function test_shift_periods_divide_the_business_day(): void
    {
        $this->businessWeek([1 => [['opens_at' => '08:00', 'closes_at' => '20:00']]]);

        $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.store'), $this->splitPayload([
                ['name' => 'Morning Shift', 'starts_at' => '09:00', 'ends_at' => '13:00', 'break_minutes' => '', 'is_active' => '1'],
                ['name' => 'Evening Shift', 'starts_at' => '16:00', 'ends_at' => '20:00', 'break_minutes' => 30, 'is_active' => '1'],
            ]))
            ->assertRedirect(route('settings.shift-rules.index'))
            ->assertSessionHasNoErrors();

        $periods = ShiftRule::withoutGlobalScopes()->with('shiftPeriods')->firstOrFail()->shiftPeriods;

        $this->assertSame(['Morning Shift', 'Evening Shift'], $periods->pluck('name')->all());
        $this->assertSame('9:00 AM – 1:00 PM', $periods[0]->rangeLabel());
        /* Four hours less the half-hour break. */
        $this->assertSame(210, $periods[1]->workedMinutes());
    }

    public function test_a_period_outside_the_business_hours_is_refused(): void
    {
        $this->businessWeek([1 => [['opens_at' => '08:00', 'closes_at' => '20:00']]]);

        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->splitPayload([
                ['name' => 'Too early', 'starts_at' => '07:00', 'ends_at' => '13:00', 'is_active' => '1'],
            ]))
            ->assertSessionHasErrors([
                'periods.0.window' => 'Shift period must fall within the business working hours.',
            ]);

        $this->assertSame(0, ShiftRule::withoutGlobalScopes()->count());
    }

    /**
     * At least one open day, not every one: a business open Mon–Fri 9–5 and
     * Saturday 10–4 must not refuse a nine-o'clock morning shift on
     * Saturday's account.
     */
    public function test_a_period_only_has_to_fit_one_open_day(): void
    {
        $this->businessWeek([6 => [['opens_at' => '10:00', 'closes_at' => '16:00']]]);

        $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.store'), $this->splitPayload([
                ['name' => 'Morning Shift', 'starts_at' => '09:00', 'ends_at' => '13:00', 'is_active' => '1'],
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_every_period_needs_a_name_and_times(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->splitPayload([
                ['name' => '', 'starts_at' => '09:00', 'ends_at' => '13:00', 'is_active' => '1'],
            ]))
            ->assertSessionHasErrors('periods.0.name');
    }

    public function test_a_period_must_end_after_it_starts(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->splitPayload([
                ['name' => 'Backwards', 'starts_at' => '13:00', 'ends_at' => '09:00', 'is_active' => '1'],
            ]))
            ->assertSessionHasErrors('periods.0.window');
    }

    public function test_split_shifts_switched_on_needs_at_least_one_period(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->from(route('settings.shift-rules.index', ['add' => 1]))
            ->post(route('settings.shift-rules.store'), $this->splitPayload([]))
            ->assertSessionHasErrors('periods');
    }

    /**
     * Dividing the day into periods says nothing about whether anybody may
     * exceed their hours. Switching one on must never quietly switch the
     * other on with it.
     */
    public function test_split_shifts_do_not_imply_overtime(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.store'), $this->splitPayload([
                ['name' => 'Morning Shift', 'starts_at' => '09:00', 'ends_at' => '13:00', 'is_active' => '1'],
            ]))
            ->assertSessionHasNoErrors();

        $rule = ShiftRule::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($rule->allow_split_shift);
        $this->assertFalse($rule->allow_overtime);
    }

    /**
     * A ceiling and a gap only mean something once one person may work more
     * than one period; keeping them would leave a rule quietly holding limits
     * it does not apply.
     */
    public function test_the_multi_period_limits_are_cleared_when_nobody_may_work_two(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.store'), $this->splitPayload([
                ['name' => 'Morning Shift', 'starts_at' => '09:00', 'ends_at' => '13:00', 'is_active' => '1'],
            ], [
                'allow_same_employee_multiple_periods' => '0',
                'max_periods_per_employee_per_day' => 4,
                'min_gap_minutes' => 120,
            ]))
            ->assertSessionHasNoErrors();

        $rule = ShiftRule::withoutGlobalScopes()->firstOrFail();

        $this->assertFalse($rule->allow_same_employee_multiple_periods);
        $this->assertSame(2, $rule->max_periods_per_employee_per_day);
        $this->assertNull($rule->min_gap_minutes);
    }

    public function test_the_multi_period_limits_are_kept_when_somebody_may(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->post(route('settings.shift-rules.store'), $this->splitPayload([
                ['name' => 'Morning Shift', 'starts_at' => '09:00', 'ends_at' => '13:00', 'is_active' => '1'],
                ['name' => 'Evening Shift', 'starts_at' => '16:00', 'ends_at' => '17:00', 'is_active' => '1'],
            ], [
                'allow_same_employee_multiple_periods' => '1',
                'max_periods_per_employee_per_day' => 2,
                'min_gap_minutes' => 120,
            ]))
            ->assertSessionHasNoErrors();

        $rule = ShiftRule::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($rule->allow_same_employee_multiple_periods);
        $this->assertSame(120, $rule->min_gap_minutes);
    }

    public function test_the_periods_are_copied_by_duplicate(): void
    {
        $this->businessWeek();
        $rule = $this->rule(['allow_split_shift' => true]);
        $rule->shiftPeriods()->create([
            'name' => 'Morning Shift', 'starts_at' => '09:00', 'ends_at' => '13:00', 'sort_order' => 0,
        ]);

        $this->actingAs($this->owner())->post(route('settings.shift-rules.duplicate', $rule));

        $copy = ShiftRule::withoutGlobalScopes()->where('id', '!=', $rule->id)->with('shiftPeriods')->firstOrFail();

        $this->assertSame(['Morning Shift'], $copy->shiftPeriods->pluck('name')->all());
    }

    public function test_the_split_settings_are_hidden_until_the_switch_is_on(): void
    {
        $this->businessWeek();

        $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index', ['add' => 1]))
            ->assertOk()
            ->assertSee('Allow Employee Split Shift')
            /* Rendered but hidden: the rows keep posting so that switching the
               setting off and on again does not cost what was typed. */
            ->assertSee('data-split-fields', false)
            ->assertSee('hidden', false)
            ->assertSee('data-vue-component="ShiftPeriods"', false);
    }

    // ------------------------------------------------- the feature switch

    /**
     * On by default, not off.
     *
     * The feature already exists and businesses already have rules; a switch
     * that shipped defaulting to off would make everybody's rules disappear
     * on deploy. It is there to be turned off by a business that does not
     * want it.
     */
    public function test_shift_rules_are_on_for_a_business_that_has_never_touched_the_switch(): void
    {
        $this->assertTrue($this->tenant->fresh()->shift_rules_enabled);
    }

    /**
     * Off hides the feature and keeps every row. That is the whole difference
     * between switching it off and deleting the rules.
     */
    public function test_switching_the_feature_off_hides_the_rules_without_deleting_them(): void
    {
        $this->businessWeek();
        $this->rule();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->from(route('settings.shift-rules.index'))
            ->patch(route('settings.shift-rules.feature'), ['enabled' => '0'])
            ->assertRedirect(route('settings.shift-rules.index'));

        $this->assertFalse($this->tenant->fresh()->shift_rules_enabled);
        /* The row is still there. */
        $this->assertSame(1, ShiftRule::withoutGlobalScopes()->count());

        $this->actingAs($owner)
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('Shift rules are switched off')
            /* Said in words, because a business that has written twelve rules
               needs to know they are still there. */
            ->assertSee('still here')
            ->assertDontSee('Standard Full-Time')
            /* And no way to add one while the feature is off. */
            ->assertDontSee(route('settings.shift-rules.index', ['add' => 1]), false);
    }

    public function test_switching_the_feature_back_on_shows_them_again(): void
    {
        $this->businessWeek();
        $this->rule();
        $owner = $this->owner();

        $this->tenant->forceFill(['shift_rules_enabled' => false])->save();

        $this->actingAs($owner)
            ->from(route('settings.shift-rules.index'))
            ->patch(route('settings.shift-rules.feature'), ['enabled' => '1']);

        $this->actingAs($owner)
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('Standard Full-Time');
    }

    // ------------------------------------------------- one page, two states

    public function test_adding_and_editing_happen_on_the_same_page(): void
    {
        $this->businessWeek();
        $rule = $this->rule();
        $owner = $this->owner();

        /* The form, in place, with the rule loaded into it. */
        $this->actingAs($owner)
            ->get(route('settings.shift-rules.index', ['edit' => $rule->id]))
            ->assertOk()
            ->assertSee('Edit shift rule')
            ->assertSee('value="Standard Full-Time"', false)
            ->assertSee('Back to rules');

        /* And the blank one. */
        $this->actingAs($owner)
            ->get(route('settings.shift-rules.index', ['add' => 1]))
            ->assertOk()
            ->assertSee('Add a shift rule')
            ->assertDontSee('value="Standard Full-Time"', false);
    }

    /**
     * The old addresses still land somewhere sensible rather than 404ing on
     * somebody's bookmark.
     */
    public function test_the_old_create_and_edit_addresses_resolve_to_the_page(): void
    {
        $this->businessWeek();
        $rule = $this->rule();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('settings.shift-rules.create'))
            ->assertRedirect(route('settings.shift-rules.index', ['add' => 1]));

        $this->actingAs($owner)
            ->get(route('settings.shift-rules.edit', $rule))
            ->assertRedirect(route('settings.shift-rules.index', ['edit' => $rule->id]));
    }

    public function test_saving_returns_to_the_list_on_the_same_page(): void
    {
        $this->businessWeek();
        $rule = $this->rule();

        $this->actingAs($this->owner())
            ->patch(route('settings.shift-rules.update', $rule), $this->payload(['name' => 'Renamed']))
            ->assertRedirect(route('settings.shift-rules.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $rule->refresh()->name);
    }

    /**
     * §17: the rule page says who is on it, by name.
     *
     * "3 staff members" leaves an administrator deciding whether to retire a
     * rule with no way to see whose rota it would change.
     */
    public function test_the_rule_form_names_the_staff_on_it(): void
    {
        $this->businessWeek();
        $rule = $this->rule();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Amara', 'last_name' => 'Person',
            'email' => 'amara@acme.test', 'role' => 'service-provider',
            'shift_rule_id' => $rule->id,
        ]);

        $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index', ['edit' => $rule->id]))
            ->assertOk()
            ->assertSee('1 staff member')
            ->assertSee('Amara Person');
    }

    /**
     * A rule somebody is on cannot be deleted — the schedules that use it need
     * it to stay readable. Deactivate is the answer, and it is the action
     * directly above Delete on the card.
     */
    public function test_a_rule_in_use_cannot_be_deleted(): void
    {
        $this->businessWeek();
        $rule = $this->rule();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Amara', 'last_name' => 'Person',
            'email' => 'amara@acme.test', 'role' => 'service-provider',
            'shift_rule_id' => $rule->id,
        ]);

        $owner = $this->owner();

        $this->actingAs($owner)
            ->from(route('settings.shift-rules.index'))
            ->delete(route('settings.shift-rules.destroy', $rule))
            ->assertRedirect(route('settings.shift-rules.index'));

        $this->assertSame(1, ShiftRule::withoutGlobalScopes()->count());

        /* And the card does not offer Delete at all: an action that is always
           there and sometimes fails teaches the reader to expect failure. */
        /* Asserted on the verb, not the URL: destroy, update and status all
           hang off /shift-rules/{id}, so the delete address is a prefix of
           the others and would be "seen" whether or not it is offered. */
        $this->actingAs($owner)
            ->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertDontSee('value="DELETE"', false);
    }

    /**
     * A way out, in the corner every other screen keeps it in.
     *
     * Adding and editing are states of this page rather than pages of their
     * own, so the button has to know which state it is in: out of the form to
     * the rules, and out of the rules to App settings.
     */
    public function test_the_page_offers_a_way_back_from_wherever_the_reader_is(): void
    {
        $this->actingAs($this->owner());

        $this->get(route('settings.shift-rules.index'))
            ->assertOk()
            ->assertSee('href="'.route('settings.index').'"', false)
            ->assertSee(__('navigation.app_settings'));

        $this->get(route('settings.shift-rules.index', ['add' => 1]))
            ->assertOk()
            ->assertSee('href="'.route('settings.shift-rules.index').'"', false)
            ->assertSee(__('shift_rules.back_to_list'));
    }

    /** And only one of them: two buttons to the same place is one decision drawn twice. */
    public function test_the_form_carries_a_single_way_back(): void
    {
        $html = $this->actingAs($this->owner())
            ->get(route('settings.shift-rules.index', ['add' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, __('shift_rules.back_to_list')));
    }
}
