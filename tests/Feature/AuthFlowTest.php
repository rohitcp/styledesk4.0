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
            'login' => ['/login', 'Log in to StyleDesk'],
            'register' => ['/register', 'Create your StyleDesk account'],
            'forgot password' => ['/forgot-password', 'Reset your password'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_auth_page_renders(string $uri, string $expected): void
    {
        $this->get('http://styledesk.test'.$uri)
            ->assertOk()
            ->assertSee($expected);
    }

    public function test_a_new_user_is_registered_and_sent_into_onboarding(): void
    {
        $this->post('http://styledesk.test/register', [
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'rohit@styledesk.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'terms' => '1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();

        // A brand-new account has no business yet, so the app is not reachable:
        // every tenant-scoped query would come back empty. The onboarding gate
        // sends them to step 1 instead.
        $this->get('http://styledesk.test/dashboard')
            ->assertRedirect(route('onboarding.business'));

        $this->get('http://styledesk.test/onboarding/business')
            ->assertOk()
            ->assertSee('Tell us about your business');
    }

    public function test_dashboard_shows_the_tenant_resolved_from_the_user(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        $user = User::create([
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'r@styledesk.test',
            'password' => 'password1234',
        ]);
        $user->tenant_id = $tenant->id;
        $user->save();

        $this->actingAs($user)
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('Acme Salon')
            ->assertSee('Rohit Philip');
    }

    public function test_registration_requires_accepting_the_terms(): void
    {
        // The prototype makes this explicit rather than implied by pressing
        // the button, so an unticked box must fail rather than silently pass.
        $this->post('http://styledesk.test/register', [
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'noterms@styledesk.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
    }

    public function test_the_dashboard_is_closed_to_guests(): void
    {
        $this->get('http://styledesk.test/dashboard')->assertRedirect('/login');
    }
}
