<?php

namespace Tests\Feature;

use App\Models\ClientSettings;
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

    /**
     * The configuration link is only shown to someone who can open it.
     *
     * A card that bounces the reader to the dashboard is worse than no card.
     */
    public function test_only_owner_and_admin_are_offered_the_configuration(): void
    {
        $this->actingAs($this->owner())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee(route('settings.clients.show'), false);

        $this->actingAs($this->member('front-desk'))
            ->get(route('clients.index'))
            ->assertOk()
            ->assertDontSee(route('settings.clients.show'), false);
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
     * No client records exist, and the page says so.
     *
     * An empty table with column headings would read as a business that has
     * lost its clients rather than as a module that has not arrived.
     */
    public function test_the_page_says_plainly_that_there_are_no_records_yet(): void
    {
        $this->actingAs($this->owner())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('No clients yet.')
            ->assertSee('Client records arrive with the Clients module');
    }

    /**
     * What has been configured is shown as evidence.
     *
     * A page that says "no clients yet" and nothing else gives the reader no
     * reason to believe anything is coming.
     */
    public function test_the_page_reflects_what_has_been_configured(): void
    {
        $owner = $this->owner();

        $this->tenant->clientPreferences()->create(['label' => 'Quiet appointment']);
        $this->tenant->clientTags()->create(['label' => 'VIP', 'color' => 'violet']);

        ClientSettings::forTenant($this->tenant)->forceFill([
            'name_format' => 'last_first',
        ])->save();

        $this->actingAs($owner)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('First name')
            ->assertSee('Last name, then first name')
            ->assertSee('1 active preference')
            ->assertSee('1 active tag');
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
            ->assertSee('Todavía no hay clientes.')
            ->assertDontSee('No clients yet.');
    }
}
