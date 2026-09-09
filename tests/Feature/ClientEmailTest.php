<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Mail\ClientMessageMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientEmailMessage;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\ClientEmailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Writing to a client from their profile.
 *
 * The rules worth holding are the ones a drawer cannot enforce on its own: a
 * business that has not switched the feature on cannot send whatever the
 * screen offers, a client with no address is not written to, and what was sent
 * is recorded as sent rather than reconstructed later from a template that may
 * since have changed.
 */
class ClientEmailTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        /* StyleDesk Email is what these tests are about. Pinned so a machine
           with Google credentials in its .env does not quietly change which
           provider they run against. */
        config(['client_email.providers.gmail.available' => false]);

        $this->tenant = Tenant::create([
            'name' => 'Smile Spa', 'slug' => 'smile-email',
            'client_email_enabled' => true,
            'email_provider' => 'styledesk',
            'email_reply_to' => 'hello@smilespa.test',
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

        $this->client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Jane', 'last_name' => 'Smith',
            'email' => 'jane@example.test',
        ]);
    }

    // ------------------------------------------------------------ sending

    public function test_an_email_is_sent_and_recorded(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Appointment Follow-up',
                'message' => 'Thank you for coming in.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', __('client_email.send.sent', ['name' => $this->client->displayName()]));

        Mail::assertSent(ClientMessageMail::class);

        $email = ClientEmailMessage::withoutGlobalScopes()->first();

        $this->assertSame('jane@example.test', $email->recipient_email);
        $this->assertSame('Appointment Follow-up', $email->subject);
        $this->assertSame('styledesk', $email->provider);
        $this->assertSame($this->owner->id, $email->sent_by);
        $this->assertSame(ClientEmailMessage::STATUS_SENT, $email->status);
        $this->assertNotNull($email->sent_at);
    }

    /** Sent, not delivered: nothing may claim delivery without a provider saying so. */
    public function test_a_sent_message_does_not_claim_to_be_delivered(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)->postJson(route('clients.emails.store', $this->client), [
            'subject' => 'Hello', 'message' => 'Body',
        ])->assertCreated();

        $this->assertSame(
            ClientEmailMessage::STATUS_SENT,
            ClientEmailMessage::withoutGlobalScopes()->first()->status
        );
    }

    public function test_the_subject_and_message_are_both_required(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), ['subject' => '', 'message' => ''])
            ->assertJsonValidationErrors(['subject', 'message']);

        Mail::assertNothingSent();
    }

    public function test_a_business_with_the_feature_switched_off_cannot_send(): void
    {
        Mail::fake();

        $this->tenant->forceFill(['client_email_enabled' => false])->save();

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Hello', 'message' => 'Body',
            ])
            ->assertJsonValidationErrors('subject');

        Mail::assertNothingSent();
        $this->assertSame(0, ClientEmailMessage::withoutGlobalScopes()->count());
    }

    public function test_a_client_with_no_address_is_not_written_to(): void
    {
        Mail::fake();

        $this->client->forceFill(['email' => null])->save();

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Hello', 'message' => 'Body',
            ])
            ->assertJsonValidationErrors('subject');

        Mail::assertNothingSent();
    }

    /** A booking from another client must not be attached to this one's record. */
    public function test_a_booking_belonging_to_someone_else_is_refused(): void
    {
        Mail::fake();

        $other = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Someone', 'last_name' => 'Else',
        ]);

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $other->id,
            'date' => now()->toDateString(),
            'starts_at' => '10:00',
            'minutes' => 30,
            'status' => 'confirmed',
            'total_minor' => 1000,
            'currency_code' => 'USD',
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Hello', 'message' => 'Body', 'booking_id' => $booking->id,
            ])
            ->assertJsonValidationErrors('booking_id');
    }

    // ------------------------------------------------------------ history

    public function test_the_send_is_written_to_the_client_timeline(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)->postJson(route('clients.emails.store', $this->client), [
            'subject' => 'Appointment Follow-up', 'message' => 'Body',
        ])->assertCreated();

        $entry = ClientActivity::withoutGlobalScopes()->where('type', 'email.sent')->first();

        $this->assertNotNull($entry);
        $this->assertSame('email', $entry->category);
        $this->assertSame('Appointment Follow-up', $entry->description);
        $this->assertSame('jane@example.test', $entry->meta['recipient']);
    }

    public function test_the_history_lists_what_was_sent(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)->postJson(route('clients.emails.store', $this->client), [
            'subject' => 'Appointment Follow-up', 'message' => 'Body',
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('clients.emails.index', $this->client))
            ->assertOk()
            ->assertJsonPath('emails.0.subject', 'Appointment Follow-up')
            ->assertJsonPath('emails.0.status_label', __('client_email.statuses.sent'))
            ->assertJsonPath('emails.0.sent_by', $this->owner->name);
    }

    /**
     * The record is what was sent, not a view onto what would be sent now.
     *
     * A client who changes their address must not rewrite where last month's
     * message actually went.
     */
    public function test_the_record_keeps_the_address_it_was_sent_to(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)->postJson(route('clients.emails.store', $this->client), [
            'subject' => 'Hello', 'message' => 'Body',
        ]);

        $this->client->forceFill(['email' => 'moved@example.test'])->save();

        $this->assertSame(
            'jane@example.test',
            ClientEmailMessage::withoutGlobalScopes()->first()->recipient_email
        );
    }

    // -------------------------------------------------------- permissions

    public function test_a_role_without_the_send_permission_is_refused(): void
    {
        Mail::fake();

        $member = $this->staffMember(['email.view_history' => 'all']);

        $this->actingAs($member)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Hello', 'message' => 'Body',
            ])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_a_role_without_the_history_permission_cannot_read_it(): void
    {
        $member = $this->staffMember(['email.send' => 'all']);

        $this->actingAs($member)
            ->getJson(route('clients.emails.index', $this->client))
            ->assertForbidden();
    }

    // ---------------------------------------------------------- templates

    public function test_a_template_fills_in_the_clients_details(): void
    {
        $rendered = collect(ClientEmailTemplates::all($this->client))
            ->firstWhere('key', 'thank_you');

        $this->assertStringContainsString('Jane', $rendered['body']);
        $this->assertStringContainsString('Smile Spa', $rendered['body']);
        $this->assertStringNotContainsString('{{client_first_name}}', $rendered['body']);
    }

    /**
     * A variable with nothing behind it is left standing rather than blanked.
     *
     * "{{balance_due}}" reaching a client is a bug somebody reports within the
     * hour; a silently emptied sentence is one nobody ever notices.
     */
    public function test_a_variable_with_no_value_is_left_in_the_text(): void
    {
        $values = ClientEmailTemplates::values($this->client);

        $this->assertSame('', $values['balance_due']);
        $this->assertSame(
            'You owe {{balance_due}}',
            ClientEmailTemplates::render('You owe {{balance_due}}', $values)
        );
    }

    public function test_an_unknown_variable_is_left_alone(): void
    {
        $this->assertSame(
            'Hello {{nonsense}}',
            ClientEmailTemplates::render('Hello {{nonsense}}', ClientEmailTemplates::values($this->client))
        );
    }

    // ----------------------------------------------------- App Settings

    public function test_the_settings_screen_opens(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email.show'))
            ->assertOk()
            ->assertSee(__('client_email.providers.styledesk.name'))
            ->assertSee(__('client_email.providers.gmail.name'));
    }

    public function test_the_switch_and_the_sender_are_saved(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.email.update'), [
                'client_email_enabled' => '1',
                'email_provider' => 'styledesk',
                'email_sender_name' => 'Smile Spa',
                'email_reply_to' => 'reception@smilespa.test',
            ])
            ->assertRedirect();

        $this->tenant->refresh();

        $this->assertTrue($this->tenant->client_email_enabled);
        $this->assertSame('Smile Spa', $this->tenant->email_sender_name);
        $this->assertSame('reception@smilespa.test', $this->tenant->email_reply_to);
    }

    /**
     * A provider this deployment cannot send through is refused.
     *
     * Choosing it would leave the business switched on and unable to send, so
     * the form refuses rather than storing a provider nothing can use.
     *
     * The availability is forced here rather than read from the environment:
     * Gmail is available wherever Google credentials are configured, and a
     * test that passed or failed depending on the developer's own .env would
     * be testing the machine rather than the rule.
     */
    public function test_a_provider_this_deployment_cannot_send_through_is_refused(): void
    {
        config(['client_email.providers.gmail.available' => false]);

        $this->actingAs($this->owner)
            ->patch(route('settings.email.update'), [
                'client_email_enabled' => '1',
                'email_provider' => 'gmail',
            ])
            ->assertSessionHasErrors('email_provider');

        $this->assertSame('styledesk', $this->tenant->fresh()->email_provider);
    }

    /** And accepted once it is. */
    public function test_gmail_may_be_chosen_where_it_is_available(): void
    {
        config(['client_email.providers.gmail.available' => true]);

        $this->actingAs($this->owner)
            ->patch(route('settings.email.update'), [
                'client_email_enabled' => '1',
                'email_provider' => 'gmail',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('gmail', $this->tenant->fresh()->email_provider);
    }

    /** The test goes to the person asking, never to an address they type. */
    public function test_the_test_email_goes_to_the_signed_in_user(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->post(route('settings.email.test'))
            ->assertRedirect();

        Mail::assertSent(ClientMessageMail::class, fn ($mail) => $mail->hasTo($this->owner->email));

        /* A test is not something a client was told, so it leaves no row and
           no timeline entry. */
        $this->assertSame(0, ClientEmailMessage::withoutGlobalScopes()->count());
    }

    public function test_the_test_email_is_refused_when_the_feature_is_off(): void
    {
        Mail::fake();

        $this->tenant->forceFill(['client_email_enabled' => false])->save();

        $this->actingAs($this->owner)
            ->post(route('settings.email.test'))
            ->assertSessionHasErrors('client_email_enabled');

        Mail::assertNothingSent();
    }

    /** @param array<string, string> $permissions */
    private function staffMember(array $permissions): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Reid',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->first();

        app(ProvisionSystemRoles::class)->syncPermissions($role, $permissions + ['clients.view' => 'all']);

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'first_name' => 'Sam', 'last_name' => 'Reid',
        ]);

        return $user->fresh();
    }
}
