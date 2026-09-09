<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmail;
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

    /**
     * The sign-up form and the client form validate through the same module.
     *
     * The rules used to be a hand-written list inside this page's script. A
     * second form copying that list would have been a second set of messages
     * to keep in step with the server's, so the rules moved onto the fields
     * and the list became shared code — asserted here because the markup is
     * what connects the two.
     */
    public function test_the_form_uses_the_shared_live_validation(): void
    {
        $this->get('http://styledesk.test/signup')
            ->assertOk()
            ->assertSee('data-validate-form', false)
            ->assertSee('data-rules="required|max:100"', false)
            ->assertSee('data-rules="required|email|max:255"', false)
            /* Where the label cannot phrase the message — a consent checkbox
               whose label is a whole sentence — the field says the words. */
            ->assertSee('data-message-required="You must agree', false);
    }

    /**
     * A refused submission gives the form back with the answers in it.
     *
     * Missing the checkbox is the commonest way to fail this form, and
     * re-typing five fields because of one unticked box is the difference
     * between a signup somebody finishes and one they abandon.
     */
    public function test_a_refused_submission_keeps_everything_that_was_typed(): void
    {
        $response = $this->from('http://styledesk.test/signup')
            ->post('http://styledesk.test/signup', $this->payload(['terms' => null]));

        $response->assertRedirect('http://styledesk.test/signup')
            ->assertSessionHasErrors('terms');

        /* Everything but the passwords comes back. */
        $response->assertSessionHasInput('first_name', 'Rohit')
            ->assertSessionHasInput('last_name', 'Philip')
            ->assertSessionHasInput('email', 'rohit@styledesk.test');

        /* And the page renders them, rather than merely holding them. */
        $this->followingRedirects()
            ->from('http://styledesk.test/signup')
            ->post('http://styledesk.test/signup', $this->payload(['terms' => null]))
            ->assertOk()
            ->assertSee('value="Rohit"', false)
            ->assertSee('value="Philip"', false)
            ->assertSee('value="rohit@styledesk.test"', false);
    }

    /**
     * The password is the one thing that does not come back.
     *
     * Repopulating it would put the plaintext into the page source, the
     * browser's cache and any proxy between them — a worse trade than asking
     * for eleven characters again.
     */
    public function test_the_password_is_never_returned_to_the_page(): void
    {
        $this->followingRedirects()
            ->from('http://styledesk.test/signup')
            ->post('http://styledesk.test/signup', $this->payload(['terms' => null]))
            ->assertOk()
            ->assertDontSee('Str0ng!Pass');
    }

    /** Each refusal says which answer it wanted, in the words the label used. */
    public function test_each_required_field_is_named_plainly(): void
    {
        $this->from('http://styledesk.test/signup')
            ->post('http://styledesk.test/signup', [
                'first_name' => '', 'last_name' => '', 'email' => 'not-an-email',
                'password' => 'x', 'password_confirmation' => 'x',
            ])
            ->assertSessionHasErrors([
                'first_name' => 'First name is required.',
                'last_name' => 'Last name is required.',
                'email' => 'Enter a valid email address.',
                'terms' => 'You must agree to the Terms of Service and Privacy Policy to continue.',
            ]);
    }

    /**
     * The two auth pages point at each other from the same corner.
     */
    public function test_signup_offers_a_way_back_to_logging_in(): void
    {
        $this->get('http://styledesk.test/signup')
            ->assertOk()
            ->assertSee('Already have an account?')
            ->assertSee('>Log in</a>', false);
    }

    public function test_login_offers_a_way_to_create_an_account(): void
    {
        $this->get('http://styledesk.test/login')
            ->assertOk()
            ->assertSee('New to StyleDesk?')
            ->assertSee('>Create account</a>', false);
    }

    /**
     * Account mail comes from the account address, on the shared template.
     *
     * Its own sender rather than the general one: verification and password
     * resets are the mail a reader is most likely to check the sender of.
     */
    public function test_the_verification_email_is_branded_and_from_the_account_address(): void
    {
        Notification::fake();

        $this->post('http://styledesk.test/signup', $this->payload());

        $user = User::where('email', 'rohit@styledesk.test')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
            $mail = $notification->toMail($user);
            $rendered = $mail->render();

            /* The sender, the subject and the copy the specification asks
               for — and the shared layout underneath, which is what stops
               these drifting into five different-looking emails. */
            /* The address itself, not config read back at itself: a test
               that asserts config against config passes on any value. */
            $this->assertSame(['account@styledesk.dev', 'StyleDesk'], $mail->from);
            $this->assertSame('Verify your email to get started with StyleDesk', $mail->subject);

            $this->assertStringContainsString('You’re almost there', $rendered);
            $this->assertStringContainsString('Hi Rohit,', $rendered);
            $this->assertStringContainsString('Verify Email Address', $rendered);
            $this->assertStringContainsString('styledesk-logo.svg', $rendered);
            $this->assertStringContainsString('StyleDesk will never ask you to send your password by email.', $rendered);

            return true;
        });
    }

    public function test_signup_is_reachable_at_the_specified_url(): void
    {
        $this->get('http://styledesk.test/signup')
            ->assertOk()
            ->assertSee('Create your StyleDesk account');
    }
}
