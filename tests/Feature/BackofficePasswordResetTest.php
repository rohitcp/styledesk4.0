<?php

namespace Tests\Feature;

use App\Mail\BackofficePasswordResetMail;
use App\Models\BackofficeAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Forgotten passwords on the platform console.
 *
 * The console has its own guard, its own broker and its own token table, and
 * these are the tests that keep it that way. The bug they were written for:
 * the mailed link pointed at the salon application's reset screen, because
 * the inherited notification builds its URL from `route('password.reset')`.
 */
class BackofficePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): BackofficeAdmin
    {
        return BackofficeAdmin::query()->create($attributes + [
            'name' => 'Rohit Philip',
            'email' => 'admin@styledesk.test',
            'password' => 'Str0ng!Passw0rd!',
            'role' => 'super-owner',
            'status' => BackofficeAdmin::STATUS_ACTIVE,
        ]);
    }

    public function test_the_mailed_link_points_at_the_console_not_the_salon_application(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post(route('backoffice.password.email'), ['email' => $admin->email]);

        Mail::assertSent(BackofficePasswordResetMail::class, function ($mail) use ($admin) {
            $this->assertStringContainsString('/backoffice/reset-password/', $mail->resetUrl);
            $this->assertStringNotContainsString('/reset-password/', str_replace('/backoffice/reset-password/', '', $mail->resetUrl));

            return $mail->hasTo($admin->email);
        });
    }

    public function test_the_link_is_sent_immediately_rather_than_queued(): void
    {
        Mail::fake();
        $this->admin();

        $this->post(route('backoffice.password.email'), ['email' => 'admin@styledesk.test']);

        Mail::assertSent(BackofficePasswordResetMail::class);
        Mail::assertNotQueued(BackofficePasswordResetMail::class);
    }

    public function test_an_administrator_can_reset_and_then_sign_in_with_the_new_password(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post(route('backoffice.password.email'), ['email' => $admin->email]);

        $token = null;
        Mail::assertSent(BackofficePasswordResetMail::class, function ($mail) use (&$token) {
            $token = str($mail->resetUrl)->after('/backoffice/reset-password/')->before('?')->toString();

            return true;
        });

        $this->get(route('backoffice.password.reset', ['token' => $token, 'email' => $admin->email]))
            ->assertOk()
            ->assertSee($token, false);

        $this->post(route('backoffice.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'An0ther!Str0ng!Pass',
            'password_confirmation' => 'An0ther!Str0ng!Pass',
        ])->assertRedirect(route('backoffice.verify.email'));

        $this->assertTrue(Hash::check('An0ther!Str0ng!Pass', $admin->fresh()->password));
    }

    public function test_tokens_are_kept_out_of_the_salon_applications_table(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post(route('backoffice.password.email'), ['email' => $admin->email]);

        $this->assertDatabaseCount('backoffice_password_reset_tokens', 1);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    /**
     * The reason the console has a table of its own: Laravel's repository keys
     * on the address alone, so one shared table would let a salon owner and an
     * administrator with the same address redeem each other's links.
     */
    public function test_a_salon_users_reset_token_cannot_be_redeemed_against_the_console(): void
    {
        Mail::fake();
        $shared = 'shared@styledesk.test';

        $admin = $this->admin(['email' => $shared]);
        User::factory()->create(['email' => $shared]);

        $salonToken = Password::broker('users')->createToken(User::query()->where('email', $shared)->first());

        $this->post(route('backoffice.password.update'), [
            'token' => $salonToken,
            'email' => $shared,
            'password' => 'An0ther!Str0ng!Pass',
            'password_confirmation' => 'An0ther!Str0ng!Pass',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Str0ng!Passw0rd!', $admin->fresh()->password));
        $this->assertSame(0, DB::table('backoffice_password_reset_tokens')->count());
    }

    /** An address nobody has must look exactly like one somebody does. */
    public function test_an_unknown_address_gets_the_same_answer_and_no_mail(): void
    {
        Mail::fake();

        $this->from(route('backoffice.password.request'))
            ->post(route('backoffice.password.email'), ['email' => 'nobody@styledesk.test'])
            ->assertRedirect(route('backoffice.password.request'))
            ->assertSessionHas('status', __('backoffice.auth.reset_sent'));

        Mail::assertNothingSent();
    }
}
