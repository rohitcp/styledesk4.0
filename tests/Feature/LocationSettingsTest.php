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
            'state' => 'TX',
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
            'state' => 'TX',
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

    // ------------------------------------------------------ state / region

    /**
     * The state field offers the chosen country's regions, searchably.
     *
     * The list is the same one onboarding writes from, so a branch added here
     * and a branch added there store the same kind of value — a code, not
     * whatever spelling of "New Jersey" somebody typed.
     */
    public function test_the_state_field_offers_the_countrys_regions(): void
    {
        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.create'))
            ->assertOk()
            ->assertSee('data-state-combo', false)
            /* The searchable island, not a bare select. */
            ->assertSee('data-vue-component="MultiSelect"', false)
            ->assertSee('New Jersey')
            ->assertSee('Texas');
    }

    /**
     * Every country's regions reach the browser, not just the chosen one's.
     *
     * The country is editable on this form, so the list has to be rebuildable
     * without another request — otherwise choosing Canada would leave US
     * states on offer.
     */
    public function test_the_form_carries_the_regions_of_other_countries(): void
    {
        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.create'))
            ->assertOk()
            ->assertSee('Ontario')
            ->assertSee('Queensland');
    }

    public function test_a_region_the_country_does_not_have_is_refused(): void
    {
        $this->actingAs($this->member('owner'))
            /* A real code, of the wrong country: Ontario is not a US state,
               and the dropdown never offered it here. */
            ->post(route('settings.locations.store'), $this->validPayload(['state' => 'ON']))
            ->assertSessionHasErrors('state');

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_a_typed_region_name_is_refused_where_a_list_exists(): void
    {
        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload(['state' => 'Texas']))
            ->assertSessionHasErrors('state');

        $this->assertDatabaseCount('locations', 0);
    }

    /**
     * A country with no regions of its own keeps a free-text box.
     *
     * Singapore has no meaningful subdivision, and a half-remembered list
     * would be worse than the honest field.
     */
    public function test_a_country_without_regions_accepts_free_text(): void
    {
        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload([
                'country' => 'SG',
                'state' => 'Central Region',
                'timezone' => 'Asia/Singapore',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', ['state' => 'Central Region']);
    }

    // ------------------------------------------------------------ website

    /**
     * A new branch starts with the business's own website filled in.
     *
     * Captured on the first onboarding step. Most branches share one site, so
     * the suggestion saves the typing on the common case.
     */
    public function test_add_suggests_the_businesss_website(): void
    {
        $this->tenant->forceFill(['website' => 'https://nadia.example.com'])->save();

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.create'))
            ->assertOk()
            ->assertSee('value="https://nadia.example.com"', false);
    }

    /**
     * A suggestion, not a rule: saving something else saves something else.
     */
    public function test_the_suggested_website_can_be_replaced(): void
    {
        $this->tenant->forceFill(['website' => 'https://nadia.example.com'])->save();

        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload([
                'website' => 'https://riverside.example.com',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', ['website' => 'https://riverside.example.com']);
    }

    /**
     * Edit shows what is stored, including when nothing is.
     *
     * A branch whose website somebody deliberately cleared must not have the
     * business's one put back every time the form is opened — that would undo
     * the clearing, silently, on the next save.
     */
    public function test_edit_does_not_refill_a_cleared_website(): void
    {
        $this->tenant->forceFill(['website' => 'https://nadia.example.com'])->save();

        $location = $this->location(['website' => null]);

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.edit', $location))
            ->assertOk()
            ->assertDontSee('value="https://nadia.example.com"', false);
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

        /* Offered once, for the branch that is not primary. Counting the
           control rather than its label: the confirmation dialog repeats the
           wording, so the label appears more than once per control. */
        $this->assertSame(1, substr_count($response->getContent(), 'data-status="inactive"'));
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

    // ------------------------------------------- contact fields and their rules

    /**
     * The five contact fields are checked as they are typed, with the same
     * module and the same messages as the resource form.
     */
    public function test_the_form_carries_the_live_validation_rules(): void
    {
        $page = $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.create'))
            ->assertOk();

        $page->assertSee('data-validate-form', false);
        $page->assertSee('data-rules="required|phone"', false);
        $page->assertSee('data-rules="required|email|max:255"', false);
        $page->assertSee('data-rules="url|max:255"', false);
        $page->assertSee('data-remote-check', false);
    }

    /**
     * Every field carrying rules has somewhere to print them.
     *
     * A rule with no message box beside it fails silently in the browser: the
     * module paints into [data-error-for="<the field's id>"], and without one
     * the reader is refused with nothing said.
     */
    public function test_every_validated_field_has_a_message_box(): void
    {
        $html = $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.create'))
            ->getContent();

        preg_match_all('/id="([^"]+)"[^>]*data-rules=/', $html, $withRules);
        preg_match_all('/data-rules=[^>]*id="([^"]+)"/', $html, $rulesFirst);

        $ids = array_unique(array_merge($withRules[1], $rulesFirst[1]));

        $this->assertNotEmpty($ids);

        foreach ($ids as $id) {
            $this->assertStringContainsString('data-error-for="'.$id.'"', $html,
                "The field {$id} declares rules but has nowhere to print the message.");
        }
    }

    /**
     * Both numbers are entered with a dialling code beside them, and both
     * codes are saved. The primary column existed and was never posted; the
     * secondary had nowhere to go at all.
     */
    public function test_both_phone_numbers_keep_their_country(): void
    {
        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload([
                'phone' => '20 7946 0958',
                'phone_country' => 'GB',
                'phone_secondary' => '1 512 555 0111',
                'phone_secondary_country' => 'US',
            ]))
            ->assertSessionHasNoErrors();

        $location = Location::withoutGlobalScopes()->where('name', 'Downtown Salon')->firstOrFail();

        $this->assertSame('GB', $location->phone_country);
        $this->assertSame('US', $location->phone_secondary_country);
    }

    public function test_the_phone_fields_offer_a_country_picker(): void
    {
        $html = $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.create'))
            ->getContent();

        $this->assertStringContainsString('name="phone_country" data-phone-country-value', $html);
        $this->assertStringContainsString('name="phone_secondary_country" data-phone-country-value', $html);
    }

    /**
     * The address the browser refuses is the address the server refuses.
     *
     * "desk@salon" passes Laravel's `email` rule — no dot in the domain is
     * legal on a local network and reaches nobody a client lives on.
     */
    public function test_an_address_with_no_domain_is_refused(): void
    {
        $owner = $this->member('owner');

        foreach (['email', 'booking_email', 'support_email'] as $field) {
            $this->actingAs($owner)
                ->from(route('settings.locations.create'))
                ->post(route('settings.locations.store'), $this->validPayload([$field => 'desk@salon']))
                ->assertSessionHasErrors($field);
        }
    }

    public function test_real_addresses_are_accepted(): void
    {
        $this->actingAs($this->member('owner'))
            ->post(route('settings.locations.store'), $this->validPayload([
                'email' => 'desk@nadia.co.uk',
                'booking_email' => 'book@nadia.co.uk',
                'support_email' => 'help@nadia.co.uk',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_main_number_and_an_email_are_required(): void
    {
        $this->actingAs($this->member('owner'))
            ->from(route('settings.locations.create'))
            ->post(route('settings.locations.store'), $this->validPayload(['phone' => '', 'email' => '']))
            ->assertSessionHasErrors(['phone', 'email']);
    }

    // ---------------------------------------------------- the live code check

    public function test_the_code_check_reports_a_code_already_in_use(): void
    {
        $owner = $this->member('owner');

        $this->actingAs($owner)->post(route('settings.locations.store'), $this->validPayload(['code' => 'DT']));

        $this->actingAs($owner)
            ->getJson(route('settings.locations.code-in-use', ['value' => 'DT']))
            ->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_the_code_check_passes_a_free_code(): void
    {
        $this->actingAs($this->member('owner'))
            ->getJson(route('settings.locations.code-in-use', ['value' => 'WEST']))
            ->assertJson(['ok' => true]);
    }

    public function test_the_code_check_does_not_report_the_location_being_edited(): void
    {
        $owner = $this->member('owner');

        $this->actingAs($owner)->post(route('settings.locations.store'), $this->validPayload(['code' => 'DT']));
        $location = Location::withoutGlobalScopes()->where('code', 'DT')->firstOrFail();

        $this->actingAs($owner)
            ->getJson(route('settings.locations.code-in-use', ['value' => 'DT', 'ignore' => $location->id]))
            ->assertJson(['ok' => true]);
    }

    /**
     * The "Configured elsewhere" card is gone from the detail page.
     *
     * It listed what other modules own — holidays, staff, services, booking
     * rules, currency — which described the rest of the product rather than
     * this branch, and every row was a link away from the page just opened.
     */
    public function test_the_configured_elsewhere_card_is_not_shown(): void
    {
        $location = $this->location();

        $this->actingAs($this->member('owner'))
            ->get(route('settings.locations.show', $location))
            ->assertOk()
            ->assertDontSee(__('locations.cards.elsewhere'))
            ->assertDontSee(__('locations.cards.elsewhere_hint'));
    }
}
