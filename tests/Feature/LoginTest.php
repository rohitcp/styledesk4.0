<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Signing in: the paths through the form, and what each of them says.
 *
 * A refusal that says nothing is the same to the reader as a site that is
 * broken, so every path here is asserted on the message as well as on where
 * it lands.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Pass';

    protected function setUp(): void
    {
        parent::setUp();

        /* The limiter is keyed by address and IP and outlives a test, so a
           test that exercises throttling would otherwise poison the next. */
        RateLimiter::clear('owner@styledesk.test|127.0.0.1');
        RateLimiter::clear('ghost@example.com|127.0.0.1');
    }

    private function owner(bool $verified = true): User
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => self::PASSWORD,
        ]);

        if ($verified) {
            $user->markEmailAsVerified();
        }

        $user->forceFill(['tenant_id' => $tenant->getTenantKey()])->save();
        $tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    private function attempt(array $payload)
    {
        return $this->from(route('login'))->post('http://styledesk.test/login', $payload);
    }

    // ------------------------------------------------------------ it works

    public function test_the_right_credentials_sign_the_user_in(): void
    {
        $user = $this->owner();

        $this->attempt(['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect('http://styledesk.test/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_leaves_a_cookie_behind(): void
    {
        $user = $this->owner();

        $response = $this->attempt([
            'email' => $user->email, 'password' => self::PASSWORD, 'remember' => '1',
        ]);

        $this->assertTrue(
            collect($response->headers->getCookies())
                ->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_')),
            'Remember me must issue the cookie that outlives the session.',
        );
    }

    public function test_an_unverified_account_signs_in_but_stops_at_verification(): void
    {
        $user = $this->owner(verified: false);

        $this->attempt(['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect('http://styledesk.test/dashboard');

        $this->get('http://styledesk.test/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    // ------------------------------------------------------------ refusals

    public function test_a_wrong_password_is_refused_with_a_message(): void
    {
        $user = $this->owner();

        $this->attempt(['email' => $user->email, 'password' => 'not-it'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_an_unknown_address_is_refused_in_the_same_words(): void
    {
        $this->owner();

        /* Telling the two apart would tell a stranger which addresses hold
           accounts here. */
        $this->attempt(['email' => 'ghost@example.com', 'password' => 'not-it'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_the_address_comes_back_but_the_password_does_not(): void
    {
        $user = $this->owner();

        $this->attempt(['email' => $user->email, 'password' => 'not-it'])
            ->assertSessionHasInput('email', $user->email);

        $this->followingRedirects()
            ->from(route('login'))
            ->post('http://styledesk.test/login', ['email' => $user->email, 'password' => 'not-it'])
            ->assertDontSee('not-it');
    }

    public function test_an_empty_form_is_refused_on_both_fields(): void
    {
        $this->attempt(['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);
    }

    // ---------------------------------------------------------- throttling

    public function test_too_many_attempts_returns_to_the_form_with_an_explanation(): void
    {
        $user = $this->owner();

        for ($i = 0; $i < 5; $i++) {
            $this->attempt(['email' => $user->email, 'password' => 'wrong'.$i]);
        }

        /* A redirect back to the form, not the framework's 429 page: that
           page is a dead end with no way back and no indication that waiting
           will fix it. */
        $this->attempt(['email' => $user->email, 'password' => 'wrong-again'])
            /* The path, not the whole URL: the scheme the test environment
               builds URLs with is not what this is about. */
            ->assertRedirectContains('/login')
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email', $user->email);

        $this->assertStringContainsString(
            'Too many login attempts',
            (string) session('errors')->getBag('default')->first('email'),
        );
    }

    public function test_the_throttle_message_reaches_the_page(): void
    {
        $user = $this->owner();

        for ($i = 0; $i < 5; $i++) {
            $this->attempt(['email' => $user->email, 'password' => 'wrong'.$i]);
        }

        $this->followingRedirects()
            ->from(route('login'))
            ->post('http://styledesk.test/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertOk()
            ->assertSee('Too many login attempts', false)
            /* And it is drawn as a refusal, not as an error page. */
            ->assertSee('sd-alert--danger', false);
    }

    // ---------------------------------------------------- live validation

    public function test_the_form_validates_the_way_the_sign_up_form_does(): void
    {
        $this->get('http://styledesk.test/login')
            ->assertOk()
            ->assertSee('data-validate-form', false)
            ->assertSee('data-rules="required|email"', false)
            ->assertSee('data-rules="required"', false)
            ->assertSee('data-message-required="Email address is required."', false)
            /* A box for each field, addressed by id — what SD.setError writes
               into, and what the server's own message renders in. */
            ->assertSee('data-error-for="email"', false)
            ->assertSee('data-error-for="password"', false);
    }

    // ------------------------------------------------- what the page shows

    public function test_a_refusal_reaches_the_page_and_keeps_the_address(): void
    {
        $user = $this->owner();

        $page = $this->followingRedirects()
            ->from(route('login'))
            ->post('http://styledesk.test/login', ['email' => $user->email, 'password' => 'not-it']);

        /* The whole point of the bug this covers: a failed sign-in must never
           be a silent reload of the form. */
        $page->assertOk()
            ->assertSee('Incorrect email or password. Please try again.', false)
            ->assertSee('sd-alert--danger', false)
            /* Typed once, not twice. */
            ->assertSee('value="'.$user->email.'"', false)
            /* Both fields carry the standard error styling — the server
               cannot say which of the two is wrong. */
            ->assertSee('aria-invalid="true"', false);

        /* Said once. Under a field as well as at the top would be the same
           sentence twice on one screen. */
        $this->assertSame(
            1,
            substr_count($page->getContent(), 'Incorrect email or password'),
            'The refusal must be stated once, in the alert at the top.',
        );
    }

    public function test_the_button_cannot_be_clicked_twice(): void
    {
        $this->get('http://styledesk.test/login')
            ->assertSee('data-submit-once', false)
            ->assertSee('data-busy-label="Signing in', false);
    }

    // ------------------------------------------------------- broken, not refused

    public function test_an_unexpected_failure_is_logged_and_explained(): void
    {
        $this->owner();

        Log::spy();

        /* Something inside the attempt throwing — a mail driver, a listener,
           a database that has gone away. Attempting fires once the address is
           known and before anyone is signed in, so this stands for a failure
           partway through an otherwise valid sign-in. */
        Event::listen(Attempting::class, function (): void {
            throw new \RuntimeException('the database went away');
        });

        $page = $this->followingRedirects()
            ->from(route('login'))
            ->post('http://styledesk.test/login', [
                'email' => 'owner@styledesk.test', 'password' => self::PASSWORD,
            ]);

        $page->assertOk()
            /* Escaped, because Blade escapes the apostrophe. */
            ->assertSee("We couldn't sign you in right now. Please try again.")
            ->assertSee('sd-alert--danger', false)
            /* A fault on our side must not also cost the reader what they typed. */
            ->assertSee('value="owner@styledesk.test"', false);

        $this->assertGuest();

        /* The technical detail is not on the page — it is in the log, with
           its stack trace, which is where it is of use to somebody. */
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message) => str_contains($message, 'the database went away'))
            ->once();
    }
}
