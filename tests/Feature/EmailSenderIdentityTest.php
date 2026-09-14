<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantGmailConnection;
use App\Support\EmailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who a StyleDesk email comes from, and where a reply goes.
 *
 * The settings existed and reached almost nothing: a business could set a
 * sender name and a reply-to address and watch neither appear on a booking
 * confirmation, a cancellation, a review request or a payment link. A client
 * hitting Reply was writing to StyleDesk's noreply.
 */
class EmailSenderIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Smile Spa',
            'slug' => 'smile-spa-'.bin2hex(random_bytes(3)),
            'country_code' => 'US',
        ], $overrides));
    }

    /** A business sending through its own Gmail, connected and working. */
    private function connectedToGmail(array $overrides = []): Tenant
    {
        config(['client_email.providers.gmail.available' => true]);

        $tenant = $this->tenant(array_merge(['email_provider' => 'gmail'], $overrides));

        TenantGmailConnection::create([
            'tenant_id' => $tenant->getTenantKey(),
            'email' => 'bella@bellastudio.test',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'access_expires_at' => now()->addHour(),
            'status' => TenantGmailConnection::STATUS_CONNECTED,
            'connected_at' => now(),
        ]);

        return $tenant;
    }

    public function test_the_business_name_is_what_the_client_sees(): void
    {
        $tenant = $this->tenant(['email_sender_name' => 'Smile Spa Reception']);

        $this->assertSame('Smile Spa Reception', EmailSender::for($tenant)['name']);
    }

    /** Nothing set falls back to the business's own name, never to blank. */
    public function test_a_business_that_set_no_sender_name_uses_its_own(): void
    {
        $this->assertSame('Smile Spa', EmailSender::for($this->tenant())['name']);
    }

    /**
     * The From address is StyleDesk's and stays StyleDesk's.
     *
     * Claiming to send from the salon's domain without being authorised to
     * sign for it lands the message in spam, if it leaves at all — which is
     * why the settings screen shows it rather than offering a box.
     */
    public function test_the_from_address_is_styledesks_own_on_smtp(): void
    {
        $tenant = $this->tenant(['email_reply_to' => 'hello@myspa.test']);

        $this->assertSame(config('mail.from.address'), EmailSender::for($tenant)['address']);
    }

    /** And the name carries the qualifier, because the inbox will show it. */
    public function test_the_from_header_says_via_styledesk_on_its_own_address(): void
    {
        $tenant = $this->tenant(['email_sender_name' => 'Smile Spa']);

        $this->assertSame(
            __('client_email.send.from_via', ['name' => 'Smile Spa']),
            EmailSender::fromAddress($tenant)->name,
        );
    }

    public function test_a_reply_goes_where_the_business_said(): void
    {
        $tenant = $this->tenant(['email_reply_to' => 'hello@myspa.test']);

        $replyTo = EmailSender::replyTo($tenant);

        $this->assertCount(1, $replyTo);
        $this->assertSame('hello@myspa.test', $replyTo[0]->address);
    }

    /**
     * No reply-to set is no reply-to header.
     *
     * An empty array is how a Mailable is told there is nobody to reply to;
     * an address invented here would be one the business never chose.
     */
    public function test_no_reply_to_set_adds_no_header(): void
    {
        $this->assertSame([], EmailSender::replyTo($this->tenant()));
    }

    /**
     * A connected mailbox is the business's own address.
     *
     * The whole point of connecting one: the salon genuinely is the sender,
     * so the address is theirs and a client pressing Reply writes back into
     * the inbox they are watching.
     */
    public function test_a_connected_mailbox_sends_from_its_own_address(): void
    {
        $tenant = $this->connectedToGmail();

        $this->assertSame('bella@bellastudio.test', EmailSender::for($tenant)['address']);
    }

    /** And drops the qualifier, because "via StyleDesk" would be a lie. */
    public function test_a_connected_mailbox_is_not_qualified_with_via(): void
    {
        $tenant = $this->connectedToGmail(['email_sender_name' => 'Bella Studio']);

        $this->assertSame('Bella Studio', EmailSender::fromAddress($tenant)->name);
    }

    /**
     * Connected and broken is not connected.
     *
     * Falling back to StyleDesk's address is what keeps the business sending
     * while they reconnect; claiming their address on a connection that
     * cannot send would put nothing in anybody's inbox.
     */
    public function test_a_connection_needing_attention_falls_back_to_styledesk(): void
    {
        $tenant = $this->connectedToGmail();

        TenantGmailConnection::query()->where('tenant_id', $tenant->getTenantKey())
            ->update(['status' => TenantGmailConnection::STATUS_NEEDS_ATTENTION]);

        $sender = EmailSender::for($tenant->fresh());

        $this->assertSame(config('mail.from.address'), $sender['address']);
    }

    /** Chosen but never connected is the same answer. */
    public function test_gmail_chosen_without_a_mailbox_falls_back_to_styledesk(): void
    {
        config(['client_email.providers.gmail.available' => true]);

        $tenant = $this->tenant(['email_provider' => 'gmail']);

        $this->assertSame(config('mail.from.address'), EmailSender::for($tenant)['address']);
    }

    public function test_nothing_at_all_still_answers(): void
    {
        $sender = EmailSender::for(null);

        $this->assertNotSame('', $sender['name']);
        $this->assertSame(config('mail.from.address'), $sender['address']);
        $this->assertNull($sender['reply_to']);
    }
}
