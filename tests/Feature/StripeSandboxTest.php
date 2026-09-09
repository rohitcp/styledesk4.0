<?php

namespace Tests\Feature;

use App\Models\StripeWebhookEvent;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\TenantStripeAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test money and real money, and the record of what Stripe told us.
 *
 * The rule this file exists to hold: whether a connection moves real money is
 * decided by the KEY, not by a setting. `sk_test_` cannot charge a real card
 * and `sk_live_` cannot avoid it, so sandbox is not something a salon can
 * flip. A badge that claimed otherwise would be the worst lie on a payments
 * screen — a business believing it was testing while charging clients.
 */
class StripeSandboxTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);
    }

    private function owner(): User
    {
        $user = User::create([
            'first_name' => 'Emma', 'last_name' => 'Martin',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    /**
     * An account without saving it.
     *
     * One Stripe account per business, so anything comparing two keys has to
     * work on unsaved models — and everything these assert reads off the key
     * rather than the row.
     */
    private function unsaved(array $overrides = []): TenantStripeAccount
    {
        return new TenantStripeAccount($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'mode' => TenantStripeAccount::MODE_OWN,
            'stripe_account_id' => 'acct_test123',
            'api_key' => 'sk_test_abc123',
            'charges_enabled' => true,
            'details_submitted' => true,
        ]);
    }

    private function account(array $overrides = []): TenantStripeAccount
    {
        return TenantStripeAccount::create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'mode' => TenantStripeAccount::MODE_OWN,
            'stripe_account_id' => 'acct_test123',
            'api_key' => 'sk_test_abc123',
            'business_name' => 'Smile Spa',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'connected_at' => now(),
        ]);
    }

    // ---------------------------------------------------- test versus live

    public function test_a_test_key_is_sandbox_and_a_live_key_is_not(): void
    {
        $this->assertTrue($this->unsaved()->isSandbox());

        $live = $this->unsaved(['api_key' => 'sk_live_abc123']);

        $this->assertTrue($live->isLive());
        $this->assertFalse($live->isSandbox());
    }

    public function test_a_restricted_key_is_read_the_same_way(): void
    {
        $this->assertTrue($this->unsaved(['api_key' => 'rk_live_abc'])->isLive());
        $this->assertTrue($this->unsaved(['api_key' => 'rk_test_abc'])->isSandbox());
    }

    /* The safe direction for a wrong guess is a business told it is in
       sandbox when it is not — they will check — rather than one told it is
       live when it is testing, which they will not. */
    public function test_an_unrecognised_key_is_treated_as_sandbox(): void
    {
        $this->assertTrue($this->unsaved(['api_key' => 'something_else'])->isSandbox());
    }

    /* Sandbox outranks connected, because it is the more important half of
       the sentence: "Connected" where no real money can move is the reading
       that gets a salon to open for business on test keys. */
    public function test_a_working_test_connection_reads_as_sandbox_not_connected(): void
    {
        $this->assertSame('sandbox', $this->unsaved()->statusKey());

        $live = $this->unsaved(['api_key' => 'sk_live_abc']);

        $this->assertSame('connected', $live->statusKey());
    }

    public function test_an_unverified_account_still_reads_as_needing_attention(): void
    {
        $account = $this->unsaved(['charges_enabled' => false, 'details_submitted' => true]);

        /* Sandbox is not an excuse to say "connected" about an account Stripe
           will not let charge. */
        $this->assertSame('needs_attention', $account->statusKey());
    }

    public function test_the_settings_screen_says_it_is_a_sandbox(): void
    {
        $this->account();

        $this->actingAs($this->owner())
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertSee(__('payments.stripe.sandbox_notice'));
    }

    public function test_a_live_connection_carries_no_sandbox_warning(): void
    {
        $this->account(['api_key' => 'sk_live_abc']);

        $this->actingAs($this->owner())
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertDontSee(__('payments.stripe.sandbox_notice'));
    }

    /* Two connection models, named on the screen so an owner can tell which
       one they are on. */
    public function test_the_screen_names_which_stripe_is_processing(): void
    {
        $this->account();

        $this->actingAs($this->owner())
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertSee(__('payments.stripe.provider_own'));
    }

    // --------------------------------------------------- the secret itself

    /* A Stripe secret can charge, refund and read every customer on the
       account. It is never rendered, never logged, never in an exception. */
    public function test_the_secret_key_never_reaches_a_screen(): void
    {
        $this->account(['api_key' => 'sk_test_supersecretvalue']);

        $this->actingAs($this->owner())
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertDontSee('sk_test_supersecretvalue');

        /* Nor through the model's own serialisation. */
        $this->assertArrayNotHasKey('api_key', TenantStripeAccount::first()->toArray());
    }

    // ------------------------------------------------------- the event log

    public function test_the_log_can_hold_what_stripe_sent(): void
    {
        foreach (['event_id', 'event_type', 'status', 'error', 'attempts', 'received_at', 'processed_at'] as $column) {
            $this->assertContains($column, Schema::getColumnListing('stripe_webhook_events'));
        }
    }

    /* Stripe delivers at least once and retries on anything that is not a
       2xx, so the same event id arriving twice is normal — and has to be
       recognised as one event rather than processed twice. */
    public function test_the_same_event_delivered_twice_is_one_record(): void
    {
        $first = StripeWebhookEvent::begin('evt_123', 'payment_intent.succeeded', 'acct_1');
        $second = StripeWebhookEvent::begin('evt_123', 'payment_intent.succeeded', 'acct_1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, StripeWebhookEvent::count());
        /* Counted on every delivery: four attempts and no completion is the
           shape of a problem. */
        $this->assertSame(2, $second->attempts);
    }

    public function test_an_event_records_how_it_ended(): void
    {
        $event = StripeWebhookEvent::begin('evt_ok', 'account.updated');
        $event->complete($this->tenant->getTenantKey());

        $this->assertSame('completed', $event->fresh()->status);
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame($this->tenant->getTenantKey(), $event->fresh()->tenant_id);
        $this->assertTrue($event->fresh()->isSettled());
    }

    /* Nothing to do with it is an outcome, not a failure. Logging the events
       StyleDesk has no opinion about as errors would bury the ones that
       matter. */
    public function test_an_event_nobody_handles_is_ignored_not_failed(): void
    {
        $event = StripeWebhookEvent::begin('evt_noop', 'invoice.upcoming');
        $event->ignore(__('payments.webhooks.unhandled'));

        $this->assertSame('ignored', $event->fresh()->status);
        $this->assertTrue($event->fresh()->isSettled());
        $this->assertSame(0, StripeWebhookEvent::unresolved()->count());
    }

    public function test_a_failure_is_kept_for_somebody_to_read(): void
    {
        $event = StripeWebhookEvent::begin('evt_bad', 'charge.refunded');
        $event->fail('No such payment intent: pi_missing');

        $event = $event->fresh();

        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('pi_missing', $event->error);
        $this->assertFalse($event->isSettled());
        $this->assertSame(1, StripeWebhookEvent::unresolved()->count());
    }
}
