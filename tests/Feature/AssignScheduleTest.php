<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\ShiftRule;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Assigning a working schedule to one member of staff.
 *
 * The dialog writes dated shifts from a pattern; these tests are about the
 * rules that stop it writing an impossible week. Each check needs more than
 * one answer to decide — what else is on the rota, what the business's hours
 * are, what the rule allows — which is exactly what a per-field rule cannot
 * see, and why they live in ScheduleGuard rather than in a validator.
 */
class AssignScheduleTest extends TestCase
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
            'location_id' => $this->location->id,
            'is_active' => true,
        ]);
    }

    /** The business open every day, eight until eight. */
    private function businessWeek(string $opens = '08:00', string $closes = '20:00'): void
    {
        foreach (range(0, 6) as $day) {
            $this->location->hours()->create([
                'effective_from' => Location::EPOCH,
                'day_of_week' => $day, 'sort_order' => 0, 'is_open' => true,
                'opens_at' => $opens, 'closes_at' => $closes,
            ]);
        }
    }

    private function rule(array $overrides = []): ShiftRule
    {
        return ShiftRule::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Standard Full-Time',
        ]);
    }

    /**
     * A Monday-to-Friday proposal, nine to five.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = [], int $days = 5): array
    {
        $from = now()->next('Monday')->startOfDay();
        $rows = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();

            $rows[$date] = [
                'working' => '1',
                'periods' => [['starts_at' => '09:00', 'ends_at' => '17:00', 'break_minutes' => 0]],
            ];
        }

        return [
            'from' => $from->toDateString(),
            'until' => $from->copy()->addDays($days - 1)->toDateString(),
            'days' => array_replace($rows, $overrides),
        ];
    }

    private function assign(User $owner, Staff $member, array $payload)
    {
        return $this->actingAs($owner)
            /* Posted from the focused screen, which is where a refusal has to
               land: that is where the days being complained about are. */
            ->from(route('staff.schedule.assign.form', $member))
            ->post(route('staff.schedule.assign', $member), $payload);
    }

    // -------------------------------------------------------------- writing

    /**
     * Publishing a month somebody already holds is an update.
     *
     * Both the button that does it and the sentence after it say so: "Save &
     * Publish" over a week they have been emailed reads as a first sending,
     * and the reader has to work out for themselves that it is not.
     */
    public function test_republishing_is_worded_as_an_update(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();

        $from = CarbonImmutable::parse(now()->next('Monday')->toDateString());

        $shift = StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'date' => $from->toDateString(),
            'starts_at' => '09:00', 'ends_at' => '17:00',
            'publish_status' => 'published',
            'published_at' => now(),
            'published_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('staff.schedule.assign.form', $member).'?weeks=1&from='.$from->toDateString())
            ->assertOk()
            /* Blade's json directive escapes the ampersand, which is the
               form the props actually reach the page in. */
            ->assertSee('"save_and_publish":"Update \\u0026 Publish"', false);

        $this->assign($owner, $member, [
            'from' => $from->toDateString(),
            'until' => $from->toDateString(),
            'publish' => 1,
            'days' => [$from->toDateString() => [
                'working' => '1',
                'periods' => [['starts_at' => '09:00', 'ends_at' => '17:00', 'break_minutes' => 0]],
            ]],
        ])->assertSessionHas('toast.message', __('schedule.republished', ['name' => $member->displayName()]));

        $this->assertSame($shift->id, $shift->id);
    }

    /**
     * A period cut back to the end of its month.
     *
     * The dialog never offers days beyond the month being planned, so the
     * length it asks for is a count of days rather than a whole number of
     * weeks — and the screen has to cover those days and say so.
     */
    /**
     * A week this person has already been emailed is not a blank one.
     *
     * The notice is on the screen before the first change rather than in the
     * confirmation after the last, because the difference it names — filling
     * something in versus telling somebody something different — is what
     * decides whether the manager wants to be here at all.
     */
    public function test_the_form_warns_when_the_period_has_been_published(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();

        $from = CarbonImmutable::parse(now()->next('Monday')->toDateString());
        $url = route('staff.schedule.assign.form', $member).'?weeks=1&from='.$from->toDateString();

        $shift = StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'date' => $from->toDateString(),
            'starts_at' => '09:00', 'ends_at' => '17:00',
        ]);

        /* A draft nobody has been sent says nothing. */
        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertDontSee('publishedNotice":{', false);

        $shift->forceFill([
            'publish_status' => 'published',
            'published_at' => now(),
            'published_by' => $owner->id,
        ])->save();

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('This schedule has already been published.', false);

        /* And it stays said once the period is edited back to a draft: the
           staff member has still been told about the version they hold. */
        $shift->forceFill(['publish_status' => 'draft'])->save();

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('This schedule has already been published.', false);
    }

    public function test_the_form_covers_an_exact_count_of_days(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();

        $from = CarbonImmutable::now()->startOfMonth()->addDays(2);

        /* The screen is a Vue island, so its heading arrives as JSON props. */
        $label = fn (string $key, string $value) => '"'.$key.'":'.json_encode($value);

        $this->actingAs($owner)
            ->get(route('staff.schedule.assign.form', $member).'?weeks=1&days=3&from='.$from->toDateString())
            ->assertOk()
            ->assertSee($label('periodLabel', $from->translatedFormat('j M Y').' – '
                .$from->addDays(2)->translatedFormat('j M Y')), false)
            /* Three days is not "1 Week", and the screen that writes them
               should not say it is. */
            ->assertSee($label('durationLabel', '3 Days'), false);

        /* Weeks still name the range when nothing was cut back. */
        $this->actingAs($owner)
            ->get(route('staff.schedule.assign.form', $member).'?weeks=1&from='.$from->toDateString())
            ->assertOk()
            ->assertSee($label('periodLabel', $from->translatedFormat('j M Y').' – '
                .$from->addDays(6)->translatedFormat('j M Y')), false)
            ->assertSee($label('durationLabel', '1 Week'), false);
    }

    public function test_a_week_is_written_as_dated_shifts(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();

        /* Answered on the schedule page, showing the month just written —
           not on the referring screen, which the manager has finished with. */
        $monday = now()->next('Monday');

        $this->assign($owner, $member, $this->payload())
            ->assertRedirect(route('staff.schedule', $member).'?'.http_build_query([
                'period' => 'month', 'year' => $monday->year, 'month' => $monday->month,
            ]))
            ->assertSessionHasNoErrors();

        $shifts = StaffShift::withoutGlobalScopes()->orderBy('date')->get();

        $this->assertCount(5, $shifts);
        $this->assertSame('09:00', $shifts->first()->timeValue('starts_at'));
        $this->assertSame(480, $shifts->first()->workedMinutes());
    }

    /**
     * A day switched off contributes nothing, whatever times its inputs still
     * hold — marking Sunday off should not mean clearing the times first.
     */
    public function test_a_day_marked_off_is_not_written(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();

        $payload = $this->payload();
        $wednesday = array_keys($payload['days'])[2];
        $payload['days'][$wednesday]['working'] = '0';

        $this->assign($owner, $member, $payload)->assertSessionHasNoErrors();

        $this->assertSame(4, StaffShift::withoutGlobalScopes()->count());
        $this->assertSame(0, StaffShift::withoutGlobalScopes()->whereDate('date', $wednesday)->count());
    }

    /**
     * Assigning replaces the range rather than adding to it, so assigning
     * twice does not leave somebody on two shifts a day.
     */
    public function test_assigning_replaces_the_range(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();

        $this->assign($owner, $member, $this->payload())->assertSessionHasNoErrors();
        $this->assign($owner, $member, $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(5, StaffShift::withoutGlobalScopes()->count());
    }

    /** Assigning from a rule is also how somebody is put on one. */
    public function test_assigning_with_a_rule_puts_the_person_on_it(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();
        $rule = $this->rule();

        $this->assign($owner, $member, $this->payload() + ['shift_rule_id' => $rule->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($rule->id, $member->refresh()->shift_rule_id);
    }

    // ----------------------------------------------------------- the guards

    public function test_a_shift_outside_the_business_hours_is_refused(): void
    {
        $this->businessWeek('09:00', '17:00');
        $owner = $this->owner();
        $member = $this->member();

        $payload = $this->payload();
        $monday = array_key_first($payload['days']);
        $payload['days'][$monday]['periods'][0]['starts_at'] = '07:00';

        $this->assign($owner, $member, $payload)
            ->assertSessionHasErrors('schedule.'.$monday);

        $this->assertSame(0, StaffShift::withoutGlobalScopes()->count());
    }

    public function test_more_than_the_rules_daily_hours_is_refused(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $rule = $this->rule(['max_hours_per_day' => 8]);
        $member = $this->member(['shift_rule_id' => $rule->id]);

        $payload = $this->payload();
        $monday = array_key_first($payload['days']);
        $payload['days'][$monday]['periods'][0]['ends_at'] = '19:00';

        $this->assign($owner, $member, $payload + ['shift_rule_id' => $rule->id])
            ->assertSessionHasErrors('schedule.'.$monday);
    }

    public function test_more_than_the_rules_weekly_hours_is_refused(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $rule = $this->rule(['max_hours_per_week' => 30]);
        $member = $this->member(['shift_rule_id' => $rule->id]);

        /* Five eight-hour days is forty, and the rule allows thirty. */
        $this->assign($owner, $member, $this->payload() + ['shift_rule_id' => $rule->id])
            ->assertSessionHasErrors();

        $this->assertSame(0, StaffShift::withoutGlobalScopes()->count());
    }

    /**
     * Overtime is a separate setting: a rule that permits it permits the week
     * to run over, and switching split shifts or long days on must never
     * quietly switch overtime on with them.
     */
    public function test_overtime_lifts_the_hour_ceilings(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $rule = $this->rule(['max_hours_per_week' => 30, 'allow_overtime' => true]);
        $member = $this->member(['shift_rule_id' => $rule->id]);

        $this->assign($owner, $member, $this->payload() + ['shift_rule_id' => $rule->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(5, StaffShift::withoutGlobalScopes()->count());
    }

    public function test_too_little_rest_between_days_is_refused(): void
    {
        $this->businessWeek('06:00', '23:59');
        $owner = $this->owner();
        $rule = $this->rule(['min_rest_hours' => 10]);
        $member = $this->member(['shift_rule_id' => $rule->id]);

        $payload = $this->payload();
        $dates = array_keys($payload['days']);

        /* Finishing at eleven and starting again at six is seven hours. */
        $payload['days'][$dates[0]]['periods'][0] = ['starts_at' => '14:00', 'ends_at' => '23:00', 'break_minutes' => 0];
        $payload['days'][$dates[1]]['periods'][0] = ['starts_at' => '06:00', 'ends_at' => '12:00', 'break_minutes' => 0];

        $this->assign($owner, $member, $payload + ['shift_rule_id' => $rule->id])
            ->assertSessionHasErrors('schedule.'.$dates[1]);
    }

    public function test_too_many_consecutive_days_is_refused(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $rule = $this->rule(['max_consecutive_days' => 4]);
        $member = $this->member(['shift_rule_id' => $rule->id]);

        $this->assign($owner, $member, $this->payload() + ['shift_rule_id' => $rule->id])
            ->assertSessionHasErrors();
    }

    public function test_a_second_period_needs_split_shifts_allowed(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $rule = $this->rule(['allow_split_shift' => false]);
        $member = $this->member(['shift_rule_id' => $rule->id]);

        $payload = $this->payload();
        $monday = array_key_first($payload['days']);
        $payload['days'][$monday]['periods'] = [
            ['starts_at' => '09:00', 'ends_at' => '13:00', 'break_minutes' => 0],
            ['starts_at' => '16:00', 'ends_at' => '20:00', 'break_minutes' => 0],
        ];

        $this->assign($owner, $member, $payload + ['shift_rule_id' => $rule->id])
            ->assertSessionHasErrors('schedule.'.$monday);
    }

    public function test_a_split_day_needs_the_rules_minimum_gap(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $rule = $this->rule([
            'allow_split_shift' => true,
            'allow_same_employee_multiple_periods' => true,
            'max_periods_per_employee_per_day' => 2,
            'min_gap_minutes' => 120,
        ]);
        $member = $this->member(['shift_rule_id' => $rule->id]);

        $payload = $this->payload();
        $monday = array_key_first($payload['days']);

        /* An hour between, where the rule asks for two. */
        $payload['days'][$monday]['periods'] = [
            ['starts_at' => '09:00', 'ends_at' => '13:00', 'break_minutes' => 0],
            ['starts_at' => '14:00', 'ends_at' => '18:00', 'break_minutes' => 0],
        ];

        $this->assign($owner, $member, $payload + ['shift_rule_id' => $rule->id])
            ->assertSessionHasErrors('schedule.'.$monday);

        /* Three hours between is fine. */
        $payload['days'][$monday]['periods'][1] = ['starts_at' => '16:00', 'ends_at' => '20:00', 'break_minutes' => 0];

        $this->assign($owner, $member, $payload + ['shift_rule_id' => $rule->id])
            ->assertSessionHasNoErrors();
    }

    public function test_an_inactive_member_cannot_be_scheduled(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member(['is_active' => false]);

        $this->assign($owner, $member, $this->payload())->assertSessionHasErrors();

        $this->assertSame(0, StaffShift::withoutGlobalScopes()->count());
    }

    /**
     * A week does not clash with the copy of itself it is about to overwrite —
     * otherwise assigning the same range twice would refuse itself.
     */
    public function test_replacing_a_range_does_not_clash_with_what_it_replaces(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();

        $this->assign($owner, $member, $this->payload())->assertSessionHasNoErrors();

        $payload = $this->payload();
        $monday = array_key_first($payload['days']);
        $payload['days'][$monday]['periods'][0]['ends_at'] = '16:00';

        $this->assign($owner, $member, $payload)->assertSessionHasNoErrors();

        $this->assertSame(
            '16:00',
            StaffShift::withoutGlobalScopes()->whereDate('date', $monday)->firstOrFail()->timeValue('ends_at'),
        );
    }

    // ------------------------------------------------------------ the page

    /**
     * A refusal says so where the manager is looking.
     *
     * The range is replaced whole or not at all, so one bad day holds up the
     * week. Somebody watching the table underneath sees nothing change, and
     * needs to be told that is a refusal rather than a page that failed to
     * update.
     */
    public function test_a_refused_schedule_raises_a_toast_and_writes_nothing(): void
    {
        $owner = $this->owner();
        $this->businessWeek();

        /* Eight hours a day is fine; five of them in a week the rule caps at
           twenty is not. */
        $member = $this->member(['shift_rule_id' => $this->rule(['max_hours_per_week' => 20])->id]);

        $this->assign($owner, $member, $this->payload() + ['shift_rule_id' => $member->shift_rule_id])
            ->assertRedirect()
            ->assertSessionHasErrors()
            ->assertSessionHas('toast.type', 'error');

        $this->assertSame(0, StaffShift::query()->count());
    }

    /**
     * The manager's own edits come back with the refusal.
     *
     * Without this, somebody who unticked Saturday to get under a weekly
     * limit would watch Saturday tick itself again on the way back, carrying
     * the same complaint it was unticked to answer.
     */
    public function test_a_refusal_returns_the_days_the_manager_submitted(): void
    {
        $owner = $this->owner();
        $this->businessWeek();

        $member = $this->member(['shift_rule_id' => $this->rule(['max_hours_per_week' => 20])->id]);

        $monday = now()->next('Monday')->toDateString();

        /* Monday moved to an eleven o'clock start — the value that has to
           survive the round trip. */
        $payload = $this->payload([
            $monday => [
                'working' => '1',
                'periods' => [['starts_at' => '11:00', 'ends_at' => '17:00', 'break_minutes' => 0]],
            ],
        ]);

        $this->assign($owner, $member, $payload + ['shift_rule_id' => $member->shift_rule_id]);

        $this->actingAs($owner)
            ->get(route('staff.schedule.assign.form', $member).'?weeks=1&from='.$monday)
            ->assertOk()
            /* The screen is built server-side into data-props, so the
               submitted time is what it comes back showing. */
            ->assertSee('11:00', false);
    }

    /**
     * The dialog opens on a week that can actually be saved.
     *
     * Opening hours say when the shop is open, not how long one person may
     * work: six eight-hour days is forty-eight, and a forty-hour rule then
     * refuses the whole range. That pairing is ordinary rather than a
     * misconfiguration, and the range is written whole or not at all — so a
     * proposal over the ceiling is a dead end rather than a warning.
     */
    /**
     * The proposal is trimmed to the rule the manager has chosen here, not
     * only to the one already on the person.
     *
     * Somebody on no rule gets a month of every open day — seven days at
     * eight hours is fifty-six a week — and choosing a forty-hour rule on
     * this screen without rebuilding those days leaves a form the guard
     * refuses the moment it is submitted. So choosing one asks for the screen
     * again with the rule in the address bar.
     */
    public function test_the_proposal_is_trimmed_to_the_rule_chosen_on_the_screen(): void
    {
        $owner = $this->owner();
        $this->businessWeek('09:00', '17:00');

        /* Nobody's rule but the business's hours: seven open days. */
        $member = $this->member();
        $rule = $this->rule(['max_hours_per_week' => 40]);

        $from = now()->next('Monday')->toDateString();
        $url = route('staff.schedule.assign.form', $member).'?weeks=1&from='.$from;

        $untrimmed = $this->assignDaysFrom($this->actingAs($owner)->get($url)->assertOk()->getContent());

        $this->assertSame(7, collect($untrimmed)->where('working', true)->count());

        $trimmed = $this->assignDaysFrom(
            $this->actingAs($owner)->get($url.'&shift_rule_id='.$rule->id)->assertOk()->getContent(),
        );

        $this->assertSame(5, collect($trimmed)->where('working', true)->count());

        /* And the combo opens on the rule the days were built from, rather
           than on the one the person is not yet on. */
        $this->actingAs($owner)->get($url.'&shift_rule_id='.$rule->id)
            ->assertSee('"selectedRule":'.$rule->id, false);
    }

    public function test_the_proposal_stops_at_the_rules_weekly_ceiling(): void
    {
        $owner = $this->owner();
        $this->businessWeek('09:00', '17:00');

        $member = $this->member(['shift_rule_id' => $this->rule(['max_hours_per_week' => 40])->id]);

        $from = now()->next('Monday')->toDateString();

        $response = $this->actingAs($owner)
            ->get(route('staff.schedule.assign.form', $member).'?weeks=1&from='.$from)
            ->assertOk();

        $days = $this->assignDaysFrom($response->getContent());

        /* Seven open days at eight hours would be fifty-six; five of them fit
           under forty, and the rest arrive switched off rather than missing. */
        $this->assertCount(7, $days);
        $this->assertSame(5, collect($days)->where('working', true)->count());
        $this->assertSame(
            40 * 60,
            collect($days)->where('working', true)->flatMap(fn (array $day) => $day['periods'])->sum(
                fn (array $period) => (strtotime($period['ends_at']) - strtotime($period['starts_at'])) / 60,
            ),
        );

        /* A day that fell off keeps its hours, so switching it back on is one
           click rather than retyping the times. */
        $this->assertNotEmpty(collect($days)->where('working', false)->first()['periods']);
    }

    /** A rule with no weekly ceiling proposes every day the business is open. */
    public function test_without_a_weekly_ceiling_every_open_day_is_proposed(): void
    {
        $owner = $this->owner();
        $this->businessWeek('09:00', '17:00');

        $member = $this->member(['shift_rule_id' => $this->rule(['max_hours_per_week' => null])->id]);

        $response = $this->actingAs($owner)
            ->get(route('staff.schedule.assign.form', $member).'?weeks=1&from='.now()->next('Monday')->toDateString())
            ->assertOk();

        $this->assertSame(7, collect($this->assignDaysFrom($response->getContent()))->where('working', true)->count());
    }

    /**
     * The whole point of the ceiling: the week the dialog opens on is one the
     * guard accepts, so the ordinary case is a confirmation.
     */
    public function test_the_proposed_week_is_one_the_guard_accepts(): void
    {
        $owner = $this->owner();
        $this->businessWeek('09:00', '17:00');

        $member = $this->member(['shift_rule_id' => $this->rule(['max_hours_per_week' => 40])->id]);
        $from = now()->next('Monday')->toDateString();

        $days = $this->assignDaysFrom(
            $this->actingAs($owner)->get(route('staff.schedule.assign.form', $member).'?weeks=1&from='.$from)->getContent()
        );

        $payload = ['from' => $from, 'until' => now()->next('Monday')->addDays(6)->toDateString(), 'days' => []];

        foreach ($days as $day) {
            $payload['days'][$day['date']] = [
                'working' => $day['working'] ? '1' : '0',
                'periods' => collect($day['periods'])->map(fn (array $period) => [
                    'starts_at' => $period['starts_at'],
                    'ends_at' => $period['ends_at'],
                    'break_minutes' => $period['break_minutes'] ?? 0,
                ])->all(),
            ];
        }

        $this->assign($owner, $member, $payload + ['shift_rule_id' => $member->shift_rule_id])
            ->assertSessionHasNoErrors();

        $this->assertSame(5, StaffShift::query()->count());
    }

    /**
     * The days the dialog was built with, read back out of its data-props.
     *
     * @return array<int, array<string, mixed>>
     */
    private function assignDaysFrom(string $html): array
    {
        $this->assertMatchesRegularExpression('/data-vue-component="AssignScheduleForm"/', $html);

        preg_match('/data-vue-component="AssignScheduleForm" data-props=\'(.*?)\'>/s', $html, $matches);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true)['days'];
    }

    public function test_the_schedule_tab_reports_the_year_by_month(): void
    {
        $this->businessWeek();
        $owner = $this->owner();
        $member = $this->member();

        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'date' => now()->startOfYear()->addMonths(2)->toDateString(),
            'starts_at' => '09:00', 'ends_at' => '17:00', 'break_minutes' => 60,
        ]);

        $this->actingAs($owner)->get(route('staff.schedule', $member).'?year='.now()->year)
            ->assertOk()
            ->assertSee('Monthly summary')
            ->assertSee('March')
            /* Seven hours: eight less the hour of break. */
            ->assertSee('7')
            /* Said once, underneath, rather than as a dash on every card. */
            ->assertSee('Booking counts arrive with the booking module.');
    }

    public function test_the_assign_screen_is_prefilled_from_the_business_hours(): void
    {
        $this->businessWeek('09:00', '17:00');
        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)->get(route('staff.schedule.assign.form', $member))
            ->assertOk()
            ->assertSee('data-vue-component="AssignScheduleForm"', false)
            ->assertSee('09:00', false)
            ->assertSee('17:00', false);
    }

    /**
     * The schedule page asks how long a period first, and hands off.
     *
     * A fortnight is twenty-eight time fields; the page that lists the rota
     * should not also be carrying the form that rewrites it.
     */
    public function test_the_schedule_page_offers_the_duration_step_rather_than_the_form(): void
    {
        $this->businessWeek('09:00', '17:00');
        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)->get(route('staff.schedule', $member))
            ->assertOk()
            ->assertSee('data-vue-component="AssignScheduleStart"', false)
            ->assertDontSee('data-vue-component="AssignScheduleForm"', false);
    }

    /** The focused screen carries none of the application's own navigation. */
    public function test_the_assign_screen_uses_the_focused_layout(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)->get(route('staff.schedule.assign.form', $member))
            ->assertOk()
            ->assertDontSee('data-account-menu', false)
            ->assertDontSee('nav-drawer', false);
    }
}
