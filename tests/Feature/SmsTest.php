<?php

namespace Tests\Feature;

use App\Messaging\ClickSendProvider;
use App\Messaging\MessagingService;
use App\Messaging\SmsSegments;
use App\Models\Client;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * StyleDesk SMS.
 *
 * What these guard is the difference between a message and a delivery. An API
 * that accepted a text has not put it on a phone, so nothing here calls a
 * message delivered until the carrier says so — and when it does, it must be
 * able to say so twice without anything happening twice.
 *
 * The other half is consent. A client who replied STOP has told the carrier
 * and the business, and no setting on this side outranks that.
 */
class SmsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-10 11:00:00');

        $this->tenant = Tenant::create(['name' => 'Acme Spa', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    // ---------------------------------------------------------- the counting

    /**
     * A text is charged by the segment, not by the message.
     *
     * The cliff is what matters: one character outside the seven-bit alphabet
     * moves the whole message to Unicode, where 160 characters becomes 70.
     */
    public function test_segments_are_counted_the_way_a_carrier_bills_them(): void
    {
        $this->assertSame(1, SmsSegments::count(str_repeat('a', 160)));
        $this->assertSame(2, SmsSegments::count(str_repeat('a', 161)));
        $this->assertSame(2, SmsSegments::count(str_repeat('a', 306)));
        $this->assertSame(3, SmsSegments::count(str_repeat('a', 307)));

        /* An emoji in a birthday message halves what fits. Accented Latin is
           not the example to use here — è and é are in the seven-bit
           alphabet, which is exactly the sort of thing this class exists to
           get right. */
        $unicode = SmsSegments::measure('Happy Birthday 🎉');
        $this->assertSame('unicode', $unicode['encoding']);
        $this->assertSame('gsm', SmsSegments::measure(str_repeat('é', 70))['encoding']);
        $this->assertSame(1, SmsSegments::count(str_repeat('ж', 70)));
        $this->assertSame(2, SmsSegments::count(str_repeat('ж', 71)));
    }

    /**
     * The extended characters take two slots, as they do on the wire.
     *
     * Two braces are two characters and four of the 160, which is why a
     * template full of them runs out sooner than its length suggests.
     */
    public function test_an_extended_character_costs_two(): void
    {
        $this->assertSame(4, SmsSegments::measure('{}')['characters']);
        $this->assertSame('gsm', SmsSegments::measure('{}')['encoding']);
        $this->assertSame(2, SmsSegments::count(str_repeat('[', 81)));
    }

    // ----------------------------------------------------------- the sending

    public function test_a_message_is_written_down_before_it_is_sent(): void
    {
        $client = $this->client();

        $message = app(MessagingService::class)
            ->send('+13055550100', 'Your appointment is confirmed.', 'booking_confirmation', [
                'client_id' => $client->id,
            ]);

        $this->assertNotNull($message);
        $this->assertSame('sent', $message->status);
        $this->assertSame(1, $message->segments);
        $this->assertNotNull($message->queued_at);
        $this->assertNotNull($message->sent_at);
        /* Accepted by a provider is not delivered by a carrier. */
        $this->assertNull($message->delivered_at);
    }

    /**
     * The same message is not sent twice.
     *
     * A retried job would otherwise text the client again. The unique key on
     * the event catches it, and the caller is handed the message that
     * already went rather than a second one.
     */
    public function test_an_event_key_stops_a_second_send(): void
    {
        $client = $this->client();

        $send = fn () => app(MessagingService::class)->send(
            '+13055550100', 'Your appointment is confirmed.', 'booking_confirmation',
            ['client_id' => $client->id, 'event_key' => 'booking:1:confirmation:1'],
        );

        $first = $send();
        $second = $send();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SmsMessage::withoutGlobalScopes()->count());
    }

    /**
     * A message carries the number it was sent from.
     *
     * Stamped on the record rather than read back at display time: which
     * number a message went from is a fact about that message, and switching
     * carrier next month must not rewrite last month's log.
     *
     * It comes from the active carrier, not from the business. Every salon on
     * StyleDesk sends from one shared number — which is what makes a single
     * 10DLC registration cover them all, and what makes a reply ambiguous.
     */
    public function test_a_message_records_the_number_it_was_sent_from(): void
    {
        config()->set('services.clicksend.from', '+15856651465');

        $message = app(MessagingService::class)
            ->send('+13055550100', 'Confirmed.', 'booking_confirmation');

        $this->assertSame('+15856651465', $message->from_number);
    }

    /**
     * Refused here rather than by the carrier.
     *
     * A carrier refuses a message with no source number, and the error it
     * hands back names neither the missing number nor the setting that should
     * have held it.
     */
    public function test_a_message_with_no_sender_number_is_refused_before_it_is_sent(): void
    {
        config()->set('services.clicksend.from', null);
        config()->set('services.clicksend.username', 'user');
        config()->set('services.clicksend.key', 'test-key');

        Http::fake();

        $message = (new MessagingService(new ClickSendProvider))
            ->send('+13055550100', 'Confirmed.', 'booking_confirmation');

        $this->assertSame('failed', $message->status);
        $this->assertSame('no_sender', $message->error_code);

        /* And the carrier was never troubled with it. */
        Http::assertNothingSent();
    }

    // ----------------------------------------------------------- the consent

    public function test_a_client_who_opted_out_is_not_texted(): void
    {
        $client = $this->client(['sms_opted_out_at' => now()]);

        $this->assertNull(app(MessagingService::class)->send(
            '+13055550100', 'Your appointment is confirmed.', 'booking_confirmation',
            ['client_id' => $client->id],
        ));

        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    /**
     * Marketing is a separate permission.
     *
     * A client who agreed to hear about their own appointment has not agreed
     * to a birthday greeting, and inferring one from the other is the thing
     * a carrier suspends a campaign for.
     */
    public function test_marketing_answers_to_its_own_switch(): void
    {
        $client = $this->client(['comm_sms' => true, 'marketing_sms' => false]);

        $this->assertNotNull(app(MessagingService::class)->send(
            '+13055550100', 'Confirmed.', 'booking_confirmation', ['client_id' => $client->id],
        ));

        $this->assertNull(app(MessagingService::class)->send(
            '+13055550100', 'Happy birthday!', 'birthday', ['client_id' => $client->id],
        ));
    }

    /** A walk-in handed a confirmation at the desk has no profile to consult. */
    public function test_a_message_with_no_client_is_allowed(): void
    {
        $this->assertNotNull(app(MessagingService::class)->send(
            '+13055550100', 'Confirmed.', 'booking_confirmation',
        ));
    }

    // ------------------------------------------------------------- fixtures

    /** @param array<string, mixed> $overrides */
    private function client(array $overrides = []): Client
    {
        return Client::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker',
            'mobile' => '+13055550100', 'email' => 'mia@acme.test',
            'status' => 'active',
        ]);
    }

    private function sentMessage(): SmsMessage
    {
        return SmsMessage::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'booking_confirmation',
            'to_number' => '+13055550100',
            'body' => 'Your appointment is confirmed.',
            'segments' => 1,
            'provider' => 'clicksend',
            'provider_message_id' => 'msg_abc123',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
