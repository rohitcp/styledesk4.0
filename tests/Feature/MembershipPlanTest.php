<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\MembershipPlan;
use App\Models\MembershipSettings;
use App\Models\Service;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Clients → Membership: building the plans a business sells.
 *
 * What these guard is that a plan is a product rather than a form: it cannot
 * exist without something included, it cannot claim a saving it does not
 * make, it cannot change kind once credits have been granted under it, and it
 * is a draft until somebody publishes it.
 */
class MembershipPlanTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

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

    private function membershipOn(array $overrides = []): MembershipSettings
    {
        return MembershipSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            $overrides + ['is_enabled' => true] + MembershipSettings::defaults(),
        );
    }

    private function service(string $name = 'Swedish Massage'): Service
    {
        return Service::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey(), 'name' => $name],
            ['duration_minutes' => 60, 'is_active' => true],
        );
    }

    /** Everything a valid save carries, so a test can vary one thing. */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'recurring',
            'name' => 'Monthly Massage Membership',
            'description' => 'One massage a month.',
            'price' => '79',
            'billing_frequency' => 'monthly',
            'services' => [
                ['service_id' => $this->service()->id, 'quantity' => 1],
            ],
            'discount_type' => 'percent',
            'discount_value' => '10',
            'location_mode' => 'all',
            'sell_in_store' => 1,
            'is_draft' => 0,
        ];
    }

    private function plan(array $overrides = []): MembershipPlan
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
            'service_id' => $this->service()->id, 'quantity' => 1, 'position' => 0,
        ]);

        return $plan->fresh(['planServices']);
    }

    // ------------------------------------------------------------- gating

    public function test_the_module_is_unreachable_until_membership_is_switched_on(): void
    {
        $this->actingAs($this->owner())
            ->get(route('membership.index'))
            ->assertNotFound();

        $this->membershipOn();

        $this->actingAs($this->owner())
            ->get(route('membership.index'))
            ->assertOk();
    }

    public function test_the_menu_only_offers_membership_when_it_is_on(): void
    {
        $owner = $this->actingAs($this->owner());

        $owner->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('membership.index'), false);

        $this->membershipOn();

        $owner->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('membership.index'), false);
    }

    // -------------------------------------------------------------- making

    public function test_choosing_the_kind_comes_before_the_form(): void
    {
        $this->membershipOn();
        $owner = $this->actingAs($this->owner());

        $owner->get(route('membership.create'))
            ->assertOk()
            ->assertSee(__('membership.choose.question'))
            ->assertDontSee(__('membership.form.basics'));

        $owner->get(route('membership.create', ['type' => 'package']))
            ->assertOk()
            ->assertSee(__('membership.form.basics'))
            ->assertSee(__('membership.form.regular_value'));
    }

    public function test_a_recurring_plan_is_created_with_its_services(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload())
            ->assertRedirect();

        $plan = MembershipPlan::withoutGlobalScopes()->with('planServices')->firstOrFail();

        $this->assertSame('recurring', $plan->type);
        $this->assertSame(7900, $plan->price_minor);
        $this->assertSame('monthly', $plan->billing_frequency);
        $this->assertFalse($plan->is_draft);
        $this->assertSame('percent', $plan->discount_type);
        $this->assertSame(10, $plan->discount_value);
        $this->assertCount(1, $plan->planServices);
        $this->assertSame(1, $plan->planServices->first()->quantity);
    }

    public function test_a_package_keeps_its_regular_value_and_no_billing_frequency(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'type' => 'package',
                'name' => 'Massage Package',
                'price' => '150',
                'regular_value' => '200',
                /* A stale field from the recurring form must not survive
                   onto a product that never bills again. */
                'billing_frequency' => 'monthly',
                'services' => [['service_id' => $this->service()->id, 'quantity' => 4]],
            ]))
            ->assertRedirect();

        $plan = MembershipPlan::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('package', $plan->type);
        $this->assertSame(15000, $plan->price_minor);
        $this->assertSame(20000, $plan->regular_value_minor);
        $this->assertNull($plan->billing_frequency);
        $this->assertSame(5000, $plan->savingMinor());
    }

    public function test_a_membership_that_includes_nothing_is_refused(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload(['services' => []]))
            ->assertSessionHasErrors('services');

        $this->assertSame(0, MembershipPlan::withoutGlobalScopes()->count());
    }

    public function test_the_same_service_cannot_be_listed_twice(): void
    {
        $this->membershipOn();
        $service = $this->service();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'services' => [
                    ['service_id' => $service->id, 'quantity' => 1],
                    ['service_id' => $service->id, 'quantity' => 2],
                ],
            ]))
            ->assertSessionHasErrors('services');
    }

    public function test_a_package_cannot_claim_a_saving_it_does_not_make(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'type' => 'package',
                'price' => '200',
                'regular_value' => '150',
                'billing_frequency' => null,
            ]))
            ->assertSessionHasErrors('regular_value');
    }

    public function test_a_plan_cannot_change_kind_after_it_exists(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->patch(route('membership.update', $plan), $this->payload(['type' => 'package']))
            ->assertSessionHasErrors('type');

        $this->assertSame('recurring', $plan->fresh()->type);
    }

    public function test_saving_as_a_draft_keeps_it_out_of_what_can_be_sold(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload(['is_draft' => 1]));

        $this->assertSame(0, MembershipPlan::withoutGlobalScopes()->sellable()->count());
        $this->assertSame('draft', MembershipPlan::withoutGlobalScopes()->firstOrFail()->status());
    }

    public function test_editing_rewrites_the_service_list_rather_than_adding_to_it(): void
    {
        $this->membershipOn();
        $plan = $this->plan();
        $facial = $this->service('Facial');

        $this->actingAs($this->owner())
            ->patch(route('membership.update', $plan), $this->payload([
                'services' => [['service_id' => $facial->id, 'quantity' => 2]],
            ]))
            ->assertRedirect();

        $lines = $plan->fresh(['planServices'])->planServices;

        $this->assertCount(1, $lines);
        $this->assertSame($facial->id, $lines->first()->service_id);
        $this->assertSame(2, $lines->first()->quantity);
    }

    // ---------------------------------------------------------- the image

    /** Upload a picture the way the form does, and return its id. */
    private function uploadImage(string $name = 'massage.jpg'): int
    {
        $response = $this->actingAs($this->owner())
            ->post(route('membership.images.store'), [
                'image' => UploadedFile::fake()->image($name, 600, 400),
            ]);

        $response->assertOk()->assertJsonStructure(['id', 'url', 'name']);

        return (int) $response->json('id');
    }

    public function test_a_picture_uploaded_before_the_plan_is_claimed_on_save(): void
    {
        $this->membershipOn();
        $fileId = $this->uploadImage();

        /* Unattached until something claims it: on the create form there is
           no plan to point it at yet. */
        $this->assertNull(StoredFile::withoutGlobalScopes()->findOrFail($fileId)->entity_id);

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload(['image_file_id' => $fileId]))
            ->assertRedirect();

        $plan = MembershipPlan::withoutGlobalScopes()->firstOrFail();
        $file = StoredFile::withoutGlobalScopes()->findOrFail($fileId);

        $this->assertSame($fileId, $plan->image_file_id);
        $this->assertSame('membership_plan', $file->entity_type);
        $this->assertSame((string) $plan->id, (string) $file->entity_id);
    }

    public function test_replacing_the_picture_discards_the_one_it_replaced(): void
    {
        $this->membershipOn();
        $first = $this->uploadImage('first.jpg');

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload(['image_file_id' => $first]));

        $plan = MembershipPlan::withoutGlobalScopes()->firstOrFail();
        $second = $this->uploadImage('second.jpg');

        $this->actingAs($this->owner())
            ->patch(route('membership.update', $plan), $this->payload(['image_file_id' => $second]));

        $this->assertSame($second, $plan->fresh()->image_file_id);
        /* A membership carries one picture, so nothing points at the old
           one any more and leaving it would be litter nobody collects.
           withoutGlobalScopes drops the soft-delete scope too, so the row
           comes back and has to be asked whether it is trashed. */
        $this->assertTrue(StoredFile::withoutGlobalScopes()->findOrFail($first)->trashed());
    }

    public function test_removing_the_picture_leaves_the_plan_without_one(): void
    {
        $this->membershipOn();
        $fileId = $this->uploadImage();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload(['image_file_id' => $fileId]));

        $plan = MembershipPlan::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner())
            ->patch(route('membership.update', $plan), $this->payload(['image_file_id' => null]));

        $this->assertNull($plan->fresh()->image_file_id);
        $this->assertTrue(StoredFile::withoutGlobalScopes()->findOrFail($fileId)->trashed());
    }

    /* An id in a form is a number a reader can change. */
    public function test_a_file_that_is_not_a_membership_picture_cannot_be_claimed(): void
    {
        $this->membershipOn();

        $foreign = StoredFile::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'category' => 'client-file',
            'storage_disk' => 'local',
            'storage_path' => 'clients/private.pdf',
            'stored_filename' => 'private.pdf',
            'original_filename' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => 100,
            'visibility' => 'private',
        ]);

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload(['image_file_id' => $foreign->id]))
            ->assertRedirect();

        $this->assertNull(MembershipPlan::withoutGlobalScopes()->firstOrFail()->image_file_id);
        /* And the document is untouched — not claimed, not deleted. */
        $this->assertNull(StoredFile::withoutGlobalScopes()->findOrFail($foreign->id)->entity_id);
    }

    public function test_a_copy_does_not_take_the_original_picture(): void
    {
        $this->membershipOn();
        $fileId = $this->uploadImage();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload(['image_file_id' => $fileId]));

        $plan = MembershipPlan::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner())->get(route('membership.duplicate', $plan));

        $copy = MembershipPlan::withoutGlobalScopes()->where('id', '!=', $plan->id)->firstOrFail();

        /* A file belongs to one row. Two plans pointing at it would mean
           editing either one's picture silently changed the other's. */
        $this->assertNull($copy->image_file_id);
        $this->assertSame($fileId, $plan->fresh()->image_file_id);
    }

    public function test_a_picture_that_is_not_an_image_is_refused(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.images.store'), [
                'image' => UploadedFile::fake()->create('prices.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('image');
    }

    // ------------------------------------------------------ credits per line

    /* Quantity describes the benefit; credits is what can be redeemed. They
       agree in every ordinary membership, and credits is the one the engine
       reads where they differ. */
    public function test_a_line_stores_its_quantity_and_its_credits(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'services' => [['service_id' => $this->service()->id, 'quantity' => 2, 'credits' => 3]],
            ]))
            ->assertRedirect();

        $line = MembershipPlan::withoutGlobalScopes()->with('planServices')->firstOrFail()->planServices->first();

        $this->assertSame(2, $line->quantity);
        $this->assertSame(3, $line->credits);
        $this->assertSame(3, $line->grantedCredits());
    }

    /* A form or an integration that only knows about quantity still
       describes a coherent benefit rather than granting one of everything. */
    public function test_credits_fall_back_to_the_quantity_when_not_given(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'services' => [['service_id' => $this->service()->id, 'quantity' => 4]],
            ]))
            ->assertRedirect();

        $line = MembershipPlan::withoutGlobalScopes()->with('planServices')->firstOrFail()->planServices->first();

        $this->assertSame(4, $line->quantity);
        $this->assertSame(4, $line->credits);
    }

    /* What the form actually posts when the credits box is left alone: the
       key is there and empty, not absent. */
    public function test_credits_fall_back_to_the_quantity_when_left_blank(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'services' => [['service_id' => $this->service()->id, 'quantity' => 4, 'credits' => '']],
            ]))
            ->assertRedirect();

        $line = MembershipPlan::withoutGlobalScopes()->with('planServices')->firstOrFail()->planServices->first();

        $this->assertSame(4, $line->quantity);
        $this->assertSame(4, $line->credits);
    }

    /* A pre-filled 1 is a number nobody chose, and it saves as though they
       had. The first row opens empty and the operator says how many. */
    public function test_the_first_service_row_opens_with_no_numbers_in_it(): void
    {
        $this->membershipOn();

        $html = $this->actingAs($this->owner())
            ->get(route('membership.create', ['type' => 'package']))
            ->assertOk()
            ->getContent();

        foreach (['quantity', 'credits'] as $field) {
            $this->assertSame(
                1,
                preg_match('/<input[^>]*name="services\[0\]\['.$field.'\]"[^>]*>/', $html, $matches),
                "no services[0][{$field}] input rendered"
            );

            $this->assertStringContainsString('value=""', $matches[0]);
        }
    }

    public function test_a_copy_carries_the_credits_across(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'services' => [['service_id' => $this->service()->id, 'quantity' => 1, 'credits' => 5]],
            ]));

        $plan = MembershipPlan::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner())->get(route('membership.duplicate', $plan));

        $copy = MembershipPlan::withoutGlobalScopes()
            ->with('planServices')->where('id', '!=', $plan->id)->firstOrFail();

        $this->assertSame(5, $copy->planServices->first()->credits);
    }

    public function test_a_credit_count_below_one_is_refused(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'services' => [['service_id' => $this->service()->id, 'quantity' => 1, 'credits' => 0]],
            ]))
            ->assertSessionHasErrors('services.0.credits');
    }

    // ------------------------------------------------- credits switched off

    /* A business whose memberships are discount-only sells the perks, not a
       list of services. Insisting on one would make that product
       unsellable. */
    public function test_a_membership_needs_no_services_when_credits_are_off(): void
    {
        $this->membershipOn(['credits_enabled' => false]);

        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload(['services' => []]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan = MembershipPlan::withoutGlobalScopes()->with('planServices')->firstOrFail();

        $this->assertCount(0, $plan->planServices);
        /* The discount is what the client bought. */
        $this->assertSame('percent', $plan->discount_type);
    }

    public function test_the_plan_form_stops_asking_about_credits(): void
    {
        $this->membershipOn(['credits_enabled' => false]);

        $this->actingAs($this->owner())
            ->get(route('membership.create', ['type' => 'recurring']))
            ->assertOk()
            /* Nothing to draw down, so no list to build and no rules to
               overrule. */
            ->assertDontSee(__('membership.form.services'))
            ->assertDontSee(__('membership.form.credits'))
            /* What the client does get is still asked about. */
            ->assertSee(__('membership.form.benefits'));
    }

    public function test_the_plan_form_asks_about_both_when_credits_are_on(): void
    {
        $this->membershipOn();

        $this->actingAs($this->owner())
            ->get(route('membership.create', ['type' => 'recurring']))
            ->assertOk()
            ->assertSee(__('membership.form.services'))
            ->assertSee(__('membership.form.credits'));
    }

    // ------------------------------------------------------ credit rules

    public function test_a_plan_with_no_opinion_follows_the_business_credit_rules(): void
    {
        $settings = $this->membershipOn(['allow_rollover' => true, 'maximum_rollover' => 4, 'credit_expiry' => '6m']);
        $plan = $this->plan();

        $rules = $plan->creditRules($settings);

        $this->assertSame('6m', $rules['expiry']);
        $this->assertTrue($rules['rollover']);
        $this->assertSame(4, $rules['maximum_rollover']);

        /* And it keeps following it: the business changing its mind changes
           the plan, which is the whole reason the override is nullable. */
        $settings->allow_rollover = false;

        $this->assertFalse($plan->creditRules($settings)['rollover']);
    }

    public function test_a_plan_can_overrule_the_business_credit_rules(): void
    {
        $settings = $this->membershipOn(['allow_rollover' => false, 'credit_expiry' => 'cycle']);
        $plan = $this->plan(['allow_rollover' => true, 'maximum_rollover' => 2, 'credit_expiry' => '12m']);

        $rules = $plan->creditRules($settings);

        $this->assertSame('12m', $rules['expiry']);
        $this->assertTrue($rules['rollover']);
        $this->assertSame(2, $rules['maximum_rollover']);
    }

    // ------------------------------------------------------------ listing

    public function test_the_two_tabs_show_the_two_kinds(): void
    {
        $this->membershipOn();
        $this->plan();
        $this->actingAs($this->owner())
            ->post(route('membership.store'), $this->payload([
                'type' => 'package', 'name' => 'Massage Package', 'price' => '150',
                'billing_frequency' => null,
                'services' => [['service_id' => $this->service()->id, 'quantity' => 4]],
            ]));

        $plans = $this->actingAs($this->owner())->getJson(route('membership.plans.data'))->json('data');
        $packages = $this->actingAs($this->owner())->getJson(route('membership.packages.data'))->json('data');

        $this->assertCount(1, $plans);
        $this->assertCount(1, $packages);
        $this->assertSame('Monthly Massage Membership', $plans[0]['name']);
        $this->assertSame('Massage Package', $packages[0]['name']);
    }

    public function test_taking_a_plan_off_sale_keeps_it_and_hides_it(): void
    {
        $this->membershipOn();
        $plan = $this->plan();

        $this->actingAs($this->owner())
            ->patch(route('membership.toggle', $plan))
            ->assertRedirect();

        $this->assertTrue($plan->fresh()->is_disabled);
        $this->assertSame('disabled', $plan->fresh()->status());
        $this->assertSame(0, MembershipPlan::withoutGlobalScopes()->sellable()->count());
        /* Still there. The people who bought it point at it. */
        $this->assertSame(1, MembershipPlan::withoutGlobalScopes()->count());
    }

    public function test_a_copy_is_a_draft_with_no_code_of_its_own(): void
    {
        $this->membershipOn();
        $plan = $this->plan(['internal_code' => 'MASSAGE-1']);

        $this->actingAs($this->owner())
            ->get(route('membership.duplicate', $plan))
            ->assertRedirect();

        $copy = MembershipPlan::withoutGlobalScopes()->where('id', '!=', $plan->id)->firstOrFail();

        $this->assertTrue($copy->is_draft);
        $this->assertNull($copy->internal_code);
        $this->assertSame(__('membership.copy_of', ['name' => $plan->name]), $copy->name);
        $this->assertCount(1, $copy->planServices);
    }

    public function test_the_plan_page_reads_as_the_client_would_be_sold_it(): void
    {
        $this->membershipOn();
        $plan = $this->plan(['discount_type' => 'percent', 'discount_value' => 10]);

        $this->actingAs($this->owner())
            ->get(route('membership.show', $plan))
            ->assertOk()
            ->assertSee($plan->name)
            ->assertSee(__('membership.show.includes'))
            ->assertSee('Swedish Massage')
            ->assertSee(__('membership.discount_off', ['amount' => '10%']));
    }
}
