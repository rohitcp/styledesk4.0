<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Change Password: the checks, and what happens to the other sessions.
 */
class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Pass';

    private const NEW_PASSWORD = 'Even5tr0nger!';

    private function member(): User
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-pw']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'password' => self::PASSWORD,
        ]);

        $tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    public function test_the_screen_opens(): void
    {
        $this->actingAs($this->member())->get(route('account.password'))->assertOk();
    }

    /**
     * The card ships open, and the script closes it.
     *
     * That pairing is what a reader with no JavaScript gets, and asserting it
     * pins the fallback: if the markup ever ships closed instead, somebody
     * without scripts cannot change their password at all.
     */
    public function test_the_card_ships_open_with_a_summary_to_close_onto(): void
    {
        $response = $this->actingAs($this->member())
            ->get(route('account.password'))
            ->assertOk();

        $response->assertSee('data-editing="false"', false);
        $response->assertSee('data-editable-view hidden', false);
        $response->assertSee('data-editable-edit hidden', false);
        $response->assertSee('id="passwordFields"', false);

        /* Without the partial the markup is a form that never closes, which
           is exactly how this shipped once. */
        $response->assertSee("querySelectorAll('[data-editable-card]')", false);
    }

    /** A refusal reopens the card, because the messages are attached to it. */
    public function test_a_refused_attempt_leaves_the_card_open(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->from(route('account.password'))
            ->put(route('account.password.update'), [
                'current_password' => 'not-my-password',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertRedirect(route('account.password'));

        $this->actingAs($user)
            ->get(route('account.password'))
            ->assertOk()
            ->assertSee('data-editing="true"', false);
    }

    public function test_the_password_is_changed(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => self::PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertRedirect(route('account.password'))
            ->assertSessionHas('toast');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    /**
     * The tab it was changed in stays signed in.
     *
     * Being signed out of the page you just used reads as the change having
     * failed, which is the opposite of what happened.
     */
    public function test_this_session_survives_the_change(): void
    {
        $user = $this->member();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => self::PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            'logout_other_devices' => '1',
        ]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_wrong_current_password_is_refused(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->from(route('account.password'))
            ->put(route('account.password.update'), [
                'current_password' => 'not-it',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_the_new_password_cannot_be_the_current_one(): void
    {
        $this->actingAs($this->member())
            ->from(route('account.password'))
            ->put(route('account.password.update'), [
                'current_password' => self::PASSWORD,
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_the_confirmation_must_match(): void
    {
        $this->actingAs($this->member())
            ->from(route('account.password'))
            ->put(route('account.password.update'), [
                'current_password' => self::PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => 'something-else',
            ])
            ->assertSessionHasErrors('password');
    }

    /**
     * The same rules the sign-up form shows and the server already enforces:
     * eight characters, mixed case, a number and a symbol.
     */
    public function test_a_weak_password_is_refused(): void
    {
        $user = $this->member();

        foreach (['short1!A', 'alllowercase1!', 'ALLUPPERCASE1!', 'NoNumbers!!', 'NoSymbols123'] as $weak) {
            $response = $this->actingAs($user)
                ->from(route('account.password'))
                ->put(route('account.password.update'), [
                    'current_password' => self::PASSWORD,
                    'password' => $weak,
                    'password_confirmation' => $weak,
                ]);

            /* 'short1!A' is eight characters and satisfies everything else, so
               it is the control: it must pass, and is put back afterwards. */
            if ($weak === 'short1!A') {
                $response->assertSessionHasNoErrors();
                $user->forceFill(['password' => Hash::make(self::PASSWORD)])->save();

                continue;
            }

            $response->assertSessionHasErrors('password');
        }
    }
}
