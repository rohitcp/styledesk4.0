<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Location;
use App\Models\MembershipCredit;
use App\Models\MembershipPlan;
use App\Models\MembershipSettings;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Selling a membership from the booking screen.
 *
 * What these guard is that a sale is a promise the business can keep: the
 * money is the server's figure and never the form's, what the client agreed
 * to is frozen against a later repricing, a membership dated forward grants
 * nothing until its day, and a subscription cannot be sold on a method that
 * can never be charged again.
 */
class MembershipSaleTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-09';

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY.' 10:00:00');

        $this->tenant = Tenant::create(['name' => 'Acme Spa', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------ fixtures

    private function owner(): User
    {
        if ($existing = User::where('email', 'owner@styledesk.test')->first()) {
            return $existing;
        }

        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    private function client(): Client
    {
        return Client::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'email' => 'sarah@example.test'],
            [
                'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
                'first_name' => 'Sarah', 'last_name' => 'Johnson', 'status' => 'active',
            ],
        );
    }

    private function service(string $name = 'Swedish Massage'): Service
    {
        return Service::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => $name],
            ['duration_minutes' => 60, 'is_active' => true],
        );
    }

    private function membershipOn(array $overrides = []): MembershipSettings
    {
        return MembershipSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            $overrides + ['is_enabled' => true] + MembershipSettings::defaults(),
        );
    }

    private function plan(array $overrides = [], int $quantity = 1): MembershipPlan
    {
        $plan = MembershipPlan::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'recurring',
            'name' => 'Monthly Massage Membership',
            'price_minor' => 7900,
            'billing_frequency' => 'monthly',
            'location_mode' => 'all',
            'sell_in_store' => true,
            'is_draft' => false,
        ]);

        $plan->planServices()->create([
            'service_id' => $this->service()->id, 'quantity' => $quantity, 'position' => 0,
        ]);

        return $plan->fresh(['planServices.service']);
    }

    /** Everything the purchase summary posts. */
    private function payload(MembershipPlan $plan, array $overrides = []): array
    {
        return $overrides + [
            'membership_plan_id' => $plan->id,
            'client_id' => $this->client()->id,
            'location_id' => $this->location->id,
            'starts_on' => self::TODAY,
            'payment_method' => 'card',
        ];
    }

    private function sold(): ClientMembership
    {
        return ClientMembership::withoutGlobalScopes()->with('credits')->firstOrFail();
    }

    // ---------------------------------------------------- the members listing

    /**
     * The listing is searched and filtered like every other one.
     *
     * It was a plain table on the argument that this list is read rather
     * than filtered — which stopped being true the moment there were members
     * enough to look one up.
     */
    public function test_members_can_be_searched_and_filtered(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($this->plan()))
            ->assertRedirect();

        $membership = $this->sold();

        $rows = fn (array $query) => $this->actingAs($this->owner())
            ->getJson(route('membership.members.data', $query))
            ->assertOk()
            ->json('data');

        /* By the number somebody is reading off a card. */
        $this->assertCount(1, $rows(['search' => $membership->reference]));
        $this->assertCount(1, $rows(['search' => 'Sarah']));
        $this->assertCount(1, $rows(['search' => 'Monthly Massage']));
        $this->assertCount(0, $rows(['search' => 'nobody at all']));

        /* And by what it is and where it stands. */
        $this->assertCount(1, $rows(['type' => 'recurring']));
        $this->assertCount(0, $rows(['type' => 'package']));
        $this->assertCount(1, $rows(['status' => 'active']));
        $this->assertCount(0, $rows(['status' => 'ended']));
    }

    /** Each row offers the panel rather than a page to navigate to. */
    public function test_a_member_row_offers_plan_details(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($this->plan()))
            ->assertRedirect();

        $membership = $this->sold();

        $menu = collect($this->actingAs($this->owner())
            ->getJson(route('membership.members.data'))
            ->json('data.0.menu'));

        $details = $menu->firstWhere('label', __('membership.member.drawer.plan_details'));

        $this->assertNotNull($details);
        $this->assertSame('membership:details', $details['event']);
        $this->assertSame(
            route('client-memberships.drawer', $membership),
            $details['payload']['url']
        );
    }

    // ------------------------------------------------ the membership number

    /**
     * Every membership sold gets a number of its own.
     *
     * Not the plan's code. That names the product the business sells; this
     * names the thing one client bought — and two clients on the same plan
     * hold two memberships that a shared code could not tell apart.
     */
    public function test_a_sale_gives_the_membership_its_own_number(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($this->plan()))
            ->assertRedirect();

        $membership = $this->sold();

        $this->assertMatchesRegularExpression('/^MBR-\d{8}-\d{4}$/', $membership->reference);
    }

    /** Two sales of the same plan are two numbers. */
    public function test_two_memberships_never_share_a_number(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertRedirect();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['acknowledge_existing' => 1]))
            ->assertRedirect();

        $references = ClientMembership::withoutGlobalScopes()->pluck('reference');

        $this->assertCount(2, $references);
        $this->assertCount(2, $references->unique());
        $this->assertTrue($references->every(fn (?string $r) => $r !== null));
    }

    /**
     * And it stays with the membership.
     *
     * It goes on the confirmation, into the ledger and onto whatever the
     * client is handed, so a number that changed would name two things.
     */
    public function test_the_number_survives_being_cancelled(): void
    {
        $this->membershipOn(['allow_cancellation' => true]);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($this->plan()))
            ->assertRedirect();

        $membership = $this->sold();
        $reference = $membership->reference;

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertRedirect();

        $this->assertSame($reference, $membership->fresh()->reference);
    }

    /** The confirmation reads it out, where a booking reads out its own. */
    public function test_the_confirmation_shows_the_membership_number(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($this->plan()))
            ->assertRedirect();

        $membership = $this->sold();

        $this->actingAs($this->owner())
            ->get(route('membership.sales.show', $membership))
            ->assertOk()
            ->assertSee($membership->reference)
            ->assertSee(__('membership.sold.reference'));
    }

    // ------------------------------------------ what the client already holds

    /**
     * A second membership is a decision, never an accident.
     *
     * Never a refusal — a client may hold a monthly plan and a massage
     * package, and even two of the same package is somebody's call. What this
     * stops is the silent one: a second subscription created because nobody
     * knew about the first, and a client who finds out at the next billing
     * run.
     */
    public function test_selling_a_second_membership_needs_saying_so(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertRedirect();

        $this->assertSame(1, ClientMembership::withoutGlobalScopes()->count());

        /* The same client again, with nothing said about the first. */
        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertSessionHasErrors('membership_plan_id');

        $this->assertSame(1, ClientMembership::withoutGlobalScopes()->count());
    }

    /** Said, and it goes through — with the first one untouched. */
    public function test_acknowledging_it_sells_the_second_and_leaves_the_first_alone(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertRedirect();

        $first = $this->sold();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['acknowledge_existing' => 1]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ClientMembership::withoutGlobalScopes()->count());

        /* The warning flow changes nothing about what they already had. */
        $first = $first->fresh();
        $this->assertSame('active', $first->status);
        $this->assertNull($first->cancelled_at);
        $this->assertNull($first->ends_on);
    }

    /** A client holding nothing is sold to without a word. */
    public function test_a_first_membership_needs_no_acknowledgement(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($this->plan()))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    /**
     * The screen asks before the sale, so there is a decision to make.
     *
     * A package is worth different facts from a subscription: what is left
     * decides whether another is wanted at all.
     */
    public function test_the_check_reports_a_held_package_with_what_is_left_of_it(): void
    {
        $this->membershipOn();
        $package = $this->plan(['type' => 'package', 'billing_frequency' => null], quantity: 4);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($package))
            ->assertRedirect();

        $held = $this->actingAs($this->owner())
            ->getJson(route('membership.sales.check', [
                'client_id' => $this->client()->id,
                'membership_plan_id' => $package->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $held['held']);
        $this->assertTrue($held['same_plan']);
        $this->assertTrue($held['held'][0]['same_plan']);
        $this->assertSame(4, $held['held'][0]['remaining']);
        $this->assertSame(4, $held['held'][0]['granted']);
    }

    /** And a subscription is worth knowing when it bills next. */
    public function test_the_check_reports_a_held_subscription_with_its_billing(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertRedirect();

        $row = $this->actingAs($this->owner())
            ->getJson(route('membership.sales.check', ['client_id' => $this->client()->id]))
            ->assertOk()
            ->json('held.0');

        $this->assertSame('recurring', $row['type']);
        $this->assertSame('monthly', $row['billing_frequency']);
        $this->assertNotNull($row['next_billing_on']);
        $this->assertSame(__('membership.member_statuses.active'), $row['status_label']);
    }

    /** A different plan is still worth warning about, but not as the same one. */
    public function test_a_different_plan_is_reported_without_the_same_plan_flag(): void
    {
        $this->membershipOn();
        $held = $this->plan();
        $other = $this->plan(['name' => 'Massage Package', 'type' => 'package', 'billing_frequency' => null]);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($held))
            ->assertRedirect();

        $json = $this->actingAs($this->owner())
            ->getJson(route('membership.sales.check', [
                'client_id' => $this->client()->id,
                'membership_plan_id' => $other->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['held']);
        $this->assertFalse($json['same_plan']);
    }

    /** Nothing held, nothing to say. */
    public function test_the_check_is_empty_for_a_client_with_no_membership(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->getJson(route('membership.sales.check', ['client_id' => $this->client()->id]))
            ->assertOk()
            ->assertJson(['held' => [], 'same_plan' => false]);
    }

    /** A membership that has ended is not something to warn about. */
    public function test_an_ended_membership_is_not_reported(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($this->plan()))
            ->assertRedirect();

        $this->sold()->forceFill(['status' => 'ended'])->save();

        $this->actingAs($this->owner())
            ->getJson(route('membership.sales.check', ['client_id' => $this->client()->id]))
            ->assertOk()
            ->assertJson(['held' => []]);

        /* And the sale that follows needs no acknowledgement. */
        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($this->plan()))
            ->assertSessionHasNoErrors();
    }

    // --------------------------------------------------------- the purchase

    public function test_a_recurring_membership_is_sold_with_its_first_cycle_of_credits(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertRedirect();

        $membership = $this->sold();

        $this->assertSame('active', $membership->status);
        $this->assertSame(7900, $membership->price_minor);
        $this->assertSame('monthly', $membership->billing_frequency);
        /* A cycle after it starts, not a cycle after it was sold. */
        $this->assertSame('2026-10-09', $membership->next_billing_on->toDateString());

        $this->assertCount(1, $membership->credits);
        $this->assertSame(1, $membership->credits->first()->quantity_granted);
        $this->assertSame(0, $membership->credits->first()->quantity_used);
    }

    public function test_a_package_is_sold_once_and_never_bills_again(): void
    {
        $this->membershipOn();
        $plan = $this->plan([
            'type' => 'package', 'name' => 'Massage Package',
            'price_minor' => 15000, 'regular_value_minor' => 20000,
            'billing_frequency' => null,
        ], quantity: 4);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['payment_method' => 'cash']))
            ->assertRedirect();

        $membership = $this->sold();

        $this->assertSame('package', $membership->type);
        $this->assertNull($membership->billing_frequency);
        /* Not "none yet" — a question that does not apply. */
        $this->assertNull($membership->next_billing_on);
        $this->assertSame(4, $membership->credits->first()->quantity_granted);
        /* A package's credits belong to no cycle, so they carry no period. */
        $this->assertNull($membership->credits->first()->period_start);
    }

    /* What it costs is the server's answer. A price posted from a page is a
       price somebody can edit. */
    public function test_the_amount_taken_is_the_servers_figure_not_the_forms(): void
    {
        $this->membershipOn();
        $plan = $this->plan(['joining_fee_minor' => 2000]);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, [
                'price' => '1', 'amount_minor' => 1, 'due_today_minor' => 1,
            ]))
            ->assertRedirect();

        $membership = $this->sold();

        $this->assertSame(9900, $membership->paidMinor());
        $this->assertSame(9900, $membership->dueTodayMinor());
    }

    /* The plan is what the business offers today; the membership is what one
       person was told on one day. */
    public function test_repricing_the_plan_does_not_reprice_what_was_sold(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())->post(route('membership.sales.store'), $this->payload($plan));

        $plan->update(['price_minor' => 12900, 'name' => 'Renamed']);

        $this->assertSame(7900, $this->sold()->price_minor);
    }

    /* Credits governs what the client can redeem, not the quantity printed
       beside it on the card. */
    public function test_the_sale_grants_what_credits_says(): void
    {
        $this->membershipOn();

        $plan = $this->plan();
        $plan->planServices()->update(['quantity' => 1, 'credits' => 3]);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan->fresh('planServices')))
            ->assertRedirect();

        $credit = $this->sold()->credits->first();

        $this->assertSame(3, $credit->quantity_granted);
        $this->assertSame(3, $credit->remaining());
    }

    /* A line written without naming credits grants what it describes. A
       default of one would quietly turn a four-massage package into a
       one-massage one. */
    public function test_a_line_written_without_credits_grants_its_quantity(): void
    {
        $this->membershipOn();

        $plan = $this->plan([
            'type' => 'package', 'name' => 'Massage Package', 'billing_frequency' => null,
        ], quantity: 4);

        $this->assertSame(4, $plan->planServices->first()->credits);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['payment_method' => 'cash']));

        $this->assertSame(4, $this->sold()->credits->first()->quantity_granted);
    }

    // ------------------------------------------------------- the start date

    public function test_a_membership_dated_forward_is_scheduled_and_grants_nothing_yet(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['starts_on' => '2026-10-01']))
            ->assertRedirect();

        $membership = $this->sold();

        $this->assertSame('scheduled', $membership->status);
        $this->assertSame('scheduled', $membership->status());
        $this->assertFalse($membership->isLive());
        /* It renews a cycle after it starts, not a cycle after it was sold. */
        $this->assertSame('2026-11-01', $membership->next_billing_on->toDateString());
    }

    public function test_a_scheduled_membership_becomes_active_when_its_day_arrives(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['starts_on' => '2026-09-20']));

        $this->assertSame('scheduled', $this->sold()->status());

        Carbon::setTestNow('2026-09-20 09:00:00');

        /* Nothing rewrote the column. Waiting for something to would leave it
           reading "Scheduled" on the morning it began. */
        $this->assertSame('active', $this->sold()->status());
        $this->assertTrue($this->sold()->isLive());
    }

    public function test_a_business_that_forbids_future_starts_refuses_one(): void
    {
        $this->membershipOn(['allow_start_date_selection' => false]);
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['starts_on' => '2026-10-01']))
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(0, ClientMembership::withoutGlobalScopes()->count());
    }

    public function test_a_start_date_in_the_past_is_refused(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['starts_on' => '2026-09-01']))
            ->assertSessionHasErrors('starts_on');
    }

    // ---------------------------------------------------------- the payment

    /* Cash buys a package; it does not renew a subscription. */
    public function test_a_recurring_membership_cannot_be_sold_for_cash(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['payment_method' => 'cash']))
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, ClientMembership::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------- refusals

    public function test_a_draft_plan_cannot_be_sold(): void
    {
        $this->membershipOn();
        $plan = $this->plan(['is_draft' => true]);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertSessionHasErrors('membership_plan_id');
    }

    public function test_a_plan_taken_off_sale_cannot_be_sold(): void
    {
        $this->membershipOn();
        $plan = $this->plan(['is_disabled' => true]);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertSessionHasErrors('membership_plan_id');
    }

    public function test_nothing_can_be_sold_while_the_module_is_off(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertNotFound();
    }

    // ---------------------------------------------------------- the credits

    public function test_credits_expire_with_the_cycle_they_belong_to(): void
    {
        $this->membershipOn(['credit_expiry' => 'cycle']);
        $plan = $this->plan();

        $this->actingAs($this->owner())->post(route('membership.sales.store'), $this->payload($plan));

        $credit = $this->sold()->credits->first();

        /* The day before the next one begins. */
        $this->assertSame('2026-10-08', $credit->expires_on->toDateString());
        $this->assertTrue($credit->isSpendable());
    }

    public function test_a_package_with_cycle_expiry_never_expires(): void
    {
        $this->membershipOn(['credit_expiry' => 'cycle']);
        $plan = $this->plan(['type' => 'package', 'billing_frequency' => null], quantity: 4);

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan, ['payment_method' => 'cash']));

        /* There is no cycle for them to die with, so 'cycle' means never. */
        $this->assertNull($this->sold()->credits->first()->expires_on);
    }

    public function test_an_expired_credit_cannot_be_spent(): void
    {
        $this->membershipOn(['credit_expiry' => '1m']);
        $plan = $this->plan();

        $this->actingAs($this->owner())->post(route('membership.sales.store'), $this->payload($plan));

        $this->assertTrue($this->sold()->credits->first()->isSpendable());

        Carbon::setTestNow('2026-11-01 09:00:00');

        $credit = MembershipCredit::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($credit->isExpired());
        $this->assertFalse($credit->isSpendable());
    }

    /* A membership that includes nothing grants nothing. Writing credit rows
       for it would put massages on the client's profile nobody sold them. */
    public function test_a_discount_only_membership_grants_no_credits(): void
    {
        $this->membershipOn(['credits_enabled' => false]);
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan))
            ->assertRedirect();

        $membership = $this->sold();

        $this->assertCount(0, $membership->credits);
        /* The membership itself is real — it is the perks the client bought. */
        $this->assertSame('active', $membership->status());
        $this->assertSame(7900, $membership->price_minor);
    }

    // ------------------------------------------------------- after the sale

    public function test_the_confirmation_says_what_the_client_now_holds(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $response = $this->actingAs($this->owner())
            ->post(route('membership.sales.store'), $this->payload($plan));

        $this->actingAs($this->owner())
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee(__('membership.sold.title'))
            ->assertSee('Sarah Johnson')
            ->assertSee($plan->name)
            ->assertSee('Swedish Massage');
    }

    public function test_the_member_appears_under_the_members_tab(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())->post(route('membership.sales.store'), $this->payload($plan));

        /* The page is the frame — heading, toolbar and grid; the rows arrive
           from the endpoint the grid reads. Both are asserted, because a
           page that renders without its grid is a page with no listing. */
        $this->actingAs($this->owner())
            ->get(route('membership.members'))
            ->assertOk()
            ->assertSee('data-grid', false)
            ->assertSee('members/data', false)
            ->assertSee(__('membership.members.columns.reference'));

        $row = $this->actingAs($this->owner())
            ->getJson(route('membership.members.data'))
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Sarah Johnson', $row['client']);
        $this->assertSame($plan->name, $row['membership']);
        /* And the number this membership is known by: two clients on the
           same plan are two rows that the plan's name cannot tell apart. */
        $this->assertSame($this->sold()->reference, $row['reference']);
        $this->assertSame(__('membership.member_statuses.active'), $row['status']);
    }

    public function test_the_booking_screen_can_now_sell_a_membership(): void
    {
        $this->membershipOn();
        $this->plan();

        $content = $this->actingAs($this->owner())
            ->get(route('bookings.create'))
            ->assertOk()
            ->getContent();

        preg_match('/data-props=\'(.*?)\'/s', $content, $matches);
        $props = json_decode(html_entity_decode($matches[1] ?? '{}'), true);

        $types = collect($props['purchaseTypes'])->keyBy('key');

        $this->assertTrue($types['membership']['ready']);
        $this->assertCount(1, $props['membershipPlans']);
        $this->assertSame('Monthly Massage Membership', $props['membershipPlans'][0]['name']);
    }
}
