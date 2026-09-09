<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireAccessCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The door in front of sign-in and sign-up.
 *
 * StyleDesk is invitation only for now: a visitor without one of the codes
 * should not reach a form asking for an email address, by the page or by
 * posting to it.
 */
class AccessCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /* Undo the suite-wide "already past the gate" cookie, which is the one
           thing these tests must not assume. */
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }

    /** The cookie a browser would be carrying after answering the gate. */
    private function assertGateOpened($response): void
    {
        $response->assertCookie(RequireAccessCode::COOKIE, RequireAccessCode::VALUE);

        $this->withCookie(RequireAccessCode::COOKIE, RequireAccessCode::VALUE);
    }

    public function test_the_front_door_asks_for_a_code(): void
    {
        $this->get('/')->assertRedirect(route('access-code.show'));
    }

    public function test_the_front_door_goes_to_login_once_the_code_is_known(): void
    {
        $this->assertGateOpened(
            $this->post(route('access-code.store'), ['access_code' => '4000'])
        );

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_front_door_goes_to_the_dashboard_when_signed_in(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-home']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_login_page_is_gated_until_a_code_is_entered(): void
    {
        $this->get('/login')->assertRedirect(route('access-code.show'));
    }

    public function test_signup_page_is_gated_until_a_code_is_entered(): void
    {
        $this->get('/signup')->assertRedirect(route('access-code.show'));
    }

    public function test_posting_to_signup_without_a_code_creates_nothing(): void
    {
        $this->post('/signup', [
            'name' => 'Gate Crasher',
            'email' => 'crasher@example.com',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
        ])->assertRedirect(route('access-code.show'));

        $this->assertDatabaseMissing('users', ['email' => 'crasher@example.com']);
    }

    public function test_a_valid_code_opens_the_login_screen(): void
    {
        $response = $this->post(route('access-code.store'), ['access_code' => '1000'])
            ->assertRedirect(route('login'));

        $this->assertGateOpened($response);

        $this->get('/login')->assertOk();
        $this->get('/signup')->assertOk();
    }

    /**
     * Every code on the list works, not only the first.
     */
    public function test_each_configured_code_is_accepted(): void
    {
        foreach (['1000', '2000', '3000', '4000', '5000'] as $code) {
            $this->post(route('access-code.store'), ['access_code' => $code])
                ->assertRedirect(route('login'))
                ->assertCookie(RequireAccessCode::COOKIE, RequireAccessCode::VALUE);
        }
    }

    public function test_a_wrong_code_is_refused_and_the_gate_stays_shut(): void
    {
        $this->from(route('access-code.show'))
            ->post(route('access-code.store'), ['access_code' => '9999'])
            ->assertRedirect(route('access-code.show'))
            ->assertSessionHasErrors('access_code')
            ->assertCookieMissing(RequireAccessCode::COOKIE);

        $this->get('/login')->assertRedirect(route('access-code.show'));
    }

    public function test_an_empty_code_is_refused(): void
    {
        $this->from(route('access-code.show'))
            ->post(route('access-code.store'), ['access_code' => ''])
            ->assertSessionHasErrors('access_code');
    }

    /**
     * Where the visitor was going is where the code takes them.
     */
    public function test_the_requested_screen_is_returned_to_after_the_code(): void
    {
        $this->get('/signup')->assertRedirect(route('access-code.show'));

        $this->post(route('access-code.store'), ['access_code' => '2000'])
            ->assertRedirect(route('register'));
    }

    public function test_password_reset_is_not_behind_the_gate(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    /**
     * A signed-in person is past the question the gate asks.
     */
    public function test_the_gate_survives_signing_out(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-logout']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'email_verified_at' => now(),
        ]);

        $this->assertGateOpened(
            $this->post(route('access-code.store'), ['access_code' => '3000'])
        );

        /* Logging out invalidates the session. Someone who has been inside the
           product should come back to the login form, not to the door. */
        $this->actingAs($user)->post('/logout');

        $this->get('/login')->assertOk();
    }

    public function test_a_signed_in_user_is_not_sent_to_the_gate(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-gate']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get('/login')->assertRedirect();
    }
}
