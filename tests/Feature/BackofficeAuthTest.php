<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateBackoffice;
use App\Mail\BackofficeVerificationCodeMail;
use App\Models\BackofficeAdmin;
use App\Models\BackofficeAuditLog;
use App\Models\BackofficeLoginCode;
use App\Models\User;
use App\Support\BackofficeVerification;
use Database\Seeders\BackofficeSuperOwnerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Getting into the platform console.
 *
 * The rule most of these are about: an address that is not an administrator's
 * must be indistinguishable from one that is. Every screen, every message and
 * every redirect is the same either way — an endpoint that answers otherwise
 * is a staff list handed out one guess at a time.
 */
class BackofficeAuthTest extends TestCase
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

    /** Sign in as far as the password screen, and answer the code on the way. */
    private function verified(BackofficeAdmin $admin): void
    {
        $this->post(route('backoffice.verify.send'), ['email' => $admin->email]);

        $this->post(route('backoffice.verify.check'), ['code' => $this->codeSentTo($admin->email)]);
    }

    /** The code that was mailed, read back out of the mailable. */
    private function codeSentTo(string $email): string
    {
        $code = null;

        Mail::assertSent(BackofficeVerificationCodeMail::class, function ($mail) use (&$code, $email) {
            if ($mail->admin->email === $email) {
                $code = $mail->code;
            }

            return true;
        });

        return (string) $code;
    }

    // --------------------------------------------------------- the two steps

    public function test_the_console_asks_for_an_address_before_anything_else(): void
    {
        $this->get('/backoffice')->assertRedirect(route('backoffice.verify.email'));

        $this->get(route('backoffice.verify.email'))
            ->assertOk()
            ->assertSee(__('backoffice.auth.email_title'))
            /* No password field on the first screen: somebody who has found
               this page has nothing to attack yet. */
            ->assertDontSee('name="password"', false);
    }

    public function test_an_administrator_is_emailed_a_code(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post(route('backoffice.verify.send'), ['email' => $admin->email])
            ->assertRedirect(route('backoffice.verify.code'));

        Mail::assertSent(BackofficeVerificationCodeMail::class);

        /* Hashed, like a password: a database dump must not hand over a live
           second factor. */
        $row = BackofficeLoginCode::query()->firstOrFail();
        $this->assertNotSame($this->codeSentTo($admin->email), $row->code_hash);
        $this->assertTrue(Hash::check($this->codeSentTo($admin->email), $row->code_hash));
    }

    /**
     * An address nobody has behaves exactly like one somebody does.
     *
     * Same redirect, same wording, and a row written either way — but no mail,
     * because there is nowhere to send it.
     */
    public function test_an_unknown_address_is_answered_identically(): void
    {
        Mail::fake();

        $this->post(route('backoffice.verify.send'), ['email' => 'nobody@styledesk.test'])
            ->assertRedirect(route('backoffice.verify.code'))
            ->assertSessionHas('status', __('backoffice.auth.code_sent'));

        Mail::assertNothingSent();

        $this->assertDatabaseCount('backoffice_login_codes', 1);
        $this->assertDatabaseHas('backoffice_audit_logs', ['action' => 'auth.code_requested_unknown']);
    }

    public function test_a_wrong_code_is_refused_and_counted(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post(route('backoffice.verify.send'), ['email' => $admin->email]);

        $this->post(route('backoffice.verify.check'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, (int) BackofficeLoginCode::query()->firstOrFail()->attempts);
        $this->assertDatabaseHas('backoffice_audit_logs', ['action' => 'auth.code_failed']);
    }

    /** One-time means one time. */
    public function test_a_code_cannot_be_used_twice(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->verified($admin);

        $code = $this->codeSentTo($admin->email);

        /* A second attempt with the same code, from a fresh session. */
        $this->flushSession();

        $this->post(route('backoffice.verify.send'), ['email' => $admin->email]);
        $this->post(route('backoffice.verify.check'), ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_a_code_stops_working_once_it_has_expired(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post(route('backoffice.verify.send'), ['email' => $admin->email]);
        $code = $this->codeSentTo($admin->email);

        BackofficeLoginCode::query()->update(['expires_at' => now()->subMinute()]);

        $this->post(route('backoffice.verify.check'), ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    // ------------------------------------------------------------- the login

    /**
     * The password screen is a gate, not a suggestion.
     *
     * Without this the second step is decoration: anybody who knows the URL
     * types past the code straight to a password form.
     */
    public function test_the_password_screen_cannot_be_reached_without_the_code(): void
    {
        $this->get(route('backoffice.login'))->assertRedirect(route('backoffice.verify.email'));

        $this->post(route('backoffice.login.store'), [
            'email' => 'admin@styledesk.test',
            'password' => 'Str0ng!Passw0rd!',
        ])->assertRedirect(route('backoffice.verify.email'));

        $this->assertGuest('backoffice');
    }

    public function test_a_verified_administrator_can_sign_in(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->verified($admin);

        $this->get(route('backoffice.login'))->assertOk()->assertSee($admin->email);

        $this->post(route('backoffice.login.store'), [
            'email' => $admin->email,
            'password' => 'Str0ng!Passw0rd!',
        ])->assertRedirect(route('backoffice.dashboard'));

        $this->assertAuthenticatedAs($admin, 'backoffice');
        $this->assertDatabaseHas('backoffice_audit_logs', ['action' => 'auth.login', 'admin_id' => $admin->id]);
        $this->assertNotNull($admin->fresh()->last_login_at);

        /* The code has done its work and must not still be standing: a signed
           out session would otherwise walk back to the password screen. */
        $this->assertNull(session(BackofficeVerification::EMAIL));
    }

    /**
     * A session verified for one address cannot sign in as another.
     *
     * Without this the code is a formality rather than a factor: verify your
     * own address, then submit somebody else's on the password form.
     */
    public function test_a_verified_session_cannot_sign_in_as_somebody_else(): void
    {
        Mail::fake();
        $mine = $this->admin();
        $theirs = $this->admin(['email' => 'other@styledesk.test', 'role' => 'admin']);

        $this->verified($mine);

        $this->post(route('backoffice.login.store'), [
            'email' => $theirs->email,
            'password' => 'Str0ng!Passw0rd!',
        ])->assertRedirect(route('backoffice.verify.email'));

        $this->assertGuest('backoffice');
    }

    public function test_a_wrong_password_is_refused_and_recorded(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->verified($admin);

        $this->post(route('backoffice.login.store'), [
            'email' => $admin->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest('backoffice');
        $this->assertDatabaseHas('backoffice_audit_logs', ['action' => 'auth.login_failed']);
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->verified($admin);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('backoffice.login.store'), ['email' => $admin->email, 'password' => 'wrong']);
        }

        $this->post(route('backoffice.login.store'), ['email' => $admin->email, 'password' => 'Str0ng!Passw0rd!'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('backoffice');

        RateLimiter::clear('backoffice-login:'.$admin->email.'|127.0.0.1');
    }

    /**
     * A disabled administrator is refused, and told nothing more than anybody.
     *
     * They do not even get as far as a code: no mail is sent, and the screen
     * still says one is on its way — the same answer an address nobody has
     * gets, because saying otherwise would confirm the account exists.
     */
    public function test_a_disabled_administrator_cannot_sign_in(): void
    {
        Mail::fake();
        $admin = $this->admin(['status' => BackofficeAdmin::STATUS_DISABLED]);

        $this->post(route('backoffice.verify.send'), ['email' => $admin->email])
            ->assertRedirect(route('backoffice.verify.code'))
            ->assertSessionHas('status', __('backoffice.auth.code_sent'));

        Mail::assertNothingSent();

        /* And even holding a verified session, the password is refused. */
        BackofficeVerification::passed(request(), $admin->email);
        session([BackofficeVerification::EMAIL => $admin->email, BackofficeVerification::AT => time()]);

        $this->post(route('backoffice.login.store'), [
            'email' => $admin->email,
            'password' => 'Str0ng!Passw0rd!',
        ])->assertSessionHasErrors('password');

        $this->assertGuest('backoffice');
    }

    /**
     * Disabling somebody ends the session they are already in.
     *
     * The status is read from the database on every request rather than
     * trusted from the session, or an administrator disabled at ten past nine
     * keeps the console until their cookie expires.
     */
    public function test_disabling_an_administrator_ends_their_session(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->verified($admin);
        $this->post(route('backoffice.login.store'), ['email' => $admin->email, 'password' => 'Str0ng!Passw0rd!']);

        $this->get(route('backoffice.dashboard'))->assertOk();

        $admin->forceFill(['status' => BackofficeAdmin::STATUS_DISABLED])->save();

        $this->get(route('backoffice.dashboard'))
            ->assertRedirect(route('backoffice.verify.email'))
            ->assertSessionHas(AuthenticateBackoffice::DISABLED);

        $this->assertGuest('backoffice');
    }

    public function test_an_idle_session_is_ended_on_the_server(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->verified($admin);
        $this->post(route('backoffice.login.store'), ['email' => $admin->email, 'password' => 'Str0ng!Passw0rd!']);

        $minutes = (int) config('backoffice.session_timeout_minutes');
        session([AuthenticateBackoffice::ACTIVITY_KEY => time() - (($minutes + 1) * 60)]);

        $this->get(route('backoffice.dashboard'))
            ->assertRedirect(route('backoffice.verify.email'))
            ->assertSessionHas(AuthenticateBackoffice::TIMED_OUT);

        $this->assertGuest('backoffice');
    }

    // -------------------------------------------------------------- the walls

    public function test_the_console_is_closed_to_everybody_signed_out(): void
    {
        foreach (['backoffice.dashboard', 'backoffice.clients.index', 'backoffice.plans.index',
            'backoffice.billing.index', 'backoffice.settings.index', 'backoffice.profile'] as $route) {
            $this->get(route($route))->assertRedirect(route('backoffice.verify.email'));
        }
    }

    /**
     * A salon's own sign-in reaches none of it.
     *
     * The two guards are separate on purpose: a stolen salon session must
     * never open the console that can suspend a salon.
     */
    public function test_a_signed_in_salon_user_cannot_reach_the_console(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('backoffice.dashboard'))
            ->assertRedirect(route('backoffice.verify.email'));
    }

    /** A role reaches what its permissions say, and refuses the rest. */
    public function test_a_role_only_reaches_what_it_may(): void
    {
        Mail::fake();
        $admin = $this->admin(['role' => 'read-only']);
        $this->verified($admin);
        $this->post(route('backoffice.login.store'), ['email' => $admin->email, 'password' => 'Str0ng!Passw0rd!']);

        /* Read Only may look at clients... */
        $this->get(route('backoffice.clients.index'))->assertOk();

        /* ...and has no business in platform settings. */
        $this->get(route('backoffice.settings.index'))->assertForbidden();
    }

    public function test_the_navigation_hides_what_the_reader_cannot_reach(): void
    {
        Mail::fake();
        $admin = $this->admin(['role' => 'read-only']);
        $this->verified($admin);
        $this->post(route('backoffice.login.store'), ['email' => $admin->email, 'password' => 'Str0ng!Passw0rd!']);

        $this->get(route('backoffice.dashboard'))
            ->assertOk()
            ->assertSee(__('backoffice.nav.clients'))
            ->assertDontSee(__('backoffice.nav.settings'));
    }

    // ---------------------------------------------------------------- the log

    /** Immutable, and enforced rather than promised. */
    public function test_an_audit_entry_cannot_be_changed_or_removed(): void
    {
        $entry = BackofficeAuditLog::record(action: 'auth.login', actorEmail: 'someone@styledesk.test');

        $this->assertFalse($entry->update(['action' => 'auth.logout']));
        $this->assertFalse($entry->delete());
        $this->assertSame('auth.login', $entry->fresh()->action);
    }

    /** The Super Owner's own account is what a fresh install can sign in as. */
    public function test_the_super_owner_is_seeded_without_a_password_in_source(): void
    {
        $this->seed(BackofficeSuperOwnerSeeder::class);

        $admin = BackofficeAdmin::forEmail((string) config('backoffice.super_owner.email'));

        $this->assertNotNull($admin);
        $this->assertSame('super-owner', $admin->role);
        $this->assertTrue($admin->isSuperOwner());

        /* Running the seeders again must not hand a live account back to
           whoever ran them. */
        $before = $admin->password;
        $this->seed(BackofficeSuperOwnerSeeder::class);
        $this->assertSame($before, $admin->fresh()->password);
    }

    /** Only a Super Owner may act on a Super Owner. */
    public function test_an_admin_may_not_manage_a_super_owner(): void
    {
        $owner = $this->admin();
        $admin = $this->admin(['email' => 'admin2@styledesk.test', 'role' => 'admin']);

        $this->assertTrue($owner->mayManage($admin));
        $this->assertTrue($owner->mayManage($owner));
        $this->assertFalse($admin->mayManage($owner));

        /* And the platform is never left without one. */
        $this->assertTrue($owner->isLastSuperOwner());
    }
}
