<?php

namespace Tests\Feature;

use App\Messaging\ClickSendProvider;
use App\Messaging\LocalSmsProvider;
use App\Messaging\NullSmsProvider;
use App\Messaging\SmsProvider;
use App\Messaging\SmsProviders;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Which carrier carries a message — and, mostly, which does not.
 *
 * The rule these exist for is the one whose failure is silent and expensive:
 * a developer's machine must never text a client. A seeded database holds a
 * thousand real-looking phone numbers, and nobody finds out until the clients
 * do — so it is not enough that local development happens to be configured
 * safely. It has to refuse.
 */
class SmsProviderResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /* Credentials present throughout, because that is the dangerous
           case: a machine that could reach a carrier if anything let it. */
        config()->set('services.clicksend.username', 'user');
        config()->set('services.clicksend.key', 'key');
        config()->set('services.clicksend.from', '+15550000000');
    }

    // ------------------------------------------------------------ the catch

    /**
     * A local machine does not reach a carrier, whatever it is told.
     *
     * Not a default that can be overridden — a refusal. This is the test that
     * would fail if somebody "helpfully" made the provider setting
     * authoritative everywhere.
     */
    public function test_local_never_reaches_a_carrier_even_when_told_to(): void
    {
        config()->set('sms.provider', 'clicksend');
        config()->set('sms.allow_live_in_local', false);

        $this->assertFalse(SmsProviders::mayReachACarrier());
        $this->assertInstanceOf(LocalSmsProvider::class, SmsProviders::resolve());
    }

    /** And nothing leaves the machine when a message is actually sent. */
    public function test_nothing_is_posted_to_a_carrier_from_local(): void
    {
        config()->set('sms.provider', 'clicksend');
        config()->set('sms.allow_live_in_local', false);
        config()->set('sms-catcher.enabled', false);

        Http::fake();

        app(SmsProvider::class)->send(new SmsMessage([
            'to_number' => '+12015551234',
            'from_number' => '+15550000000',
            'body' => 'Your appointment is confirmed.',
            'type' => 'booking_confirmation',
        ]));

        Http::assertNothingSent();
    }

    /**
     * The one way past it is deliberate, named, and defaults to off.
     *
     * A developer testing a real carrier for an afternoon, not a setting
     * anybody arrives at by accident.
     */
    public function test_a_developer_can_deliberately_switch_the_catch_off(): void
    {
        config()->set('sms.provider', 'clicksend');
        config()->set('sms.allow_live_in_local', true);

        $this->assertTrue(SmsProviders::mayReachACarrier());
        $this->assertInstanceOf(ClickSendProvider::class, SmsProviders::resolve());
    }

    /** And it is off unless somebody says otherwise. */
    public function test_the_catch_is_on_by_default(): void
    {
        $this->assertFalse((bool) config('sms.allow_live_in_local'));
    }

    // -------------------------------------------------------- the selection

    /**
     * Anywhere that may reach a carrier uses the one it was told to.
     *
     * Faked as production, because that is the only environment where the
     * setting is consulted at all.
     */
    public function test_production_uses_the_configured_provider(): void
    {
        app()['env'] = 'production';

        config()->set('sms.provider', 'clicksend');
        $this->assertInstanceOf(ClickSendProvider::class, SmsProviders::resolve());
    }

    /** Switching provider changes what the next message goes out on. */
    public function test_switching_the_provider_switches_the_carrier(): void
    {
        app()['env'] = 'production';

        config()->set('sms.provider', 'clicksend');
        $this->assertSame('clicksend', SmsProviders::resolve()->name());

        config()->set('sms.provider', 'disabled');
        $this->assertSame('disabled', SmsProviders::resolve()->name());
    }

    /** Both carriers are selectable, and each is reached when chosen. */
    public function test_either_carrier_can_be_chosen(): void
    {
        app()['env'] = 'production';
        config()->set('services.telnyx.key', 'tn-key');
        config()->set('services.telnyx.from', '+15856651465');

        config()->set('sms.provider', 'telnyx');
        $this->assertSame('telnyx', SmsProviders::resolve()->name());
        /* And the sender follows the carrier: pointing at Telnyx while still
           sending a ClickSend number is a message refused for a source that
           carrier has never heard of. */
        $this->assertSame('+15856651465', SmsProviders::senderNumber());

        config()->set('sms.provider', 'clicksend');
        $this->assertSame('clicksend', SmsProviders::resolve()->name());
        $this->assertSame('+15550000000', SmsProviders::senderNumber());
    }

    /** Telnyx is caught by the local rule too, not just ClickSend. */
    public function test_telnyx_is_also_refused_from_local(): void
    {
        config()->set('sms.provider', 'telnyx');
        config()->set('services.telnyx.key', 'tn-key');
        config()->set('sms.allow_live_in_local', false);

        $this->assertInstanceOf(LocalSmsProvider::class, SmsProviders::resolve());
    }

    /**
     * Switched off refuses; it does not quietly swallow.
     *
     * A provider that accepted and discarded would leave a business believing
     * its clients were texted, and the log would read `sent` over a message
     * that never existed.
     */
    public function test_disabled_refuses_rather_than_pretending(): void
    {
        app()['env'] = 'production';
        config()->set('sms.provider', 'disabled');

        Http::fake();

        $result = (new NullSmsProvider)->send(new SmsMessage([
            'to_number' => '+12015551234',
            'body' => 'Your appointment is confirmed.',
        ]));

        $this->assertFalse($result->accepted);
        $this->assertSame('sms_disabled', $result->errorCode);
        Http::assertNothingSent();
    }

    /** An unrecognised provider name is off, not a guess. */
    public function test_an_unknown_provider_is_treated_as_off(): void
    {
        app()['env'] = 'production';
        config()->set('sms.provider', 'carrier-pigeon');

        $this->assertInstanceOf(NullSmsProvider::class, SmsProviders::resolve());
    }
}
