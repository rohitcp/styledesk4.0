<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App Settings → Payments.
 *
 * The rule underneath this screen: a business that has never opened it must
 * still be able to take money. Every default here is "whatever the gateway can
 * do" rather than "nothing", because an empty configuration stored eagerly
 * would switch the till off for everybody who never visited the page.
 */
class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile-paysettings']);

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
    }

    public function test_the_screen_opens_and_is_linked_from_app_settings(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.payments.show'), false);

        $this->actingAs($this->owner)
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertSee(__('payments.enable'))
            ->assertSee(__('payments.processor'))
            ->assertSee(__('payments.methods'));
    }

    /** Processors that are not built say so rather than being hidden. */
    public function test_unbuilt_processors_are_listed_as_coming_soon(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.payments.show'))
            ->assertOk()
            ->assertSee(__('payments.gateways.stripe.name'))
            ->assertSee(__('payments.gateways.square.name'))
            ->assertSee(__('payments.gateways.stripe.unavailable'));
    }

    public function test_a_processor_that_is_not_built_cannot_be_chosen(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.payments.update'), [
                'payments_enabled' => '1',
                'payment_gateway' => 'stripe',
            ])
            ->assertSessionHasErrors('payment_gateway');

        $this->assertNull($this->tenant->fresh()->payment_gateway);
    }

    public function test_the_accepted_methods_are_saved(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.payments.update'), [
                'payments_enabled' => '1',
                'accepted_methods' => ['cash', 'card'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['cash', 'card'], $this->tenant->fresh()->accepted_methods);
    }

    /**
     * Unticking everything means "whatever the gateway can take", not "nothing".
     *
     * Storing an empty list would switch the till off, and nobody clearing a
     * set of checkboxes means that.
     */
    public function test_clearing_every_method_falls_back_rather_than_taking_none(): void
    {
        $this->tenant->forceFill(['accepted_methods' => ['cash']])->save();

        $this->actingAs($this->owner)
            ->patch(route('settings.payments.update'), ['payments_enabled' => '1']);

        $tenant = $this->tenant->fresh();

        $this->assertNull($tenant->accepted_methods);
        $this->assertContains('cash', $tenant->acceptedMethods());
    }

    /** A business that has never opened the screen still takes cash. */
    public function test_a_business_that_never_configured_payments_still_accepts_methods(): void
    {
        $this->assertNull($this->tenant->accepted_methods);
        $this->assertContains('cash', $this->tenant->acceptedMethods());
        $this->assertContains('card', $this->tenant->acceptedMethods());
    }

    /**
     * A method the current gateway cannot take is dropped on the way out.
     *
     * Offering Apple Pay with no processor is offering something the till
     * cannot complete.
     */
    public function test_a_method_the_gateway_cannot_take_is_not_returned(): void
    {
        $this->tenant->forceFill(['accepted_methods' => ['cash', 'apple_pay']])->save();

        $this->assertSame(['cash'], $this->tenant->fresh()->acceptedMethods());
    }

    public function test_a_percentage_deposit_is_stored_as_a_percentage(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.payments.update'), [
                'payments_enabled' => '1',
                'default_deposit_type' => 'percent',
                'default_deposit_value' => '25',
            ]);

        $tenant = $this->tenant->fresh();

        $this->assertSame('percent', $tenant->default_deposit_type);
        $this->assertSame(25, (int) $tenant->default_deposit_value);
    }

    /** A fixed amount is minor units, like every other amount in the app. */
    public function test_a_fixed_deposit_is_stored_in_minor_units(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.payments.update'), [
                'payments_enabled' => '1',
                'default_deposit_type' => 'fixed',
                'default_deposit_value' => '25.00',
            ]);

        $this->assertSame(2500, (int) $this->tenant->fresh()->default_deposit_value);
    }

    public function test_choosing_no_deposit_clears_the_amount(): void
    {
        $this->tenant->forceFill(['default_deposit_type' => 'fixed', 'default_deposit_value' => 2500])->save();

        $this->actingAs($this->owner)
            ->patch(route('settings.payments.update'), [
                'payments_enabled' => '1',
                'default_deposit_type' => 'none',
                'default_deposit_value' => '25.00',
            ]);

        $tenant = $this->tenant->fresh();

        $this->assertNull($tenant->default_deposit_type);
        $this->assertNull($tenant->default_deposit_value);
    }
}
