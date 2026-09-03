<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Client;
use App\Models\ClientEmailMessage;
use App\Models\Tenant;
use App\Models\TenantGmailConnection;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\ClientEmailSender;
use App\Support\Gmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Connect Gmail.
 *
 * The tests worth having are the ones about the handshake going wrong, because
 * that is where a salon ends up sending its client mail from somewhere it did
 * not intend: a callback nobody started, a token that cannot be renewed, a
 * disconnect that quietly stops all email.
 */
class GmailConnectionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        /* Credentials make the provider available at all — see
           config/client_email.php. Without them Gmail is honestly "coming
           soon" and none of this is reachable. */
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'https://styledesk.test/settings/email/gmail/callback',
            'client_email.providers.gmail.available' => true,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Smile Spa', 'slug' => 'smile-gmail',
            'client_email_enabled' => true,
            'email_provider' => 'styledesk',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->owner = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();
        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();
        $this->owner = $this->owner->fresh();

        app(ProvisionSystemRoles::class)->forTenant($this->tenant);
    }

    // ------------------------------------------------------------ the card

    /**
     * The Connect button is on the Gmail card, where somebody choosing Gmail
     * is already looking.
     *
     * It used to sit at the foot of the page below Save, which meant selecting
     * Gmail offered no visible way to authorise it: the button existed and
     * nobody could find it.
     */
    public function test_the_gmail_card_offers_connect_when_nothing_is_connected(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email.show'))
            ->assertOk()
            ->assertSee(route('settings.email.gmail.connect'), false)
            ->assertSee(__('client_email.gmail.connect'))
            ->assertSee(__('client_email.connection.disconnected'))
            /* On the form rather than on the word: "Disconnect" is a substring
               of "Disconnected", which the card is showing as its status. */
            ->assertDontSee('id="gmail-disconnect"', false);
    }

    public function test_the_card_shows_the_mailbox_and_offers_reconnect_and_disconnect(): void
    {
        $this->connection();

        $this->actingAs($this->owner)
            ->get(route('settings.email.show'))
            ->assertOk()
            ->assertSee('salon@smilespa.test')
            ->assertSee(__('client_email.connection.connected'))
            ->assertSee(__('client_email.gmail.reconnect'))
            ->assertSee(__('client_email.gmail.disconnect'))
            /* The disconnect form must be rendered outside the settings form,
               or the browser drops it and the button does nothing. */
            ->assertSee('id="gmail-disconnect"', false);
    }

    public function test_a_broken_connection_says_so_on_the_card(): void
    {
        $this->connection(['status' => TenantGmailConnection::STATUS_NEEDS_ATTENTION]);

        $this->actingAs($this->owner)
            ->get(route('settings.email.show'))
            ->assertOk()
            ->assertSee(__('client_email.connection.needs_attention'))
            ->assertSee(__('client_email.errors.reconnect_gmail'));
    }

    // ------------------------------------------------------- the handshake

    public function test_connecting_sends_the_owner_to_google_asking_only_to_send(): void
    {
        $response = $this->actingAs($this->owner)->get(route('settings.email.gmail.connect'));

        $response->assertRedirectContains('accounts.google.com');

        $url = $response->headers->get('Location');

        /* Send only. StyleDesk has no business reading a salon's inbox, and a
           scope asked for is a scope that has to be justified on the consent
           screen the owner is looking at. */
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/gmail.send'), $url);
        $this->assertStringNotContainsString('gmail.readonly', $url);
        $this->assertStringNotContainsString('gmail.modify', $url);

        /* Offline with a forced consent, or Google withholds the refresh
           token and the connection dies quietly an hour later. */
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
    }

    public function test_the_callback_stores_the_connection_and_switches_the_provider(): void
    {
        $this->fakeGoogle();

        $state = $this->startHandshake();

        $this->actingAs($this->owner)
            ->get(route('settings.email.gmail.callback', ['code' => 'good-code', 'state' => $state]))
            ->assertRedirect(route('settings.email.show'));

        $connection = TenantGmailConnection::first();

        $this->assertSame('salon@smilespa.test', $connection->email);
        $this->assertSame('refresh-abc', $connection->refresh_token);
        $this->assertSame(TenantGmailConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame($this->owner->id, $connection->connected_by);

        /* Connecting is choosing. An owner who connects a mailbox and finds
           StyleDesk Email still sending would call that broken. */
        $this->assertSame('gmail', $this->tenant->fresh()->email_provider);
    }

    /**
     * A callback nobody started is refused.
     *
     * Without the state check, anybody can hand a signed-in owner a link that
     * completes a handshake against an attacker's Google account, and the
     * salon starts sending its client mail from a stranger's mailbox.
     */
    public function test_a_callback_with_the_wrong_state_is_refused(): void
    {
        $this->fakeGoogle();
        $this->startHandshake();

        $this->actingAs($this->owner)
            ->get(route('settings.email.gmail.callback', ['code' => 'good-code', 'state' => 'not-the-one']))
            ->assertRedirect(route('settings.email.show'))
            ->assertSessionHasErrors('gmail');

        $this->assertSame(0, TenantGmailConnection::count());
    }

    public function test_a_callback_with_no_state_at_all_is_refused(): void
    {
        $this->fakeGoogle();

        $this->actingAs($this->owner)
            ->get(route('settings.email.gmail.callback', ['code' => 'good-code', 'state' => 'anything']))
            ->assertSessionHasErrors('gmail');

        $this->assertSame(0, TenantGmailConnection::count());
    }

    /** Pressing Cancel on Google's screen is not an error. */
    public function test_a_declined_consent_returns_quietly(): void
    {
        $state = $this->startHandshake();

        $this->actingAs($this->owner)
            ->get(route('settings.email.gmail.callback', ['error' => 'access_denied', 'state' => $state]))
            ->assertRedirect(route('settings.email.show'))
            ->assertSessionHasNoErrors();
    }

    public function test_a_refusal_from_google_is_reported_rather_than_thrown(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error_description' => 'Bad code'], 400)]);

        $state = $this->startHandshake();

        $this->actingAs($this->owner)
            ->get(route('settings.email.gmail.callback', ['code' => 'bad', 'state' => $state]))
            ->assertRedirect(route('settings.email.show'))
            ->assertSessionHasErrors('gmail');
    }

    /** A re-consent that reuses a grant sends no refresh token; the old one stands. */
    public function test_reconnecting_keeps_the_refresh_token_when_google_sends_none(): void
    {
        $this->connection(['refresh_token' => 'original-refresh']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'new-access', 'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => 'salon@smilespa.test', 'name' => 'Smile Spa',
            ]),
        ]);

        $state = $this->startHandshake();

        $this->actingAs($this->owner)
            ->get(route('settings.email.gmail.callback', ['code' => 'good-code', 'state' => $state]));

        $this->assertSame('original-refresh', TenantGmailConnection::first()->refresh_token);
    }

    // ------------------------------------------------------- disconnecting

    public function test_disconnecting_removes_the_mailbox_and_falls_back(): void
    {
        $this->connection();
        $this->tenant->forceFill(['email_provider' => 'gmail'])->save();

        $this->actingAs($this->owner)
            ->delete(route('settings.email.gmail.disconnect'))
            ->assertRedirect(route('settings.email.show'));

        $this->assertSame(0, TenantGmailConnection::count());

        /* Disconnecting Gmail must not silently stop client email altogether. */
        $this->assertSame('styledesk', $this->tenant->fresh()->email_provider);
        $this->assertTrue(ClientEmailSender::readyFor($this->tenant->fresh()));
    }

    // -------------------------------------------------------------- status

    public function test_a_business_on_gmail_with_nothing_connected_cannot_send(): void
    {
        $this->tenant->forceFill(['email_provider' => 'gmail'])->save();

        $this->assertSame(
            __('client_email.errors.gmail_not_connected'),
            ClientEmailSender::blockingReason($this->tenant->fresh())
        );
    }

    /**
     * Connected and broken is not the same as disconnected.
     *
     * The business believes it is set up, so the difference is what they have
     * to do about it.
     */
    public function test_a_connection_that_needs_attention_blocks_sending(): void
    {
        $this->connection(['status' => TenantGmailConnection::STATUS_NEEDS_ATTENTION]);
        $this->tenant->forceFill(['email_provider' => 'gmail'])->save();

        $this->assertSame(
            __('client_email.errors.reconnect_gmail'),
            ClientEmailSender::blockingReason($this->tenant->fresh())
        );
    }

    public function test_a_lapsed_token_that_cannot_be_refreshed_marks_the_connection(): void
    {
        $connection = $this->connection([
            'access_expires_at' => now()->subHour(),
            'refresh_token' => 'stale-refresh',
        ]);

        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        try {
            Gmail::accessToken($connection);
            $this->fail('A dead refresh token should not yield an access token.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('client_email.gmail.reconnect_needed'), $e->getMessage());
        }

        $this->assertSame(
            TenantGmailConnection::STATUS_NEEDS_ATTENTION,
            $connection->fresh()->status
        );
    }

    public function test_a_lapsed_token_is_renewed_before_sending(): void
    {
        $connection = $this->connection([
            'access_expires_at' => now()->subHour(),
            'refresh_token' => 'good-refresh',
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'renewed', 'expires_in' => 3600]),
        ]);

        $this->assertSame('renewed', Gmail::accessToken($connection));
        $this->assertSame(TenantGmailConnection::STATUS_CONNECTED, $connection->fresh()->status);
    }

    // ------------------------------------------------------------- sending

    public function test_a_client_email_goes_out_through_gmail(): void
    {
        $this->connection();
        $this->tenant->forceFill(['email_provider' => 'gmail'])->save();

        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'sent-1'])]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Jane', 'last_name' => 'Smith',
            'email' => 'jane@example.test',
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $client), [
                'subject' => 'Hello', 'message' => 'Body',
            ])
            ->assertCreated();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'gmail.googleapis.com')
            && isset($request->data()['raw']));

        $email = ClientEmailMessage::withoutGlobalScopes()->first();

        $this->assertSame('gmail', $email->provider);
        $this->assertSame(ClientEmailMessage::STATUS_SENT, $email->status);
    }

    /**
     * The business signs its own name, not the Google account's.
     *
     * An owner who connected a mailbox called "Nadia K" should still sign as
     * Smile Spa — the settings screen is where they said so.
     */
    public function test_gmail_sends_under_the_businesss_sender_name(): void
    {
        $this->connection(['google_name' => 'Nadia K']);
        $this->tenant->forceFill([
            'email_provider' => 'gmail',
            'email_sender_name' => 'Smile Spa',
        ])->save();

        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'sent-1'])]);

        $this->sendToAClient();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'gmail.googleapis.com')) {
                return false;
            }

            $mime = base64_decode(strtr($request->data()['raw'], '-_', '+/'));

            return str_contains($mime, base64_encode('Smile Spa'))
                && ! str_contains($mime, base64_encode('Nadia K'));
        });
    }

    /**
     * Replies land in the connected inbox.
     *
     * The message goes out FROM that mailbox, so a Reply-To pointing at the
     * StyleDesk address would divert every reply away from the inbox the salon
     * is actually watching — which is the whole point of connecting one.
     */
    public function test_gmail_leaves_replies_going_to_the_connected_inbox(): void
    {
        $this->connection();
        $this->tenant->forceFill([
            'email_provider' => 'gmail',
            'email_reply_to' => 'elsewhere@smilespa.test',
        ])->save();

        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'sent-1'])]);

        $this->sendToAClient();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'gmail.googleapis.com')) {
                return false;
            }

            $mime = base64_decode(strtr($request->data()['raw'], '-_', '+/'));

            return str_contains($mime, 'From: ')
                && str_contains($mime, 'salon@smilespa.test')
                && ! str_contains($mime, 'Reply-To:');
        });
    }

    // ------------------------------------------------------------- helpers

    private function sendToAClient(): void
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Jane', 'last_name' => 'Smith',
            'email' => 'jane@example.test',
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $client), [
                'subject' => 'Hello', 'message' => 'Body',
            ])
            ->assertCreated();
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'access-abc',
                'refresh_token' => 'refresh-abc',
                'expires_in' => 3600,
                'scope' => 'openid email https://www.googleapis.com/auth/gmail.send',
            ]),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => 'salon@smilespa.test',
                'name' => 'Smile Spa',
            ]),
        ]);
    }

    /** Start the handshake so the session holds the state the callback checks. */
    private function startHandshake(): string
    {
        $url = $this->actingAs($this->owner)
            ->get(route('settings.email.gmail.connect'))
            ->headers->get('Location');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) $query['state'];
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): TenantGmailConnection
    {
        return TenantGmailConnection::create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'email' => 'salon@smilespa.test',
            'google_name' => 'Smile Spa',
            'access_token' => 'access-abc',
            'refresh_token' => 'refresh-abc',
            'access_expires_at' => now()->addHour(),
            'status' => TenantGmailConnection::STATUS_CONNECTED,
            'connected_at' => now(),
        ]);
    }
}
