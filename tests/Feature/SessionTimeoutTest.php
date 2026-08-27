<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionTimeout;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function signedInUser(): User
    {
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $tenant->getTenantKey()])->save();
        $tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    // ------------------------------------------------------------ sign out

    public function test_signing_out_lands_on_the_login_page(): void
    {
        // Not '/': someone who has just signed out is being shown the front
        // door of a product they were already inside.
        $this->actingAs($this->signedInUser())
            ->post('http://styledesk.test/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_shell_carries_a_real_logout_form(): void
    {
        // The account menu is built by script and has no form of its own, so
        // without this its Sign out only ever showed a toast.
        $this->actingAs($this->signedInUser())
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('id="sd-logout-form"', false)
            ->assertSee('onSignOut', false);
    }

    // ------------------------------------------------------------- timeout

    public function test_an_idle_session_is_ended_by_the_server(): void
    {
        $user = $this->signedInUser();

        $this->actingAs($user)->get('http://styledesk.test/dashboard')->assertOk();

        // Past the limit. The browser's cookie is not what decides this.
        $this->travel(config('session.idle_timeout') + 1)->minutes();

        $this->get('http://styledesk.test/dashboard')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_activity_inside_the_window_keeps_the_session(): void
    {
        $user = $this->signedInUser();

        $this->actingAs($user)->get('http://styledesk.test/dashboard')->assertOk();

        // Two visits, each inside the window, totalling more than it. The
        // timeout is idleness, not session age.
        $this->travel(config('session.idle_timeout') - 5)->minutes();
        $this->get('http://styledesk.test/dashboard')->assertOk();

        $this->travel(config('session.idle_timeout') - 5)->minutes();
        $this->get('http://styledesk.test/dashboard')->assertOk();

        $this->assertAuthenticated();
    }

    public function test_the_login_screen_says_the_session_timed_out(): void
    {
        $user = $this->signedInUser();

        $this->actingAs($user)->get('http://styledesk.test/dashboard');
        $this->travel(config('session.idle_timeout') + 1)->minutes();
        $this->get('http://styledesk.test/dashboard');

        $this->get('http://styledesk.test/login')
            ->assertOk()
            ->assertSee('Your session timed out');
    }

    /**
     * A redirect answered to fetch() is parsed as data, and the caller
     * reports a JSON error rather than an expiry.
     */
    public function test_an_expired_json_request_gets_a_401_not_a_redirect(): void
    {
        $user = $this->signedInUser();

        $this->actingAs($user)->get('http://styledesk.test/dashboard');
        $this->travel(config('session.idle_timeout') + 1)->minutes();

        $this->getJson('http://styledesk.test/dashboard')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Your session timed out. Please sign in again.')
            ->assertJsonPath('redirect', route('login'));
    }

    public function test_keeping_alive_extends_the_session(): void
    {
        $user = $this->signedInUser();

        $this->actingAs($user)->get('http://styledesk.test/dashboard')->assertOk();

        $this->travel(config('session.idle_timeout') - 1)->minutes();
        $this->postJson('http://styledesk.test/session/keep-alive')->assertOk();

        // Without the keep-alive this next visit would be past the limit.
        $this->travel(config('session.idle_timeout') - 1)->minutes();
        $this->get('http://styledesk.test/dashboard')->assertOk();
    }

    public function test_the_warning_is_shown_to_signed_in_users_only(): void
    {
        $this->actingAs($this->signedInUser())
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('id="sd-timeout"', false)
            ->assertSee('Still there?');

        // A guest has no session to lose. Signed out first, or Fortify
        // redirects an authenticated visitor away from the login screen and
        // the assertion measures the wrong page.
        Auth::logout();
        $this->app['auth']->forgetGuards();

        $this->get('http://styledesk.test/login')
            ->assertOk()
            ->assertDontSee('id="sd-timeout"', false);
    }

    public function test_the_countdown_uses_the_configured_lifetime(): void
    {
        // One source, so the tab and the server cannot disagree about when a
        // session ends.
        $this->assertSame(config('session.idle_timeout') * 60, EnforceSessionTimeout::timeoutSeconds());

        $this->actingAs($this->signedInUser())
            ->get('http://styledesk.test/dashboard')
            ->assertSee((string) (config('session.idle_timeout') * 60 * 1000), false);
    }
}
