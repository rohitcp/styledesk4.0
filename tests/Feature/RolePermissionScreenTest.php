<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 1 acceptance criteria for Roles & Permissions.
 */
class RolePermissionScreenTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);
    }

    private function member(string $role): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => ucfirst($role),
            'email' => $role.'@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        if ($role === 'owner') {
            $this->tenant->forceFill(['owner_user_id' => $user->id])->save();
        }

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => ucfirst($role),
            'email' => $user->email, 'role' => $role,
        ]);

        return $user->fresh();
    }

    private function roleKeyed(string $key): Role
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)->firstOrFail();
    }

    // ------------------------------------------------------------- access

    public static function allowed(): array
    {
        return [['owner'], ['administrator']];
    }

    public static function denied(): array
    {
        return [['manager'], ['front-desk'], ['service-provider']];
    }

    #[DataProvider('allowed')]
    public function test_owner_and_admin_can_open_the_module(string $role): void
    {
        $user = $this->member($role);

        $this->actingAs($user)->get('http://styledesk.test/settings/roles-permissions')->assertOk();
        $this->actingAs($user)
            ->get('http://styledesk.test/settings/roles-permissions/'.$this->roleKeyed('manager')->id)
            ->assertOk();
    }

    /**
     * Enforced on the route, not by hiding a card. A direct URL is the whole
     * point of the requirement.
     */
    #[DataProvider('denied')]
    public function test_no_other_role_can_reach_it_even_by_url(string $role): void
    {
        $user = $this->member($role);

        $this->actingAs($user)->get('http://styledesk.test/settings/roles-permissions')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->get('http://styledesk.test/settings/roles-permissions/'.$this->roleKeyed('owner')->id)
            ->assertRedirect(route('dashboard'));
    }

    public function test_another_business_s_role_is_not_reachable(): void
    {
        $owner = $this->member('owner');

        $rival = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);
        $theirs = Role::withoutGlobalScopes()
            ->where('tenant_id', $rival->getTenantKey())->where('key', 'manager')->firstOrFail();

        $this->actingAs($owner)
            ->get('http://styledesk.test/settings/roles-permissions/'.$theirs->id)
            ->assertNotFound();
    }

    // -------------------------------------------------------------- list

    public function test_every_predefined_role_is_listed_with_its_details(): void
    {
        $owner = $this->member('owner');
        $this->member('manager');

        $response = $this->actingAs($owner)->get('http://styledesk.test/settings/roles-permissions');

        $response->assertOk();

        foreach (['Owner', 'Admin', 'Manager', 'Receptionist', 'Service Provider'] as $name) {
            $response->assertSee($name);
        }

        // Description, type badge, permission count and staff count.
        $response->assertSee('Full access to the business')
            ->assertSee('System role')
            ->assertSee('of '.count(Permissions::all()).' permissions', false)
            ->assertSee('staff member', false)
            ->assertSee('View permissions');
    }

    public function test_the_staff_count_reflects_who_holds_the_role(): void
    {
        $owner = $this->member('owner');
        $this->member('manager');

        $content = $this->actingAs($owner)->get('http://styledesk.test/settings/roles-permissions')->getContent();

        $manager = $this->roleKeyed('manager');
        $this->assertSame(1, $manager->staff()->count());

        /* The rendered phrase, not the markup around it. The count used to sit
           in a <span> of its own and this asserted on that tag; it is now
           inside one translated sentence, because a number bolted to a
           separately-translated noun cannot be reordered by a language that
           needs to. */
        $this->assertStringContainsString(
            trans_choice('roles.staff_summary', 1, ['count' => 1]),
            $content
        );
    }

    // ------------------------------------------------------------ detail

    public function test_permissions_are_grouped_by_category(): void
    {
        $owner = $this->member('owner');

        $response = $this->actingAs($owner)
            ->get('http://styledesk.test/settings/roles-permissions/'.$this->roleKeyed('manager')->id);

        $response->assertOk();

        foreach (config('permissions.groups') as $group) {
            $response->assertSee(e($group['label'], false), false);
        }
    }

    public function test_granted_and_ungranted_permissions_are_both_shown(): void
    {
        $owner = $this->member('owner');

        $response = $this->actingAs($owner)
            ->get('http://styledesk.test/settings/roles-permissions/'.$this->roleKeyed('front-desk')->id);

        // A screen listing only what a role has answers "what can they do" but
        // not "what are they missing", which is the question it is opened with.
        $response->assertOk()
            ->assertSee('Check in client')      // granted
            ->assertSee('Delete client')        // not granted
            ->assertSee('Granted')
            ->assertSee('Not granted');
    }

    public function test_scope_is_shown_where_it_is_narrower_than_everything(): void
    {
        $owner = $this->member('owner');

        $provider = $this->actingAs($owner)
            ->get('http://styledesk.test/settings/roles-permissions/'.$this->roleKeyed('service-provider')->id);

        // "View clients" and "View clients (Assigned)" are different
        // permissions to the person reading.
        $provider->assertOk()->assertSee('Own')->assertSee('Assigned');
    }

    public function test_the_owner_screen_says_the_role_is_protected(): void
    {
        $owner = $this->member('owner');

        $this->actingAs($owner)
            ->get('http://styledesk.test/settings/roles-permissions/'.$this->roleKeyed('owner')->id)
            ->assertOk()
            ->assertSee('Protected')
            ->assertSee('cannot be renamed or deleted')
            ->assertSee('never be left without an owner');
    }

    // ------------------------------------------------------ phase 1 limits

    /**
     * Read-only means the actions do not exist, not that they are disabled.
     * A greyed-out control implies it might work for somebody else.
     */
    public function test_no_editing_affordances_are_rendered(): void
    {
        $owner = $this->member('owner');

        foreach ([
            'http://styledesk.test/settings/roles-permissions',
            'http://styledesk.test/settings/roles-permissions/'.$this->roleKeyed('manager')->id,
        ] as $url) {
            $content = $this->actingAs($owner)->get($url)->getContent();
            $page = substr($content, strpos($content, '<main'), strpos($content, '</main>') - strpos($content, '<main'));

            $this->assertStringNotContainsString('<input type="checkbox"', $page);
            $this->assertStringNotContainsString('type="submit"', $page);
            $this->assertStringNotContainsString('Add role', $page);
            $this->assertStringNotContainsString('Duplicate', $page);
            $this->assertStringNotContainsString('Save changes', $page);
        }
    }

    public function test_there_are_no_write_routes_at_all(): void
    {
        // Not guarded routes — absent ones. A route that exists and refuses is
        // still a route somebody can find.
        $paths = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'roles-permissions'))
            ->flatMap(fn ($route) => $route->methods())
            ->unique()
            ->values()
            ->all();

        sort($paths);

        $this->assertSame(['GET', 'HEAD'], $paths);
    }

    public function test_the_settings_card_says_view_only_rather_than_coming_soon(): void
    {
        $owner = $this->member('owner');

        $content = $this->actingAs($owner)->get('http://styledesk.test/settings')->getContent();

        $card = substr($content, strpos($content, 'Roles &amp; Permissions') - 600, 1400);

        // "Coming soon" would say the whole module is unavailable, when it is
        // here and useful.
        $this->assertStringContainsString('View only', $card);
        $this->assertStringContainsString(route('settings.roles.index'), $content);
    }

    // ------------------------------------------------------- phase 2 ready

    /**
     * The storage does not assume read-only: a role is a row and a grant is a
     * row, so an editable matrix writes exactly what this screen reads.
     */
    public function test_the_model_already_supports_editable_roles(): void
    {
        $manager = $this->roleKeyed('manager');

        app(ProvisionSystemRoles::class)
            ->syncPermissions($manager, ['clients.view' => 'own']);

        $this->assertSame(['clients.view' => 'own'], $manager->fresh()->permissionMap());

        // And a custom role needs no new concept: it is a row without
        // is_system set.
        $custom = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'senior-stylist', 'name' => 'Senior Stylist',
            'description' => 'A custom role.', 'display_order' => 10,
        ]);

        $this->assertFalse($custom->isSystem());

        $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/settings/roles-permissions')
            ->assertOk()
            ->assertSee('Senior Stylist')
            ->assertSee('Custom role');
    }
}
