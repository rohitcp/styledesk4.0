<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Clients module's landing page.
 *
 * Its settings live in ClientSettingsTest; this is about the screen the
 * navigation now reaches, and about the separation §Access Control asks for
 * between configuring client records and working with them.
 */
class ClientModuleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio', 'slug' => 'nadia', 'business_email' => 'hello@nadia.test',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
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
            'tenant_id' => $this->tenant->getTenantKey(), 'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => $role,
        ]);

        return $user->fresh();
    }

    /** A client on this business, with only what the test cares about set. */
    private function client(array $attributes = []): Client
    {
        return $this->tenant->clients()->create(array_merge([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amara',
        ], $attributes));
    }

    // -------------------------------------------------------------- access

    /**
     * §Access Control: working with clients is not configuring them.
     *
     * A receptionist works with clients all day and configures nothing, so
     * this page is open at whatever scope their role holds — unlike App
     * Settings → Clients, which is Owner and Admin only.
     */
    public static function rolesWithClientAccess(): array
    {
        return [
            'owner' => ['owner'],
            'administrator' => ['administrator'],
            'manager' => ['manager'],
            'receptionist' => ['front-desk'],
            'service provider' => ['service-provider'],
        ];
    }

    #[DataProvider('rolesWithClientAccess')]
    public function test_every_role_that_works_with_clients_can_open_the_page(string $role): void
    {
        $user = $role === 'owner' ? $this->owner() : $this->member($role);

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Clients');
    }

    public function test_a_role_without_the_permission_is_refused(): void
    {
        $user = $this->member('front-desk');

        // Take clients.view away from the role rather than from the person,
        // because that is where the permission actually lives.
        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->firstOrFail();

        $role->permissions()->where('permission', 'clients.view')->delete();

        $this->actingAs($user->fresh())
            ->get(route('clients.index'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------ the page

    /**
     * A business with no clients gets the onboarding page, not an empty table.
     *
     * §Empty State is explicit: no search, no filters, no pagination and no
     * "0 results". A search box over nothing is a control that can only fail.
     */
    public function test_a_business_with_no_clients_sees_the_empty_state(): void
    {
        $this->actingAs($this->owner())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Start building your client list')
            ->assertSee('Add your first client')
            ->assertDontSee('Search clients');
    }

    /**
     * A search matching nothing is not a business with no clients.
     *
     * Offering "Add your first client" to someone with four hundred, because
     * their spelling was off, would be the page telling them something untrue.
     */
    public function test_a_fruitless_search_keeps_the_listing(): void
    {
        $client = $this->client(['first_name' => 'Amara', 'last_name' => 'Osei']);

        $this->actingAs($this->owner())
            ->get(route('clients.index', ['search' => 'Zzzzz']))
            ->assertOk()
            // The grid's own empty-result wording, which names both reasons
            // a filtered list can come back empty.
            ->assertSee('No clients match your search or filters.')
            ->assertDontSee('Start building your client list');

        $this->assertNotNull($client->fresh());
    }

    /**
     * Every client archived is not a search that found nothing.
     *
     * Archived clients are out of the list by default, so the page can be
     * empty with nothing typed and no filter set — and telling that reader
     * their search matched nothing describes something they never did.
     */
    public function test_an_all_archived_list_says_so_rather_than_blaming_a_search(): void
    {
        $this->client(['status' => Client::STATUS_ARCHIVED]);

        $this->actingAs($this->owner())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Every client is archived.')
            ->assertSee('View archived clients')
            ->assertDontSee('No clients match your search.');
    }

    /**
     * The page renders the grid; the grid's rows come from their own endpoint.
     *
     * Two assertions rather than one because they are two things now: the
     * screen has to mount a grid pointed at the right URL, and that URL has
     * to return this business's clients.
     */
    public function test_the_listing_mounts_the_grid(): void
    {
        $this->client(['first_name' => 'Amara', 'last_name' => 'Osei']);

        $this->actingAs($this->owner())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('data-grid', false)
            ->assertSee(route('clients.data'), false);
    }

    public function test_the_grid_endpoint_returns_this_businesss_clients(): void
    {
        $client = $this->client(['first_name' => 'Amara', 'last_name' => 'Osei']);

        $this->actingAs($this->owner())
            ->getJson(route('clients.data'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Amara Osei')
            ->assertJsonPath('data.0.ref', $client->client_ref)
            ->assertJsonPath('data.0.url', route('clients.show', $client))
            // Bookings do not exist yet, and the row says so rather than
            // carrying an empty cell.
            ->assertJsonPath('data.0.last_visit', 'No visits yet')
            ->assertJsonPath('total', 1);
    }

    /**
     * The figures above the toolbar count the business, not the current view.
     *
     * They are the reason to change the filters, so a set that moved with
     * them could only ever agree with the grid and would never tell the
     * reader anything they could not already see.
     */
    public function test_the_page_carries_the_business_figures(): void
    {
        $this->client(['first_name' => 'Amara', 'status' => Client::STATUS_ACTIVE]);
        $this->client(['first_name' => 'Bea', 'status' => Client::STATUS_INACTIVE]);
        $this->client(['first_name' => 'Cara', 'status' => Client::STATUS_ARCHIVED]);

        $this->actingAs($this->owner())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 3
                && $stats['inactive'] === 1
                && $stats['new_this_month'] === 3
                && $stats['upcoming'] === 0);
    }

    /** The card filters reach the query, not just the address bar. */
    public function test_the_grid_endpoint_honours_the_card_filters(): void
    {
        $this->client(['first_name' => 'Amara']);
        $this->client(['first_name' => 'Bea', 'status' => Client::STATUS_INACTIVE]);

        $owner = $this->owner();

        $this->actingAs($owner)
            ->getJson(route('clients.data', ['status' => Client::STATUS_INACTIVE]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Bea');

        // Nothing is booked, because bookings do not exist yet.
        $this->actingAs($owner)
            ->getJson(route('clients.data', ['upcoming' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    /** The endpoint is the page: same permission, same tenant boundary. */
    public function test_the_grid_endpoint_refuses_a_role_without_the_permission(): void
    {
        $user = $this->member('front-desk');

        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->firstOrFail()
            ->permissions()->where('permission', 'clients.view')->delete();

        $this->actingAs($user->fresh())
            ->getJson(route('clients.data'))
            ->assertForbidden();
    }

    public function test_the_grid_endpoint_honours_the_search(): void
    {
        $this->client(['first_name' => 'Amara', 'last_name' => 'Osei']);
        $this->client(['first_name' => 'Bea', 'last_name' => 'Lin']);

        $this->actingAs($this->owner())
            ->getJson(route('clients.data', ['search' => 'Osei']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Amara Osei');
    }

    // ----------------------------------------------------------- the nav

    public function test_the_navigation_reaches_the_page(): void
    {
        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('clients.index'), false);
    }

    public function test_the_page_is_translated(): void
    {
        $owner = $this->owner();

        $this->tenant->forceFill(['default_language' => 'en'])->save();
        $this->tenant->languages()->create(['language_code' => 'es', 'position' => 1]);
        $owner->forceFill(['locale' => 'es'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Clientes')
            ->assertSee('Empieza a crear tu lista de clientes')
            ->assertDontSee('Start building your client list');
    }
}
