<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Service;
use App\Models\ShiftRule;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The Staff module: the same records reached from a second address.
 *
 * §12 and §13 are the whole point of these tests — one controller, one form,
 * one set of rules, two doors. So most of what is asserted here is sameness:
 * that /staff/create is the form from /settings/staff/create rather than a
 * second copy of it, and that a person added through either door is the same
 * row seen from both.
 */
class StaffSectionTest extends TestCase
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

    private function roleId(string $key): int
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)->value('id');
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'katherine',
            'last_name' => 'wu',
            'email' => 'kit@acme.test',
            'role_id' => $this->roleId('service-provider'),
            'account_status' => 'active',
            'login_enabled' => '0',
        ], $overrides);
    }

    private function staffMember(array $attributes = []): Staff
    {
        return Staff::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'sam'.Staff::withoutGlobalScopes()->count().'@acme.test',
            'role' => 'service-provider',
        ]);
    }

    private function resource(string $name = 'Chair 1'): Resource
    {
        return Resource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'capacity' => 1,
        ]);
    }

    // ------------------------------------------------------ one feature

    public function test_the_module_and_settings_open_the_same_add_form(): void
    {
        $owner = $this->owner();

        $module = $this->actingAs($owner)->get('http://styledesk.test/staff/create')->assertOk();
        $settings = $this->actingAs($owner)->get('http://styledesk.test/settings/staff/create')->assertOk();

        /* The same fields, posting to the section they were opened in. A
           second implementation would be a second set of names. */
        /* Plain inputs, deliberately: the combo boxes render as Vue islands
           whose field name lives in a JSON prop, which would be asserting on
           how a control is built rather than on the form being the same. */
        foreach (['name="first_name"', 'name="last_name"', 'name="email"', 'name="account_status"'] as $field) {
            $module->assertSee($field, false);
            $settings->assertSee($field, false);
        }

        /* Scheme-agnostic: the app builds URLs from APP_URL, and which of
           http or https that is has nothing to do with what is being tested. */
        $this->assertMatchesRegularExpression(
            '~action="https?://styledesk\.test/staff"~', $module->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '~action="https?://styledesk\.test/settings/staff"~', $settings->getContent(),
        );
    }

    public function test_a_member_added_from_the_module_lands_back_in_the_module(): void
    {
        Queue::fake();

        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('http://styledesk.test/staff', $this->payload())
            ->assertRedirect(route('staff.index'));

        /* One record, visible from both doors — §12. */
        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        /* The rows come from the grid's endpoint now, so that is where both
           doors are asked whether they can see the same person. */
        $this->actingAs($owner)->get('http://styledesk.test/staff/data')
            ->assertOk()->assertJsonFragment(['name' => 'Katherine Wu']);
        $this->actingAs($owner)->get('http://styledesk.test/settings/staff/data')
            ->assertOk()->assertJsonFragment(['name' => 'Katherine Wu']);
        $this->assertSame(1, Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->count());
        $this->assertNotNull($staff->id);
    }

    public function test_a_member_added_from_settings_lands_back_in_settings(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload())
            ->assertRedirect(route('settings.staff.index'));
    }

    public function test_save_and_add_another_returns_to_an_empty_form_in_the_same_section(): void
    {
        Queue::fake();

        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('http://styledesk.test/staff', $this->payload(['after_save' => 'add_another']))
            ->assertRedirect(route('staff.create'));

        $this->actingAs($owner)
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'email' => 'second@acme.test', 'after_save' => 'add_another',
            ]))
            ->assertRedirect(route('settings.staff.create'));

        $this->assertSame(2, Staff::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------- new fields

    public function test_the_new_fields_are_saved(): void
    {
        Queue::fake();

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Swedish Massage', 'duration_minutes' => 60,
        ]);
        $room = $this->resource('Massage Room 1');

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/staff', $this->payload([
                'date_of_birth' => '1990-04-02',
                'started_on' => '2026-09-01',
                'service_ids' => [$service->id],
                'resource_ids' => [$room->id],
            ]))
            ->assertSessionHasNoErrors();

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $this->assertSame('1990-04-02', $staff->date_of_birth->toDateString());
        $this->assertSame('2026-09-01', $staff->started_on->toDateString());
        $this->assertSame([$service->id], $staff->services->pluck('id')->all());
        $this->assertSame([$room->id], $staff->resources->pluck('id')->all());
    }

    public function test_a_birthday_cannot_be_in_the_future(): void
    {
        $this->actingAs($this->owner())
            ->from(route('staff.create'))
            ->post('http://styledesk.test/staff', $this->payload([
                'date_of_birth' => now()->addYear()->toDateString(),
            ]))
            ->assertSessionHasErrors('date_of_birth');
    }

    /**
     * Away, not gone: someone on leave keeps their record and their mappings
     * but is not available to be booked.
     */
    public function test_on_leave_is_its_own_status(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/staff', $this->payload(['account_status' => 'on-leave']))
            ->assertSessionHasNoErrors();

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $this->assertSame('on-leave', $staff->status());
        $this->assertSame('On Leave', $staff->statusLabel());
        $this->assertFalse($staff->is_active);
    }

    // ----------------------------------------------------- the nav count

    public function test_the_navigation_shows_how_many_are_active_and_follows_a_change(): void
    {
        $owner = $this->owner();

        $this->staffMember(['is_active' => true]);
        $away = $this->staffMember(['is_active' => true]);

        /* The owner has no staff row of their own here, so the two above are
           the whole team. */
        $this->assertSame(2, Staff::activeCount());

        $this->actingAs($owner)->get('http://styledesk.test/staff')
            ->assertOk()
            ->assertSee('sd-navicon__count', false)
            ->assertSee('2 active staff members');

        /* Deactivated, not deleted — the count has to follow. */
        $away->forceFill(['is_active' => false])->save();

        $this->actingAs($owner)->get('http://styledesk.test/staff')
            ->assertSee('1 active staff member');
    }

    // ------------------------------------------------------- permissions

    /**
     * The reason the module is not simply an alias of the settings address:
     * running the rota is a daily job, configuring the business is not.
     */
    public function test_the_module_is_reachable_without_the_settings_permission(): void
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'manager@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => 'manager',
        ]);

        $user = $user->fresh();

        $this->actingAs($user)->get('http://styledesk.test/staff')->assertOk();
        /* Turned away rather than shown a 403 page: the settings gate sends
           people back where they came from. Either way it is not theirs. */
        $this->actingAs($user)->get('http://styledesk.test/settings/staff')->assertRedirect();
    }

    // ------------------------------------------------- the listing screen

    /**
     * §1 and §2: the listing is the clients listing's frame, not a page of
     * its own design — the same full-width container, the same shared grid,
     * the same search-and-filters toolbar.
     */
    public function test_the_listing_uses_the_shared_grid_in_the_full_width_frame(): void
    {
        $owner = $this->owner();
        $this->staffMember();

        $page = $this->actingAs($owner)->get('http://styledesk.test/staff')->assertOk();

        /* The frame the clients listing uses, and not the narrow column this
           screen used to sit in. */
        $page->assertSee('w-full px-6 lg:px-8 pt-5 pb-[100px]', false)
            ->assertDontSee('max-w-[1180px]', false);

        /* The shared grid rather than a staff-only table. */
        $page->assertSee('data-grid', false)
            ->assertSee('styledesk_gridframe', false)
            ->assertSee('data-result-count', false)
            ->assertDontSee('<table', false);
    }

    public function test_the_grid_rows_carry_what_the_columns_need(): void
    {
        $owner = $this->owner();

        $member = $this->staffMember([
            'first_name' => 'Amara', 'employee_ref' => 'EMP-014',
            'job_title' => 'Salon Manager', 'phone' => '+15125550001',
        ]);

        $row = $this->actingAs($owner)
            ->get('http://styledesk.test/staff/data')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Amara Person', $row['name']);
        $this->assertSame('AP', $row['initials']);
        /* The staff ID beside the name rather than in a column of its own:
           it is what tells two people of the same name apart, so it has to
           survive the table narrowing. */
        $this->assertSame('EMP-014', $row['primary_badge']);
        $this->assertSame('Salon Manager', $row['role']);
        $this->assertSame('+15125550001', $row['phone']);
        $this->assertSame($member->email, $row['email']);
        $this->assertSame('Active', $row['status']);
        $this->assertStringContainsString('/staff/'.$member->id, $row['url']);
    }

    /**
     * §7: a row opens the staff member, in the section the reader is in.
     */
    public function test_a_row_opens_the_member_in_its_own_section(): void
    {
        $owner = $this->owner();
        $member = $this->staffMember();

        $module = $this->actingAs($owner)->get('http://styledesk.test/staff/data')->json('data.0.url');
        $settings = $this->actingAs($owner)->get('http://styledesk.test/settings/staff/data')->json('data.0.url');

        $this->assertMatchesRegularExpression('~https?://styledesk\.test/staff/'.$member->id.'$~', $module);
        $this->assertMatchesRegularExpression('~https?://styledesk\.test/settings/staff/'.$member->id.'$~', $settings);
    }

    public function test_a_member_can_be_deactivated_and_activated_from_the_row_menu(): void
    {
        $owner = $this->owner();
        $member = $this->staffMember(['is_active' => true]);

        $this->actingAs($owner)
            ->patch('http://styledesk.test/staff/'.$member->id.'/status')
            ->assertRedirect();

        $this->assertFalse($member->refresh()->is_active);
        $this->assertSame('inactive', $member->status());

        $this->actingAs($owner)
            ->patch('http://styledesk.test/staff/'.$member->id.'/status')
            ->assertRedirect();

        $this->assertTrue($member->refresh()->is_active);
        $this->assertSame('active', $member->status());
    }

    /**
     * Nobody at all is a different fact from nobody matching, and gets a
     * different answer — the reader has not searched for anything.
     */
    public function test_a_business_with_no_staff_sees_the_empty_state(): void
    {
        $this->actingAs($this->owner())
            ->get('http://styledesk.test/staff')
            ->assertOk()
            ->assertSee('No staff members yet')
            ->assertSee('Add staff members to manage schedules, services, locations and appointment availability.')
            ->assertDontSee('data-grid', false);
    }

    // -------------------------------------------------- shift rule on staff

    private function shiftRule(array $overrides = []): ShiftRule
    {
        return ShiftRule::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Standard Full-Time',
        ], $overrides));
    }

    /**
     * Two conditions, and the card is drawn only when both hold: the feature
     * is on, and there is an active rule to choose. A card offering an empty
     * dropdown is a question with no answers.
     */
    public function test_the_shift_rule_card_needs_the_feature_and_an_active_rule(): void
    {
        $owner = $this->owner();

        /* Feature on, no rules at all. */
        $this->actingAs($owner)->get('http://styledesk.test/staff/create')
            ->assertOk()
            ->assertDontSee('data-shift-rule-card', false);

        /* Feature on, one rule — but switched off. */
        $inactive = $this->shiftRule(['name' => 'Retired pattern', 'status' => 'inactive']);

        $this->actingAs($owner)->get('http://styledesk.test/staff/create')
            ->assertOk()
            ->assertDontSee('data-shift-rule-card', false);

        /* Feature on, an active rule. */
        $inactive->forceFill(['status' => 'active'])->save();

        $this->actingAs($owner)->get('http://styledesk.test/staff/create')
            ->assertOk()
            ->assertSee('data-shift-rule-card', false)
            ->assertSee('Retired pattern');

        /* Feature off.

           The acting user is re-fetched: actingAs holds one model instance
           across requests, so its already-loaded tenant relation would answer
           from before the switch moved. A real request resolves the user from
           the database every time. */
        $this->tenant->forceFill(['shift_rules_enabled' => false])->save();

        $this->actingAs($owner->fresh())->get('http://styledesk.test/staff/create')
            ->assertOk()
            ->assertDontSee('data-shift-rule-card', false);
    }

    public function test_a_staff_member_is_saved_with_their_shift_rule(): void
    {
        Queue::fake();

        $rule = $this->shiftRule();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/staff', $this->payload(['shift_rule_id' => $rule->id]))
            ->assertSessionHasNoErrors();

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $this->assertSame($rule->id, $staff->shift_rule_id);
        /* And the rule now knows how many people are on it, which is what the
           delete guard reads. */
        $this->assertSame(1, $rule->fresh()->assignedStaffCount());
        $this->assertTrue($rule->fresh()->isInUse());
    }

    public function test_a_staff_member_may_be_saved_with_no_shift_rule(): void
    {
        Queue::fake();

        $this->shiftRule();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/staff', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNull(
            Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail()->shift_rule_id,
        );
    }

    /**
     * A rule restricted to branches cannot be given to somebody at another
     * one. Checked on the server as well as filtered in the form, because the
     * form only decides what is drawn.
     */
    public function test_a_rule_for_another_branch_is_refused(): void
    {
        $elsewhere = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Northside', 'address_line1' => '2 North St', 'city' => 'Austin',
            'postal_code' => '78702', 'country' => 'US', 'timezone' => 'America/Chicago',
        ]);

        $rule = $this->shiftRule(['location_scope' => 'specific']);
        $rule->locations()->sync([$elsewhere->id]);

        $this->actingAs($this->owner())
            ->from(route('staff.create'))
            ->post('http://styledesk.test/staff', $this->payload([
                'location_id' => $this->location->id,
                'shift_rule_id' => $rule->id,
            ]))
            ->assertSessionHasErrors([
                'shift_rule_id' => 'This Shift Rule is not available for the selected location. Select another Shift Rule.',
            ]);

        $this->assertSame(0, Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->count());
    }

    public function test_a_rule_restricted_to_this_branch_is_accepted(): void
    {
        Queue::fake();

        $rule = $this->shiftRule(['location_scope' => 'specific']);
        $rule->locations()->sync([$this->location->id]);

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/staff', $this->payload([
                'location_id' => $this->location->id,
                'shift_rule_id' => $rule->id,
            ]))
            ->assertSessionHasNoErrors();
    }

    /**
     * A rule switched off after somebody was put on it stays on them — it is
     * new assignments it is kept out of. Refusing the save would mean nobody
     * could edit that person's phone number until the rule was sorted out.
     */
    public function test_an_inactive_rule_already_assigned_survives_an_edit(): void
    {
        $rule = $this->shiftRule();
        $member = $this->staffMember(['shift_rule_id' => $rule->id, 'role' => 'service-provider']);

        $rule->forceFill(['status' => 'inactive'])->save();

        $this->actingAs($this->owner())
            ->patch(route('staff.update', $member), [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'role_id' => $this->roleId('service-provider'),
                'account_status' => 'active',
                'shift_rule_id' => $rule->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($rule->id, $member->refresh()->shift_rule_id);
    }

    public function test_an_inactive_rule_cannot_be_newly_assigned(): void
    {
        Queue::fake();

        $rule = $this->shiftRule(['status' => 'inactive']);

        $this->actingAs($this->owner())
            ->from(route('staff.create'))
            ->post('http://styledesk.test/staff', $this->payload(['shift_rule_id' => $rule->id]))
            ->assertSessionHasErrors('shift_rule_id');
    }

    /** Both doors offer the same card, because both render the same form. */
    public function test_the_card_appears_from_both_entry_points(): void
    {
        $owner = $this->owner();
        $this->shiftRule();

        $this->actingAs($owner)->get('http://styledesk.test/staff/create')
            ->assertOk()->assertSee('Shift Rule');

        $this->actingAs($owner)->get('http://styledesk.test/settings/staff/create')
            ->assertOk()->assertSee('Shift Rule');
    }
}
