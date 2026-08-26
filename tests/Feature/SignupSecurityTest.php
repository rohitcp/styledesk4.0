<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sign-up rules from sections 2-5 and 19 of the requirements.
 */
class SignupSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'rohit@styledesk.test',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
            'terms' => '1',
        ], $overrides);
    }

    public static function weakPasswordProvider(): array
    {
        return [
            'too short' => ['Ab1!x'],
            'no uppercase' => ['str0ng!pass'],
            'no lowercase' => ['STR0NG!PASS'],
            'no number' => ['Strong!Pass'],
            'no symbol' => ['Str0ngPass1'],
        ];
    }

    #[DataProvider('weakPasswordProvider')]
    public function test_password_must_meet_every_stated_rule(string $password): void
    {
        // The form shows the user a checklist of these five conditions, so the
        // server has to reject anything the checklist would mark as failing.
        $this->post('http://styledesk.test/signup', $this->payload([
            'password' => $password,
            'password_confirmation' => $password,
        ]))->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_a_duplicate_email_is_refused_with_a_route_back_to_login(): void
    {
        User::create($this->payload(['email' => 'taken@styledesk.test']));

        $this->post('http://styledesk.test/signup', $this->payload(['email' => 'taken@styledesk.test']))
            ->assertSessionHasErrors(['email' => 'An account already exists with this email address.']);

        $this->assertSame(1, User::count(), 'A duplicate address must never create a second account.');

        // The sign-up page keys its "Log in instead" panel off that wording.
        // from() matters: a validation failure redirects back to where the
        // request came from, and without it that is the marketing page.
        $this->from(route('register'))
            ->followingRedirects()
            ->post('http://styledesk.test/signup', $this->payload(['email' => 'taken@styledesk.test']))
            ->assertSee('Log in instead');
    }

    public function test_email_is_normalized_so_the_same_inbox_cannot_register_twice(): void
    {
        $this->post('http://styledesk.test/signup', $this->payload(['email' => 'Rohit@StyleDesk.test']));

        $this->assertSame('rohit@styledesk.test', User::first()->email);

        // Registering signs the user in, and the sign-up route is guest-only,
        // so a second attempt would be redirected before it was ever validated.
        auth()->logout();

        // Without normalization the unique index would happily accept this.
        $this->post('http://styledesk.test/signup', $this->payload(['email' => 'ROHIT@styledesk.TEST']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_registering_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post('http://styledesk.test/signup', $this->payload());

        Notification::assertSentTo(User::first(), VerifyEmail::class);
    }

    public function test_an_unverified_user_cannot_reach_the_app_or_the_wizard(): void
    {
        $this->post('http://styledesk.test/signup', $this->payload());

        $this->get('http://styledesk.test/dashboard')->assertRedirect(route('verification.notice'));
        $this->get('http://styledesk.test/onboarding/business')->assertRedirect(route('verification.notice'));
    }

    public function test_a_mistyped_address_can_be_corrected_before_verifying(): void
    {
        $this->post('http://styledesk.test/signup', $this->payload(['email' => 'typo@styledesk.test']));

        Notification::fake();

        $this->patch('http://styledesk.test/email/verify/update', ['email' => 'Correct@styledesk.test'])
            ->assertRedirect();

        $user = User::first();

        $this->assertSame('correct@styledesk.test', $user->email);
        $this->assertNull($user->email_verified_at, 'Changing the address must not carry verification across.');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signup_is_reachable_at_the_specified_url(): void
    {
        $this->get('http://styledesk.test/signup')
            ->assertOk()
            ->assertSee('Create your StyleDesk account');
    }
}
