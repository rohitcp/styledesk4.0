<?php

namespace Tests\Feature;

use App\Messaging\InboundSms;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Replies to the shared StyleDesk number.
 *
 * One number carries every business's texts, so a reply arrives identifying
 * nobody: a phone number, a few words, and no clue which salon it concerns.
 * The only evidence is what was sent to that number recently.
 *
 * The rule these guard is the one that cannot be got wrong. Where the
 * evidence points at more than one business, nothing is decided — a guess
 * that lands on the wrong salon shows one business another business's client,
 * and a wrong cancellation is worse than an unanswered text.
 */
class SmsReplyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $spa;

    private Tenant $salon;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-14 10:00:00');
        config()->set('services.clicksend.webhook_secret', 'shhh');
        config()->set('services.clicksend.from', '+15550000000');

        $this->spa = $this->tenant('Smile Spa', 'spa');
        $this->salon = $this->tenant('Bella Salon', 'salon');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------- matching

    /** One business has texted this person lately, so the reply is theirs. */
    public function test_a_reply_is_matched_to_the_only_recent_conversation(): void
    {
        $booking = $this->booking($this->spa);
        $this->sent($this->spa, $booking);

        $reply = app(InboundSms::class)->record('+13055550100', 'YES');

        $this->assertSame($this->spa->getTenantKey(), $reply->tenant_id);
        $this->assertSame($booking->id, $reply->booking_id);
        $this->assertSame('yes', $reply->reply_keyword);
        $this->assertSame('handled', $reply->reply_status);

        $this->assertSame('confirmed', $booking->refresh()->client_confirmation);
        $this->assertNotNull($booking->client_confirmed_at);
    }

    /**
     * Two businesses, one "YES", no guessing.
     *
     * The whole reason this is not a lookup: a guess would confirm the wrong
     * salon's appointment and show them a client who is not theirs.
     */
    public function test_a_reply_two_businesses_could_own_is_left_for_a_person(): void
    {
        $spaBooking = $this->booking($this->spa);
        $salonBooking = $this->booking($this->salon);

        $this->sent($this->spa, $spaBooking);
        $this->sent($this->salon, $salonBooking);

        $reply = app(InboundSms::class)->record('+13055550100', 'YES');

        $this->assertNull($reply->tenant_id);
        $this->assertNull($reply->booking_id);
        $this->assertSame('needs_review', $reply->reply_status);
        /* And who it might have been, for whoever picks it up. */
        $this->assertCount(2, $reply->reply_candidates);

        /* Neither booking moved. */
        $this->assertSame('pending', $spaBooking->refresh()->client_confirmation);
        $this->assertSame('pending', $salonBooking->refresh()->client_confirmation);
    }

    /** Evidence goes stale: a text from a fortnight ago answers nothing. */
    public function test_an_old_conversation_is_not_evidence(): void
    {
        $booking = $this->booking($this->spa);
        $this->sent($this->spa, $booking, now()->subDays(14));

        $reply = app(InboundSms::class)->record('+13055550100', 'YES');

        $this->assertNull($reply->tenant_id);
        $this->assertSame('needs_review', $reply->reply_status);
    }

    /** A reply to a number nobody has texted stands alone. */
    public function test_a_reply_out_of_nowhere_is_filed_for_review(): void
    {
        $reply = app(InboundSms::class)->record('+13055559999', 'YES');

        $this->assertNull($reply->tenant_id);
        $this->assertSame('needs_review', $reply->reply_status);
        $this->assertNull($reply->reply_candidates);
    }

    // -------------------------------------------------------------- the acts

    /**
     * CANCEL asks; it never cancels.
     *
     * A booking called off by a text nobody read is a chair nobody filled —
     * and a client charged a late fee for a cancellation the salon never saw.
     */
    public function test_cancel_raises_a_request_rather_than_cancelling(): void
    {
        $booking = $this->booking($this->spa);
        $this->sent($this->spa, $booking);

        app(InboundSms::class)->record('+13055550100', 'CANCEL');

        $booking->refresh();

        $this->assertSame('cancellation_requested', $booking->client_confirmation);
        /* The appointment itself is untouched: it is still in the diary. */
        $this->assertSame('confirmed', $booking->status);
    }

    /**
     * STOP needs no conversation.
     *
     * The client has told the carrier as well, and honouring it for one
     * business only is honouring it for none.
     */
    public function test_stop_opts_out_everywhere_even_when_ambiguous(): void
    {
        $spaClient = $this->client($this->spa);
        $salonClient = $this->client($this->salon);

        $this->sent($this->spa, $this->booking($this->spa));
        $this->sent($this->salon, $this->booking($this->salon));

        $reply = app(InboundSms::class)->record('+13055550100', 'STOP');

        /* Ambiguous as to which booking, and acted on regardless. */
        $this->assertSame('handled', $reply->reply_status);

        foreach ([$spaClient, $salonClient] as $client) {
            $client->refresh();
            $this->assertNotNull($client->sms_opted_out_at);
            $this->assertFalse((bool) $client->comm_sms);
            $this->assertFalse((bool) $client->marketing_sms);
            $this->assertSame('sms_reply', $client->sms_opt_out_source);
        }
    }

    /** "YES" is an answer to a booking, not a request to be re-subscribed. */
    public function test_yes_does_not_undo_an_opt_out(): void
    {
        $client = $this->client($this->spa, ['sms_opted_out_at' => now(), 'comm_sms' => false]);
        $this->sent($this->spa, $this->booking($this->spa));

        app(InboundSms::class)->record('+13055550100', 'YES');

        $this->assertNotNull($client->refresh()->sms_opted_out_at);
    }

    /** Free text is nobody's keyword. It is filed for a person to read. */
    public function test_a_free_text_reply_is_filed_for_review(): void
    {
        $booking = $this->booking($this->spa);
        $this->sent($this->spa, $booking);

        $reply = app(InboundSms::class)->record('+13055550100', "I'm running 10 minutes late.");

        /* Matched to the conversation — that part is not in doubt — but
           nobody has decided what it means. */
        $this->assertSame($this->spa->getTenantKey(), $reply->tenant_id);
        $this->assertSame($booking->id, $reply->booking_id);
        $this->assertNull($reply->reply_keyword);
        $this->assertSame('needs_review', $reply->reply_status);
        $this->assertSame('pending', $booking->refresh()->client_confirmation);
    }

    // ------------------------------------------------------------ the webhook

    public function test_the_inbound_webhook_records_a_reply(): void
    {
        $booking = $this->booking($this->spa);
        $this->sent($this->spa, $booking);

        $this->postJson($this->url('inbound'), [
            'from' => '+13055550100',
            'body' => 'YES',
            'message_id' => 'cs_1',
        ])->assertOk();

        $this->assertSame('confirmed', $booking->refresh()->client_confirmation);
    }

    /** A carrier retries anything that is not a 2xx. */
    public function test_the_same_reply_twice_is_acted_on_once(): void
    {
        $booking = $this->booking($this->spa);
        $this->sent($this->spa, $booking);

        $payload = ['from' => '+13055550100', 'body' => 'CANCEL', 'message_id' => 'cs_2'];

        $this->postJson($this->url('inbound'), $payload)->assertOk();
        $this->postJson($this->url('inbound'), $payload)->assertOk();

        $this->assertSame(1, SmsMessage::withoutGlobalScopes()->where('direction', 'inbound')->count());
    }

    /** The address is the credential, and a wrong one is not admitted to. */
    public function test_a_webhook_without_the_secret_is_not_found(): void
    {
        $this->postJson(route('webhooks.clicksend.inbound', ['secret' => 'wrong']), [
            'from' => '+13055550100', 'body' => 'STOP',
        ])->assertNotFound();

        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    public function test_a_delivery_receipt_settles_the_message(): void
    {
        $sent = $this->sent($this->spa, $this->booking($this->spa));

        $this->postJson($this->url('delivery'), [
            'message_id' => 'cs_out_1',
            'custom_string' => (string) $sent->id,
            'status' => 'delivered',
        ])->assertOk();

        $this->assertSame('delivered', $sent->refresh()->status);
        $this->assertNotNull($sent->delivered_at);
    }

    public function test_a_failed_receipt_carries_its_reason(): void
    {
        $sent = $this->sent($this->spa, $this->booking($this->spa));

        $this->postJson($this->url('delivery'), [
            'message_id' => 'cs_out_2',
            'custom_string' => (string) $sent->id,
            'status' => 'failed',
            'error_text' => 'Handset unreachable.',
        ])->assertOk();

        $sent->refresh();

        $this->assertSame('failed', $sent->status);
        $this->assertSame('Handset unreachable.', $sent->error_message);
        $this->assertTrue($sent->canRetry());
    }

    // ------------------------------------------------------------- fixtures

    private function url(string $name): string
    {
        return route('webhooks.clicksend.'.$name, ['secret' => 'shhh']);
    }

    private function tenant(string $name, string $slug): Tenant
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => $slug]);

        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        return $tenant;
    }

    /** @param array<string, mixed> $overrides */
    private function client(Tenant $tenant, array $overrides = []): Client
    {
        return Client::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $tenant->getTenantKey(),
            'client_ref' => Client::nextRef($tenant->getTenantKey()),
            'first_name' => 'John', 'last_name' => 'Smith',
            'mobile' => '+13055550100', 'status' => 'active',
        ]);
    }

    private function booking(Tenant $tenant): Booking
    {
        $location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'name' => 'Main', 'address_line1' => '1 St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'UTC',
        ]);

        $client = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->first() ?? $this->client($tenant);

        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'reference' => 'BK-'.uniqid(),
            'client_id' => $client->id,
            'location_id' => $location->id,
            'date' => '2026-09-15',
            'starts_at' => '14:00', 'ends_at' => '15:00', 'minutes' => 60,
            'status' => 'confirmed',
            'client_confirmation' => 'pending',
            'currency_code' => 'USD',
        ]);
    }

    /** An outbound message, which is the only evidence a reply has. */
    private function sent(Tenant $tenant, Booking $booking, ?Carbon $at = null): SmsMessage
    {
        return SmsMessage::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'direction' => 'outbound',
            'client_id' => $booking->client_id,
            'booking_id' => $booking->id,
            'type' => 'booking_confirmation',
            'from_number' => '+15550000000',
            'to_number' => '+13055550100',
            'body' => $tenant->name.' via StyleDesk: your appointment is confirmed.',
            'segments' => 1,
            'provider' => 'clicksend',
            'provider_message_id' => 'cs_out_'.uniqid(),
            'status' => 'sent',
            'sent_at' => $at ?? now(),
            'created_at' => $at ?? now(),
        ]);
    }
}
