<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\Tenant;
use App\Payments\ManualGateway;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payment gateway seam.
 *
 * The brief's key architectural decision: StyleDesk owns the payment
 * experience and a processor handles card data, verification and payouts.
 * What these tests protect is that nothing outside App\Payments names a
 * processor — so Stripe, and later Square, can be added without touching
 * Booking, Checkout, Sales or Client.
 */
class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile-pay']);
        tenancy()->initialize($this->tenant);
    }

    private function booking(int $totalMinor = 20000): Booking
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Sarah', 'last_name' => 'Mitchell',
        ]);

        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $client->id,
            'date' => now()->toDateString(),
            'starts_at' => '10:00',
            'minutes' => 60,
            'status' => 'confirmed',
            'total_minor' => $totalMinor,
            'currency_code' => 'USD',
        ]);
    }

    private function gateway(): PaymentGateway
    {
        return app(PaymentGatewayManager::class)->for($this->tenant);
    }

    // ------------------------------------------------------- the resolver

    /** A business with no processor still has a gateway. */
    public function test_a_business_without_a_processor_records_rather_than_nothing(): void
    {
        $this->assertInstanceOf(ManualGateway::class, $this->gateway());
        $this->assertTrue($this->gateway()->isReady());

        /* It does not move money, and says so — which is what decides whether
           the checkout asks for card details. */
        $this->assertFalse($this->gateway()->processes());
    }

    /**
     * A processor chosen but not connected must not stop the desk taking cash.
     *
     * Falling back to nothing would take a salon offline the moment somebody
     * half-finished an onboarding form.
     */
    public function test_an_unready_processor_falls_back_to_recording(): void
    {
        $this->tenant->forceFill(['payment_gateway' => 'stripe'])->save();

        $this->assertInstanceOf(ManualGateway::class, $this->gateway());
    }

    /** Every gateway is listed, connected or not, so none is hidden. */
    public function test_the_catalogue_lists_every_gateway(): void
    {
        $keys = collect(app(PaymentGatewayManager::class)->all())
            ->map(fn (PaymentGateway $g) => $g->key());

        $this->assertContains('manual', $keys->all());
    }

    // -------------------------------------------------------- gateway ≠ method

    /**
     * A method nobody can hand over is not offered without a processor.
     *
     * Nobody walks in holding an Apple Pay.
     */
    public function test_gateway_only_methods_are_not_offered_by_the_recorder(): void
    {
        $methods = $this->gateway()->methods();

        $this->assertContains('cash', $methods);
        $this->assertContains('card', $methods);
        $this->assertNotContains('apple_pay', $methods);
        $this->assertNotContains('google_pay', $methods);
    }

    // ------------------------------------------------------------ charging

    public function test_recording_a_payment_writes_the_transaction_and_settles_the_booking(): void
    {
        $booking = $this->booking(20000);

        $payment = $this->gateway()->charge($booking, new PaymentRequest(
            amountMinor: 20000,
            method: 'cash',
            receivedMinor: 25000,
        ));

        $this->assertSame(20000, $payment->amount_minor);
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);

        /* The booking's own status moves with it, or the desk chases a bill
           that is already settled. */
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    /** The tip stays beside the bill, never folded into it. */
    public function test_a_tip_is_recorded_apart_from_the_amount(): void
    {
        $booking = $this->booking(20000);

        $payment = $this->gateway()->charge($booking, new PaymentRequest(
            amountMinor: 20000,
            method: 'card',
            tipMinor: 4000,
        ));

        $this->assertSame(20000, $payment->amount_minor);
        $this->assertSame(4000, $payment->tip_minor);

        /* A total that has absorbed a tip can never be taken apart again. */
        $this->assertSame(20000, (int) $booking->fresh()->paid_minor);
    }

    /** Change is worked out against the whole handover, tip included. */
    public function test_change_accounts_for_the_tip(): void
    {
        $request = new PaymentRequest(
            amountMinor: 8000,
            method: 'cash',
            tipMinor: 2000,
            receivedMinor: 10000,
        );

        $this->assertSame(10000, $request->totalMinor());
        $this->assertSame(0, $request->changeMinor());
    }

    public function test_a_part_payment_leaves_the_balance_owing(): void
    {
        $booking = $this->booking(20000);

        $this->gateway()->charge($booking, new PaymentRequest(amountMinor: 5000, method: 'cash'));

        $booking->refresh();

        $this->assertSame('partial', $booking->payment_status);
        $this->assertSame(15000, $booking->dueMinor());
    }

    /** Split payments are separate transactions against one booking. */
    public function test_a_split_payment_is_two_transactions_on_one_booking(): void
    {
        $booking = $this->booking(20000);

        $this->gateway()->charge($booking, new PaymentRequest(amountMinor: 5000, method: 'gift_card'));
        $this->gateway()->charge($booking, new PaymentRequest(amountMinor: 15000, method: 'card'));

        $booking->refresh()->load('payments');

        $this->assertCount(2, $booking->payments);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(0, $booking->dueMinor());
    }

    // ------------------------------------------------------------ refunds

    /**
     * A refund is a second row, never an edit to the first.
     *
     * The original payment happened, and a history that rewrites it cannot
     * answer "what did we actually take in March".
     */
    public function test_a_refund_is_recorded_beside_the_payment_it_reverses(): void
    {
        $booking = $this->booking(20000);

        $payment = $this->gateway()->charge($booking, new PaymentRequest(amountMinor: 20000, method: 'card'));

        $refund = $this->gateway()->refund($payment, 5000, 'Service issue', 'Client unhappy with the add-on.');

        $this->assertSame('refunded', $refund->status);
        $this->assertSame(5000, $refund->amount_minor);
        $this->assertStringContainsString('Service issue', $refund->note);

        /* Both rows survive. */
        $this->assertSame(2, BookingPayment::withoutGlobalScopes()->where('booking_id', $booking->id)->count());
        $this->assertSame('paid', $payment->fresh()->status);
    }

    /** A recorder cannot offer a link, because it cannot take the money. */
    public function test_the_recorder_offers_no_payment_link(): void
    {
        $this->assertNull($this->gateway()->paymentLink($this->booking(), 20000));
    }

    // ------------------------------------------------------ the abstraction

    /**
     * Nothing outside App\Payments names a processor.
     *
     * This is the decision the brief calls key, and the one that quietly
     * erodes: one `Stripe::` in a controller and the seam is gone.
     */
    public function test_no_module_outside_the_payments_namespace_names_a_processor(): void
    {
        $offenders = [];

        foreach (['app/Http/Controllers', 'app/Models', 'app/Support'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($directory)));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (preg_match('/\b(Stripe|Square|Adyen)\\\\/', $contents)) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'A processor is named outside App\Payments: '.implode(', ', $offenders));
    }
}
