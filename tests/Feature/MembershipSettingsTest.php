<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\MembershipSettings;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App Settings → Membership.
 *
 * What these guard is the shape of the screen rather than its wording: nothing
 * below the switch exists until the switch is on, switching it off takes
 * nothing away, and the two answers that contradict each other — rollover and
 * reset — can only ever be stored as one.
 */
class MembershipSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Spa', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);
    }

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

    /** Everything a valid save has to carry, so a test can vary one thing. */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'is_enabled' => 1,
            'channels' => ['in_store'],
            'allow_staff_to_sell' => 1,
            'allow_start_date_selection' => 1,
            'default_activation' => 'immediately',
            'allow_rollover' => 0,
            'credit_expiry' => 'cycle',
            'allow_credits_across_locations' => 1,
            'allow_service_substitution' => 0,
            'allow_cancellation' => 1,
            'allow_pause' => 0,
            'minimum_commitment_months' => 0,
            'cancellation_notice_days' => 0,
            'cancellation_effective' => 'end_of_cycle',
        ];
    }

    private function settings(): MembershipSettings
    {
        return MembershipSettings::withoutGlobalScopes()->firstOrFail();
    }

    // -------------------------------------------------------------- reading

    public function test_reading_the_screen_does_not_write_a_row(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.membership.index'))
            ->assertOk()
            ->assertSee(__('membership.settings.enable'));

        $this->assertSame(0, MembershipSettings::withoutGlobalScopes()->count());
    }

    public function test_the_terms_are_not_asked_about_until_membership_is_on(): void
    {
        $owner = $this->actingAs($this->owner());

        $owner->get(route('settings.membership.index'))
            ->assertDontSee(__('membership.settings.rules'))
            ->assertSee(__('membership.settings.disabled_note'));

        $owner->patch(route('settings.membership.update'), $this->payload());

        $owner->get(route('settings.membership.index'))
            ->assertSee(__('membership.settings.rules'))
            ->assertDontSee(__('membership.settings.disabled_note'));
    }

    // -------------------------------------------------------------- writing

    public function test_the_screen_saves_the_terms(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.membership.update'), $this->payload([
                'default_activation' => 'start_date',
                'credit_expiry' => '6m',
                'allow_service_substitution' => 1,
                'allow_pause' => 1,
                'minimum_commitment_months' => 6,
                'cancellation_notice_days' => 30,
                'cancellation_effective' => 'immediately',
            ]))
            ->assertRedirect();

        $settings = $this->settings();

        $this->assertTrue($settings->is_enabled);
        $this->assertTrue($settings->allow_purchase_in_store);
        $this->assertFalse($settings->allow_purchase_online);
        $this->assertSame('start_date', $settings->default_activation);
        $this->assertSame('6m', $settings->credit_expiry);
        $this->assertTrue($settings->allow_service_substitution);
        $this->assertTrue($settings->allow_pause);
        $this->assertSame(6, $settings->minimum_commitment_months);
        $this->assertSame(30, $settings->cancellation_notice_days);
        $this->assertSame('immediately', $settings->cancellation_effective);
    }

    /* Rollover and reset are one question asked from both ends. Stored
       independently they produce credits that both survive the cycle and are
       wiped by it. */
    public function test_rollover_and_reset_are_stored_as_one_answer(): void
    {
        $owner = $this->actingAs($this->owner());

        $owner->patch(route('settings.membership.update'), $this->payload([
            'allow_rollover' => 1,
            'maximum_rollover' => 3,
        ]));

        $this->assertTrue($this->settings()->allow_rollover);
        $this->assertFalse($this->settings()->reset_credits_on_cycle);
        $this->assertSame(3, $this->settings()->maximum_rollover);

        $owner->patch(route('settings.membership.update'), $this->payload([
            'allow_rollover' => 0,
            'maximum_rollover' => 3,
        ]));

        $this->assertFalse($this->settings()->allow_rollover);
        $this->assertTrue($this->settings()->reset_credits_on_cycle);
        /* A cap on a rollover that does not happen is a number that
           reappears, unexplained, the day somebody turns rollover on. */
        $this->assertNull($this->settings()->maximum_rollover);
    }

    public function test_switching_membership_off_keeps_every_term(): void
    {
        $owner = $this->actingAs($this->owner());

        $owner->patch(route('settings.membership.update'), $this->payload([
            'minimum_commitment_months' => 12,
            'credit_expiry' => '12m',
        ]));

        /* What the switch's own form posts: the answer that changed, and
           every other one carried along as a hidden field. */
        $owner->patch(route('settings.membership.update'), $this->payload([
            'is_enabled' => 0,
            'minimum_commitment_months' => 12,
            'credit_expiry' => '12m',
        ]));

        $settings = $this->settings();

        $this->assertFalse($settings->is_enabled);
        $this->assertSame(12, $settings->minimum_commitment_months);
        $this->assertSame('12m', $settings->credit_expiry);
    }

    public function test_a_channel_nothing_can_sell_through_yet_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.membership.update'), $this->payload([
                'channels' => ['online'],
            ]))
            ->assertSessionHasErrors('channels.0');
    }

    public function test_a_commitment_longer_than_the_terms_allow_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.membership.update'), $this->payload([
                'minimum_commitment_months' => 36,
            ]))
            ->assertSessionHasErrors('minimum_commitment_months');
    }

    // ------------------------------------------------------------- the model

    public function test_nothing_can_be_sold_with_every_channel_closed(): void
    {
        $settings = new MembershipSettings(MembershipSettings::defaults());
        $settings->is_enabled = true;

        $this->assertTrue($settings->canSell());

        $settings->allow_purchase_in_store = false;

        $this->assertFalse($settings->canSell());
    }

    // ---------------------------------------------------------- app settings

    public function test_the_app_settings_card_opens_the_membership_screen(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.membership.index'), false);
    }
}
