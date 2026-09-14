<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\LoyaltySettings;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\MembershipSettings;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LoyaltyPoints;
use App\Support\MembershipPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Points on a membership, where the business gives them.
 *
 * Two switches rather than one, because a package is bought once and a
 * subscription bills again: a salon happy to give points on a one-off
 * purchase has not thereby agreed to give them every month for the life of a
 * subscription.
 *
 * The switches are the point of these tests. An earning rule that can be
 * turned on and awards nothing is worse than one that is not offered.
 */
class LoyaltyMembershipEarningTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme-msp', 'country_code' => 'US']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        MembershipSettings::forTenant($this->tenant)->forceFill([
            'tenant_id' => $this->tenant->getTenantKey(),
            'is_enabled' => true,
        ])->save();

        $this->owner = $this->makeOwner();

        $this->actingAs($this->owner);
    }

    private function makeOwner(): User
    {
        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    /**
     * @param  array<int, string>  $earnsOn
     */
    private function loyalty(array $earnsOn): LoyaltySettings
    {
        /* array_merge, not `+`: the union operator keeps the LEFT side on a
           duplicate key, so defaults() would quietly win and every one of
           these tests would run against the default earning rules. */
        return LoyaltySettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            array_merge(LoyaltySettings::defaults(), [
                'is_enabled' => true,
                /* A dollar a point, which makes the arithmetic readable. */
                'spend_amount' => 1,
                'points_earned' => 1,
                'eligible_purchases' => $earnsOn,
            ]),
        );
    }

    private function plan(string $type): MembershipPlan
    {
        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $type === 'recurring' ? 'Monthly glow' : 'Ten-pack',
            'type' => $type,
            'price_minor' => 10000,
            'billing_frequency' => $type === 'recurring' ? 'monthly' : null,
            'location_mode' => 'all', 'sell_in_store' => true, 'is_draft' => false,
        ]);

        $plan->prices()->create([
            'currency_code' => 'USD',
            'price_minor' => 10000,
        ]);

        return $plan->fresh('prices');
    }

    private function client(): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
        ]);
    }

    private function sell(MembershipPlan $plan, Client $client): void
    {
        MembershipPurchase::sell(
            plan: $plan,
            client: $client,
            startsOn: Carbon::parse('2026-09-12'),
            payment: ['method' => 'cash', 'amount_minor' => 10000],
            seller: $this->owner,
        );
    }

    // ------------------------------------------------------------ the switches

    public function test_a_package_earns_when_the_business_says_packages_earn(): void
    {
        $this->loyalty(['services', 'membership_package']);

        $client = $this->client();
        $this->sell($this->plan('package'), $client);

        $this->assertSame(100, LoyaltyPoints::balanceFor($client->fresh()));
    }

    public function test_a_subscription_earns_when_the_business_says_subscriptions_earn(): void
    {
        $this->loyalty(['services', 'membership_recurring']);

        $client = $this->client();
        $this->sell($this->plan('recurring'), $client);

        $this->assertSame(100, LoyaltyPoints::balanceFor($client->fresh()));
    }

    /**
     * The two switches are genuinely separate.
     *
     * Switching packages on must not start awarding on subscriptions — that
     * is the whole reason this is two settings rather than "memberships".
     */
    public function test_switching_packages_on_does_not_award_on_subscriptions(): void
    {
        $this->loyalty(['services', 'membership_package']);

        $client = $this->client();
        $this->sell($this->plan('recurring'), $client);

        $this->assertSame(0, LoyaltyPoints::balanceFor($client->fresh()));
    }

    public function test_switching_subscriptions_on_does_not_award_on_packages(): void
    {
        $this->loyalty(['services', 'membership_recurring']);

        $client = $this->client();
        $this->sell($this->plan('package'), $client);

        $this->assertSame(0, LoyaltyPoints::balanceFor($client->fresh()));
    }

    public function test_a_business_that_gives_no_membership_points_gives_none(): void
    {
        $this->loyalty(['services']);

        $client = $this->client();
        $this->sell($this->plan('package'), $client);

        $this->assertSame(0, LoyaltyPoints::balanceFor($client->fresh()));
        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    public function test_a_paused_scheme_awards_nothing(): void
    {
        $this->loyalty(['services', 'membership_package'])->forceFill(['is_enabled' => false])->save();

        $client = $this->client();
        $this->sell($this->plan('package'), $client);

        $this->assertSame(0, LoyaltyPoints::balanceFor($client->fresh()));
    }

    // --------------------------------------------------------- the ledger line

    /**
     * Every movement is a transaction, and this one says where it came from.
     *
     * Without the payment on the line the engine could only add, and adding
     * is what makes a repeated call award twice.
     */
    public function test_the_line_records_which_payment_earned_it(): void
    {
        $this->loyalty(['services', 'membership_package']);

        $client = $this->client();
        $this->sell($this->plan('package'), $client);

        $line = ClientLoyaltyPoint::withoutGlobalScopes()->sole();

        $this->assertSame('earned', $line->type);
        $this->assertSame(100, $line->points);
        $this->assertNotNull($line->membership_payment_id);
        $this->assertNull($line->booking_id, 'A membership is not an appointment.');
        $this->assertSame(10000, $line->eligible_amount_minor);
    }

    /**
     * Reconciled, not accumulated.
     *
     * The same shape as the booking engine: settling twice writes the
     * difference, which is nothing the second time.
     */
    public function test_settling_the_same_payment_twice_awards_once(): void
    {
        $this->loyalty(['services', 'membership_package']);

        $client = $this->client();
        $this->sell($this->plan('package'), $client);

        $payment = MembershipPayment::withoutGlobalScopes()->sole();

        $this->assertNull(LoyaltyPoints::settleMembershipPayment($payment), 'Nothing left to write.');
        $this->assertSame(100, LoyaltyPoints::balanceFor($client->fresh()));
        $this->assertSame(1, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    /**
     * Money that did not land earns nothing, and takes back what it earned.
     *
     * A payment later marked refunded needs no branch of its own: its target
     * becomes zero, and the difference is written as a reversal.
     */
    public function test_a_payment_that_is_no_longer_paid_takes_its_points_back(): void
    {
        $this->loyalty(['services', 'membership_package']);

        $client = $this->client();
        $this->sell($this->plan('package'), $client);

        $payment = MembershipPayment::withoutGlobalScopes()->sole();
        $payment->forceFill(['status' => 'refunded'])->save();

        LoyaltyPoints::settleMembershipPayment($payment->fresh());

        $this->assertSame(0, LoyaltyPoints::balanceFor($client->fresh()));
        $this->assertSame(2, ClientLoyaltyPoint::withoutGlobalScopes()->count(), 'The reversal is its own line.');
    }

    // ------------------------------------------------------------ the screen

    public function test_the_settings_screen_offers_both_membership_switches(): void
    {
        $this->loyalty(['services']);

        $this->get(route('settings.loyalty.index'))
            ->assertOk()
            ->assertSee('Membership package')
            ->assertSee('Membership recurring');
    }

    public function test_both_membership_switches_can_be_saved(): void
    {
        $this->loyalty(['services']);

        $this->patch(route('settings.loyalty.update'), [
            'is_enabled' => '1',
            'program_name' => 'Rewards',
            'spend_amount' => 1,
            'points_earned' => 1,
            'eligible_purchases' => ['membership_package', 'membership_recurring'],
            'points_required' => 500,
            'reward_value' => '5.00',
            'minimum_redemption' => 500,
            'expiry' => 'never',
            'enrollment_mode' => 'default_on',
            'welcome_points' => 0,
        ])->assertRedirect();

        $saved = LoyaltySettings::forTenant($this->tenant->fresh())->eligible_purchases;

        $this->assertContains('membership_package', $saved);
        $this->assertContains('membership_recurring', $saved);
        $this->assertContains('services', $saved, 'Services are always on.');
    }

    /** A purchase type nothing can sell yet is still refused. */
    public function test_a_type_that_is_not_built_cannot_be_saved(): void
    {
        $this->loyalty(['services']);

        $this->patch(route('settings.loyalty.update'), [
            'is_enabled' => '1',
            'program_name' => 'Rewards',
            'spend_amount' => 1,
            'points_earned' => 1,
            'eligible_purchases' => ['gift_cards'],
            'points_required' => 500,
            'reward_value' => '5.00',
            'minimum_redemption' => 500,
            'expiry' => 'never',
            'enrollment_mode' => 'default_on',
            'welcome_points' => 0,
        ])->assertSessionHasErrors('eligible_purchases.0');
    }
}
