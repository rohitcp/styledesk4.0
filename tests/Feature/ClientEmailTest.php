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
use App\Support\ClientEmailSender;
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

    /**
     * The three addresses read across, not down.
     *
     * They are three answers to one question — who this is between — and
     * stacked they took a third of a composer that has a message to fit in.
     */
    public function test_the_addresses_sit_on_one_row(): void
    {
        $content = $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sm:grid-cols-3', $content);
        $this->assertStringContainsString('data-email-to-address', $content);
        $this->assertStringContainsString('data-email-from-address', $content);
        $this->assertStringContainsString('data-email-reply-address', $content);
    }

    /**
     * The send says so on the page the reader lands on.
     *
     * The reload is what brings the new message into the email history, so a
     * toast raised before it would be wiped out half a second later.
     */
    public function test_a_successful_send_answers_with_the_message_to_show(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Appointment Follow-up',
                'message' => 'Thank you for coming in.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', __('client_email.send.sent', ['name' => $this->client->displayName()]));
    }

    /** One appointment for a client, enough for a template to read from. */
    private function booking(?Client $for = null): Booking
    {
        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => ($for ?? $this->client)->id,
            'reference' => Booking::nextReference(),
            'date' => now()->toDateString(),
            'starts_at' => '10:00',
            'minutes' => 30,
            'status' => 'confirmed',
            'total_minor' => 1000,
            'currency_code' => 'USD',
        ]);
    }

    // ------------------------------------------------------- template values

    /**
     * A template asks about an appointment; one has to be named.
     *
     * Without a booking the date and the service have nothing behind them,
     * and `render()` leaves the variable standing rather than blanking the
     * sentence — a placeholder in an inbox is reported within the hour, a
     * silently emptied sentence never is.
     */
    public function test_a_template_without_a_booking_keeps_its_placeholders(): void
    {
        $payload = $this->actingAs($this->owner)
            ->getJson(route('clients.emails.compose', $this->client))
            ->assertOk()
            ->json();

        $bodies = collect($payload['templates'])->pluck('body')->implode(' ');

        $this->assertStringContainsString('{{', $bodies, 'Nothing to put there, so nothing was put there.');
        $this->assertNull($payload['context_booking_id']);
    }

    /**
     * Only the wording that asks about an appointment says so.
     *
     * The drawer shows its Related booking field on this flag: a thank-you
     * for a visit needs to know which visit, and "your card is about to
     * expire" does not.
     */
    public function test_each_template_says_whether_it_needs_a_booking(): void
    {
        $templates = collect(
            $this->actingAs($this->owner)
                ->getJson(route('clients.emails.compose', $this->client))
                ->assertOk()
                ->json('templates')
        );

        $this->assertTrue($templates->every(fn (array $t) => array_key_exists('needs_booking', $t)));

        /* Both kinds exist, or the flag is answering nothing. */
        $this->assertTrue($templates->contains(fn (array $t) => $t['needs_booking'] === true));
        $this->assertTrue($templates->contains(fn (array $t) => $t['needs_booking'] === false));
    }

    /** And the field itself starts hidden, before anything is chosen. */
    public function test_the_related_booking_field_starts_hidden(): void
    {
        $content = $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-email-booking-field hidden', $content);
    }

    /** Name the booking and the wording fills in. */
    public function test_a_template_rendered_against_a_booking_resolves_its_variables(): void
    {
        $booking = $this->booking();

        $payload = $this->actingAs($this->owner)
            ->getJson(route('clients.emails.compose', $this->client).'?booking_id='.$booking->id)
            ->assertOk()
            ->json();

        $bodies = collect($payload['templates'])->pluck('body')->implode(' ');

        $this->assertSame($booking->id, $payload['context_booking_id']);
        $this->assertStringNotContainsString('{{booking_date}}', $bodies);
        $this->assertStringContainsString($booking->date->translatedFormat('j M Y'), $bodies);
    }

    /**
     * Another client's appointment is not context for this message.
     *
     * It would write somebody else's date and service into this one.
     */
    public function test_a_booking_belonging_to_somebody_else_is_ignored(): void
    {
        $other = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Someone', 'last_name' => 'Else', 'status' => 'active',
        ]);

        $theirs = $this->booking($other);

        $payload = $this->actingAs($this->owner)
            ->getJson(route('clients.emails.compose', $this->client).'?booking_id='.$theirs->id)
            ->assertOk()
            ->json();

        $this->assertNull($payload['context_booking_id']);
    }

    // ---------------------------------------------------------- the default

    /**
     * StyleDesk Email is on from the start.
     *
     * It used to default to off, which made the one provider that needs no
     * setting up something every business had to go and find before a single
     * email would leave — and until they did, the Email button on a client
     * profile fell back to a mail link, so the feature looked missing rather
     * than switched off.
     */
    public function test_a_new_business_can_send_without_configuring_anything(): void
    {
        $fresh = Tenant::create([
            'name' => 'Brand New Salon',
            'slug' => 'brand-new-'.bin2hex(random_bytes(3)),
            'country_code' => 'US',
        ]);

        $this->assertTrue((bool) $fresh->fresh()->client_email_enabled);
        $this->assertSame('styledesk', ClientEmailSender::providerFor($fresh->fresh()));
        $this->assertTrue(ClientEmailSender::enabledFor($fresh->fresh()));
    }

    /**
     * The provider is a switch to look at and a radio underneath.
     *
     * Exactly one provider sends, so the control has to be one that cannot
     * leave both on or both off.
     */
    public function test_the_provider_choice_is_a_switch_that_still_picks_one(): void
    {
        $content = $this->actingAs($this->owner)
            ->get(route('settings.email.show'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('styledesk_toggle__track', $content);
        $this->assertStringContainsString('type="radio" name="email_provider"', $content);
        $this->assertStringNotContainsString('type="radio" name="email_provider" value="styledesk" class="sd-check"', $content);
    }

    // ------------------------------------------------------- the entry point

    /**
     * The button opens the drawer, not the reader's mail client.
     *
     * Even before the business has switched client email on. The drawer says
     * which setting is missing and disables its own Send; handing somebody
     * Outlook instead quietly takes the message off the client's record and
     * tells them nothing.
     */
    public function test_the_email_button_opens_the_drawer_even_before_sending_is_set_up(): void
    {
        $this->tenant->forceFill(['client_email_enabled' => false])->save();

        $content = $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-send-email', $content);
        $this->assertStringNotContainsString('mailto:'.$this->client->email, $content);
    }

    /** And it says why it cannot send, naming the screen that fixes it. */
    public function test_the_compose_explains_a_business_that_has_not_switched_it_on(): void
    {
        $this->tenant->forceFill(['client_email_enabled' => false])->save();

        $this->actingAs($this->owner)
            ->getJson(route('clients.emails.compose', $this->client))
            ->assertOk()
            ->assertJsonPath('blocked', __('client_email.errors.disabled'));
    }

    /**
     * A composer, not a dialog.
     *
     * Nothing behind it is inert — the sender is usually looking at the very
     * thing they are writing about — so it carries no aria-modal and draws no
     * scrim over the profile.
     */
    public function test_the_composer_floats_over_the_profile_rather_than_taking_it_over(): void
    {
        $content = $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('styledesk_compose', $content);
        $this->assertStringContainsString('data-email-minimise', $content);
        $this->assertStringContainsString('data-email-expand', $content);
        $this->assertStringContainsString(__('client_email.send.new_message'), $content);

        /* The prompt that stands between a mis-clicked close and a lost
           draft. */
        $this->assertStringContainsString('data-email-discard-ask', $content);
        $this->assertStringContainsString(__('client_email.send.keep_draft'), $content);
    }

    // ------------------------------------------------------- the compose

    /**
     * Every address on file, not only the cached primary.
     *
     * A client whose work address is the one they answer should be reachable
     * at it without their record being edited first.
     */
    public function test_the_compose_offers_every_address_and_the_reply_to(): void
    {
        $this->client->syncEmails([
            ['email' => 'jane@example.test', 'type' => 'personal', 'is_primary' => true],
            ['email' => 'jane@work.test', 'type' => 'work'],
        ]);

        $payload = $this->actingAs($this->owner)
            ->getJson(route('clients.emails.compose', $this->client))
            ->assertOk()
            ->json();

        $this->assertCount(2, $payload['to']['options']);
        $this->assertSame('jane@example.test', $payload['to']['options'][0]['email'], 'Primary first.');
        $this->assertArrayHasKey('reply_to', $payload['from']);
    }

    public function test_a_chosen_address_is_the_one_written_to(): void
    {
        Mail::fake();

        $this->client->syncEmails([
            ['email' => 'jane@example.test', 'type' => 'personal', 'is_primary' => true],
            ['email' => 'jane@work.test', 'type' => 'work'],
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Appointment Follow-up',
                'message' => 'Thank you for coming in.',
                'to' => 'jane@work.test',
            ])
            ->assertCreated();

        $this->assertSame(
            'jane@work.test',
            ClientEmailMessage::withoutGlobalScopes()->latest('id')->first()->recipient_email,
        );
    }

    /**
     * An address that is not theirs is refused.
     *
     * Otherwise a crafted request sends this client's history to a stranger
     * and files it as delivered.
     */
    public function test_an_address_the_client_does_not_own_is_refused(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Appointment Follow-up',
                'message' => 'Thank you for coming in.',
                'to' => 'somebody@else.test',
            ])
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    /** No choice made still means their primary. */
    public function test_sending_without_a_choice_uses_the_primary_address(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->postJson(route('clients.emails.store', $this->client), [
                'subject' => 'Appointment Follow-up',
                'message' => 'Thank you for coming in.',
                'to' => null,
            ])
            ->assertCreated();

        $this->assertSame(
            'jane@example.test',
            ClientEmailMessage::withoutGlobalScopes()->latest('id')->first()->recipient_email,
        );
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
