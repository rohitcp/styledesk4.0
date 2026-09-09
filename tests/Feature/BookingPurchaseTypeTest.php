<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\MembershipPlan;
use App\Models\MembershipSettings;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Select Type accordion above the services on the booking screen.
 *
 * What these guard is that the switcher tells the truth. A type that cannot
 * be sold is offered and disabled with the reason beside it — a missing
 * option reads as a product StyleDesk does not have, and a disabled one names
 * the switch somebody can go and turn on — and nothing about the existing
 * booking flow moves because the card exists.
 */
class BookingPurchaseTypeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

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

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Susan', 'last_name' => 'Pena', 'email' => 'susan@acme.test',
            'role' => 'service-provider', 'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
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

    private function service(): Service
    {
        return Service::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Swedish Massage'],
            ['duration_minutes' => 60, 'is_active' => true],
        );
    }

    private function membershipOn(): MembershipSettings
    {
        return MembershipSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            ['is_enabled' => true] + MembershipSettings::defaults(),
        );
    }

    private function publishedPlan(): MembershipPlan
    {
        $plan = MembershipPlan::withoutGlobalScopes()->create([
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
            'service_id' => $this->service()->id, 'quantity' => 1, 'position' => 0,
        ]);

        return $plan;
    }

    /**
     * Everything the screen handed the booking builder.
     *
     * Read out of the props rather than off the rendered page: the whole
     * middle column is a Vue island, so the labels and the types live in one
     * JSON attribute and nothing else about them is in the HTML.
     */
    private function props(): array
    {
        $content = $this->actingAs($this->owner())
            ->get(route('bookings.create'))
            ->assertOk()
            ->getContent();

        preg_match('/data-props=\'(.*?)\'/s', $content, $matches);

        return json_decode(html_entity_decode($matches[1] ?? '{}'), true) ?? [];
    }

    /** The purchase types the screen was handed, keyed by name. */
    private function types(): array
    {
        return collect($this->props()['purchaseTypes'] ?? [])->keyBy('key')->all();
    }

    // ---------------------------------------------------------- the switcher

    public function test_the_screen_offers_all_three_types(): void
    {
        $this->service();

        $types = $this->types();

        $this->assertSame(['services', 'membership', 'gift_card'], array_keys($types));
    }

    public function test_services_is_the_one_that_can_be_sold(): void
    {
        $this->service();

        $types = $this->types();

        $this->assertTrue($types['services']['ready']);
        $this->assertNull($types['services']['reason']);
    }

    /* A type that is off says which switch turns it on, rather than being
       absent and reading as a product StyleDesk does not have. */
    public function test_membership_names_the_setting_that_would_open_it(): void
    {
        $this->service();

        $types = $this->types();

        $this->assertFalse($types['membership']['available']);
        $this->assertSame(__('bookings.purchase.membership_off'), $types['membership']['reason']);
    }

    public function test_membership_with_no_published_plan_says_so(): void
    {
        $this->service();
        $this->membershipOn();

        $types = $this->types();

        $this->assertFalse($types['membership']['available']);
        $this->assertSame(__('bookings.purchase.membership_empty'), $types['membership']['reason']);
    }

    /* A draft is not something the desk can sell, so it does not open the
       type either — that is the difference `sellable` exists to make. */
    public function test_a_draft_plan_does_not_make_membership_available(): void
    {
        $this->service();
        $this->membershipOn();
        $this->publishedPlan()->update(['is_draft' => true]);

        $this->assertFalse($this->types()['membership']['available']);
    }

    public function test_a_published_plan_makes_membership_available(): void
    {
        $this->service();
        $this->membershipOn();
        $this->publishedPlan();

        $types = $this->types();

        $this->assertTrue($types['membership']['available']);
        $this->assertTrue($types['membership']['ready']);
        /* Nothing left to explain: a type that can be chosen carries no
           reason, because the reason is only ever why it cannot be. */
        $this->assertNull($types['membership']['reason']);
    }

    public function test_gift_cards_are_offered_and_marked_coming_soon(): void
    {
        $this->service();

        $types = $this->types();

        $this->assertFalse($types['gift_card']['available']);
        $this->assertSame(__('bookings.purchase.coming_soon'), $types['gift_card']['reason']);
    }

    // --------------------------------------------------- nothing else moved

    public function test_the_switcher_is_translated_and_the_other_cards_are_untouched(): void
    {
        $this->service();

        $labels = $this->props()['labels'] ?? [];

        $this->assertSame(__('bookings.purchase.title'), $labels['purchase']['title'] ?? null);
        $this->assertSame(__('bookings.purchase.question'), $labels['purchase']['question'] ?? null);

        /* The five cards that were there before are still named, in order.
           The switcher sits above them and replaces none of them. */
        foreach (['service', 'when', 'details', 'payment', 'comms'] as $section) {
            $this->assertSame(
                __('bookings.sections.'.$section),
                $labels['sections'][$section] ?? null,
                "The [{$section}] card lost its name.",
            );
        }
    }
}
