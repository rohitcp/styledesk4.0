<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
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
            'signup' => ['/signup', 'Create your StyleDesk account'],
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
        $this->post('http://styledesk.test/signup', [
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'rohit@styledesk.test',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
            'terms' => '1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();

        // Email verification comes before anything else: an unverified account
        // cannot reach the app or the wizard.
        $this->get('http://styledesk.test/dashboard')
            ->assertRedirect(route('verification.notice'));

        $this->get('http://styledesk.test/onboarding/business')
            ->assertRedirect(route('verification.notice'));

        $this->get('http://styledesk.test/email/verify')
            ->assertOk()
            ->assertSee('Check your email');
    }

    public function test_dashboard_shows_the_tenant_resolved_from_the_user(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        $user = User::create([
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'r@styledesk.test',
            'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
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
        $this->post('http://styledesk.test/signup', [
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'noterms@styledesk.test',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
    }

    public function test_the_dashboard_is_closed_to_guests(): void
    {
        $this->get('http://styledesk.test/dashboard')->assertRedirect('/login');
    }
}
