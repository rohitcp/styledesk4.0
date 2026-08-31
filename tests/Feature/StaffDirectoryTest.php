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

    /**
     * The rows, read from where the page now reads them.
     *
     * The directory draws itself with the shared listing grid, which fetches
     * its rows from /staff/data — so the search, the filters, the order and
     * the paging are all asserted against that response rather than against
     * scraped markup. Same behaviour, one hop closer to it.
     *
     * @return array<string, mixed>
     */
    private function payload(string $query = ''): array
    {
        return $this->get('http://styledesk.test/settings/staff/data?'.$query)
            ->assertOk()
            ->json();
    }

    /** @return array<int, string> */
    private function names(string $query = ''): array
    {
        return array_column($this->payload($query)['data'], 'name');
    }

    public function test_the_directory_lists_the_business_s_staff(): void
    {
        $this->actingAs($this->owner());

        $this->staff('Amara', 'manager', ['job_title' => 'Salon Manager']);
        $this->staff('Priya', 'service-provider', ['job_title' => 'Senior Colourist']);

        /* The page itself is the frame — heading, toolbar and grid; the rows
           arrive from the endpoint the grid reads. Both are asserted, because
           a page that renders without its grid is a page with no directory. */
        $this->get('http://styledesk.test/settings/staff')
            ->assertOk()
            ->assertSee('data-grid', false)
            ->assertSee('staff/data', false);

        $rows = collect($this->payload()['data']);

        $this->assertSame(['Amara Person', 'Priya Person'], $rows->pluck('name')->all());
        /* Job title where there is one, and the role where there is not: a
           dash would read as "this person has no role". */
        $this->assertSame(['Salon Manager', 'Senior Colourist'], $rows->pluck('role')->all());
        $this->assertSame(['Riverside', 'Riverside'], $rows->pluck('location')->all());
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
     * Each row carries its own actions.
     *
     * Decided on the server, in the reader's language: which entries a row
     * offers is a permission question, and a grid that assembled the menu
     * itself would be a second place for that rule to live. The panel's own
     * behaviour — fixed rather than absolute, so a sideways-scrolling table
     * cannot clip it — belongs to resources/js/data-grid.js now.
     */
    public function test_each_row_carries_the_actions_the_reader_may_take(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        $menu = $this->payload()['data'][0]['menu'];
        $labels = array_column($menu, 'label');

        $this->assertContains('View staff', $labels);
        $this->assertContains('Edit', $labels);
        $this->assertContains('Deactivate', $labels);

        /* Designed, not built. Shown disabled rather than hidden: an entry
           that quietly disappears reads as a permission the reader lacks. */
        $scheduling = collect($menu)->firstWhere('label', 'Manage schedule');
        $this->assertTrue($scheduling['disabled']);
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

        // A dash would read as "this person has no role" when what happened
        // is that a link was never made.
        $roles = array_column($this->payload()['data'], 'role');

        $this->assertContains('Manager', $roles);
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

        $first = $this->payload('size=25');

        $this->assertCount(25, $first['data']);
        /* The total counts everything that matched, not the page — the grid's
           counter reads it, and a rounded-up figure would say "of 50". */
        $this->assertSame(27, $first['total']);
        $this->assertSame(2, $first['last_page']);

        $this->assertCount(2, $this->names('size=25&page=2'));
    }

    public function test_a_directory_that_fits_on_one_page_says_so(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        // One page, so the grid has nothing to page to.
        $this->assertSame(1, $this->payload()['last_page']);
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

        /* The filter is carried into the URL the grid fetches from, so every
           page it asks for is still the filtered list. */
        $this->get('http://styledesk.test/settings/staff')
            ->assertOk();

        $this->get('http://styledesk.test/settings/staff?role=manager')
            ->assertOk()
            ->assertSee('role=manager', false);

        $names = $this->names('role=manager&size=25&page=2');

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

        $this->get('http://styledesk.test/settings/staff')
            ->assertOk()
            ->assertSee('27 active members');
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

        // Status, location, role and service — the same four the clients
        // listing offers, in the same control.
        $this->assertSame(4, substr_count($content, 'data-vue-component="MultiSelect"'));
    }

    /**
     * A shared URL must not hide the reason the list is short.
     *
     * The filters are in the open now, as they are on the clients listing,
     * and what carries them is the address the grid fetches from — so a
     * bookmarked filtered URL draws a filtered grid.
     */
    public function test_a_filtered_url_reaches_the_grid(): void
    {
        $this->actingAs($this->owner());
        $this->staff('Amara', 'manager');

        $this->get('http://styledesk.test/settings/staff')
            ->assertOk()
            ->assertDontSee('role=manager', false);

        $this->get('http://styledesk.test/settings/staff?role=manager')
            ->assertOk()
            ->assertSee('data-active-filters', false)
            ->assertSee('role=manager', false);
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
