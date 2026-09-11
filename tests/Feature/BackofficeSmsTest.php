<?php

namespace Tests\Feature;

use App\Messaging\SmsProviders;
use App\Models\BackofficeAdmin;
use App\Models\BackofficeAuditLog;
use App\Models\PlatformSmsSettings;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Back Office → SMS.
 *
 * The platform's carrier account: which company carries every business's
 * messages, and the keys to reach them. Two things these guard above all
 * else — that a key never comes back out of the screen once it has gone in,
 * and that switching the platform to a half-configured carrier is refused
 * rather than saved. The second is every business's messages failing at once.
 */
class BackofficeSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SmsProviders::forget();
    }

    // ------------------------------------------------------------- reading

    public function test_the_screen_opens(): void
    {
        $this->signIn();

        $this->get(route('backoffice.sms.index'))
            ->assertOk()
            ->assertSee(__('backoffice.sms.title'))
            ->assertSee(__('backoffice.sms.providers.clicksend'));
    }

    /**
     * A stored key never comes back out of the screen.
     *
     * The whole point of encrypting it. A form that rendered the secret would
     * put it in the page source, in the browser cache and in anybody's
     * shoulder view.
     */
    public function test_a_stored_key_is_never_rendered(): void
    {
        $this->signIn();

        PlatformSmsSettings::create([
            'provider' => 'clicksend',
            'clicksend_username' => 'acme',
            'clicksend_key' => 'super-secret-key',
            'clicksend_from' => '+15550000000',
        ]);

        $this->get(route('backoffice.sms.index'))
            ->assertOk()
            ->assertDontSee('super-secret-key')
            ->assertDontSee('acme')
            ->assertSee(__('backoffice.sms.stored'));
    }

    // ------------------------------------------------------------- writing

    public function test_the_carrier_and_its_keys_can_be_saved(): void
    {
        $admin = $this->signIn();

        $this->patch(route('backoffice.sms.update'), [
            'is_enabled' => 1,
            'provider' => 'clicksend',
            'clicksend_username' => 'acme',
            'clicksend_key' => 'secret',
            'clicksend_from' => '+1 555 000 0000',
        ])->assertSessionHasNoErrors();

        $settings = PlatformSmsSettings::firstOrFail();

        $this->assertTrue($settings->is_enabled);
        $this->assertSame('clicksend', $settings->provider);
        $this->assertSame('secret', $settings->clicksend_key);
        /* Punctuation stripped: a carrier wants digits. */
        $this->assertSame('+15550000000', $settings->clicksend_from);
        $this->assertSame($admin->id, $settings->updated_by);
    }

    /** Encrypted at rest: the database holds ciphertext, not the key. */
    public function test_credentials_are_encrypted_in_the_database(): void
    {
        $this->signIn();

        $this->patch(route('backoffice.sms.update'), [
            'is_enabled' => 1,
            'provider' => 'clicksend',
            'clicksend_username' => 'acme',
            'clicksend_key' => 'super-secret-key',
            'clicksend_from' => '+15550000000',
        ]);

        $raw = \DB::table('platform_sms_settings')->value('clicksend_key');

        $this->assertNotSame('super-secret-key', $raw);
        $this->assertStringNotContainsString('super-secret-key', (string) $raw);
        /* And still readable through the model. */
        $this->assertSame('super-secret-key', PlatformSmsSettings::firstOrFail()->clicksend_key);
    }

    /**
     * A blank secret means "leave it alone", never "clear it".
     *
     * The form cannot show what is stored, so an empty box is an untouched
     * box — otherwise changing a sender number would wipe a working key and
     * take every business's messages down with it.
     */
    public function test_an_empty_field_does_not_wipe_a_stored_key(): void
    {
        $this->signIn();

        PlatformSmsSettings::create([
            'provider' => 'clicksend',
            'clicksend_username' => 'acme',
            'clicksend_key' => 'keep-me',
            'clicksend_from' => '+15550000000',
        ]);

        $this->patch(route('backoffice.sms.update'), [
            'is_enabled' => 1,
            'provider' => 'clicksend',
            'clicksend_username' => '',
            'clicksend_key' => '',
            'clicksend_from' => '+15559999999',
        ])->assertSessionHasNoErrors();

        $settings = PlatformSmsSettings::firstOrFail();

        $this->assertSame('keep-me', $settings->clicksend_key);
        $this->assertSame('acme', $settings->clicksend_username);
        $this->assertSame('+15559999999', $settings->clicksend_from);
    }

    /**
     * Switching to a carrier with no key in it is refused.
     *
     * Saved, it would be every business's messages failing at once — and the
     * first anybody hears of it is a client who never got their confirmation.
     */
    public function test_a_half_configured_carrier_cannot_be_switched_on(): void
    {
        $this->signIn();

        $this->patch(route('backoffice.sms.update'), [
            'is_enabled' => 1,
            'provider' => 'telnyx',
        ])->assertSessionHasErrors('provider');

        $this->assertSame(0, PlatformSmsSettings::count());
    }

    /** Switched off, it does not have to be ready. */
    public function test_an_incomplete_carrier_can_be_saved_while_sms_is_off(): void
    {
        $this->signIn();

        $this->patch(route('backoffice.sms.update'), [
            'is_enabled' => 0,
            'provider' => 'telnyx',
            'telnyx_key' => 'partial',
        ])->assertSessionHasNoErrors();

        $this->assertSame('telnyx', PlatformSmsSettings::firstOrFail()->provider);
    }

    /** Changing where every business's messages go is not done anonymously. */
    public function test_switching_carrier_is_written_to_the_audit_log(): void
    {
        $this->signIn();

        $this->patch(route('backoffice.sms.update'), [
            'is_enabled' => 1,
            'provider' => 'clicksend',
            'clicksend_username' => 'acme',
            'clicksend_key' => 'secret',
            'clicksend_from' => '+15550000000',
        ]);

        $this->assertTrue(
            BackofficeAuditLog::query()->where('action', 'sms.settings_updated')->exists()
        );
    }

    // ------------------------------------------------------- what it drives

    /**
     * The back office is what the application reads.
     *
     * Otherwise the screen is decoration: somebody switches carrier and
     * messages keep going out on the old one.
     */
    public function test_the_saved_carrier_is_the_one_the_application_uses(): void
    {
        $this->signIn();

        $this->patch(route('backoffice.sms.update'), [
            'is_enabled' => 1,
            'provider' => 'clicksend',
            'clicksend_username' => 'acme',
            'clicksend_key' => 'secret',
            'clicksend_from' => '+15550000000',
        ]);

        SmsProviders::forget();
        app()['env'] = 'production';

        $this->assertSame('clicksend', SmsProviders::configured());
        $this->assertSame('+15550000000', SmsProviders::senderNumber());
    }

    /** Switched off at the platform means off, whatever the environment says. */
    public function test_switching_the_service_off_stops_every_business(): void
    {
        $this->signIn();

        config()->set('sms.provider', 'clicksend');

        $this->patch(route('backoffice.sms.update'), [
            'is_enabled' => 0,
            'provider' => 'clicksend',
        ]);

        SmsProviders::forget();
        app()['env'] = 'production';

        $this->assertSame('disabled', SmsProviders::configured());
        $this->assertSame('disabled', SmsProviders::resolve()->name());
    }

    // -------------------------------------------------------------- testing

    /** A test message goes through the ordinary service and is recorded. */
    public function test_a_test_message_is_sent_and_recorded(): void
    {
        $this->signIn();

        $this->post(route('backoffice.sms.test-message'), ['to' => '+1 201 555 0142'])
            ->assertSessionHasNoErrors();

        $message = SmsMessage::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('test', $message->type);
        $this->assertSame('+12015550142', $message->to_number);
        /* No tenant and no user: the platform sent it, not a salon. A
           back-office admin id is not a user id, and writing one into a
           column that points at `users` aims a foreign key at whichever
           unrelated person happens to hold that number. */
        $this->assertNull($message->tenant_id);
        $this->assertNull($message->created_by);
    }

    /** And on a developer's machine it never reaches a carrier. */
    public function test_a_test_message_does_not_reach_a_carrier_locally(): void
    {
        $this->signIn();

        Http::fake();

        $this->post(route('backoffice.sms.test-message'), ['to' => '+12015550142']);

        Http::assertNothingSent();
    }

    public function test_a_number_that_is_not_one_is_refused(): void
    {
        $this->signIn();

        $this->post(route('backoffice.sms.test-message'), ['to' => 'nope'])
            ->assertSessionHasErrors('to');

        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    // ---------------------------------------------------------- permissions

    /**
     * Reading which carrier is on is one thing; holding the key that spends
     * money on every business's behalf is another.
     */
    public function test_reading_and_changing_are_separate_permissions(): void
    {
        $this->actingAs($this->admin('read-only'), 'backoffice');

        $this->get(route('backoffice.sms.index'))->assertOk();

        $this->patch(route('backoffice.sms.update'), [
            'provider' => 'disabled',
        ])->assertForbidden();

        $this->post(route('backoffice.sms.test-message'), ['to' => '+12015550142'])
            ->assertForbidden();
    }

    /** A role with neither cannot see it at all. */
    public function test_a_role_without_sms_cannot_reach_the_screen(): void
    {
        $this->actingAs($this->admin('nonexistent-role'), 'backoffice');

        $this->get(route('backoffice.sms.index'))->assertForbidden();
    }

    // ------------------------------------------------------------- fixtures

    private function signIn(string $role = 'super-owner'): BackofficeAdmin
    {
        $admin = $this->admin($role);

        $this->actingAs($admin, 'backoffice');

        return $admin;
    }

    private function admin(string $role = 'super-owner'): BackofficeAdmin
    {
        return BackofficeAdmin::query()->create([
            'name' => 'Rohit Philip',
            'email' => $role.'@styledesk.test',
            'password' => 'Str0ng!Passw0rd!',
            'role' => $role,
            'status' => BackofficeAdmin::STATUS_ACTIVE,
        ]);
    }
}
