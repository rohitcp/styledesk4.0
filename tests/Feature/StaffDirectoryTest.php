<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance criteria for the staff directory (§3).
 */
class StaffDirectoryTest extends TestCase
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

    /** @param array<string, mixed> $attributes */
    private function staff(string $first, string $role, array $attributes = []): Staff
    {
        return Staff::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => $first, 'last_name' => 'Person',
            'email' => mb_strtolower($first).'@acme.test',
            'role' => $role,
            'location_id' => $this->location->id,
        ], $attributes));
    }

    private function names(string $query = ''): array
    {
        $content = $this->get('http://styledesk.test/settings/staff?'.$query)->getContent();

        preg_match_all('/font-semibold text-head truncate">([^<]+)</', $content, $matches);

        return array_map('trim', $matches[1]);
    }

    public function test_the_directory_lists_the_business_s_staff(): void
    {
        $this->actingAs($this->owner());

        $this->staff('Amara', 'manager', ['job_title' => 'Salon Manager']);
        $this->staff('Priya', 'service-provider', ['job_title' => 'Senior Colourist']);

        $response = $this->get('http://styledesk.test/settings/staff');

        $response->assertOk()
            ->assertSee('Amara Person')
            ->assertSee('Salon Manager')
            ->assertSee('Manager')
            ->assertSee('Riverside');
    }

    /**
     * The directory names someone by the record, with what they go by beside
     * it: a directory is scanned by people looking for a record, and the name
     * on the record is the one they were hired under.
     */
    public function test_the_directory_shows_the_legal_name_then_the_preferred_one(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Katherine', 'manager', ['preferred_name' => 'Kit']);

        $this->assertSame(['Katherine Person (Kit)'], $this->names());
    }

    public function test_a_preferred_name_matching_the_first_name_is_not_repeated(): void
    {
        $this->actingAs($this->owner());

        // Otherwise the row reads "Kit Person (Kit)".
        $this->staff('Kit', 'manager', ['preferred_name' => 'Kit']);

        $this->assertSame(['Kit Person'], $this->names());
    }

    public function test_the_profile_still_leads_with_what_they_go_by(): void
    {
        $this->actingAs($this->owner());
        $staff = $this->staff('Katherine', 'manager', ['preferred_name' => 'Kit']);

        // The directory is a list to search; the profile is a person to
        // address, so the two lead with different halves on purpose.
        $this->get('http://styledesk.test/settings/staff/'.$staff->id)
            ->assertOk()
            ->assertSee('Kit Person');
    }

    /**
     * The row menu is fixed rather than absolute.
     *
     * The table scrolls sideways and a scroll container clips anything
     * absolutely positioned inside it, so the menu on the last column was cut
     * off at the table's edge.
     */
    public function test_the_row_menu_escapes_the_scrolling_table(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        $content = $this->get('http://styledesk.test/settings/staff')->getContent();

        $this->assertStringContainsString('data-rowmenu-pop', $content);
        // Positioned by script against the button, and closed on scroll,
        // because a fixed panel cannot follow what it is anchored to.
        $this->assertStringContainsString("addEventListener('scroll'", $content);
    }

    public function test_search_covers_name_email_phone_and_job_title(): void
    {
        $this->actingAs($this->owner());

        $this->staff('Amara', 'manager', ['job_title' => 'Salon Manager', 'phone' => '+15125550001']);
        $this->staff('Priya', 'service-provider', ['job_title' => 'Senior Colourist']);

        $this->assertSame(['Amara Person'], $this->names('search=amara'));
        $this->assertSame(['Priya Person'], $this->names('search=colourist'));
        $this->assertSame(['Amara Person'], $this->names('search=5550001'));
        $this->assertSame(['Priya Person'], $this->names('search=priya@acme.test'));
        $this->assertSame([], $this->names('search=nobody'));
    }

    /**
     * An ungrouped chain of orWhere in the search would turn every filter
     * applied alongside it into a suggestion.
     */
    public function test_search_narrows_a_filter_rather_than_widening_it(): void
    {
        $this->actingAs($this->owner());

        $this->staff('Amara', 'manager');
        $this->staff('Priya', 'service-provider');

        $this->assertSame([], $this->names('role=manager&search=priya'));
        $this->assertSame(['Amara Person'], $this->names('role=manager&search=amara'));
    }

    public function test_the_directory_filters_by_role_location_provider_and_employment(): void
    {
        $this->actingAs($this->owner());

        $this->staff('Amara', 'manager', ['provider_type' => 'manager-provider', 'employment_type' => 'full-time']);
        $this->staff('Sam', 'front-desk', ['provider_type' => 'front-desk', 'employment_type' => 'part-time']);

        $this->assertSame(['Amara Person'], $this->names('role=manager'));
        $this->assertSame(['Sam Person'], $this->names('provider_type=front-desk'));
        $this->assertSame(['Sam Person'], $this->names('employment_type=part-time'));
        $this->assertSame(['Amara Person', 'Sam Person'], $this->names('location='.$this->location->id));
    }

    public function test_the_directory_filters_by_service(): void
    {
        $this->actingAs($this->owner());

        $colour = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Balayage', 'duration_minutes' => 150,
        ]);

        $priya = $this->staff('Priya', 'service-provider');
        $this->staff('Sam', 'front-desk');

        $priya->services()->sync([$colour->id]);

        $this->assertSame(['Priya Person'], $this->names('service='.$colour->id));
    }

    public function test_status_is_derived_from_the_record_rather_than_stored(): void
    {
        $this->actingAs($this->owner());

        $active = $this->staff('Amara', 'manager');
        $inactive = $this->staff('Sam', 'front-desk', ['is_active' => false]);
        $invited = $this->staff('Priya', 'service-provider', ['invite_status' => 'sent']);
        $archived = $this->staff('Jo', 'service-provider', ['archived_at' => now()]);

        $this->assertSame('active', $active->status());
        $this->assertSame('inactive', $inactive->status());
        $this->assertSame('pending-invite', $invited->status());

        // Archived outranks everything: the row is history, whatever else it
        // still says about itself.
        $this->assertSame('archived', $archived->status());

        $this->assertSame(['Amara Person'], $this->names('status=active'));
        $this->assertSame(['Priya Person'], $this->names('status=pending-invite'));
    }

    public function test_an_unknown_filter_value_is_ignored_rather_than_applied(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        // A status the config does not define would otherwise filter the list
        // down to nothing and look like an empty business.
        $this->assertSame(['Amara Person'], $this->names('status=invented&sort=nonsense'));
    }

    public function test_sorting_changes_the_order(): void
    {
        $this->actingAs($this->owner());

        $this->staff('Zara', 'manager');
        $this->travel(1)->minute();
        $this->staff('Amara', 'manager');

        $this->assertSame(['Amara Person', 'Zara Person'], $this->names('sort=name'));
        $this->assertSame(['Amara Person', 'Zara Person'], $this->names('sort=recent'));
    }

    /**
     * The owner is seeded without an explicit tenant_id, and BelongsToTenant
     * stamps that on `creating` — which fires after `saving`. The hook that
     * resolves role_id therefore saw a null tenant and gave up, leaving the
     * owner with a role name, no role record, and no permissions.
     */
    public function test_a_staff_row_created_without_an_explicit_tenant_still_gets_its_role(): void
    {
        $owner = $this->owner();

        tenancy()->initialize($this->tenant);

        // Exactly how onboarding seeds the business owner.
        $staff = Staff::create([
            'user_id' => $owner->id,
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'nadia@acme.test', 'role' => 'owner',
        ]);

        tenancy()->end();

        $this->assertNotNull($staff->role_id, 'The role link was never made.');
        $this->assertSame('owner', $staff->roleRecord->key);
        $this->assertSame('Owner', $staff->roleName());
    }

    public function test_the_directory_never_shows_a_dash_for_a_named_role(): void
    {
        $this->actingAs($this->owner());

        $amara = $this->member ?? null;
        $staff = $this->staff('Amara', 'manager');

        // Simulate the broken state: a role name with no link.
        $staff->forceFill(['role_id' => null])->save();

        $content = $this->get('http://styledesk.test/settings/staff')->getContent();

        // A dash would read as "this person has no role" when what happened
        // is that a link was never made.
        $this->assertStringContainsString('Manager', $content);
    }

    // ------------------------------------------------------ pagination

    public function test_the_directory_pages_after_twenty_five(): void
    {
        $this->actingAs($this->owner());

        for ($i = 1; $i <= 27; $i++) {
            $this->staff('Person'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'manager', [
                'email' => 'person'.$i.'@acme.test',
            ]);
        }

        $first = $this->get('http://styledesk.test/settings/staff');

        $first->assertOk()
            ->assertSee('Showing')
            ->assertSee('25')
            ->assertSee('27')
            // The page link exists, so there is a way to the rest.
            ->assertSee('page=2', false);

        $this->assertCount(25, $this->namesFrom($first->getContent()));

        $second = $this->get('http://styledesk.test/settings/staff?page=2');

        $second->assertOk()->assertSee('26');
        $this->assertCount(2, $this->namesFrom($second->getContent()));
    }

    public function test_a_directory_that_fits_on_one_page_shows_no_pager(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        // A pager under a list that fits is furniture describing nothing.
        $this->get('http://styledesk.test/settings/staff')
            ->assertOk()
            ->assertDontSee('Showing');
    }

    /**
     * Page two of a filtered list must still be filtered. Without the query
     * carried onto the links, following one returns the whole directory.
     */
    public function test_paging_keeps_the_search_and_filters(): void
    {
        $this->actingAs($this->owner());

        for ($i = 1; $i <= 26; $i++) {
            $this->staff('Keeper'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'manager', ['email' => 'keep'.$i.'@acme.test']);
        }

        $this->staff('Excluded', 'front-desk', ['email' => 'nope@acme.test']);

        $response = $this->get('http://styledesk.test/settings/staff?role=manager');

        $response->assertOk()->assertSee('Showing');
        $this->assertStringContainsString('role=manager', $response->getContent());

        $page2 = $this->get('http://styledesk.test/settings/staff?role=manager&page=2');
        $names = $this->namesFrom($page2->getContent());

        $this->assertCount(1, $names);
        $this->assertStringNotContainsString('Excluded', implode(' ', $names));
    }

    /**
     * The header counts describe everything that matched, not the page being
     * looked at — "27 active members" must not become "2" on page two.
     */
    public function test_the_counts_describe_the_whole_result_not_the_page(): void
    {
        $this->actingAs($this->owner());

        for ($i = 1; $i <= 27; $i++) {
            $this->staff('Person'.$i, 'manager', ['email' => 'p'.$i.'@acme.test']);
        }

        $this->get('http://styledesk.test/settings/staff?page=2')
            ->assertOk()
            ->assertSee('27 active members');
    }

    /** @return array<int, string> */
    private function namesFrom(string $content): array
    {
        preg_match_all('/font-semibold text-head truncate">([^<]+)</', $content, $matches);

        return array_map('trim', $matches[1]);
    }

    // ---------------------------------------------------------- access

    /**
     * §1 makes both modules administrative, and §24 gives Service Provider no
     * App Settings access at all. Their "staff directory — view basic
     * information" is seeing colleagues while booking, not this screen.
     */
    public function test_a_service_provider_cannot_open_the_settings_directory(): void
    {
        $this->owner();

        $user = User::create([
            'first_name' => 'Priya', 'last_name' => 'Nair',
            'email' => 'priya@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->staff('Priya', 'service-provider', ['user_id' => $user->id, 'email' => 'priya@acme.test']);

        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/settings/staff')
            ->assertRedirect(route('dashboard'));
    }

    public function test_another_business_s_staff_never_appear(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        $rival = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);
        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $rival->getTenantKey(),
            'first_name' => 'Rival', 'last_name' => 'Person',
            'email' => 'rival@rival.test', 'role' => 'manager',
        ]);

        $this->assertSame(['Amara Person'], $this->names());
    }

    public function test_the_filters_are_combos_rather_than_native_selects(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        $content = $this->get('http://styledesk.test/settings/staff')->getContent();

        // Every dropdown in the app is the same searchable control; a native
        // select here would be the one place that looked and behaved apart.
        $this->assertStringNotContainsString('<select', $content);
        $this->assertSame(7, substr_count($content, 'data-vue-component="MultiSelect"'));
    }

    /**
     * The panel is hidden until asked for, but open when something is
     * filtering — a shared URL must not hide the reason the list is short.
     */
    public function test_the_filter_panel_opens_itself_when_a_filter_is_active(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        $closed = $this->get('http://styledesk.test/settings/staff')->getContent();
        $this->assertMatchesRegularExpression('/id="staff-filters"[^>]*hidden/', $closed);

        $open = $this->get('http://styledesk.test/settings/staff?role=manager')->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="staff-filters"[^>]*hidden/', $open);
    }

    public function test_the_settings_card_opens_the_directory(): void
    {
        $this->actingAs($this->owner());

        $this->get('http://styledesk.test/settings')
            ->assertOk()
            ->assertSee(route('settings.staff.index'), false)
            // No longer "Coming soon": the card is a live link now.
            ->assertSee('Staff Members');
    }
}
