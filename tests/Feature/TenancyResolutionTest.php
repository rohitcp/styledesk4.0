<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Covers StyleDesk's hybrid tenant identification.
 *
 * Two paths reach the same tenancy state by different means:
 *   - public booking on a subdomain, identified from the host
 *   - the central app, identified from the signed-in user's membership
 *
 * These tests also pin the single-database decision: tenancy must initialize
 * WITHOUT the connection changing. If DatabaseTenancyBootstrapper is ever
 * re-enabled in config/tenancy.php, the connection assertion fails loudly
 * instead of the app quietly querying a database that does not exist.
 */
class TenancyResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'acme'): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug).' Salon', 'slug' => $slug]);
        $tenant->domains()->create(['domain' => $slug]);

        return $tenant;
    }

    public function test_central_app_resolves_tenant_from_the_authenticated_user(): void
    {
        $tenant = $this->tenant();

        $user = User::create(['name' => 'Rohit', 'email' => 'r@styledesk.test', 'password' => 'secret123']);
        $user->tenant_id = $tenant->id;
        $user->save();

        Route::middleware(['web', 'auth', 'tenant.user'])->get('/_test/central', function () {
            return tenant()->getTenantKey();
        });

        $this->actingAs($user)
            ->get('http://styledesk.test/_test/central')
            ->assertOk()
            ->assertSee($tenant->id);
    }

    public function test_a_guest_passes_through_without_tenancy_being_initialized(): void
    {
        Route::middleware(['web', 'tenant.user'])->get('/_test/guest', function () {
            return tenancy()->initialized ? 'initialized' : 'central';
        });

        $this->get('http://styledesk.test/_test/guest')
            ->assertOk()
            ->assertSee('central');
    }

    public function test_a_user_without_a_business_yet_can_still_reach_onboarding(): void
    {
        // Sign-up creates the user before the business exists; blocking here
        // would make the onboarding routes unreachable.
        $user = User::create(['name' => 'New', 'email' => 'new@styledesk.test', 'password' => 'secret123']);

        Route::middleware(['web', 'auth', 'tenant.user'])->get('/_test/onboarding', function () {
            return tenancy()->initialized ? 'initialized' : 'no-tenant';
        });

        $this->actingAs($user)
            ->get('http://styledesk.test/_test/onboarding')
            ->assertOk()
            ->assertSee('no-tenant');
    }

    public function test_booking_subdomain_resolves_tenant_from_the_host(): void
    {
        $tenant = $this->tenant();

        $this->get('http://acme.styledesk.test/')
            ->assertOk()
            ->assertSee($tenant->id);
    }

    public function test_tenancy_does_not_switch_the_database_connection(): void
    {
        $tenant = $this->tenant();
        $central = \DB::connection()->getDatabaseName();

        tenancy()->initialize($tenant);

        $this->assertTrue(tenancy()->initialized);
        $this->assertSame(
            $central,
            \DB::connection()->getDatabaseName(),
            'Single-database tenancy: initializing a tenant must not change the connection.'
        );

        tenancy()->end();
    }
}
