<?php

namespace Tests\Feature;

use App\Models\BackofficeAdmin;
use App\Models\Client;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The list of businesses subscribed to StyleDesk.
 *
 * "Client" is the platform's word for a customer of StyleDesk, not the salon's
 * own clients — the two are different people in different consoles, and the
 * counting test below is the one that would catch them being confused.
 */
class BackofficeClientsTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'super-owner'): BackofficeAdmin
    {
        $admin = $this->admin($role);

        $this->actingAs($admin, 'backoffice');

        return $admin;
    }

    private function admin(string $role = 'super-owner'): BackofficeAdmin
    {
        return BackofficeAdmin::query()->create([
            'name' => 'Rohit Philip',
            'email' => $role.'@styledesk.test',
            'password' => 'Str0ng!Passw0rd!',
            'role' => $role,
            'status' => BackofficeAdmin::STATUS_ACTIVE,
        ]);
    }

    /** A branch, with the columns the table insists on. */
    private function location(Tenant $tenant, string $name): void
    {
        Location::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'name' => $name,
            'address_line1' => '1 River Street',
            'city' => 'Austin',
            'state' => 'Texas',
            'postal_code' => '78701',
            'country' => 'US',
            'timezone' => 'America/Chicago',
        ]);
    }

    public function test_it_lists_every_business(): void
    {
        $this->signIn();

        Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);
        Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        $this->get(route('backoffice.clients.index'))
            ->assertOk()
            ->assertSee('Smile Spa')
            ->assertSee('Acme Salon');
    }

    public function test_it_counts_each_businesss_own_rows_rather_than_every_tenants(): void
    {
        $this->signIn();

        $smile = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);
        $acme = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        $this->location($smile, 'Main');
        $this->location($acme, 'North');
        $this->location($acme, 'South');

        $rows = $this->get(route('backoffice.clients.index'))
            ->assertOk()
            ->viewData('clients');

        $this->assertSame(1, $rows->firstWhere('id', $smile->id)->locations_count);
        $this->assertSame(2, $rows->firstWhere('id', $acme->id)->locations_count);
    }

    public function test_it_searches_by_business_slug_and_owner(): void
    {
        $this->signIn();

        $owner = User::factory()->create([
            'first_name' => 'Dana',
            'last_name' => 'Reeves',
            'email' => 'dana@acme.test',
        ]);
        Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme', 'owner_user_id' => $owner->id]);
        Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);

        foreach (['Acme', 'acme', 'Dana', 'dana@acme.test'] as $term) {
            $this->get(route('backoffice.clients.index', ['search' => $term]))
                ->assertOk()
                ->assertSee('Acme Salon')
                ->assertDontSee('Smile Spa');
        }
    }

    /** A business with no owner must still be findable by its own name. */
    public function test_search_does_not_drop_a_business_without_an_owner(): void
    {
        $this->signIn();

        Tenant::create(['name' => 'Orphan Studio', 'slug' => 'orphan']);

        $this->get(route('backoffice.clients.index', ['search' => 'Orphan']))
            ->assertOk()
            ->assertSee('Orphan Studio');
    }

    /**
     * The five states the reader sees are made from two columns: `status` for
     * access, `subscription_status` for billing. Filtering has to agree with
     * the word in the Status column, not with either column alone.
     */
    public function test_it_filters_by_the_status_the_reader_sees(): void
    {
        $this->signIn();

        Tenant::create(['name' => 'Trial Spa', 'slug' => 'trial', 'subscription_status' => 'trialing']);
        Tenant::create(['name' => 'Overdue Salon', 'slug' => 'overdue', 'subscription_status' => 'past_due']);
        Tenant::create(['name' => 'Gone Studio', 'slug' => 'gone', 'subscription_status' => 'canceled']);
        Tenant::create(['name' => 'Paid Barbers', 'slug' => 'paid', 'subscription_status' => 'active']);
        Tenant::create(['name' => 'Locked Clinic', 'slug' => 'locked', 'status' => Tenant::STATUS_DISABLED]);

        $expected = [
            'trial' => 'Trial Spa',
            'past_due' => 'Overdue Salon',
            'cancelled' => 'Gone Studio',
            'active' => 'Paid Barbers',
            'disabled' => 'Locked Clinic',
        ];

        foreach ($expected as $status => $name) {
            $names = $this->get(route('backoffice.clients.index', ['status' => $status]))
                ->assertOk()
                ->viewData('clients')
                ->pluck('name')
                ->all();

            $this->assertSame([$name], $names, "filter [{$status}]");
        }
    }

    /** A business that predates billing is running, not in some sixth state. */
    public function test_a_business_with_no_subscription_row_counts_as_active(): void
    {
        $this->signIn();

        Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);

        $this->get(route('backoffice.clients.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Smile Spa');
    }

    /** Past due is a bill, disabled is a locked door. The list must not blur them. */
    public function test_a_past_due_business_is_not_listed_as_disabled(): void
    {
        $this->signIn();

        Tenant::create(['name' => 'Overdue Salon', 'slug' => 'overdue', 'subscription_status' => 'past_due']);

        $this->get(route('backoffice.clients.index', ['status' => 'disabled']))
            ->assertOk()
            ->assertDontSee('Overdue Salon');
    }

    public function test_it_sorts_by_name_when_asked(): void
    {
        $this->signIn();

        Tenant::create(['name' => 'Zebra Studio', 'slug' => 'zebra']);
        Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        $names = $this->get(route('backoffice.clients.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->viewData('clients')
            ->pluck('name')
            ->all();

        $this->assertSame(['Acme Salon', 'Zebra Studio'], $names);
    }

    /** An unknown sort column must not reach the query builder. */
    public function test_an_unknown_sort_falls_back_instead_of_being_trusted(): void
    {
        $this->signIn();
        Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);

        $this->get(route('backoffice.clients.index', ['sort' => 'password', 'direction' => 'nonsense']))
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters) => $filters['sort'] === 'created'
                && $filters['direction'] === 'desc');
    }

    public function test_a_salons_own_clients_are_not_what_this_screen_lists(): void
    {
        $this->signIn();

        $tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);
        Client::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'client_ref' => Client::nextRef($tenant->getTenantKey()),
            'first_name' => 'Marta',
            'last_name' => 'Ruiz',
        ]);

        $rows = $this->get(route('backoffice.clients.index'))
            ->assertOk()
            ->assertDontSee('Marta')
            ->viewData('clients');

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows->first()->clients_count);
    }

    public function test_a_role_without_the_permission_is_refused(): void
    {
        /* Every role in the catalogue may read the client list, so the case
           worth pinning is the one the gate is built for: a role nobody has
           defined grants nothing rather than everything. */
        $this->signIn('read-only');
        $this->get(route('backoffice.clients.index'))->assertOk();

        auth('backoffice')->logout();
        $this->actingAs($this->admin('nonexistent-role'), 'backoffice');

        $this->get(route('backoffice.clients.index'))->assertForbidden();
    }

    public function test_the_business_name_links_to_its_details_page(): void
    {
        $this->signIn();

        $tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);

        $this->get(route('backoffice.clients.index'))
            ->assertOk()
            ->assertSee(route('backoffice.clients.show', $tenant), false);
    }

    public function test_the_details_page_shows_the_business_its_owner_and_its_counts(): void
    {
        $this->signIn();

        $owner = User::factory()->create([
            'first_name' => 'Dana',
            'last_name' => 'Reeves',
            'email' => 'dana@acme.test',
        ]);

        $tenant = Tenant::create([
            'name' => 'Acme Salon',
            'slug' => 'acme',
            'owner_user_id' => $owner->id,
            'business_email' => 'hello@acme.test',
        ]);

        $this->location($tenant, 'Riverside');

        $this->get(route('backoffice.clients.show', $tenant))
            ->assertOk()
            ->assertSee('Acme Salon')
            ->assertSee('Dana Reeves')
            ->assertSee('dana@acme.test')
            ->assertSee('hello@acme.test')
            ->assertSee('Riverside');
    }

    /** The detail page is bound on the slug, not on the UUID. */
    public function test_the_details_page_is_reached_by_slug(): void
    {
        $this->signIn();

        $tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);

        $this->assertStringEndsWith('/backoffice/clients/smile', route('backoffice.clients.show', $tenant));

        $this->get('/backoffice/clients/'.$tenant->getTenantKey())->assertNotFound();
    }

    public function test_the_details_page_counts_only_this_businesss_rows(): void
    {
        $this->signIn();

        $smile = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);
        $acme = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        $this->location($smile, 'Main');
        $this->location($acme, 'North');
        $this->location($acme, 'South');

        $client = $this->get(route('backoffice.clients.show', $smile))
            ->assertOk()
            ->viewData('client');

        $this->assertSame(1, $client->locations_count);
        $this->assertSame('Main', $client->locations->first()->name);
    }

    public function test_the_details_page_needs_the_same_permission_as_the_list(): void
    {
        $tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);

        $this->actingAs($this->admin('nonexistent-role'), 'backoffice');

        $this->get(route('backoffice.clients.show', $tenant))->assertForbidden();
    }

    public function test_a_signed_out_visitor_is_sent_to_the_sign_in(): void
    {
        Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);

        $this->get(route('backoffice.clients.index'))
            ->assertRedirect(route('backoffice.verify.email'));
    }
}
