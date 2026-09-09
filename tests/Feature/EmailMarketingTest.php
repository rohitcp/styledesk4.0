<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EmailCampaign;
use App\Models\Location;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\CampaignAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Email campaigns to the whole client list.
 *
 * What is pinned here is the audience, because the audience is the promise
 * the screen makes. A count that is wrong by the number of people who asked
 * not to hear from the business is not a rounding error — it is the
 * difference between a campaign and a complaint.
 */
class EmailMarketingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Spa', 'slug' => 'nadia-marketing', 'business_email' => 'hi@nadia.test',
        ]);

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

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);

        $this->actingAs($this->owner);
    }

    // ------------------------------------------------------------ helpers

    private function client(array $attributes = []): Client
    {
        return Client::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'preferred_location_id' => $this->location->id,
            'first_name' => 'Sarah', 'last_name' => 'Johnson',
            'email' => 'sarah'.uniqid().'@example.test',
            'marketing_email' => true,
        ]);
    }

    private function visit(Client $client, string $date, array $attributes = []): Booking
    {
        return Booking::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'location_id' => $this->location->id,
            'client_id' => $client->id,
            'reference' => Booking::nextReference(),
            'date' => $date,
            'starts_at' => '10:00', 'ends_at' => '11:00', 'minutes' => 60,
            'status' => 'completed',
        ]);
    }

    private function userFor(string $email, string $roleKey): User
    {
        $member = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'location_id' => $this->location->id,
            'first_name' => 'Rae', 'last_name' => 'Ortiz',
            'is_active' => true,
        ]);

        $user = User::create([
            'first_name' => 'Rae', 'last_name' => 'Ortiz',
            'email' => $email, 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $member->forceFill([
            'user_id' => $user->id,
            'role_id' => Role::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant->getTenantKey())
                ->where('key', $roleKey)
                ->value('id'),
        ])->save();

        return $user->fresh();
    }

    // ----------------------------------------------------------- the audience

    /**
     * The estimate is three numbers, and the headline is the smallest.
     *
     * "Eligible" is who will actually receive it. Somebody who unsubscribed
     * and somebody whose email field was never filled in are both excluded,
     * and they are counted separately because they are different problems
     * with different answers.
     */
    public function test_the_estimate_separates_consent_from_a_missing_address(): void
    {
        $this->client();
        $this->client();
        $this->client(['marketing_email' => false]);
        $this->client(['email' => null]);
        $this->client(['email' => 'not-an-address']);

        $estimate = CampaignAudience::estimate(['scope' => 'all']);

        $this->assertSame(5, $estimate['total']);
        $this->assertSame(1, $estimate['unsubscribed']);
        $this->assertSame(2, $estimate['invalid']);
        $this->assertSame(2, $estimate['eligible']);
    }

    /**
     * Consent is not a rule anybody can switch off.
     *
     * Whatever the audience says, a client who has unsubscribed from
     * marketing email is not in the list that gets written to.
     */
    public function test_somebody_who_unsubscribed_is_never_eligible(): void
    {
        $optedOut = $this->client(['marketing_email' => false, 'first_name' => 'Mai']);
        $this->client(['first_name' => 'Sofia']);

        $names = CampaignAudience::eligible(['scope' => 'all'])->pluck('first_name')->all();

        $this->assertSame(['Sofia'], $names);
        $this->assertNotContains($optedOut->first_name, $names);
    }

    /**
     * "Has not visited in 90 days" is not the same as "has not visited".
     *
     * Somebody who has never been through the door has also not been for
     * ninety days, and a "we miss you" email to them is the wrong message.
     */
    public function test_lapsed_clients_are_people_who_came_once_and_stopped(): void
    {
        $lapsed = $this->client(['first_name' => 'Lapsed']);
        $this->visit($lapsed, Carbon::today()->subDays(120)->toDateString());

        $recent = $this->client(['first_name' => 'Recent']);
        $this->visit($recent, Carbon::today()->subDays(10)->toDateString());

        /* Never been. Not a lapsed client — there is nothing to miss. */
        $this->client(['first_name' => 'Never']);

        $names = CampaignAudience::query(['scope' => 'all', 'not_visited_days' => 90])
            ->pluck('first_name')->all();

        $this->assertSame(['Lapsed'], $names);
    }

    /** A booking somebody cancelled is not a visit. */
    public function test_a_cancelled_appointment_does_not_count_as_a_visit(): void
    {
        $client = $this->client(['first_name' => 'Cancelled']);
        $this->visit($client, Carbon::today()->subDays(120)->toDateString());
        $this->visit($client, Carbon::today()->subDays(5)->toDateString(), ['status' => 'cancelled']);

        /* The recent one was cancelled, so they are still lapsed. */
        $names = CampaignAudience::query(['scope' => 'all', 'not_visited_days' => 90])
            ->pluck('first_name')->all();

        $this->assertSame(['Cancelled'], $names);
    }

    public function test_an_audience_can_be_narrowed_to_clients_with_nothing_booked(): void
    {
        $booked = $this->client(['first_name' => 'Booked']);
        Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'location_id' => $this->location->id,
            'client_id' => $booked->id,
            'reference' => Booking::nextReference(),
            'date' => Carbon::tomorrow()->toDateString(),
            'starts_at' => '10:00', 'ends_at' => '11:00', 'minutes' => 60,
            'status' => 'confirmed',
        ]);

        $this->client(['first_name' => 'Free']);

        $this->assertSame(
            ['Free'],
            CampaignAudience::query(['scope' => 'all', 'has_upcoming' => false])->pluck('first_name')->all(),
        );

        $this->assertSame(
            ['Booked'],
            CampaignAudience::query(['scope' => 'all', 'has_upcoming' => true])->pluck('first_name')->all(),
        );
    }

    /** The rules in words, for the listing's Audience column. */
    public function test_the_audience_reads_as_a_sentence(): void
    {
        $said = CampaignAudience::describe([
            'scope' => 'active',
            'locations' => [$this->location->id],
            'not_visited_days' => 90,
        ]);

        $this->assertSame('Active clients · 1 location · no visit in 90 days', $said);
    }

    // ----------------------------------------------------------- the campaign

    public function test_a_campaign_is_saved_as_a_draft_with_its_audience(): void
    {
        $this->client();
        $this->client(['marketing_email' => false]);

        $this->post(route('marketing.email.store'), [
            'name' => 'September massage promotion',
            'subject' => 'Save 20% on your next massage',
            /* As the form sends it: one hidden field holding JSON. */
            'audience' => json_encode(['scope' => 'active', 'not_visited_days' => 90]),
        ])->assertRedirect();

        $campaign = EmailCampaign::firstOrFail();

        $this->assertSame('draft', $campaign->status);
        $this->assertSame($this->owner->id, $campaign->created_by);
        $this->assertSame(['scope' => 'active', 'not_visited_days' => 90], $campaign->audience);
        /* The estimate is cached with a date on it: the rules are the truth
           and they answer differently tomorrow. */
        $this->assertNotNull($campaign->estimated_at);
    }

    /**
     * The audience is whitelisted, not stored as posted.
     *
     * It is JSON in a column that is later read into a query builder, so what
     * may appear in it is the controller's decision and not the form's.
     */
    public function test_rules_the_screen_does_not_offer_are_thrown_away(): void
    {
        $this->post(route('marketing.email.store'), [
            'name' => 'Anything',
            'audience' => json_encode([
                'scope' => 'everybody-including-archived',
                'not_visited_days' => 7,
                'raw_sql' => '1=1',
            ]),
        ])->assertRedirect();

        $audience = EmailCampaign::firstOrFail()->audience;

        /* An unknown scope falls back rather than being trusted. */
        $this->assertSame('all', $audience['scope']);
        /* Seven days is not one of the windows offered, so it is dropped. */
        $this->assertArrayNotHasKey('not_visited_days', $audience);
        $this->assertArrayNotHasKey('raw_sql', $audience);
    }

    public function test_the_estimate_endpoint_answers_as_the_rules_are_built(): void
    {
        $this->client();
        $this->client(['marketing_email' => false]);

        $this->postJson(route('marketing.email.estimate'), [
            'audience' => ['scope' => 'all'],
        ])
            ->assertOk()
            ->assertJson(['total' => 2, 'unsubscribed' => 1, 'eligible' => 1]);
    }

    /**
     * A campaign that has gone cannot be rewritten.
     *
     * Editing one that is sending would change what half the list receives
     * midway; editing one that has sent would rewrite what people were
     * actually sent.
     */
    public function test_a_sent_campaign_can_no_longer_be_edited_or_deleted(): void
    {
        $campaign = EmailCampaign::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Already gone',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->patch(route('marketing.email.update', $campaign), ['name' => 'Rewritten'])
            ->assertStatus(422);

        $this->delete(route('marketing.email.destroy', $campaign))->assertStatus(422);

        $this->assertSame('Already gone', $campaign->fresh()->name);
    }

    public function test_a_draft_can_be_deleted(): void
    {
        $campaign = EmailCampaign::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Never sent',
        ]);

        $this->delete(route('marketing.email.destroy', $campaign))
            ->assertRedirect(route('marketing.email.index'));

        $this->assertSoftDeleted($campaign);
    }

    // --------------------------------------------------------- the permission

    /**
     * Writing to one client and writing to twelve hundred are different acts.
     *
     * The desk can see what went out — a client ringing about an offer is a
     * call the desk takes — and writes none of it.
     */
    public function test_the_desk_may_read_campaigns_and_not_write_them(): void
    {
        $receptionist = $this->userFor('rae@styledesk.test', 'front-desk');

        $this->actingAs($receptionist)->get(route('marketing.email.index'))->assertOk();
        $this->actingAs($receptionist)->get(route('marketing.email.create'))->assertForbidden();
        $this->actingAs($receptionist)
            ->post(route('marketing.email.store'), ['name' => 'Nope'])
            ->assertForbidden();
    }

    /** Sending is its own authority, and a manager does not have it. */
    public function test_a_manager_can_write_a_campaign_but_not_send_one(): void
    {
        $manager = $this->userFor('manager@styledesk.test', 'manager');

        $this->assertTrue($manager->hasPermission('marketing.create'));
        $this->assertTrue($manager->hasPermission('marketing.schedule'));
        $this->assertFalse($manager->hasPermission('marketing.send'));
    }

    public function test_the_screen_is_refused_to_somebody_with_no_marketing_access(): void
    {
        $stranger = User::create([
            'first_name' => 'Sam', 'last_name' => 'Doe',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $stranger->markEmailAsVerified();
        $stranger->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($stranger->fresh())
            ->get(route('marketing.email.index'))
            ->assertForbidden();
    }
}
