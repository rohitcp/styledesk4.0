<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Icon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Acceptance criteria from the App Settings landing page spec.
 */
class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete',
            'completed_at' => now(),
        ]);
    }

    private function member(string $role): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => ucfirst($role),
            'email' => $role.'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        if ($role === 'owner') {
            // The owner is read from tenants.owner_user_id, not a staff row.
            $this->tenant->forceFill(['owner_user_id' => $user->id])->save();
        } else {
            Staff::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->getTenantKey(),
                'user_id' => $user->id,
                'first_name' => 'Sam', 'last_name' => ucfirst($role),
                'email' => $role.'@styledesk.test', 'role' => $role,
            ]);
        }

        return $user->fresh();
    }

    // ------------------------------------------------------------- access

    public static function allowedRoles(): array
    {
        return [['owner'], ['administrator']];
    }

    #[DataProvider('allowedRoles')]
    public function test_owners_and_administrators_can_open_app_settings(string $role): void
    {
        $this->actingAs($this->member($role))
            ->get('http://styledesk.test/settings')
            ->assertOk()
            ->assertSee('App settings');
    }

    public static function deniedRoles(): array
    {
        return [['manager'], ['front-desk'], ['service-provider']];
    }

    /**
     * The spec is explicit that these must be turned away without a technical
     * error: a manager following a bookmark has done nothing wrong, and a 403
     * also confirms to anyone probing that the page exists.
     */
    #[DataProvider('deniedRoles')]
    public function test_every_other_role_is_redirected_rather_than_shown_an_error(string $role): void
    {
        $response = $this->actingAs($this->member($role))
            ->get('http://styledesk.test/settings');

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('toast');

        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertNotSame(401, $response->getStatusCode());
    }

    public function test_the_navigation_item_is_hidden_from_roles_that_cannot_use_it(): void
    {
        $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('App settings');

        $this->actingAs($this->member('manager'))
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertDontSee('App settings');
    }

    public function test_a_signed_out_visitor_cannot_reach_app_settings(): void
    {
        $this->get('http://styledesk.test/settings')->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------- page

    public function test_every_configured_module_is_rendered_in_its_group(): void
    {
        $response = $this->actingAs($this->member('owner'))->get('http://styledesk.test/settings');

        $response->assertOk();

        $content = $response->getContent();

        foreach (config('app_settings.groups') as $group) {
            $this->assertStringContainsString(
                e($group['name'], false),
                $content,
                "Group [{$group['name']}] is missing."
            );

            foreach ($group['modules'] as $module) {
                $this->assertStringContainsString(
                    e($module['name'], false),
                    $content,
                    "Module [{$module['name']}] is missing from the page."
                );
            }
        }
    }

    /**
     * The original spec listed 36. Shift Rules was asked for afterwards,
     * Reasons after that, Email after that, and Reviews & Feedback after that
     * — which makes 40. The count is updated deliberately rather than
     * loosened to a minimum, because the point of this test is that the
     * config and the spec are the same list, and a `>=` would stop noticing a
     * module added by accident.
     */
    public function test_every_specified_settings_module_is_configured(): void
    {
        $count = collect(config('app_settings.groups'))->sum(fn ($g) => count($g['modules']));

        $this->assertSame(40, $count, 'The spec lists 36 settings modules, plus Shift Rules, Reasons, Email and Reviews & Feedback.');
    }

    public function test_module_keys_are_unique(): void
    {
        // The key is what a module page's route will be named after, so a
        // duplicate would silently point two cards at one destination.
        $keys = collect(config('app_settings.groups'))
            ->flatMap(fn ($g) => collect($g['modules'])->pluck('key'))
            ->all();

        $this->assertSame(array_values(array_unique($keys)), $keys);
    }

    /**
     * Every icon named in the config must be vendored.
     *
     * Icons are copied out of the Font Awesome bundle by hand, and the bundle
     * is gitignored — so a missing one is not a broken image, it is a 500 on
     * the whole page, and it would only be found by opening it. This is the
     * check that a new module cannot be added without its icon.
     */
    public function test_every_module_icon_is_vendored(): void
    {
        foreach (config('app_settings.groups') as $group) {
            foreach ($group['modules'] as $module) {
                Icon::inline($module['icon'], 18);
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_search_terms_from_the_spec_all_match_something(): void
    {
        $response = $this->actingAs($this->member('owner'))->get('http://styledesk.test/settings');

        // The haystacks are built server-side and shipped to the island, so
        // the terms the spec promises can be checked without a browser.
        $haystacks = [];
        foreach (config('app_settings.groups') as $group) {
            foreach ($group['modules'] as $module) {
                $haystacks[] = mb_strtolower(implode(' ', [
                    $module['name'], $module['description'], implode(' ', $module['keywords'] ?? []), $group['name'],
                ]));
            }
        }

        foreach (['currency', 'booking', 'email', 'staff', 'tax', 'branding'] as $term) {
            $matches = array_filter($haystacks, fn ($h) => str_contains($h, $term));

            $this->assertNotEmpty($matches, "Searching for [{$term}] finds nothing.");
        }

        $response->assertOk();
    }

    public function test_unbuilt_modules_are_marked_rather_than_linked(): void
    {
        // A card that looks clickable and goes nowhere is worse than one that
        // says it is not ready.
        $response = $this->actingAs($this->member('owner'))->get('http://styledesk.test/settings');

        $response->assertOk()->assertSee('Coming soon');
    }
}
