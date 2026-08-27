<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Acceptance criteria from the Locations settings spec.
 */
class LocationSettingsTest extends TestCase
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

    private function member(string $role, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->tenant;

        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $role.'-'.$tenant->getTenantKey().'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $tenant->getTenantKey()])->save();

        if ($role === 'owner') {
            $tenant->forceFill(['owner_user_id' => $user->id])->save();
        } else {
            Staff::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'user_id' => $user->id,
                'first_name' => 'Sam', 'last_name' => 'Person',
                'email' => $user->email, 'role' => $role,
            ]);
        }

        return $user->fresh();
    }

    private function location(array $overrides = [], ?Tenant $tenant = null): Location
    {
        $tenant ??= $this->tenant;

        return Location::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->getTenantKey(),
            'name' => 'Riverside',
            'address_line1' => '1 River Street',
            'city' => 'Austin',
            'state' => 'Texas',
            'postal_code' => '78701',
            'country' => 'US',
            'timezone' => 'America/Chicago',
            'phone' => '+1 512 555 0100',
            'email' => 'riverside@nadia.test',
            'is_primary' => true,
        ], $overrides));
    }

    /**
     * A complete, valid submission.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Downtown Salon',
            'status' => 'active',
            'address_line1' => '88 Congress Avenue',
            'city' => 'Austin',
            'state' => 'Texas',
            'postal_code' => '78701',
            'country' => 'US',
            'timezone' => 'America/Chicago',
            'phone' => '+1 512 555 0199',
            'email' => 'downtown@nadia.test',
        ], $overrides);
    }

    // ------------------------------------------------------- authorisation

    public static function allowedRoles(): array
    {
        return ['owner' => ['owner'], 'administrator' => ['administrator']];
    }

    #[DataProvider('allowedRoles')]
    public function test_owner_and_admin_can_open_the_locations_list(string $role): void
    {
        $this->location();

        $this->actingAs($this->member($role))
            ->get(route('settings.locations.index'))
            ->assertOk()
            ->assertSee('Riverside');
    }

    public function test_other_roles_are_turned_away_from_the_module(): void
    {
        $this->location();

        $this->actingAs($this->member('front-desk'))
            ->get(route('settings.locations.index'))
            ->assertRedirect(route('dashboard'));
    }

    /**
     * The tenancy boundary, checked on the route rather than assumed.
     *
     * A location belonging to another business must not be reachable by
     * typing its id, which is the only thing standing between two salons'
     * addresses and phone numbers.
     */
    public function test_another_businesss_location_is_not_reachable(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $theirs = $this->location(['name' => 'Their Branch'], $other);

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.show', $theirs))
            ->assertNotFound();
    }

    // --------------------------------------------------------------- reads

    public function test_the_view_screen_shows_the_locations_details(): void
    {
        $location = $this->location(['code' => 'RV01', 'type' => 'salon']);

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.show', $location))
            ->assertOk()
            ->assertSee('Riverside')
            ->assertSee('RV01')
            ->assertSee('1 River Street')
            ->assertSee('riverside@nadia.test');
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $this->location();
        $this->location(['name' => 'Closed Branch', 'status' => 'inactive', 'is_primary' => false]);

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Closed Branch')
            ->assertDontSee('>Riverside<', false);
    }

    // -------------------------------------------------------------- writes

    public function test_a_location_can_be_created(): void
    {
        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload())
            ->assertSessionHas('toast.message', 'Location created successfully.');

        $this->assertDatabaseHas('locations', [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Downtown Salon',
            'city' => 'Austin',
            'status' => 'active',
        ]);
    }

    public function test_a_location_can_be_updated(): void
    {
        $location = $this->location();

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.update', $location), $this->validPayload([
                'name' => 'Riverside Studio',
                'email' => 'riverside@nadia.test',
            ]))
            ->assertRedirect(route('settings.locations.show', $location))
            ->assertSessionHas('toast.message', 'Location saved successfully.');

        $this->assertSame('Riverside Studio', $location->fresh()->name);
    }

    /**
     * §12: an inactive location keeps everything it had.
     */
    public function test_deactivating_a_location_keeps_its_record(): void
    {
        $location = $this->location();

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.update', $location), $this->validPayload([
                'name' => 'Riverside',
                'status' => 'inactive',
            ]));

        $fresh = $location->fresh();

        $this->assertSame('inactive', $fresh->status);
        $this->assertFalse($fresh->isActive());
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    /**
     * §1: only one location is the primary one.
     */
    public function test_making_a_location_primary_demotes_the_previous_one(): void
    {
        $first = $this->location();

        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload(['is_primary' => '1']));

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue(Location::withoutGlobalScopes()->where('name', 'Downtown Salon')->first()->is_primary);
    }

    /**
     * The business is never left without a primary branch.
     *
     * Unchecking the box on the only location cannot leave a tenant whose
     * primary location is nothing at all — other modules read that field and
     * would have to invent an answer.
     */
    public function test_the_only_location_stays_primary(): void
    {
        $location = $this->location();

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.update', $location), $this->validPayload([
                'name' => 'Riverside',
                'is_primary' => null,
            ]));

        $this->assertTrue($location->fresh()->is_primary);
    }

    // --------------------------------------------------------------- hours

    /**
     * §5: split hours, and the regression that made every day close.
     *
     * validated() returns only what the rules name. With the period rules
     * present but no rule for the day's own toggle, each day came back as a
     * bare list of periods, every day read as closed, and the form reported a
     * successful save having stored no hours at all.
     */
    public function test_opening_hours_including_split_periods_are_saved(): void
    {
        $location = $this->location();

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.update', $location), $this->validPayload([
                'name' => 'Riverside',
                'hours' => [
                    1 => [
                        'is_open' => '1',
                        ['opens_at' => '09:00', 'closes_at' => '13:00'],
                        ['opens_at' => '14:00', 'closes_at' => '19:00'],
                    ],
                    2 => ['is_open' => '1', ['opens_at' => '09:00', 'closes_at' => '17:00']],
                    // Sunday submitted with times but no toggle: closed.
                    0 => [['opens_at' => '09:00', 'closes_at' => '17:00']],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $hours = $location->fresh()->hours;

        $this->assertCount(3, $hours);

        $monday = $hours->where('day_of_week', 1)->values();
        $this->assertCount(2, $monday);
        $this->assertSame('09:00', $monday[0]->timeValue('opens_at'));
        $this->assertSame('13:00', $monday[0]->timeValue('closes_at'));
        $this->assertSame('14:00', $monday[1]->timeValue('opens_at'));
        $this->assertSame(1, (int) $monday[1]->sort_order);

        $this->assertCount(0, $hours->where('day_of_week', 0));
    }

    /**
     * Saving hours replaces the week rather than adding to it.
     */
    public function test_saving_hours_replaces_the_previous_week(): void
    {
        $location = $this->location();

        $payload = fn (array $hours) => $this->validPayload(['name' => 'Riverside', 'hours' => $hours]);

        $this->actingAs($user = $this->member('owner'))
            ->patch(route('settings.locations.update', $location), $payload([
                1 => ['is_open' => '1', ['opens_at' => '09:00', 'closes_at' => '17:00']],
                2 => ['is_open' => '1', ['opens_at' => '09:00', 'closes_at' => '17:00']],
            ]));

        $this->actingAs($user)
            ->patch(route('settings.locations.update', $location), $payload([
                1 => ['is_open' => '1', ['opens_at' => '10:00', 'closes_at' => '16:00']],
            ]));

        $hours = $location->fresh()->hours;

        $this->assertCount(1, $hours);
        $this->assertSame('10:00', $hours->first()->timeValue('opens_at'));
    }

    public function test_a_period_that_closes_before_it_opens_is_refused(): void
    {
        $location = $this->location();

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.update', $location), $this->validPayload([
                'name' => 'Riverside',
                'hours' => [1 => ['is_open' => '1', ['opens_at' => '17:00', 'closes_at' => '09:00']]],
            ]))
            ->assertSessionHasErrors('hours.1.0.closes_at');

        $this->assertCount(0, $location->fresh()->hours);
    }

    /**
     * The hours editor is the onboarding one, not a lookalike.
     *
     * Asserted rather than trusted to a comment: two hours editors that look
     * alike drift, and a picker fixed in one place and not the other becomes
     * a business whose hours behave differently depending on which screen
     * they were typed into.
     */
    public function test_the_edit_screen_mounts_the_shared_hours_island(): void
    {
        $location = $this->location();

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.edit', $location))
            ->assertOk()
            ->assertSee('data-vue-component="BusinessHours"', false)
            // Split periods are what §5 needs and onboarding does not.
            ->assertSee('splitPeriods', false);
    }

    /**
     * Saved hours reach the island rather than the form starting blank.
     */
    public function test_the_edit_screen_hands_the_island_the_stored_week(): void
    {
        $location = $this->location();

        $location->hours()->createMany([
            ['day_of_week' => 1, 'sort_order' => 0, 'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '13:00'],
            ['day_of_week' => 1, 'sort_order' => 1, 'is_open' => true, 'opens_at' => '14:00', 'closes_at' => '19:00'],
        ]);

        $content = $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.edit', $location))
            ->assertOk()
            ->getContent();

        // The props are JSON in an attribute, so the times are what to look
        // for rather than any particular markup around them.
        $this->assertStringContainsString('09:00', $content);
        $this->assertStringContainsString('14:00', $content);
        $this->assertStringContainsString('19:00', $content);
    }

    // ---------------------------------------------------------- validation

    public static function requiredFields(): array
    {
        return [
            'name' => ['name'],
            'address line 1' => ['address_line1'],
            'city' => ['city'],
            'state' => ['state'],
            'postal code' => ['postal_code'],
            'country' => ['country'],
            'timezone' => ['timezone'],
            'phone' => ['phone'],
            'email' => ['email'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function test_required_fields_are_enforced(string $field): void
    {
        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload([$field => null]))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_a_location_code_cannot_be_reused_within_the_business(): void
    {
        $this->location(['code' => 'RV01']);

        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload(['code' => 'RV01']))
            ->assertSessionHasErrors('code');
    }

    /**
     * The same code in another business is not a clash.
     */
    public function test_a_location_code_may_repeat_across_businesses(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $this->location(['code' => 'RV01'], $other);

        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload(['code' => 'RV01']))
            ->assertSessionHasNoErrors();
    }

    /**
     * A manager id from another business must not stick to this branch.
     */
    public function test_a_manager_from_another_business_is_refused(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $stranger = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'first_name' => 'Not', 'last_name' => 'Ours', 'email' => 'not@ours.test', 'role' => 'manager',
        ]);

        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload([
                'manager_staff_id' => $stranger->id,
            ]))
            ->assertSessionHasErrors('manager_staff_id');
    }

    // ------------------------------------------------------------ managers

    public function test_a_manager_and_assistants_can_be_assigned(): void
    {
        $location = $this->location();

        $manager = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Amara', 'last_name' => 'Osei', 'email' => 'amara@nadia.test', 'role' => 'manager',
        ]);

        $assistant = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Priya', 'last_name' => 'Nair', 'email' => 'priya@nadia.test', 'role' => 'service-provider',
        ]);

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.update', $location), $this->validPayload([
                'name' => 'Riverside',
                'manager_staff_id' => $manager->id,
                // The manager is also ticked as an assistant; they must not
                // appear in both roles on the same card.
                'assistant_manager_ids' => [$manager->id, $assistant->id],
            ]))
            ->assertSessionHasNoErrors();

        $fresh = $location->fresh();

        $this->assertSame($manager->id, $fresh->manager_staff_id);
        $this->assertSame([$assistant->id], $fresh->assistantManagers->pluck('id')->all());
    }

    // ------------------------------------------------------ status changes

    public function test_a_location_can_be_deactivated_from_the_list(): void
    {
        $this->location();
        $branch = $this->location(['name' => 'Eastside Spa', 'is_primary' => false]);

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.status', $branch), ['status' => 'inactive'])
            ->assertSessionHas('toast.type', 'success');

        $this->assertSame('inactive', $branch->fresh()->status);

        // §12: everything the branch was holding survives.
        $this->assertDatabaseHas('locations', ['id' => $branch->id, 'name' => 'Eastside Spa']);
    }

    public function test_an_inactive_location_can_be_reactivated(): void
    {
        $this->location();
        $branch = $this->location(['name' => 'Eastside Spa', 'is_primary' => false, 'status' => 'inactive']);

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.status', $branch), ['status' => 'active']);

        $this->assertSame('active', $branch->fresh()->status);
    }

    /**
     * The primary branch cannot be retired out from under the business.
     *
     * Other modules read is_primary to answer "where does this business
     * operate from", and an inactive answer to that is worse than none.
     */
    public function test_the_primary_location_cannot_be_deactivated(): void
    {
        $primary = $this->location();

        $this->actingAs($this->member('owner'))
            ->patch(route('settings.locations.status', $primary), ['status' => 'inactive'])
            ->assertForbidden();

        $this->assertSame('active', $primary->fresh()->status);
    }

    public function test_the_card_menu_withholds_deactivate_from_the_primary_location(): void
    {
        $this->location(['name' => 'Head Office']);
        $this->location(['name' => 'Eastside Spa', 'is_primary' => false]);

        $response = $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.index'))
            ->assertOk();

        // Offered once, for the branch that is not primary.
        $this->assertSame(1, substr_count($response->getContent(), 'Deactivate location'));
    }

    // ------------------------------------------------------------ the grid

    public function test_each_card_links_to_its_location(): void
    {
        $branch = $this->location(['name' => 'Eastside Spa']);

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.index'))
            ->assertOk()
            ->assertSee('styledesk_locationgrid', false)
            ->assertSee(route('settings.locations.show', $branch), false);
    }

    /**
     * The card is a summary, not a settings screen.
     *
     * Services, staff, resources, booking rules and holiday hours belong to
     * the page behind the card. A test rather than a comment, because the
     * temptation to add "just one more figure" to a card is what turns a
     * summary back into a table.
     */
    public function test_the_card_leaves_detailed_settings_to_the_location_page(): void
    {
        $this->location();

        $content = $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.index'))
            ->getContent();

        /**
         * The grid, not the whole page.
         *
         * The app's global nav lists Services and Resources on every screen,
         * so an assertion against the entire response answers a question
         * about the chrome rather than about the cards.
         */
        $start = mb_strpos($content, 'styledesk_locationgrid');
        $grid = mb_substr($content, $start, mb_strpos($content, 'locationStatusForm') - $start);

        foreach (['Services', 'staff', 'Resources', 'Booking', 'Holiday'] as $detail) {
            $this->assertStringNotContainsString($detail, $grid);
        }
    }

    public function test_an_empty_account_is_offered_a_first_location(): void
    {
        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.index'))
            ->assertOk()
            ->assertSee('No locations yet.')
            ->assertSee('Add your first location');
    }

    /**
     * A search that finds nothing is not the same as having nothing.
     */
    public function test_a_fruitless_search_is_not_offered_a_first_location(): void
    {
        $this->location();

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.index', ['search' => 'nowhere']))
            ->assertOk()
            ->assertSee('No locations match your search.')
            ->assertDontSee('Add your first location');
    }

    // ---------------------------------------------------------- the module

    public function test_the_app_settings_card_links_to_the_module(): void
    {
        $this->location();

        $this->actingAs($this->member('owner'))
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.locations.index'), false)
            // The figure and its label are separate elements on the card, so
            // the label alone is what a text assertion can match.
            ->assertSee('active location');
    }
}
