<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Http\Controllers\SalesController;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientTag;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\SalesPeriod;
use App\Support\SalesSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sales — what was sold, what was collected, what is still owed.
 *
 * The figures are the point of the screen, and the one that matters most is
 * that they reconcile: a business reading "collected $100" under "total
 * $200" must find $100 outstanding, or it stops trusting the page.
 */
class SalesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Serenity Spa', 'slug' => 'serenity-sales']);

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

    private function booking(int $totalMinor, int $paidMinor = 0, ?string $date = null): Booking
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Sarah', 'last_name' => 'Mitchell',
        ]);

        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $client->id,
            'date' => $date ?? now()->toDateString(),
            'starts_at' => '10:00',
            'minutes' => 60,
            'status' => 'confirmed',
            'total_minor' => $totalMinor,
            'paid_minor' => $paidMinor,
            'currency_code' => 'USD',
        ]);
    }

    private function payment(Booking $booking, int $minor, array $attributes = []): BookingPayment
    {
        return BookingPayment::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'booking_id' => $booking->id,
            'method' => 'cash',
            'status' => 'paid',
            'amount_minor' => $minor,
            'currency_code' => 'USD',
            'paid_at' => now(),
        ]);
    }

    /**
     * A membership sale: the plan, the client who bought it, and the money.
     *
     * Built directly rather than through the till — this is a test about the
     * ledger, and a purchase would bring a payment method, a card and a
     * settings row into it.
     */
    private function membershipSale(int $minor, array $attributes = [], string $type = 'package'): MembershipPayment
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amara', 'last_name' => 'Diallo',
        ]);

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => $type,
            'name' => 'Massage 4-Pack',
            'internal_code' => 'PKG-20260909-0001',
            'price_minor' => $minor,
            'billing_frequency' => $type === 'recurring' ? 'monthly' : null,
            'location_mode' => 'all',
            'sell_in_store' => true,
            'is_draft' => false,
        ]);

        $membership = ClientMembership::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'type' => $type,
            'price_minor' => $minor,
            'currency_code' => 'USD',
            'billing_frequency' => $type === 'recurring' ? 'monthly' : null,
        ]);

        return MembershipPayment::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_membership_id' => $membership->id,
            'method' => 'card',
            'status' => 'paid',
            'amount_minor' => $minor,
            'currency_code' => 'USD',
            'purpose' => 'initial',
            'paid_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ the page

    public function test_the_sales_page_opens(): void
    {
        $this->actingAs($this->owner)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee(__('sales.widgets.total_sales'))
            ->assertSee(__('sales.widgets.outstanding'))
            ->assertSee(__('sales.transactions'));
    }

    public function test_it_is_reachable_from_the_main_navigation(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('sales.index'), false);
    }

    public function test_a_role_without_the_permission_is_refused(): void
    {
        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->first();

        app(ProvisionSystemRoles::class)->syncPermissions($role, ['calendar.view' => 'own']);

        $member = User::create([
            'first_name' => 'Sam', 'last_name' => 'Reid',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $member->markEmailAsVerified();
        $member->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $member->id, 'role_id' => $role->id,
            'first_name' => 'Sam', 'last_name' => 'Reid',
        ]);

        $this->actingAs($member->fresh())->get(route('sales.index'))->assertForbidden();
    }

    // ------------------------------------------------------------ the sums

    /**
     * The figures have to reconcile.
     *
     * A business reading "collected $100" under "total $200" must find $100
     * outstanding, or it stops trusting the page.
     */
    public function test_the_figures_reconcile(): void
    {
        $booking = $this->booking(20000, 10000);
        $this->payment($booking, 10000, ['tip_minor' => 2000]);

        $summary = SalesSummary::for(SalesPeriod::preset('month'));

        $this->assertSame(20000, $summary['total_sales']['value']);
        $this->assertSame(10000, $summary['collected']['value']);
        $this->assertSame(10000, $summary['outstanding']['value']);
        $this->assertSame(2000, $summary['tips']['value']);
        $this->assertSame(1, $summary['transactions']['count']);
    }

    public function test_refunds_are_counted_separately_from_payments(): void
    {
        $booking = $this->booking(20000, 20000);
        $this->payment($booking, 20000);
        $this->payment($booking, 5000, ['status' => 'refunded']);

        $summary = SalesSummary::for(SalesPeriod::preset('month'));

        $this->assertSame(20000, $summary['collected']['value']);
        $this->assertSame(5000, $summary['refunds']['value']);
    }

    /**
     * An overpayment on one booking is not credit against another.
     *
     * Summing the raw difference would let it quietly cancel a real debt
     * somewhere else in the period.
     */
    public function test_an_overpaid_booking_does_not_cancel_another_bookings_debt(): void
    {
        $this->booking(10000, 15000);
        $this->booking(10000, 0);

        $this->assertSame(10000, SalesSummary::for(SalesPeriod::preset('month'))['outstanding']['value']);
    }

    public function test_a_period_with_nothing_to_compare_against_reports_no_change(): void
    {
        $this->booking(20000);

        $this->assertNull(SalesSummary::for(SalesPeriod::preset('month'))['total_sales']['change']);
    }

    public function test_the_previous_period_is_the_same_length(): void
    {
        $period = SalesPeriod::fromRequest('custom', '2026-09-01', '2026-09-07');
        $previous = $period->previous();

        $this->assertSame('2026-08-25', $previous->from->toDateString());
        $this->assertSame('2026-08-31', $previous->to->toDateString());
    }

    /** A nonsense range falls back rather than showing an empty page. */
    public function test_a_backwards_custom_range_falls_back(): void
    {
        $period = SalesPeriod::fromRequest('custom', '2026-09-30', '2026-09-01');

        $this->assertSame('month', $period->preset);
    }

    // --------------------------------------------------------- the drawer

    /**
     * View booking opens the drawer over the table rather than navigating.
     *
     * A bookkeeper checks four transactions in a row; leaving the page would
     * throw away the date range, the filters and their place in the list each
     * time.
     */
    public function test_the_action_menu_opens_the_booking_in_a_drawer(): void
    {
        $booking = $this->booking(10000, 10000);
        $this->payment($booking, 10000);

        $row = $this->actingAs($this->owner)
            ->getJson(route('sales.data'))
            ->assertOk()
            ->json('data.0');

        $drawer = collect($row['menu'])->firstWhere('label', __('sales.actions.view_booking'));

        $this->assertNotNull($drawer);
        /* Announced as an event, not a link: what "view booking" means is the
           page's business, not the grid's. */
        $this->assertSame('sales:booking', $drawer['event']);
        $this->assertSame(route('bookings.drawer', $booking), $drawer['payload']['url']);

        /* And the full page is still offered, for a reader who wants it. */
        $this->assertNotNull(collect($row['menu'])->firstWhere('label', __('sales.actions.open_booking')));
    }

    public function test_the_sales_page_renders_the_drawer_shell(): void
    {
        $this->actingAs($this->owner)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee('data-booking-sheet', false)
            /* Every label the renderer reads must resolve, or the panel prints
               "undefined" where a heading belongs. */
            ->assertSee(__('bookings.detail.transactions'), false)
            ->assertSee(__('clients.module.workspace.bookings.view_full'), false)
            ->assertDontSee('undefined');
    }

    /** The drawer endpoint answers for a booking reached from Sales. */
    public function test_the_drawer_endpoint_answers(): void
    {
        $booking = $this->booking(10000, 5000);

        $this->actingAs($this->owner)
            ->getJson(route('bookings.drawer', $booking))
            ->assertOk()
            ->assertJsonStructure(['name', 'reference', 'status', 'sections', 'urls']);
    }

    /** The full booking page opens in a tab of its own, safely. */
    public function test_open_booking_page_opens_in_a_new_tab(): void
    {
        $booking = $this->booking(10000, 10000);
        $this->payment($booking, 10000);

        $entry = collect($this->actingAs($this->owner)->getJson(route('sales.data'))->json('data.0.menu'))
            ->firstWhere('label', __('sales.actions.open_booking'));

        $this->assertSame(route('bookings.show', $booking), $entry['url']);
        $this->assertSame('_blank', $entry['target']);
    }

    public function test_view_client_and_view_receipt_open_in_the_drawer(): void
    {
        $booking = $this->booking(10000, 10000);
        $payment = $this->payment($booking, 10000);

        $menu = collect($this->actingAs($this->owner)->getJson(route('sales.data'))->json('data.0.menu'));

        $client = $menu->firstWhere('label', __('sales.actions.view_client'));
        $receipt = $menu->firstWhere('label', __('sales.actions.view_receipt'));

        $this->assertSame('sales:client', $client['event']);
        $this->assertSame(route('sales.client-drawer', $booking->client), $client['payload']['url']);

        $this->assertSame('sales:receipt', $receipt['event']);
        $this->assertSame(route('sales.receipt-drawer', $payment), $receipt['payload']['url']);
    }

    /** The client drawer answers in the shape the shared panel renders. */
    public function test_the_client_drawer_answers_with_what_they_owe(): void
    {
        $booking = $this->booking(20000, 5000);
        $this->payment($booking, 5000);

        $response = $this->actingAs($this->owner)
            ->getJson(route('sales.client-drawer', $booking->client))
            ->assertOk()
            ->assertJsonStructure(['name', 'reference', 'status', 'sections', 'urls'])
            ->assertJsonPath('urls.show', route('clients.show', $booking->client));

        /* The four figures are cards, drawn with the same classes and tones
           as the client's own profile — not label-and-value rows. */
        $metrics = collect($response->json('metrics'));

        $this->assertSame(
            [
                __('clients.module.workspace.summary.next_appointment'),
                __('clients.module.workspace.summary.total_visits'),
                __('clients.module.workspace.summary.lifetime_spend'),
                __('sales.drawer.owed'),
            ],
            $metrics->pluck('label')->all(),
        );

        $this->assertSame(['violet', 'teal', 'amber', 'blue'], $metrics->pluck('tone')->all());

        /* Across every appointment, not only this transaction's — which is
           what somebody opening a client from Sales is asking. */
        $this->assertSame('$150.00', $metrics->firstWhere('label', __('sales.drawer.owed'))['value']);
    }

    /**
     * The status badge shows a word, not a translation key.
     *
     * `clients.statuses.<status>.label` is not a key that exists, and __()
     * hands back what it cannot find — so the badge read
     * "clients.statuses.active.label".
     */
    public function test_the_client_drawer_shows_a_readable_status(): void
    {
        $booking = $this->booking(10000);

        $response = $this->actingAs($this->owner)
            ->getJson(route('sales.client-drawer', $booking->client))
            ->assertOk();

        $this->assertSame($booking->client->statusLabel(), $response->json('status'));
        $this->assertStringNotContainsString('clients.statuses', $response->json('status'));
    }

    /**
     * Nothing owed says so, rather than showing $0.00.
     *
     * A figure reads as a debt, and this is the card somebody scans for a
     * problem.
     */
    public function test_a_settled_client_shows_nothing_owed_rather_than_zero(): void
    {
        $booking = $this->booking(10000, 10000);

        $owed = collect($this->actingAs($this->owner)
            ->getJson(route('sales.client-drawer', $booking->client))
            ->json('metrics'))
            ->firstWhere('label', __('sales.drawer.owed'));

        $this->assertNull($owed['value']);
        $this->assertSame(__('sales.drawer.nothing_owed'), $owed['empty']);
    }

    /** Tags appear only when the client has any. */
    public function test_client_tags_are_shown_when_there_are_some(): void
    {
        $booking = $this->booking(10000);

        $this->assertNull(
            collect($this->actingAs($this->owner)
                ->getJson(route('sales.client-drawer', $booking->client))
                ->json('sections'))
                ->firstWhere('title', __('sales.drawer.tags')),
        );

        /* firstOrCreate: a business is provisioned with a starter set of
           tags, and the label is unique per tenant. */
        $tag = ClientTag::withoutGlobalScopes()->firstOrCreate([
            'tenant_id' => $this->tenant->getTenantKey(),
            'label' => 'Sales drawer tag',
        ]);
        $booking->client->tags()->attach($tag->id);

        $tags = collect($this->actingAs($this->owner)
            ->getJson(route('sales.client-drawer', $booking->client))
            ->json('sections'))
            ->firstWhere('title', __('sales.drawer.tags'));

        $this->assertSame('Sales drawer tag', $tags['rows'][__('sales.drawer.tags')]);
    }

    public function test_the_receipt_drawer_offers_the_page_and_a_copy(): void
    {
        $booking = $this->booking(20000, 20000);
        $payment = $this->payment($booking, 20000, ['tip_minor' => 1000]);

        $response = $this->actingAs($this->owner)
            ->getJson(route('sales.receipt-drawer', $payment))
            ->assertOk()
            ->assertJsonStructure(['name', 'reference', 'status', 'sections', 'urls']);

        $this->assertSame(route('bookings.receipt', $booking), $response->json('urls.show'));
        /* Print-on-open, which every browser offers as Save as PDF. */
        $this->assertSame(
            route('bookings.receipt', ['booking' => $booking, 'print' => 1]),
            $response->json('urls.download'),
        );
    }

    /** The receipt page prints itself only when asked to. */
    public function test_the_receipt_page_auto_prints_only_with_the_flag(): void
    {
        $booking = $this->booking(10000, 10000);

        $this->actingAs($this->owner)
            ->get(route('bookings.receipt', $booking))
            ->assertOk()
            ->assertDontSee('window.print();', false);

        $this->actingAs($this->owner)
            ->get(route('bookings.receipt', ['booking' => $booking, 'print' => 1]))
            ->assertOk()
            ->assertSee('window.print();', false);
    }

    // --------------------------------------------------------- the table

    public function test_the_table_lists_one_row_per_payment(): void
    {
        $booking = $this->booking(20000, 15000);
        $this->payment($booking, 5000);
        $this->payment($booking, 10000, ['method' => 'card']);

        $rows = $this->actingAs($this->owner)
            ->getJson(route('sales.data'))
            ->assertOk()
            ->json('data');

        /* A bill settled half in cash and half on a card is two transactions
           against one appointment. */
        $this->assertCount(2, $rows);
        $this->assertSame('$200.00', $rows[0]['total']);
        $this->assertSame('$50.00', $rows[0]['balance']);
    }

    public function test_a_transaction_carries_a_readable_reference(): void
    {
        $payment = $this->payment($this->booking(10000), 10000);

        $reference = SalesController::reference($payment->fresh());

        $this->assertMatchesRegularExpression('/^TXN-\d{8}-\d{6}$/', $reference);
        $this->assertSame($payment->id, SalesController::idFromReference($reference));
    }

    /** Pasting a transaction reference into the search finds it. */
    public function test_searching_by_transaction_reference_finds_the_row(): void
    {
        $payment = $this->payment($this->booking(10000), 10000);
        $reference = SalesController::reference($payment->fresh());

        $this->actingAs($this->owner)
            ->getJson(route('sales.data', ['search' => $reference]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_searching_by_client_name_finds_the_row(): void
    {
        $this->payment($this->booking(10000), 10000);

        $this->actingAs($this->owner)
            ->getJson(route('sales.data', ['search' => 'Sarah']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->owner)
            ->getJson(route('sales.data', ['search' => 'Nobody']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** The Outstanding Balance widget narrows the table to what is still owed. */
    public function test_the_balance_filter_shows_only_unsettled_bookings(): void
    {
        $this->payment($this->booking(10000, 10000), 10000);
        $this->payment($this->booking(20000, 5000), 5000);

        $rows = $this->actingAs($this->owner)
            ->getJson(route('sales.data', ['balance' => 1]))
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('$150.00', $rows[0]['balance']);
    }

    public function test_the_table_filters_by_payment_method(): void
    {
        $booking = $this->booking(20000, 20000);
        $this->payment($booking, 10000);
        $this->payment($booking, 10000, ['method' => 'card']);

        $this->actingAs($this->owner)
            ->getJson(route('sales.data', ['method' => 'card']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** Money that moved outside the range is not this period's. */
    public function test_a_payment_outside_the_range_is_excluded(): void
    {
        $booking = $this->booking(10000, 10000, now()->subMonths(2)->toDateString());
        $this->payment($booking, 10000, ['paid_at' => now()->subMonths(2)]);

        $this->actingAs($this->owner)
            ->getJson(route('sales.data'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
    // -------------------------------------------------- memberships in Sales

    /**
     * A membership sale is a sale.
     *
     * The Sales page read only booking payments, so every membership sold
     * was money the business had taken and the ledger did not mention.
     */
    public function test_a_membership_purchase_appears_in_sales(): void
    {
        $payment = $this->membershipSale(15000);

        $row = collect($this->actingAs($this->owner)
            ->getJson(route('sales.data', ['period' => 'this_month', 'size' => 50]))
            ->assertOk()
            ->json('data'))
            ->firstWhere('reference', SalesController::membershipReference($payment));

        $this->assertNotNull($row, 'the membership sale is missing from the ledger');
        $this->assertSame(__('sales.row_types.membership'), $row['type']);
        $this->assertSame('Amara Diallo', $row['client']);
        $this->assertSame('Massage 4-Pack', $row['membership']);
        $this->assertSame(__('membership.types.package'), $row['membership_type']);
        $this->assertSame('PKG-20260909-0001', $row['membership_code']);
        $this->assertSame('$150.00', $row['amount']);
        $this->assertSame(__('bookings.methods.card.name'), $row['method']);

        /* A membership takes no slot, so there is no staff member who
           performed it. The reference column is not blank, though: where a
           booking prints its own number, a membership prints the one it was
           given at the sale. */
        $this->assertSame($payment->membership->reference, $row['booking']);
        $this->assertNotNull($row['booking']);
        $this->assertSame('—', $row['staff']);
    }

    /** Its reference is told apart from a booking payment's. */
    public function test_a_membership_transaction_has_its_own_reference(): void
    {
        $payment = $this->membershipSale(9900, type: 'recurring');

        $this->assertMatchesRegularExpression(
            '/^MTX-\d{8}-\d{6}$/',
            SalesController::membershipReference($payment)
        );
    }

    /**
     * Each billing cycle is its own transaction.
     *
     * Two rows for one membership rather than one that grows: a business
     * reconciling September wants what September took, and a running total
     * cannot answer that.
     */
    public function test_every_billing_cycle_is_its_own_transaction(): void
    {
        $first = $this->membershipSale(9900, type: 'recurring');

        /* The renewal, recorded the way the billing run will record it. */
        $second = MembershipPayment::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_membership_id' => $first->client_membership_id,
            'method' => 'card',
            'status' => 'paid',
            'amount_minor' => 9900,
            'currency_code' => 'USD',
            'purpose' => 'renewal',
            'paid_at' => now(),
        ]);

        $references = collect($this->actingAs($this->owner)
            ->getJson(route('sales.data', ['period' => 'this_month', 'size' => 50]))
            ->json('data'))
            ->pluck('reference');

        $this->assertTrue($references->contains(SalesController::membershipReference($first)));
        $this->assertTrue($references->contains(SalesController::membershipReference($second)));
    }

    /** Both kinds are in the list, and either can be asked for alone. */
    public function test_transactions_can_be_filtered_by_type(): void
    {
        $this->payment($this->booking(6000, 6000), 6000);
        $this->membershipSale(15000);

        $types = fn (?string $type) => collect($this->actingAs($this->owner)
            ->getJson(route('sales.data', array_filter([
                'period' => 'this_month', 'size' => 50, 'type' => $type,
            ])))
            ->assertOk()
            ->json('data'))->pluck('type')->unique()->values()->all();

        $this->assertEqualsCanonicalizing(
            [__('sales.row_types.service'), __('sales.row_types.membership')],
            $types(null)
        );
        $this->assertSame([__('sales.row_types.membership')], $types('membership'));
        $this->assertSame([__('sales.row_types.service')], $types('service'));
    }

    /** And found by the plan's name or its code. */
    public function test_a_membership_sale_is_searchable(): void
    {
        $this->membershipSale(15000);

        foreach (['Massage 4-Pack', 'PKG-20260909-0001', 'Amara'] as $term) {
            $this->assertCount(
                1,
                $this->actingAs($this->owner)
                    ->getJson(route('sales.data', ['period' => 'this_month', 'size' => 50, 'search' => $term]))
                    ->assertOk()
                    ->json('data'),
                "searching for {$term} did not find the membership sale"
            );
        }
    }

    /** The figures count it too, or the page under-reports the month. */
    public function test_membership_revenue_is_in_the_summary(): void
    {
        $this->payment($this->booking(6000, 6000), 6000);
        $this->membershipSale(15000);

        $summary = SalesSummary::for(SalesPeriod::fromRequest('this_month', null, null));

        $this->assertSame(21000, $summary['total_sales']['value']);
        $this->assertSame(21000, $summary['collected']['value']);
        $this->assertSame(2, $summary['transactions']['count']);
    }

    /**
     * A refund is a row of its own, and it comes off the total.
     *
     * The original payment happened; a history that rewrote it could not
     * answer "what did we actually take in March".
     */
    public function test_a_membership_refund_shows_and_reduces_the_total(): void
    {
        $sale = $this->membershipSale(15000);

        MembershipPayment::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_membership_id' => $sale->client_membership_id,
            'method' => 'card',
            'status' => 'refunded',
            'amount_minor' => 15000,
            'currency_code' => 'USD',
            'purpose' => 'refund',
            'paid_at' => now(),
        ]);

        $summary = SalesSummary::for(SalesPeriod::fromRequest('this_month', null, null));

        $this->assertSame(15000, $summary['refunds']['value']);
        /* Taken in and given back: nothing kept. */
        $this->assertSame(0, $summary['total_sales']['value']);

        /* Both rows are in the ledger, and both belong to the same
           membership — which is how a booking refund is linked to its
           payment too. */
        $this->assertCount(2, $this->actingAs($this->owner)
            ->getJson(route('sales.data', ['period' => 'this_month', 'size' => 50, 'type' => 'membership']))
            ->json('data'));
    }

    /**
     * Cancelling a membership does not erase what was paid for it.
     *
     * A membership that ended is a thing that happened, and the money it
     * took is a row in the ledger for good.
     */
    public function test_cancelling_a_membership_leaves_its_transactions_alone(): void
    {
        $sale = $this->membershipSale(15000);

        ClientMembership::withoutGlobalScopes()
            ->find($sale->client_membership_id)
            ->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])
            ->save();

        $rows = $this->actingAs($this->owner)
            ->getJson(route('sales.data', ['period' => 'this_month', 'size' => 50, 'type' => 'membership']))
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('$150.00', $rows[0]['amount']);
    }

    /**
     * Paging happens across both tables at once.
     *
     * Which rows are on page two is a question about the whole ledger, and a
     * page that answered it from one table would drop the other's rows.
     */
    public function test_paging_covers_both_kinds_of_transaction(): void
    {
        $this->payment($this->booking(6000, 6000), 6000);
        $this->payment($this->booking(7000, 7000), 7000);
        $this->membershipSale(15000);

        $page = fn (int $n) => $this->actingAs($this->owner)
            ->getJson(route('sales.data', ['period' => 'this_month', 'size' => 2, 'page' => $n]))
            ->assertOk()
            ->json();

        $first = $page(1);
        $second = $page(2);

        $this->assertSame(3, $first['total']);
        $this->assertSame(2, $first['last_page']);
        $this->assertCount(2, $first['data']);
        $this->assertCount(1, $second['data']);

        /* No row on both pages and none missing from either. */
        $this->assertCount(3, collect($first['data'])->merge($second['data'])->pluck('reference')->unique());
    }
}
