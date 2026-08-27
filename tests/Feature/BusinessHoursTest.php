<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationClosure;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance criteria from the Business Hours spec.
 */
class BusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio',
            'slug' => 'nadia',
            'business_email' => 'hello@nadia.test',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete',
            'completed_at' => now(),
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

    private function member(string $role): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $role.'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => $role,
        ]);

        return $user->fresh();
    }

    private function location(array $overrides = [], ?Tenant $tenant = null): Location
    {
        $tenant ??= $this->tenant;

        return Location::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->getTenantKey(),
            'name' => 'Riverside',
            'address_line1' => '1 River Street',
            'city' => 'Austin', 'state' => 'Texas', 'postal_code' => '78701',
            'country' => 'US', 'timezone' => 'America/Chicago',
            'phone' => '+1 512 555 0100', 'email' => 'riverside@nadia.test',
            'is_primary' => true,
        ], $overrides));
    }

    /** A week open Monday and Tuesday, Monday split for lunch. */
    private function week(array $overrides = []): array
    {
        return array_replace([
            1 => [
                'is_open' => '1',
                ['opens_at' => '09:00', 'closes_at' => '13:00'],
                ['opens_at' => '14:00', 'closes_at' => '19:00'],
            ],
            2 => ['is_open' => '1', ['opens_at' => '09:00', 'closes_at' => '17:00']],
        ], $overrides);
    }

    // ------------------------------------------------------- authorisation

    public function test_the_overview_is_reachable_by_the_owner(): void
    {
        $this->location();

        $this->actingAs($this->owner())
            ->get(route('settings.hours.index'))
            ->assertOk()
            ->assertSee('Riverside');
    }

    public function test_other_roles_are_turned_away(): void
    {
        $this->location();

        $this->actingAs($this->member('front-desk'))
            ->get(route('settings.hours.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_another_businesss_location_is_not_reachable(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'business_email' => 'hi@other.test']);
        $theirs = $this->location(['name' => 'Their Branch'], $other);

        $this->actingAs($this->owner())
            ->get(route('settings.hours.edit', $theirs))
            ->assertNotFound();
    }

    // ------------------------------------------------------- weekly hours

    public function test_weekly_hours_including_split_shifts_are_saved(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), ['hours' => $this->week()])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('toast.message', 'Business hours saved successfully.');

        $hours = $location->fresh()->hours;

        $this->assertCount(3, $hours);

        $monday = $hours->where('day_of_week', 1)->values();
        $this->assertCount(2, $monday);
        $this->assertSame('09:00', $monday[0]->timeValue('opens_at'));
        $this->assertSame('14:00', $monday[1]->timeValue('opens_at'));
        $this->assertSame(1, (int) $monday[1]->sort_order);
    }

    /**
     * Overlapping periods are the fault this rule exists for.
     *
     * The booking engine reads these to work out free slots, so a noon inside
     * two periods is a noon that can be double-booked — a fault a business
     * would discover from a client standing in reception.
     */
    public function test_overlapping_periods_are_refused(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), [
                'hours' => [1 => [
                    'is_open' => '1',
                    ['opens_at' => '09:00', 'closes_at' => '13:00'],
                    ['opens_at' => '12:00', 'closes_at' => '19:00'],
                ]],
            ])
            ->assertSessionHasErrors('hours.1');

        $this->assertCount(0, $location->fresh()->hours);
    }

    /**
     * Touching is not overlapping.
     *
     * 9–1 followed by 1–5 is a business that does not close for lunch,
     * written in two rows. Refusing it would refuse something correct.
     */
    public function test_periods_that_meet_exactly_are_allowed(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), [
                'hours' => [1 => [
                    'is_open' => '1',
                    ['opens_at' => '09:00', 'closes_at' => '13:00'],
                    ['opens_at' => '13:00', 'closes_at' => '17:00'],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertCount(2, $location->fresh()->hours);
    }

    public function test_a_closing_time_before_the_opening_time_is_refused(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), [
                'hours' => [1 => ['is_open' => '1', ['opens_at' => '17:00', 'closes_at' => '09:00']]],
            ])
            ->assertSessionHasErrors('hours.1');

        $this->assertCount(0, $location->fresh()->hours);
    }

    /**
     * A closed day is closed whatever its inputs still hold.
     */
    public function test_a_day_without_its_toggle_is_closed(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), [
                'hours' => [0 => [['opens_at' => '09:00', 'closes_at' => '17:00']]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $location->fresh()->hours);
    }

    // ---------------------------------------------------- future schedules

    /**
     * The whole point of an effective date: today is left alone.
     */
    public function test_a_future_schedule_does_not_change_todays_hours(): void
    {
        $location = $this->location();

        $owner = $this->owner();

        $this->actingAs($owner)
            ->patch(route('settings.hours.update', $location), ['hours' => $this->week()]);

        $this->actingAs($owner)
            ->patch(route('settings.hours.update', $location), [
                'effective_from' => now()->addMonth()->toDateString(),
                'hours' => [3 => ['is_open' => '1', ['opens_at' => '11:00', 'closes_at' => '20:00']]],
            ])
            ->assertSessionHasNoErrors();

        $fresh = $location->fresh();

        // Today still reads the original week.
        $this->assertCount(3, $fresh->hours);
        $this->assertSame('09:00', $fresh->hours->firstWhere('day_of_week', 1)->timeValue('opens_at'));

        // And the future one is stored alongside it, not instead of it.
        $this->assertCount(1, $fresh->futureScheduleDates());
        $this->assertCount(4, $fresh->allHours);
    }

    public function test_an_effective_date_in_the_past_is_refused(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), [
                'effective_from' => now()->subDay()->toDateString(),
                'hours' => $this->week(),
            ])
            ->assertSessionHasErrors('effective_from');
    }

    public function test_a_future_schedule_can_be_discarded(): void
    {
        $location = $this->location();
        $owner = $this->owner();
        $date = now()->addMonth()->toDateString();

        $this->actingAs($owner)->patch(route('settings.hours.update', $location), ['hours' => $this->week()]);
        $this->actingAs($owner)->patch(route('settings.hours.update', $location), [
            'effective_from' => $date,
            'hours' => [3 => ['is_open' => '1', ['opens_at' => '11:00', 'closes_at' => '20:00']]],
        ]);

        $this->actingAs($owner)
            ->delete(route('settings.hours.schedule.destroy', $location), ['schedule' => $date])
            ->assertSessionHas('toast.type', 'success');

        $this->assertCount(0, $location->fresh()->futureScheduleDates());
        // The current week survives.
        $this->assertCount(3, $location->fresh()->hours);
    }

    /**
     * The hours in force cannot be discarded.
     *
     * A branch with no opening times at all is a state the booking engine has
     * no reading of. Closing every day is how a business says that, and it
     * says it explicitly.
     */
    public function test_the_current_schedule_cannot_be_discarded(): void
    {
        $location = $this->location();
        $owner = $this->owner();

        $this->actingAs($owner)->patch(route('settings.hours.update', $location), ['hours' => $this->week()]);

        $this->actingAs($owner)
            ->delete(route('settings.hours.schedule.destroy', $location), ['schedule' => Location::EPOCH])
            ->assertSessionHasErrors('schedule');

        $this->assertCount(3, $location->fresh()->hours);
    }

    // -------------------------------------------------- across locations

    public function test_hours_can_be_applied_to_other_locations(): void
    {
        $location = $this->location();
        $other = $this->location(['name' => 'Eastside', 'is_primary' => false]);

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), [
                'hours' => $this->week(),
                'apply_to' => [$other->id],
            ])
            ->assertSessionHas('toast.message', 'Business hours saved successfully. Also applied to 1 other location.');

        $this->assertCount(3, $other->fresh()->hours);
    }

    /**
     * The count in the message is the other locations, not this one.
     *
     * prepend() mutates the collection it is called on, so counting after
     * building the save list counted this location among the others — and the
     * screen cheerfully reported applying a change it had not made.
     */
    public function test_saving_without_ticking_anything_claims_no_other_locations(): void
    {
        $location = $this->location();
        $this->location(['name' => 'Eastside', 'is_primary' => false]);

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), ['hours' => $this->week()])
            ->assertSessionHas('toast.message', 'Business hours saved successfully.');
    }

    /**
     * A location id from another business must not be written to.
     */
    public function test_hours_cannot_be_applied_to_another_businesss_location(): void
    {
        $location = $this->location();

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'business_email' => 'hi@other.test']);
        $theirs = $this->location(['name' => 'Their Branch'], $other);

        $this->actingAs($this->owner())
            ->patch(route('settings.hours.update', $location), [
                'hours' => $this->week(),
                'apply_to' => [$theirs->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $theirs->fresh()->hours);
    }

    // -------------------------------------- holidays, closures, special

    public function test_a_holiday_can_be_added(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->post(route('settings.hours.closures.store', $location), [
                'type' => 'public_holiday',
                'name' => 'Christmas Day',
                'starts_on' => '2026-12-25',
                'is_closed_all_day' => '1',
            ])
            ->assertSessionHasNoErrors();

        $closure = $location->fresh()->closures->first();

        $this->assertSame('Christmas Day', $closure->name);
        $this->assertTrue($closure->is_closed_all_day);
        // A single day is a range of one, so the end date fills itself in.
        $this->assertSame('2026-12-25', $closure->ends_on->toDateString());
        $this->assertTrue($closure->isSingleDay());
    }

    public function test_a_temporary_closure_spans_a_date_range(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->post(route('settings.hours.closures.store', $location), [
                'type' => 'maintenance',
                'name' => 'Refit',
                'starts_on' => '2026-03-03',
                'ends_on' => '2026-03-17',
                'is_closed_all_day' => '1',
                'notes' => 'New basins going in.',
            ])
            ->assertSessionHasNoErrors();

        $closure = $location->fresh()->closures->first();

        $this->assertFalse($closure->isSingleDay());
        $this->assertSame('New basins going in.', $closure->notes);
        // Day nine of a refit is as closed as day one.
        $this->assertTrue($location->closures()->covering('2026-03-11')->exists());
    }

    public function test_special_hours_replace_the_days_opening_times(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->post(route('settings.hours.closures.store', $location), [
                'type' => 'special_hours',
                'name' => 'Christmas Eve',
                'starts_on' => '2026-12-24',
                'opens_at' => '09:00',
                'closes_at' => '16:00',
            ])
            ->assertSessionHasNoErrors();

        $closure = $location->fresh()->closures->first();

        $this->assertFalse($closure->is_closed_all_day);
        $this->assertSame('09:00', $closure->timeValue('opens_at'));
    }

    public function test_special_hours_without_times_are_refused(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->post(route('settings.hours.closures.store', $location), [
                'type' => 'special_hours',
                'name' => 'Christmas Eve',
                'starts_on' => '2026-12-24',
            ])
            ->assertSessionHasErrors(['opens_at', 'closes_at']);
    }

    /**
     * A full-day closure keeps no times.
     *
     * A row saying "closed all day" that also carries 9 to 5 leaves the next
     * reader — or the next query that forgets to check the flag — deciding
     * which half to believe.
     */
    public function test_a_full_day_closure_clears_any_times(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->post(route('settings.hours.closures.store', $location), [
                'type' => 'closure',
                'name' => 'Training',
                'starts_on' => '2026-05-04',
                'is_closed_all_day' => '1',
                'opens_at' => '09:00',
                'closes_at' => '17:00',
            ]);

        $closure = $location->fresh()->closures->first();

        $this->assertNull($closure->opens_at);
        $this->assertNull($closure->closes_at);
    }

    /**
     * One answer per date.
     *
     * Two rows covering next Tuesday leave the booking engine choosing
     * between them, and whichever it picks will be wrong half the time.
     */
    public function test_two_entries_cannot_cover_the_same_date(): void
    {
        $location = $this->location();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('settings.hours.closures.store', $location), [
            'type' => 'public_holiday', 'name' => 'Christmas Day',
            'starts_on' => '2026-12-25', 'is_closed_all_day' => '1',
        ]);

        $this->actingAs($owner)
            ->post(route('settings.hours.closures.store', $location), [
                'type' => 'maintenance', 'name' => 'Stocktake',
                'starts_on' => '2026-12-23', 'ends_on' => '2026-12-26', 'is_closed_all_day' => '1',
            ])
            ->assertSessionHasErrors('starts_on');

        $this->assertCount(1, $location->fresh()->closures);
    }

    public function test_an_entry_can_be_edited_without_clashing_with_itself(): void
    {
        $location = $this->location();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('settings.hours.closures.store', $location), [
            'type' => 'public_holiday', 'name' => 'Christmas Day',
            'starts_on' => '2026-12-25', 'is_closed_all_day' => '1',
        ]);

        $closure = $location->fresh()->closures->first();

        $this->actingAs($owner)
            ->patch(route('settings.hours.closures.update', [$location, $closure]), [
                'type' => 'public_holiday', 'name' => 'Christmas Day (closed)',
                'starts_on' => '2026-12-25', 'is_closed_all_day' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Christmas Day (closed)', $closure->fresh()->name);
    }

    public function test_an_entry_from_another_location_cannot_be_edited_through_this_one(): void
    {
        $location = $this->location();
        $other = $this->location(['name' => 'Eastside', 'is_primary' => false]);

        $theirs = LocationClosure::create([
            'location_id' => $other->id, 'type' => 'closure', 'name' => 'Theirs',
            'starts_on' => '2026-06-01', 'ends_on' => '2026-06-01',
        ]);

        $this->actingAs($this->owner())
            ->delete(route('settings.hours.closures.destroy', [$location, $theirs]))
            ->assertNotFound();

        $this->assertModelExists($theirs);
    }

    public function test_an_entry_can_be_deleted(): void
    {
        $location = $this->location();

        $closure = LocationClosure::create([
            'location_id' => $location->id, 'type' => 'closure', 'name' => 'Refit',
            'starts_on' => '2026-06-01', 'ends_on' => '2026-06-02',
        ]);

        $this->actingAs($this->owner())
            ->delete(route('settings.hours.closures.destroy', [$location, $closure]))
            ->assertSessionHas('toast.type', 'success');

        $this->assertModelMissing($closure);
    }

    /**
     * A closure that started last week and runs to Friday is the most
     * relevant thing on the overview; filtering on the start date hides it.
     */
    public function test_the_overview_lists_a_closure_already_under_way(): void
    {
        $location = $this->location();

        LocationClosure::create([
            'location_id' => $location->id, 'type' => 'maintenance', 'name' => 'Refit',
            'starts_on' => now()->subDays(3)->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($this->owner())
            ->get(route('settings.hours.index'))
            ->assertOk()
            ->assertSee('Refit')
            ->assertSee('In progress');
    }

    public function test_a_past_closure_is_not_listed(): void
    {
        $location = $this->location();

        LocationClosure::create([
            'location_id' => $location->id, 'type' => 'closure', 'name' => 'Last year’s refit',
            'starts_on' => now()->subYear()->toDateString(),
            'ends_on' => now()->subYear()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($this->owner())
            ->get(route('settings.hours.index'))
            ->assertOk()
            ->assertDontSee('Last year’s refit');
    }

    // ------------------------------------------------------- time display

    public function test_times_follow_the_businesss_twelve_or_twenty_four_hour_setting(): void
    {
        $location = $this->location();
        $owner = $this->owner();

        $this->actingAs($owner)->patch(route('settings.hours.update', $location), ['hours' => $this->week()]);

        $this->tenant->forceFill(['time_format' => '24'])->save();

        // Re-read the user, so the request does not answer from a tenant
        // relation loaded before the setting changed.
        $this->actingAs($owner->fresh())
            ->get(route('settings.hours.index'))
            ->assertOk()
            ->assertSee('09:00 – 13:00')
            ->assertDontSee('9:00 AM');

        $this->tenant->forceFill(['time_format' => '12'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('settings.hours.index'))
            ->assertOk()
            ->assertSee('9:00 AM – 1:00 PM');
    }

    // --------------------------------------------------------- the module

    public function test_the_editor_is_the_shared_onboarding_island(): void
    {
        $location = $this->location();

        $this->actingAs($this->owner())
            ->get(route('settings.hours.edit', $location))
            ->assertOk()
            ->assertSee('data-vue-component="BusinessHours"', false)
            ->assertSee('splitPeriods', false)
            ->assertSee('use12Hours', false);
    }

    public function test_the_app_settings_card_links_to_the_module(): void
    {
        $this->location();

        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.hours.index'), false);
    }
}
