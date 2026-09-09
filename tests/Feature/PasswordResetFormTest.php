<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * The "choose a new password" screen.
 *
 * The address it is about is shown rather than hidden — somebody with two
 * StyleDesk logins, or following a link out of an old email, has no other way
 * of knowing which account they are about to change the password on.
 */
class PasswordResetFormTest extends TestCase
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

    public function test_the_address_is_shown_and_cannot_be_edited(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        $response = $this->get('http://styledesk.test/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertOk()
            ->assertSee('Email address');

        $html = $response->getContent();

        /* Visible, holding the address, and refusing to be typed in. */
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="email"[^>]*>/',
            $html,
        );
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringContainsString('value="'.$user->email.'"', $html);

        /* And not also present as a hidden input: two fields with one name is
           a form whose posted value depends on document order. */
        $this->assertStringNotContainsString(
            '<input type="hidden" name="email"',
            $html,
        );
    }

    public function test_the_shown_address_is_still_what_gets_posted(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        /* readonly, not disabled — a disabled field is left out of the
           submitted data, and the reset would fail for want of an address. */
        $this->post('http://styledesk.test/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'An0ther!Pass',
            'password_confirmation' => 'An0ther!Pass',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(
            auth()->validate(['email' => $user->email, 'password' => 'An0ther!Pass']),
            'The new password must be the one now on the account.',
        );
    }
}
