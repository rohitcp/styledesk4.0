<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the Fortify pages the app actually exposes.
 *
 * These exist because a missing Fortify view callback does not 404 — the route
 * registers and then fails at render with a BindingResolutionException, i.e. a
 * 500 that only shows up when someone visits the page.
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public static function pageProvider(): array
    {
        return [
            'login' => ['/login', 'Sign in'],
            'register' => ['/register', 'Create your StyleDesk account'],
            'forgot password' => ['/forgot-password', 'We will email you a reset link'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_auth_page_renders(string $uri, string $expected): void
    {
        $this->get('http://styledesk.test'.$uri)
            ->assertOk()
            ->assertSee($expected);
    }

    public function test_a_user_can_register_and_land_on_the_dashboard(): void
    {
        $this->post('http://styledesk.test/register', [
            'name' => 'Rohit',
            'email' => 'rohit@styledesk.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();

        // A brand-new account has no business yet, so the dashboard must still
        // render rather than blowing up on a null tenant.
        $this->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('No business linked to this account yet');
    }

    public function test_dashboard_shows_the_tenant_resolved_from_the_user(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        $user = User::create([
            'name' => 'Rohit',
            'email' => 'r@styledesk.test',
            'password' => 'password1234',
        ]);
        $user->tenant_id = $tenant->id;
        $user->save();

        $this->actingAs($user)
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('Acme Salon')
            ->assertSee('Tenancy resolved from your account');
    }

    public function test_the_dashboard_is_closed_to_guests(): void
    {
        $this->get('http://styledesk.test/dashboard')->assertRedirect('/login');
    }
}
