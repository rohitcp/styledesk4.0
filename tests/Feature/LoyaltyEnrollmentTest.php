<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientLoyaltyPoint;
use App\Models\Location;
use App\Models\LoyaltySettings;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LoyaltyEnrollment;
use App\Support\LoyaltyPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Joining the rewards scheme, from the Add Client form.
 *
 * The rule these tests exist for is the one about ordering: the loyalty
 * account is created after the client is saved and never before. A tick box
 * on a half-filled form must leave nothing behind — no member number, no
 * welcome points, no account attached to nobody.
 */
class LoyaltyEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme-enrol', 'country_code' => 'US']);

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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function addClient(array $extra = []): TestResponse
    {
        return $this->post(route('clients.store'), array_merge([
            'first_name' => 'Mia',
            'last_name' => 'Baker',
            'mobile' => '(973) 555-1234',
            'status' => Client::STATUS_ACTIVE,
            'confirm_duplicate' => 1,
        ], $extra));
    }

    // ------------------------------------------------------------- joining

    public function test_a_new_client_is_enrolled_when_the_box_is_ticked(): void
    {
        $this->loyalty();

        $this->addClient(['loyalty_enroll' => 1])->assertRedirect();

        $client = Client::withoutGlobalScopes()->sole();

        $this->assertTrue($client->isEnrolledInLoyalty());
        $this->assertNotNull($client->loyalty_enrolled_at);
        $this->assertSame($this->owner->id, $client->loyalty_enrolled_by);
        $this->assertSame('client_creation', $client->loyalty_enrollment_source);
    }

    public function test_a_member_number_is_issued_and_never_repeated(): void
    {
        $this->loyalty();

        $this->addClient(['loyalty_enroll' => 1])->assertRedirect();
        $this->addClient(['first_name' => 'Tom', 'mobile' => '(305) 555 0100', 'loyalty_enroll' => 1])->assertRedirect();

        $ids = Client::withoutGlobalScopes()->pluck('loyalty_member_id')->filter()->values();

        $this->assertCount(2, $ids);
        $this->assertSame($ids->unique()->count(), $ids->count());
        $this->assertStringStartsWith('RW-', $ids->first());
    }

    public function test_a_client_added_without_the_box_is_not_enrolled(): void
    {
        $this->loyalty();

        $this->addClient()->assertRedirect();

        $client = Client::withoutGlobalScopes()->sole();

        $this->assertFalse($client->isEnrolledInLoyalty());
        $this->assertNull($client->loyalty_member_id);
        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    /** A paused scheme signs nobody up to something that is not running. */
    public function test_nobody_is_enrolled_while_the_scheme_is_off(): void
    {
        $this->loyalty(['is_enabled' => false]);

        $this->addClient(['loyalty_enroll' => 1])->assertRedirect();

        $this->assertFalse(Client::withoutGlobalScopes()->sole()->isEnrolledInLoyalty());
    }

    // ------------------------------------------------------ welcome points

    public function test_a_welcome_bonus_is_credited_as_its_own_kind_of_line(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $this->addClient(['loyalty_enroll' => 1])->assertRedirect();

        $client = Client::withoutGlobalScopes()->sole();
        $line = ClientLoyaltyPoint::withoutGlobalScopes()->sole();

        $this->assertSame('welcome', $line->type, 'Not an adjustment somebody made.');
        $this->assertSame(50, $line->points);
        $this->assertSame(50, LoyaltyPoints::balanceFor($client));
        $this->assertSame(50, LoyaltyEnrollment::welcomePointsFor($client));
    }

    public function test_a_business_that_gives_no_welcome_bonus_writes_no_line(): void
    {
        $this->loyalty(['welcome_points' => 0]);

        $this->addClient(['loyalty_enroll' => 1])->assertRedirect();

        $this->assertTrue(Client::withoutGlobalScopes()->sole()->isEnrolledInLoyalty());
        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    /**
     * Enrolling twice is the same person joining once.
     *
     * Otherwise every later "join the scheme" path is a second welcome bonus
     * and a rewritten joining date.
     */
    public function test_enrolling_an_existing_member_changes_nothing(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $this->addClient(['loyalty_enroll' => 1])->assertRedirect();

        $client = Client::withoutGlobalScopes()->sole();
        $memberId = $client->loyalty_member_id;
        $joined = $client->loyalty_enrolled_at;

        $this->assertFalse(LoyaltyEnrollment::enroll($client->fresh()), 'Already a member.');

        $client = $client->fresh();

        $this->assertSame($memberId, $client->loyalty_member_id);
        $this->assertEquals($joined, $client->loyalty_enrolled_at);
        $this->assertSame(50, LoyaltyPoints::balanceFor($client), 'No second bonus.');
    }

    // ------------------------------------------------------------- ordering

    /**
     * The rule the brief is most insistent about.
     *
     * A creation that fails validation must leave no member number, no
     * welcome points and no account attached to nobody.
     */
    public function test_a_failed_creation_leaves_no_loyalty_account_behind(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $this->post(route('clients.store'), [
            /* No first name: the one field the form cannot do without. */
            'last_name' => 'Baker',
            'status' => Client::STATUS_ACTIVE,
            'loyalty_enroll' => 1,
            'confirm_duplicate' => 1,
        ])->assertSessionHasErrors('first_name');

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------ the trail

    public function test_joining_is_written_to_the_clients_timeline(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $this->addClient(['loyalty_enroll' => 1])->assertRedirect();

        $types = ClientActivity::withoutGlobalScopes()->pluck('type');

        $this->assertContains('loyalty.enrolled', $types);
        $this->assertContains('loyalty.welcome_points', $types);
    }

    // ------------------------------------------------------------ the form

    public function test_the_form_offers_enrolment_only_where_the_scheme_runs(): void
    {
        $this->loyalty(['is_enabled' => false]);

        $this->get(route('clients.create'))
            ->assertOk()
            ->assertDontSee(__('loyalty.enrollment.section'));

        $this->loyalty(['is_enabled' => true]);

        $this->get(route('clients.create'))
            ->assertOk()
            ->assertSee(__('loyalty.enrollment.section'));
    }

    public function test_the_welcome_bonus_is_shown_and_cannot_be_edited_at_the_desk(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $content = $this->get(route('clients.create'))->assertOk()->getContent();

        $this->assertStringContainsString(__('loyalty.enrollment.welcome_badge', ['points' => '50']), $content);
        /* Shown as a statement, never as a field somebody can retype. */
        $this->assertStringNotContainsString('name="welcome_points"', $content);
    }

    /**
     * A business that enrols everybody says so rather than showing a ticked
     * box nobody may untick, which reads as a broken control.
     */
    public function test_automatic_enrolment_states_itself_instead_of_asking(): void
    {
        $this->loyalty(['enrollment_mode' => 'auto']);

        $this->get(route('clients.create'))
            ->assertOk()
            ->assertSee(__('loyalty.enrollment.automatic', ['program' => 'Rewards']))
            ->assertDontSee(__('loyalty.enrollment.enroll_hint'));
    }

    public function test_opt_in_leaves_the_box_unticked(): void
    {
        $this->loyalty(['enrollment_mode' => 'opt_in']);

        $this->get(route('clients.create'))->assertOk();

        /* Nothing ticked means nothing posted, and nothing posted means no
           enrolment — the default is what the setting says it is. */
        $this->addClient()->assertRedirect();

        $this->assertFalse(Client::withoutGlobalScopes()->sole()->isEnrolledInLoyalty());
    }

    // --------------------------------------------------------- the profile

    public function test_the_profile_shows_the_membership_and_its_number(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $this->addClient(['loyalty_enroll' => 1])->assertRedirect();

        $client = Client::withoutGlobalScopes()->sole();

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee(__('loyalty.enrollment.enrolled'))
            ->assertSee($client->loyalty_member_id);
    }

    public function test_a_client_who_never_joined_is_said_to_have_not_joined(): void
    {
        $this->loyalty();

        $this->addClient()->assertRedirect();

        $this->get(route('clients.show', Client::withoutGlobalScopes()->sole()))
            ->assertOk()
            ->assertSee(__('loyalty.enrollment.not_enrolled'));
    }

    // -------------------------------------------------------- the settings

    public function test_the_settings_screen_saves_the_enrolment_rules(): void
    {
        $this->patch(route('settings.loyalty.update'), [
            'is_enabled' => 1,
            'program_name' => 'Glow Rewards',
            'spend_amount' => 1,
            'points_earned' => 1,
            'points_required' => 500,
            'reward_value' => '5.00',
            'minimum_redemption' => 500,
            'expiry' => 'never',
            'enrollment_mode' => 'opt_in',
            'welcome_points' => 75,
        ])->assertRedirect();

        $settings = LoyaltySettings::withoutGlobalScopes()->sole();

        $this->assertSame('opt_in', $settings->enrollment_mode);
        $this->assertSame(75, $settings->welcome_points);
    }

    public function test_an_enrolment_mode_that_does_not_exist_is_refused(): void
    {
        $this->patch(route('settings.loyalty.update'), [
            'is_enabled' => 1,
            'program_name' => 'Glow Rewards',
            'spend_amount' => 1,
            'points_earned' => 1,
            'points_required' => 500,
            'reward_value' => '5.00',
            'minimum_redemption' => 500,
            'expiry' => 'never',
            'enrollment_mode' => 'whenever',
            'welcome_points' => 0,
        ])->assertSessionHasErrors('enrollment_mode');
    }
}
