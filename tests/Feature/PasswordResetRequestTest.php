<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Asking for a password reset link.
 *
 * What the reader is told, and — just as much — what they are not told about
 * whose addresses hold accounts here.
 */
class PasswordResetRequestTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();

        return $user->fresh();
    }

    public function test_the_page_says_what_to_do_next_once_the_link_is_sent(): void
    {
        Notification::fake();

        $user = $this->user();

        /* One submission, then read the page it lands on. Asking twice would
           be answered by the broker's own throttle rather than by the thing
           this test is about. */
        $this->followingRedirects()
            ->from(route('password.request'))
            ->post('http://styledesk.test/forgot-password', ['email' => $user->email])
            ->assertOk()
            /* The message itself, and the advice that follows it — the reader
               is told to go and read an email, not merely that something
               happened. */
            ->assertSee('Follow the instructions in that email to set a new password.', false)
            ->assertSee('check your spam folder', false);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_advice_is_not_shown_before_anything_has_been_sent(): void
    {
        $this->get('http://styledesk.test/forgot-password')
            ->assertOk()
            ->assertDontSee('check your spam folder', false);
    }

    public function test_an_address_with_no_account_is_answered_exactly_like_one_with(): void
    {
        Notification::fake();

        $this->user();

        /* Refusing an unknown address would turn this form into a way of
           asking which addresses hold StyleDesk accounts, one guess at a
           time. Same status, no errors, either way. */
        $this->from(route('password.request'))
            ->post('http://styledesk.test/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_the_two_answers_are_indistinguishable_on_the_page(): void
    {
        Notification::fake();

        $user = $this->user();

        $known = $this->followingRedirects()->from(route('password.request'))
            ->post('http://styledesk.test/forgot-password', ['email' => $user->email]);

        $unknown = $this->followingRedirects()->from(route('password.request'))
            ->post('http://styledesk.test/forgot-password', ['email' => 'nobody@example.com']);

        foreach ([$known, $unknown] as $response) {
            $response->assertOk()
                ->assertSee('sd-alert--success', false)
                /* Not merely the same words: the same colour. A green panel
                   for one address and a red one for the other says which is
                   which without a word. */
                ->assertDontSee('sd-alert--danger', false);
        }
    }

    public function test_a_malformed_address_is_still_refused(): void
    {
        /* Hiding whether an account exists is not the same as accepting
           anything: "not an address" is a fact about what was typed. */
        $this->from(route('password.request'))
            ->post('http://styledesk.test/forgot-password', ['email' => 'not-an-address'])
            ->assertSessionHasErrors('email');
    }

    public function test_the_form_validates_in_the_page_rather_than_in_a_browser_bubble(): void
    {
        $page = $this->get(route('password.request'));

        /* The rules the shared live validation reads, so a missing address is
           refused beneath the field in the words the app chose. */
        $page->assertSee('data-validate-form', false);
        $page->assertSee('data-rules="required|email"', false);
    }
}
