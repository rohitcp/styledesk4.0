<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shifts — working hours on a named date.
 *
 * The dated half of scheduling, and the tests are written against the thing
 * that makes it its own feature: a shift belongs to one day, two shifts for
 * one person on that day may not overlap, and a dropped shift is cancelled
 * rather than deleted so the week still shows what happened.
 */
class ShiftsTest extends TestCase
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

    private function member(string $first = 'Amara'): Staff
    {
        return Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => $first, 'last_name' => 'Person',
            'email' => mb_strtolower($first).'@acme.test',
            'role' => 'service-provider',
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(Staff $member, array $overrides = []): array
    {
        return array_merge([
            'staff_id' => $member->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-12',
            'starts_at' => '10:00',
            'ends_at' => '16:00',
            'break_minutes' => 30,
            'type' => 'regular',
            'status' => 'scheduled',
        ], $overrides);
    }

    private function shift(Staff $member, array $overrides = []): StaffShift
    {
        return StaffShift::withoutGlobalScopes()->create(
            $this->payload($member, $overrides) + ['tenant_id' => $this->tenant->getTenantKey()]
        );
    }

    // -------------------------------------------------------------- create

    public function test_a_shift_is_created_for_a_named_day(): void
    {
        $member = $this->member();

        $this->actingAs($this->owner())
            ->post(route('shifts.store'), $this->payload($member))
            ->assertRedirect(route('shifts.index'))
            ->assertSessionHasNoErrors();

        $shift = StaffShift::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($member->id, $shift->staff_id);
        $this->assertSame('2026-09-12', $shift->date->toDateString());
        $this->assertSame('10:00', $shift->timeValue('starts_at'));
        $this->assertSame('16:00', $shift->timeValue('ends_at'));
        /* Six hours less the half-hour break: the break is unpaid time inside
           the shift, not a second pair of times. */
        $this->assertSame(330, $shift->workedMinutes());
    }

    public function test_save_and_add_another_returns_to_an_empty_form(): void
    {
        $member = $this->member();

        $this->actingAs($this->owner())
            ->post(route('shifts.store'), $this->payload($member, ['after_save' => 'add_another']))
            ->assertRedirect(route('shifts.create'));
    }

    // ---------------------------------------------------------- validation

    public function test_a_shift_cannot_end_before_it_starts(): void
    {
        $member = $this->member();

        $this->actingAs($this->owner())
            ->from(route('shifts.create'))
            ->post(route('shifts.store'), $this->payload($member, ['starts_at' => '16:00', 'ends_at' => '10:00']))
            ->assertSessionHasErrors(['ends_at' => 'The end time must be later than the start time.']);

        $this->assertSame(0, StaffShift::withoutGlobalScopes()->count());
    }

    public function test_a_break_longer_than_the_shift_is_refused(): void
    {
        $member = $this->member();

        $this->actingAs($this->owner())
            ->from(route('shifts.create'))
            ->post(route('shifts.store'), $this->payload($member, [
                'starts_at' => '10:00', 'ends_at' => '10:30', 'break_minutes' => 60,
            ]))
            ->assertSessionHasErrors('break_minutes');
    }

    /**
     * The rule that makes a rota trustworthy: nobody is in two places at
     * once. Checked on the server because the form only decides what is
     * drawn.
     */
    public function test_two_overlapping_shifts_for_one_person_are_refused(): void
    {
        $member = $this->member();
        $this->shift($member, ['starts_at' => '10:00', 'ends_at' => '16:00']);

        $this->actingAs($this->owner())
            ->from(route('shifts.create'))
            ->post(route('shifts.store'), $this->payload($member, ['starts_at' => '15:00', 'ends_at' => '18:00']))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, StaffShift::withoutGlobalScopes()->count());
    }

    /**
     * A split day is two shifts either side of a gap, and it has to stay
     * legal — so shifts that merely touch do not overlap.
     */
    public function test_two_shifts_that_touch_end_to_end_are_allowed(): void
    {
        $member = $this->member();
        $this->shift($member, ['starts_at' => '09:00', 'ends_at' => '13:00']);

        $this->actingAs($this->owner())
            ->post(route('shifts.store'), $this->payload($member, ['starts_at' => '13:00', 'ends_at' => '18:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, StaffShift::withoutGlobalScopes()->count());
    }

    public function test_two_people_may_work_the_same_hours(): void
    {
        $amara = $this->member('Amara');
        $priya = $this->member('Priya');

        $this->shift($amara);

        $this->actingAs($this->owner())
            ->post(route('shifts.store'), $this->payload($priya))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, StaffShift::withoutGlobalScopes()->count());
    }

    /** A cancelled shift is nobody's working hours, so it cannot clash. */
    public function test_a_cancelled_shift_does_not_block_the_hours(): void
    {
        $member = $this->member();
        $this->shift($member, ['status' => 'cancelled']);

        $this->actingAs($this->owner())
            ->post(route('shifts.store'), $this->payload($member))
            ->assertSessionHasNoErrors();
    }

    public function test_editing_a_shift_does_not_clash_with_itself(): void
    {
        $member = $this->member();
        $shift = $this->shift($member);

        $this->actingAs($this->owner())
            ->patch(route('shifts.update', $shift), $this->payload($member, ['ends_at' => '17:00']))
            ->assertRedirect(route('shifts.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('17:00', $shift->refresh()->timeValue('ends_at'));
    }

    // ------------------------------------------------------------ the list

    public function test_the_listing_uses_the_shared_grid(): void
    {
        $member = $this->member();
        $this->shift($member);

        $this->actingAs($this->owner())
            ->get(route('shifts.index'))
            ->assertOk()
            ->assertSee('data-grid', false)
            ->assertSee('styledesk_gridframe', false)
            ->assertSee('shifts/data', false);
    }

    public function test_the_rows_carry_what_the_columns_need(): void
    {
        $member = $this->member();
        $this->shift($member);

        $row = $this->actingAs($this->owner())
            ->get(route('shifts.data'))
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Amara Person', $row['name']);
        $this->assertSame('10:00 AM – 4:00 PM', $row['hours']);
        $this->assertSame('30 min', $row['break']);
        $this->assertSame('Riverside', $row['location']);
        $this->assertSame('Regular', $row['type']);
        $this->assertSame('Scheduled', $row['status']);
    }

    public function test_the_rows_can_be_filtered_by_person_type_and_date_range(): void
    {
        $amara = $this->member('Amara');
        $priya = $this->member('Priya');

        $this->shift($amara, ['date' => '2026-09-12']);
        $this->shift($priya, ['date' => '2026-09-20', 'type' => 'cover']);

        $names = fn (string $query) => array_column(
            $this->get(route('shifts.data').'?'.$query)->json('data'), 'name',
        );

        $this->actingAs($this->owner());

        $this->assertSame(['Amara Person'], $names('staff='.$amara->id));
        $this->assertSame(['Priya Person'], $names('type=cover'));
        $this->assertSame(['Priya Person'], $names('from=2026-09-15'));
        $this->assertSame(['Amara Person'], $names('until=2026-09-15'));
    }

    public function test_a_business_with_no_shifts_sees_the_empty_state(): void
    {
        $this->actingAs($this->owner())
            ->get(route('shifts.index'))
            ->assertOk()
            ->assertSee('No shifts yet')
            ->assertDontSee('data-grid', false);
    }

    // ------------------------------------------------------- cancel/delete

    /**
     * Cancelled, not deleted: a shift that was dropped is a fact about the
     * week, and a row that disappears leaves the reader wondering whether it
     * was ever there.
     */
    public function test_cancelling_keeps_the_row_and_marks_it(): void
    {
        $member = $this->member();
        $shift = $this->shift($member);

        $this->actingAs($this->owner())
            ->from(route('shifts.index'))
            ->patch(route('shifts.cancel', $shift))
            ->assertRedirect(route('shifts.index'));

        $this->assertSame('cancelled', $shift->refresh()->status);
        $this->assertSame(1, StaffShift::withoutGlobalScopes()->count());
    }

    public function test_a_shift_can_be_deleted(): void
    {
        $member = $this->member();
        $shift = $this->shift($member);

        $this->actingAs($this->owner())
            ->delete(route('shifts.destroy', $shift))
            ->assertRedirect(route('shifts.index'));

        $this->assertSame(0, StaffShift::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------- tenancy

    public function test_another_business_shifts_are_never_listed(): void
    {
        $mine = $this->member();
        $this->shift($mine);

        $other = Tenant::create(['name' => 'Elsewhere', 'slug' => 'elsewhere']);
        $theirStaff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'first_name' => 'Someone', 'last_name' => 'Else',
            'email' => 'else@other.test', 'role' => 'service-provider',
        ]);
        StaffShift::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'staff_id' => $theirStaff->id,
            'date' => '2026-09-12', 'starts_at' => '10:00', 'ends_at' => '16:00',
        ]);

        $names = array_column(
            $this->actingAs($this->owner())->get(route('shifts.data'))->json('data'), 'name',
        );

        $this->assertSame(['Amara Person'], $names);
    }

    // ------------------------------------------- inside the business week

    /** Monday to Friday, nine to five, on the business's own record. */
    private function businessWeek(): void
    {
        foreach ([1, 2, 3, 4, 5] as $day) {
            $this->location->hours()->create([
                'effective_from' => Location::EPOCH,
                'day_of_week' => $day, 'sort_order' => 0, 'is_open' => true,
                'opens_at' => '09:00', 'closes_at' => '17:00',
            ]);
        }
    }

    /**
     * The business's working hours are the one source of when it is open, and
     * a rota that puts somebody on the floor at eight when the doors open at
     * nine is a rota nobody can work.
     */
    public function test_a_shift_outside_the_business_hours_is_refused(): void
    {
        $this->businessWeek();
        $member = $this->member();

        $this->actingAs($this->owner())
            ->from(route('shifts.create'))
            ->post(route('shifts.store'), $this->payload($member, [
                'date' => '2026-09-14', // a Monday
                'starts_at' => '08:00', 'ends_at' => '17:00',
            ]))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(0, StaffShift::withoutGlobalScopes()->count());
    }

    public function test_a_shift_inside_the_business_hours_is_accepted(): void
    {
        $this->businessWeek();
        $member = $this->member();

        $this->actingAs($this->owner())
            ->post(route('shifts.store'), $this->payload($member, [
                'date' => '2026-09-14',
                'starts_at' => '10:00', 'ends_at' => '16:00',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_shift_on_a_day_the_business_is_closed_is_refused(): void
    {
        $this->businessWeek();
        $member = $this->member();

        $this->actingAs($this->owner())
            ->from(route('shifts.create'))
            ->post(route('shifts.store'), $this->payload($member, [
                'date' => '2026-09-12', // a Saturday, and the week is Mon–Fri
                'starts_at' => '10:00', 'ends_at' => '16:00',
            ]))
            ->assertSessionHasErrors('date');
    }

    /**
     * A business that has recorded no hours at all is left alone: there is
     * nothing to check against, and refusing every shift would be refusing
     * them for a reason the reader cannot act on from the shift form.
     */
    public function test_a_business_with_no_recorded_hours_is_not_second_guessed(): void
    {
        $member = $this->member();

        $this->actingAs($this->owner())
            ->post(route('shifts.store'), $this->payload($member, [
                'starts_at' => '06:00', 'ends_at' => '22:00',
            ]))
            ->assertSessionHasNoErrors();
    }
}
