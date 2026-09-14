<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\LoyaltyReward;
use App\Models\LoyaltySettings;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LoyaltyPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a balance can actually buy.
 *
 * The scheme could already turn points into money at one rate, which is a
 * discount with extra steps. A catalogue is what lets a business give away an
 * upgrade it can afford rather than cash it cannot, and point the reward at
 * the services it actually wants booked.
 */
class LoyaltyRewardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme-rewards', 'country_code' => 'US']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        LoyaltySettings::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'is_enabled' => true,
        ] + LoyaltySettings::defaults());

        $this->actingAs($this->owner());
    }

    private function owner(): User
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

    private function service(string $name = 'Swedish massage', ?ServiceCategory $category = null): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'duration_minutes' => 60, 'is_active' => true,
            'service_category_id' => $category?->id,
        ]);

        $service->prices()->create(['currency_code' => 'USD', 'price_minor' => 9000]);

        return $service;
    }

    private function category(string $name = 'Signature rituals'): ServiceCategory
    {
        return ServiceCategory::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name,
        ]);
    }

    private function client(): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function rewardPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => '$10 Off Any Service',
            'type' => 'fixed_discount',
            'points_required' => 1000,
            'value' => '10.00',
            'scope' => 'all_services',
            'is_active' => '1',
        ], $overrides);
    }

    // ----------------------------------------------------------- the catalogue

    public function test_a_reward_can_be_added_to_the_catalogue(): void
    {
        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload())
            ->assertRedirect();

        $reward = LoyaltyReward::withoutGlobalScopes()->sole();

        $this->assertSame('$10 Off Any Service', $reward->name);
        $this->assertSame('fixed_discount', $reward->type);
        $this->assertSame(1000, $reward->points_required);
        $this->assertSame(1000, $reward->value_minor, 'Money is stored in minor units.');
        $this->assertTrue($reward->is_active);
    }

    public function test_the_catalogue_appears_on_the_settings_screen(): void
    {
        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload())->assertRedirect();

        $this->get(route('settings.loyalty.index'))
            ->assertOk()
            ->assertSee('$10 Off Any Service');
    }

    /**
     * Each type is worth something in a different way, and only one way.
     *
     * A reward edited from "20% off" to "$10 off" that kept its percentage
     * would be a row where two fields both claim to be the answer.
     */
    public function test_only_the_field_the_type_uses_is_kept(): void
    {
        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload([
            'type' => 'percentage_discount',
            'percent' => 20,
            'value' => '10.00',
        ]))->assertRedirect();

        $reward = LoyaltyReward::withoutGlobalScopes()->sole();

        $this->assertSame(20, $reward->percent);
        $this->assertNull($reward->value_minor, 'The amount does not survive a percentage reward.');
    }

    public function test_a_type_must_bring_the_value_it_is_worth_by(): void
    {
        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload(['value' => null]))
            ->assertSessionHasErrors('value');

        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload([
            'type' => 'percentage_discount', 'value' => null,
        ]))->assertSessionHasErrors('percent');

        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload([
            'type' => 'free_service', 'value' => null,
        ]))->assertSessionHasErrors('service_id');

        $this->assertSame(0, LoyaltyReward::withoutGlobalScopes()->count());
    }

    /** A type nothing can hand over is not a reward anybody can be given. */
    public function test_a_reward_type_that_is_not_built_yet_is_refused(): void
    {
        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload(['type' => 'free_product']))
            ->assertSessionHasErrors('type');
    }

    /**
     * "These services" with nothing chosen covers nothing, and reads on the
     * catalogue exactly like one that covers everything.
     */
    public function test_a_narrowed_reward_must_say_what_it_is_narrowed_to(): void
    {
        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload(['scope' => 'services']))
            ->assertSessionHasErrors('scope_ids');
    }

    // -------------------------------------------------------- what it covers

    public function test_a_reward_can_be_pointed_at_chosen_services(): void
    {
        $massage = $this->service('Swedish massage');
        $cut = $this->service('Cut & finish');

        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload([
            'scope' => 'services',
            'scope_ids' => [$massage->id],
        ]))->assertRedirect();

        $reward = LoyaltyReward::withoutGlobalScopes()->sole();

        $this->assertTrue($reward->covers($massage));
        $this->assertFalse($reward->covers($cut));
    }

    public function test_a_reward_can_be_pointed_at_a_whole_category(): void
    {
        $category = $this->category();
        $massage = $this->service('Swedish massage', $category);
        $cut = $this->service('Cut & finish');

        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload([
            'scope' => 'categories',
            'scope_ids' => [$category->id],
        ]))->assertRedirect();

        $reward = LoyaltyReward::withoutGlobalScopes()->sole();

        $this->assertTrue($reward->covers($massage));
        $this->assertFalse($reward->covers($cut));
    }

    // ------------------------------------------------------- what it is worth

    public function test_a_reward_never_takes_off_more_than_the_line_it_is_on(): void
    {
        $reward = LoyaltyReward::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => '$10 off', 'type' => 'fixed_discount',
            'points_required' => 1000, 'value_minor' => 1000, 'scope' => 'all_services',
        ]);

        /* A $10 reward against a $6 add-on takes six off, not ten: the
           difference would be the salon paying the client to attend. */
        $this->assertSame(600, $reward->discountMinorOn(600));
        $this->assertSame(1000, $reward->discountMinorOn(9000));
    }

    public function test_a_percentage_reward_is_worth_a_share_of_the_line(): void
    {
        $reward = LoyaltyReward::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => '20% off', 'type' => 'percentage_discount',
            'points_required' => 750, 'percent' => 20, 'scope' => 'all_services',
        ]);

        $this->assertSame(1800, $reward->discountMinorOn(9000));
    }

    /** A reward whose value is a sentence cannot be priced by the till. */
    public function test_a_custom_reward_is_worth_nothing_the_till_can_work_out(): void
    {
        $reward = LoyaltyReward::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Glass of fizz', 'type' => 'custom',
            'points_required' => 200, 'scope' => 'all_services',
        ]);

        $this->assertSame(0, $reward->discountMinorOn(9000));
    }

    // ------------------------------------------------------------ redeeming

    public function test_redeeming_a_reward_writes_one_ledger_line(): void
    {
        $client = $this->client();
        LoyaltyPoints::adjust($client, 2000, true, 'promotion');

        $reward = LoyaltyReward::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => '$10 off', 'type' => 'fixed_discount',
            'points_required' => 1000, 'value_minor' => 1000, 'scope' => 'all_services',
        ]);

        $line = LoyaltyPoints::redeemReward($client, $reward);

        $this->assertSame('redeemed', $line->type);
        $this->assertSame(-1000, $line->points);
        $this->assertSame($reward->id, $line->loyalty_reward_id);
        $this->assertSame(1000, $line->reward_value_minor, 'What it was worth on the day.');
        $this->assertSame(1000, LoyaltyPoints::balanceFor($client->fresh()));
    }

    /**
     * The one place in this engine that refuses.
     *
     * Everywhere else a balance is reconciled and a correction may push it
     * under zero — that is a fact being recorded. This is a client asking for
     * something they cannot afford, and the honest answer is no.
     */
    public function test_a_reward_cannot_be_redeemed_on_points_the_client_does_not_have(): void
    {
        $client = $this->client();
        LoyaltyPoints::adjust($client, 100, true, 'promotion');

        $reward = LoyaltyReward::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => '$10 off', 'type' => 'fixed_discount',
            'points_required' => 1000, 'value_minor' => 1000, 'scope' => 'all_services',
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            LoyaltyPoints::redeemReward($client, $reward);
        } finally {
            $this->assertSame(100, LoyaltyPoints::balanceFor($client->fresh()));
            $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->where('type', 'redeemed')->count());
        }
    }

    // ----------------------------------------------------------- retiring

    /**
     * A reward somebody has spent points on is deactivated, never deleted.
     *
     * The row is what their history reads its name from; deleting it leaves a
     * line saying "-1,000" with nothing beside it.
     */
    public function test_a_redeemed_reward_is_retired_rather_than_deleted(): void
    {
        $client = $this->client();
        LoyaltyPoints::adjust($client, 2000, true, 'promotion');

        $reward = LoyaltyReward::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => '$10 off', 'type' => 'fixed_discount',
            'points_required' => 1000, 'value_minor' => 1000, 'scope' => 'all_services',
        ]);

        LoyaltyPoints::redeemReward($client, $reward);

        $this->delete(route('settings.loyalty.rewards.destroy', $reward))->assertRedirect();

        $this->assertFalse(LoyaltyReward::withoutGlobalScopes()->find($reward->id)->is_active);
    }

    public function test_a_reward_nobody_ever_took_is_deleted(): void
    {
        $this->post(route('settings.loyalty.rewards.store'), $this->rewardPayload())->assertRedirect();

        $reward = LoyaltyReward::withoutGlobalScopes()->sole();

        $this->delete(route('settings.loyalty.rewards.destroy', $reward))->assertRedirect();

        $this->assertSame(0, LoyaltyReward::withoutGlobalScopes()->count());
    }

    // -------------------------------------------------------- who may change

    /**
     * Somebody who may open App Settings and nothing more.
     *
     * The reader this controller's own check exists for: through the group's
     * door, and refused at this particular screen. A user with no settings
     * access never gets that far — `can-manage-settings` redirects them, so
     * asserting 403 on one would be testing the wrong gate.
     */
    public function test_a_reader_without_the_loyalty_permission_cannot_change_the_catalogue(): void
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Ito',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'settings-only', 'name' => 'Settings only',
        ]);
        $role->permissions()->create(['permission' => 'settings.view', 'scope' => 'all']);

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id, 'role_id' => $role->id,
            'first_name' => 'Sam', 'last_name' => 'Ito',
            'email' => 'sam@styledesk.test', 'role' => 'administrator',
            'is_active' => true,
        ]);

        $this->actingAs($user->fresh())
            ->post(route('settings.loyalty.rewards.store'), $this->rewardPayload())
            ->assertForbidden();

        $this->assertSame(0, LoyaltyReward::withoutGlobalScopes()->count());
    }

    /** No settings access at all: turned away at the group's door, not here. */
    public function test_a_reader_outside_app_settings_never_reaches_the_catalogue(): void
    {
        $outsider = User::create([
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $outsider->markEmailAsVerified();
        $outsider->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($outsider->fresh())
            ->post(route('settings.loyalty.rewards.store'), $this->rewardPayload())
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, LoyaltyReward::withoutGlobalScopes()->count());
    }
}
