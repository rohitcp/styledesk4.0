<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\LoyaltySettings;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LoyaltyEnrollment;
use App\Support\LoyaltyPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clients → Loyalty.
 *
 * A list of members rather than a filter on the client list: balance,
 * lifetime and redeemed say nothing about somebody who never joined, and a
 * table where most rows are blank teaches the reader to stop reading it.
 */
class LoyaltyMemberListTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme-members', 'country_code' => 'US']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

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
     * @param  array<string, mixed>  $overrides
     */
    private function loyalty(array $overrides = []): LoyaltySettings
    {
        return LoyaltySettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            array_merge(LoyaltySettings::defaults(), ['is_enabled' => true], $overrides),
        );
    }

    private function client(string $first, string $last = 'Baker'): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => $first, 'last_name' => $last, 'status' => 'active',
            'mobile' => '+1 973 555 1234', 'email' => mb_strtolower($first).'@example.com',
        ]);
    }

    // ----------------------------------------------------------- switched off

    /**
     * Not an empty list: the scheme has never been switched on, so there is
     * nothing to be empty. "No members yet" would send the reader looking for
     * members instead of for the switch.
     */
    public function test_a_business_without_the_scheme_is_offered_the_switch(): void
    {
        $this->loyalty(['is_enabled' => false]);

        $this->get(route('clients.loyalty'))
            ->assertOk()
            ->assertSee(__('loyalty.members.disabled'))
            ->assertSee(route('settings.loyalty.index'), false);
    }

    public function test_the_data_endpoint_is_closed_while_the_scheme_is_off(): void
    {
        $this->loyalty(['is_enabled' => false]);

        $this->getJson(route('clients.loyalty.data'))->assertNotFound();
    }

    // -------------------------------------------------------------- the list

    public function test_only_enrolled_clients_appear(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        $this->client('Tom');

        $response = $this->getJson(route('clients.loyalty.data'))->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame('Mia Baker', $response->json('data.0.name'));
    }

    public function test_a_member_row_carries_the_figures_the_list_is_opened_for(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        LoyaltyPoints::adjust($member->fresh(), 500, true, 'promotion');
        LoyaltyPoints::redeem($member->fresh(), 200);

        $row = $this->getJson(route('clients.loyalty.data'))->assertOk()->json('data.0');

        $this->assertSame('300', $row['balance']);
        $this->assertSame('500', $row['earned'], 'Lifetime is a history, not a balance.');
        $this->assertSame('200', $row['redeemed']);
        $this->assertSame($member->fresh()->loyalty_member_id, $row['member_id']);
        $this->assertNotSame('—', $row['enrolled']);
        $this->assertNotSame('—', $row['last_activity']);
    }

    /**
     * Lapsed points leave the balance and stay in the lifetime total.
     *
     * They were still earned; what they are worth now is nothing.
     */
    public function test_expired_points_leave_the_balance_but_not_the_history(): void
    {
        $this->loyalty(['expiry' => '12m']);

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        LoyaltyPoints::adjust($member->fresh(), 500, true, 'promotion');

        ClientLoyaltyPoint::withoutGlobalScopes()
            ->where('client_id', $member->id)
            ->update(['expires_at' => now()->subDay()]);

        $row = $this->getJson(route('clients.loyalty.data'))->assertOk()->json('data.0');

        $this->assertSame('0', $row['balance']);
        $this->assertSame('500', $row['earned']);
    }

    public function test_a_member_with_no_movement_at_all_still_lists(): void
    {
        $this->loyalty(['welcome_points' => 0]);

        LoyaltyEnrollment::enroll($this->client('Mia'));

        $row = $this->getJson(route('clients.loyalty.data'))->assertOk()->json('data.0');

        $this->assertSame('0', $row['balance']);
        $this->assertSame('—', $row['last_activity']);
    }

    public function test_the_search_finds_a_member_by_name_number_or_member_id(): void
    {
        $this->loyalty();

        $mia = $this->client('Mia');
        LoyaltyEnrollment::enroll($mia);
        LoyaltyEnrollment::enroll($this->client('Tom', 'Fletcher'));

        $this->assertSame(1, $this->getJson(route('clients.loyalty.data', ['search' => 'Fletcher']))->json('total'));
        $this->assertSame(
            1,
            $this->getJson(route('clients.loyalty.data', ['search' => $mia->fresh()->loyalty_member_id]))->json('total'),
        );
    }

    /**
     * The three keys the shared grid actually reads.
     *
     * They were `status` as an array, no `url` at all and `actions` instead
     * of `menu` — which rendered as a missing status column, a row that did
     * not open, and an actions panel with nothing in it that read on screen
     * as a menu being clipped. One contract, three symptoms.
     */
    public function test_the_row_speaks_the_grids_contract(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        $row = $this->getJson(route('clients.loyalty.data'))->assertOk()->json('data.0');

        /* A string and a sibling class: the badge formatter escapes the value
           and takes its tone from `<field>_class`. */
        $this->assertIsString($row['status']);
        $this->assertSame(__('loyalty.member_statuses.active'), $row['status']);
        $this->assertStringStartsWith('styledesk_badge--', $row['status_class']);

        /* What a click on the row opens: the module's own page, not the
           client profile's tab strip. */
        $this->assertSame(route('clients.loyalty.show', $member), $row['url']);

        /* `menu`, not `actions`, and every label is real wording rather than
           a key that fell through. */
        $this->assertNotEmpty($row['menu']);
        $this->assertSame(route('clients.loyalty.show', $member), $row['menu'][0]['url']);

        foreach ($row['menu'] as $entry) {
            $this->assertStringNotContainsString('loyalty.', $entry['label'], 'A missing lang key renders as its own name.');
        }
        $this->assertArrayNotHasKey('actions', $row);
    }

    public function test_the_status_column_reports_what_became_of_the_membership(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        foreach (['paused', 'suspended', 'unenrolled'] as $status) {
            $member->fresh()->forceFill(['loyalty_status' => $status])->save();

            $row = $this->getJson(route('clients.loyalty.data'))->assertOk()->json('data.0');

            $this->assertSame(__('loyalty.member_statuses.'.$status), $row['status']);
        }
    }

    /**
     * A membership with no status recorded is active.
     *
     * Every row that predates the column is in that state, and a blank badge
     * would read as something having gone wrong rather than as nothing having
     * happened.
     */
    public function test_a_membership_with_no_status_recorded_reads_as_active(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);
        $member->fresh()->forceFill(['loyalty_status' => null])->save();

        $this->assertSame(
            __('loyalty.member_statuses.active'),
            $this->getJson(route('clients.loyalty.data'))->json('data.0.status'),
        );
    }

    /** Tiers are not built; an empty column is the honest answer. */
    public function test_the_tier_column_claims_nothing(): void
    {
        $this->loyalty();

        LoyaltyEnrollment::enroll($this->client('Mia'));

        $this->assertSame('—', $this->getJson(route('clients.loyalty.data'))->json('data.0.tier'));
    }

    // -------------------------------------------------------------- the page

    public function test_the_page_renders_the_grid_when_the_scheme_runs(): void
    {
        $this->loyalty();

        LoyaltyEnrollment::enroll($this->client('Mia'));

        $this->get(route('clients.loyalty'))
            ->assertOk()
            ->assertSee('data-grid', false)
            ->assertSee(route('clients.loyalty.data'), false)
            ->assertDontSee(__('loyalty.members.disabled'));
    }

    // -------------------------------------------------- the member's page

    public function test_the_members_own_page_shows_who_they_are_and_what_they_hold(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        $this->get(route('clients.loyalty.show', $member))
            ->assertOk()
            /* The profile's own header, chip for chip. The phone and the
               email are deliberately not among them — they live in the
               Contact card, and the header carries the facts with nowhere
               else to be. */
            ->assertSee('Mia Baker')
            ->assertSee($member->client_ref)
            ->assertSee($member->statusLabel())
            ->assertSee(__('clients.module.workspace.client_since', [
                'date' => $member->created_at->isoFormat('MMM Y'),
            ]))
            /* Then the balance, from the shared rewards body. */
            ->assertSee(__('loyalty.client.available'))
            ->assertSee($member->fresh()->loyalty_member_id)
            ->assertSee(__('loyalty.enrollment.member_since'))
            /* With a way back to the list it was opened from. */
            ->assertSee(route('clients.loyalty'), false);
    }

    /**
     * Its own page, not a redirect into the profile's tab strip.
     *
     * The two render the same body; what differs is why somebody is on them.
     */
    public function test_the_page_is_its_own_and_not_a_redirect_to_the_profile(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        $this->get(route('clients.loyalty.show', $member))
            ->assertOk()
            ->assertViewIs('clients.loyalty.show');
    }

    /**
     * The header is the profile's, from the same partial.
     *
     * Only the action row differs: back goes to the member list rather than
     * the client list, and the primary action is the rest of the record
     * rather than a booking.
     */
    public function test_the_header_is_the_profiles_own_with_its_own_actions(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        $content = $this->get(route('clients.loyalty.show', $member))->assertOk()->getContent();

        $this->assertStringContainsString('styledesk_identity', $content, 'The shared header layout.');
        $this->assertStringContainsString(route('clients.loyalty'), $content);
        $this->assertStringContainsString(__('loyalty.members.open_profile'), $content);

        /* The profile's own actions belong to the profile. */
        $this->assertStringNotContainsString(__('clients.module.workspace.quick.create_booking'), $content);
    }

    /**
     * Adjust Points sits in the header row, once.
     *
     * Back, then the thing this page is for, then where you would go next —
     * and only one button, because two controls opening the same dialog is
     * two places a permission rule could be answered differently.
     */
    public function test_the_adjust_action_sits_in_the_header_and_only_there(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        $content = $this->get(route('clients.loyalty.show', $member))->assertOk()->getContent();

        $this->assertStringContainsString(__('loyalty.client.adjust'), $content);
        $this->assertStringContainsString('rewardsAdjustModal', $content);
        $this->assertSame(1, substr_count($content, 'data-rewards-open>'), 'One button, not two.');

        /* Back before it, Open client profile after it. */
        $this->assertLessThan(
            strpos($content, 'data-rewards-open>'),
            strpos($content, route('clients.loyalty')),
        );
        $this->assertGreaterThan(
            strpos($content, 'data-rewards-open>'),
            strpos($content, __('loyalty.members.open_profile')),
        );
    }

    /** The profile's tab keeps its own button, beside the balance it moves. */
    public function test_the_profile_tab_still_carries_its_own_adjust_button(): void
    {
        $this->loyalty();

        $member = $this->client('Mia');
        LoyaltyEnrollment::enroll($member);

        $content = $this->get(route('clients.show', $member))->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, 'data-rewards-open>'));
    }

    /** A member's page has no meaning while the scheme is switched off. */
    public function test_the_members_page_is_closed_while_the_scheme_is_off(): void
    {
        $this->loyalty(['is_enabled' => false]);

        $member = $this->client('Mia');
        $member->forceFill(['loyalty_enrolled_at' => now(), 'loyalty_status' => 'active'])->save();

        $this->get(route('clients.loyalty.show', $member))->assertNotFound();
    }

    public function test_a_client_from_another_business_is_not_reachable(): void
    {
        $this->loyalty();

        $other = Tenant::create(['name' => 'Rival', 'slug' => 'rival-salon', 'country_code' => 'US']);

        $theirs = Client::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'client_ref' => 'CL-000001',
            'first_name' => 'Nadia', 'last_name' => 'Roy', 'status' => 'active',
        ]);

        $this->get(route('clients.loyalty.show', $theirs))->assertNotFound();
    }

    public function test_a_reader_without_client_access_is_refused(): void
    {
        $this->loyalty();

        $outsider = User::create([
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $outsider->markEmailAsVerified();
        $outsider->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($outsider->fresh())
            ->get(route('clients.loyalty'))
            ->assertForbidden();
    }
}
