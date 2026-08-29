<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The screen between sign-up and onboarding.
 *
 * Every test drives the routes rather than the model, because what is being
 * asserted is the flow a new business actually walks: sign up, verify by code
 * or by link, and land in the wizard.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedUser(array $overrides = []): User
    {
        return User::factory()->unverified()->create($overrides);
    }

    /**
     * The code is only ever returned by the thing that issues it, so a test
     * that wants to type it has to mint it the same way the mailer does.
     */
    private function issueCode(User $user): string
    {
        return $user->issueEmailVerificationCode();
    }

    public function test_signing_up_lands_on_the_verification_screen(): void
    {
        $this->post('http://styledesk.test/signup', [
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'rohit@styledesk.test',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
            'terms' => '1',
        ]);

        $this->followingRedirects()
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('Verify your email')
            ->assertSee('rohit@styledesk.test')
            ->assertSee('Confirmation Code')
            ->assertSee('Verify Account');
    }

    public function test_the_right_code_verifies_the_account_and_opens_onboarding(): void
    {
        Event::fake([Verified::class]);

        $user = $this->unverifiedUser();
        $code = $this->issueCode($user);

        $this->actingAs($user)
            ->post('http://styledesk.test/email/verify/code', ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);

        /* The wizard is what the dashboard hands them to, and it is now
           reachable — which is the whole point of verifying. */
        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/dashboard')
            ->assertRedirect(route('onboarding.business'));
    }

    public function test_the_code_is_retired_once_it_has_been_used(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCode($user);

        $this->actingAs($user)->post('http://styledesk.test/email/verify/code', ['code' => $code]);

        $user->refresh();

        $this->assertNull($user->email_verification_code);
        $this->assertNull($user->email_verification_code_expires_at);
    }

    public function test_a_wrong_code_is_refused_with_a_message(): void
    {
        $user = $this->unverifiedUser();
        $this->issueCode($user);

        $this->from(route('verification.notice'))
            ->actingAs($user)
            ->post('http://styledesk.test/email/verify/code', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCode($user);

        $this->travel(config('auth.verification.expire') + 1)->minutes();

        $this->from(route('verification.notice'))
            ->actingAs($user)
            ->post('http://styledesk.test/email/verify/code', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_resent_code_replaces_the_one_before_it(): void
    {
        $user = $this->unverifiedUser();
        $first = $this->issueCode($user);
        $second = $this->issueCode($user->fresh());

        $this->from(route('verification.notice'))
            ->actingAs($user->fresh())
            ->post('http://styledesk.test/email/verify/code', ['code' => $first])
            ->assertSessionHasErrors('code');

        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/email/verify/code', ['code' => $second])
            ->assertRedirect(route('dashboard'));
    }

    public function test_resending_sends_another_email_and_says_so(): void
    {
        Notification::fake();

        $user = $this->unverifiedUser();

        $this->from(route('verification.notice'))
            ->actingAs($user)
            ->post('http://styledesk.test/email/verify/resend')
            ->assertRedirectContains('/email/verify')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_second_resend_is_refused_until_the_cooldown_has_passed(): void
    {
        Notification::fake();

        $user = $this->unverifiedUser();

        $this->from(route('verification.notice'))->actingAs($user)
            ->post('http://styledesk.test/email/verify/resend');

        $this->from(route('verification.notice'))->actingAs($user)
            ->post('http://styledesk.test/email/verify/resend')
            ->assertSessionHasErrors('resend');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        /* And it is a wait, not a refusal: the same request works once the
           cooldown has run out. */
        $this->travel(31)->seconds();

        $this->from(route('verification.notice'))->actingAs($user)
            ->post('http://styledesk.test/email/verify/resend')
            ->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    public function test_the_page_shows_the_remaining_cooldown(): void
    {
        Notification::fake();

        $user = $this->unverifiedUser();

        $this->actingAs($user)->post('http://styledesk.test/email/verify/resend');

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Resend available in 30 seconds');
    }

    public function test_changing_the_address_re_sends_and_keeps_the_reader_on_the_screen(): void
    {
        Notification::fake();

        $user = $this->unverifiedUser(['email' => 'typo@styledesk.test']);

        $this->from(route('verification.notice'))
            ->actingAs($user)
            ->patch('http://styledesk.test/email/verify/update', ['email' => 'Correct@styledesk.test'])
            /* The path, not the whole URL: the scheme the test environment
               builds URLs with is not what this is about. */
            ->assertRedirectContains('/email/verify')
            ->assertSessionHas('status');

        $this->assertSame('correct@styledesk.test', $user->fresh()->email);
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    }

    public function test_an_address_that_is_already_registered_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@styledesk.test']);
        $user = $this->unverifiedUser(['email' => 'mine@styledesk.test']);

        $this->from(route('verification.notice'))
            ->actingAs($user)
            ->patch('http://styledesk.test/email/verify/update', ['email' => 'taken@styledesk.test'])
            ->assertSessionHasErrors('email');

        $this->assertSame('mine@styledesk.test', $user->fresh()->email);
    }

    public function test_the_link_verifies_and_signs_in_a_reader_who_is_not_logged_in(): void
    {
        $user = $this->unverifiedUser();

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_an_expired_link_offers_a_new_one_instead_of_an_error_page(): void
    {
        $user = $this->unverifiedUser();
        $url = $this->verificationUrl($user);

        $this->travel(config('auth.verification.expire') + 1)->minutes();

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('link');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_link_whose_signature_was_tampered_with_is_refused(): void
    {
        $user = $this->unverifiedUser();

        $this->actingAs($user)
            ->get($this->verificationUrl($user).'x')
            ->assertRedirect(route('verification.notice'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_link_stops_working_once_the_address_has_changed(): void
    {
        $user = $this->unverifiedUser(['email' => 'first@styledesk.test']);
        $url = $this->verificationUrl($user);

        $user->forceFill(['email' => 'second@styledesk.test'])->save();

        $this->actingAs($user)->get($url)->assertRedirect(route('verification.notice'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_verified_account_is_sent_to_onboarding_rather_than_the_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Tell us about your business');
    }

    public function test_onboarding_stays_shut_until_the_address_is_verified(): void
    {
        $user = $this->unverifiedUser();

        $this->actingAs($user)
            ->get('http://styledesk.test/onboarding/business')
            ->assertRedirect(route('verification.notice'));
    }

    /** The link exactly as the notification builds it. */
    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire')),
            ['id' => $user->getKey(), 'hash' => sha1((string) $user->getEmailForVerification())]
        );
    }
}
