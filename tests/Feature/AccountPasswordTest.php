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
