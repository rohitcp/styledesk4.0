<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Location;
use App\Models\MembershipCredit;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\MembershipSettings;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\MembershipCredits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A client's memberships: the tab on their profile, and ending or holding one.
 *
 * What these guard is that the terms the business set are the terms that get
 * applied. A commitment is a commitment, a notice period is not a setting that
 * does nothing, and a membership cancelled at the end of its cycle keeps
 * working until then — the client paid for the month they are standing in.
 */
class ClientMembershipTest extends TestCase
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

    private function settings(array $overrides = []): MembershipSettings
    {
        return MembershipSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            $overrides + ['is_enabled' => true] + MembershipSettings::defaults(),
        );
    }

    /** A live monthly membership with one credit, renewing on 9 October. */
    private function membership(array $overrides = [], int $credits = 1): ClientMembership
    {
        $plan = MembershipPlan::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Monthly Massage Membership'],
            [
                'type' => 'recurring', 'price_minor' => 7900, 'billing_frequency' => 'monthly',
                'location_mode' => 'all', 'sell_in_store' => true, 'is_draft' => false,
            ],
        );

        $membership = ClientMembership::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $this->client()->id,
            'membership_plan_id' => $plan->id,
            'location_id' => $this->location->id,
            'status' => 'active',
            'starts_on' => self::TODAY,
            'type' => 'recurring',
            'price_minor' => 7900,
            'currency_code' => 'USD',
            'billing_frequency' => 'monthly',
            'next_billing_on' => '2026-10-09',
        ]);

        MembershipCredit::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_membership_id' => $membership->id,
            'service_id' => $this->service()->id,
            'quantity_granted' => $credits,
            'quantity_used' => 0,
        ]);

        return $membership->fresh('credits');
    }

    /** What the credit engine would offer this client for the service. */
    private function offers(): array
    {
        return MembershipCredits::offersFor($this->client(), [$this->service()->id]);
    }

    // ---------------------------------------------------------- the profile

    public function test_the_tab_is_absent_until_the_business_runs_memberships(): void
    {
        $this->membership();

        $this->actingAs($this->owner())
            ->get(route('clients.show', $this->client()))
            ->assertOk()
            /* The literal tab, not the word: "Memberships" appears in the
               loyalty vocabulary on the same page. */
            ->assertDontSee('data-tab="membership"', false);

        $this->settings();

        $this->actingAs($this->owner())
            ->get(route('clients.show', $this->client()))
            ->assertOk()
            ->assertSee('data-tab="membership"', false);
    }

    public function test_the_tab_shows_the_membership_its_credits_and_its_history(): void
    {
        $this->settings();
        $membership = $this->membership(credits: 2);

        MembershipPayment::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_membership_id' => $membership->id,
            'method' => 'card', 'status' => 'paid',
            'amount_minor' => 7900, 'currency_code' => 'USD',
            'purpose' => 'initial', 'paid_at' => now(),
        ]);

        $this->actingAs($this->owner())
            ->get(route('clients.show', $this->client()))
            ->assertOk()
            ->assertSee(__('membership.member.active'))
            ->assertSee('Monthly Massage Membership')
            ->assertSee('Swedish Massage')
            ->assertSee(__('membership.member.credit_count', ['count' => 2]))
            ->assertSee(__('membership.member.history.payment'))
            ->assertSee(__('membership.member.history.started'));
    }

    public function test_a_client_with_no_membership_is_told_so(): void
    {
        $this->settings();

        $this->actingAs($this->owner())
            ->get(route('clients.show', $this->client()))
            ->assertOk()
            ->assertSee(__('membership.member.none'));
    }

    // ------------------------------------------------------- cancelling one

    /* End of cycle is the default: the client paid for the month they are
       standing in. */
    public function test_cancelling_at_the_end_of_the_cycle_keeps_it_usable_until_then(): void
    {
        $this->settings(['cancellation_effective' => 'end_of_cycle']);
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $membership->refresh();

        /* The day before the next payment would have been taken. */
        $this->assertSame('2026-10-08', $membership->ends_on->toDateString());
        $this->assertNotNull($membership->cancelled_at);
        $this->assertNull($membership->next_billing_on);

        /* Neither "Active" nor "Cancelled" says what is true, so it says
           both: asked to end, still running. */
        $this->assertSame('cancelling', $membership->status());
        $this->assertTrue($membership->isLive());
        /* And the credits are still theirs to spend. */
        $this->assertNotSame([], $this->offers());
    }

    public function test_cancelling_immediately_ends_it_today(): void
    {
        $this->settings(['cancellation_effective' => 'immediately']);
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('cancelled', $membership->status());
        $this->assertFalse($membership->isLive());
        /* Nothing left to spend: the membership is over. */
        $this->assertSame([], $this->offers());
    }

    /* A notice period the business set is not a setting that does nothing. */
    public function test_a_notice_period_pushes_the_end_date_out(): void
    {
        $this->settings(['cancellation_effective' => 'immediately', 'cancellation_notice_days' => 30]);
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-10-09', $membership->refresh()->ends_on->toDateString());
        /* Still running through the notice period, and still usable. */
        $this->assertSame('cancelling', $membership->status());
    }

    public function test_a_commitment_the_client_agreed_to_is_refused(): void
    {
        $this->settings(['minimum_commitment_months' => 6]);
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertSessionHasErrors('membership');

        $membership->refresh();

        $this->assertNull($membership->cancelled_at);
        $this->assertSame('active', $membership->status());
    }

    public function test_the_commitment_runs_out(): void
    {
        $this->settings(['minimum_commitment_months' => 6]);
        $membership = $this->membership(['starts_on' => '2026-01-01']);

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($membership->refresh()->cancelled_at);
    }

    public function test_cancelling_twice_is_refused(): void
    {
        $this->settings();
        $membership = $this->membership();

        $this->actingAs($this->owner())->patch(route('client-memberships.cancel', $membership));

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertSessionHasErrors('membership');
    }

    public function test_a_business_that_forbids_cancellation_refuses_it(): void
    {
        $this->settings(['allow_cancellation' => false]);
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertForbidden();
    }

    // ----------------------------------------------------------- pausing one

    public function test_pausing_stops_the_billing_and_the_credits(): void
    {
        $this->settings(['allow_pause' => true]);
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.pause', $membership))
            ->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('paused', $membership->status());
        $this->assertNotNull($membership->paused_at);
        $this->assertNull($membership->next_billing_on);
        $this->assertFalse($membership->isLive());
        /* A client who is not paying this month is not having this month's
           massage either. */
        $this->assertSame([], $this->offers());
    }

    public function test_restarting_bills_a_cycle_from_today_not_from_where_it_stopped(): void
    {
        $this->settings(['allow_pause' => true]);
        $membership = $this->membership();

        $this->actingAs($this->owner())->patch(route('client-memberships.pause', $membership));

        Carbon::setTestNow('2026-11-20 10:00:00');

        /* Two months of clock is two months of idle session, and the idle
           timeout would sign the reader out before the request landed. The
           pause and the restart are two visits to the desk, not one. */
        $this->flushSession();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.resume', $membership))
            ->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('active', $membership->status());
        $this->assertNull($membership->paused_at);
        /* The client did not pay for the months they were paused, so they are
           not charged as though they had. */
        $this->assertSame('2026-12-20', $membership->next_billing_on->toDateString());
        $this->assertNotSame([], $this->offers());
    }

    public function test_a_business_that_forbids_pausing_refuses_it(): void
    {
        $this->settings(['allow_pause' => false]);
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.pause', $membership))
            ->assertForbidden();
    }

    public function test_only_a_paused_membership_can_be_restarted(): void
    {
        $this->settings(['allow_pause' => true]);
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.resume', $membership))
            ->assertSessionHasErrors('membership');
    }

    // ------------------------------------------------------------- the past

    public function test_a_membership_whose_end_date_has_passed_reads_as_ended(): void
    {
        $this->settings();
        $membership = $this->membership();

        $this->actingAs($this->owner())->patch(route('client-memberships.cancel', $membership));

        Carbon::setTestNow('2026-12-01 10:00:00');

        $this->assertSame('ended', $membership->refresh()->status());
        $this->assertFalse($membership->isLive());
        /* Nothing rewrote the column; the date settled it. */
        $this->assertSame([], $this->offers());
    }

    public function test_the_module_being_off_makes_the_actions_unreachable(): void
    {
        $membership = $this->membership();

        $this->actingAs($this->owner())
            ->patch(route('client-memberships.cancel', $membership))
            ->assertNotFound();
    }
}
