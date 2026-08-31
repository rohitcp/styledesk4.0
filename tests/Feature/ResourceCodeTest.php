<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\ResourceCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resource codes: numbered by the app, unique within the business, and
 * checked beside the field while they are typed.
 *
 * The rule the whole thing rests on is that a code is a *name* for a thing
 * rather than a calculation about it: it is issued once, it is never
 * recalculated, and a number that has been used is never handed out again
 * even after the resource holding it is gone.
 */
class ResourceCodeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Nadia Hair Studio', 'slug' => 'nadia-codes']);

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

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Treatment room 2',
            'capacity' => 1,
            'resource_category_id' => $this->category()->id,
            'location_id' => $this->location()->id,
            'is_active' => 1,
            'availability_status' => 'available',
            'availability_type' => 'location',
        ];
    }

    private function category(): ResourceCategory
    {
        return ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'treatment-room')
            ->firstOrFail();
    }

    private function location(): Location
    {
        return Location::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->firstOr(fn () => $this->tenant->locations()->create([
                'name' => 'Downtown', 'address_line1' => '1 High Street', 'city' => 'Leeds',
                'postal_code' => 'LS1 1AA', 'country' => 'GB', 'timezone' => 'Europe/London',
            ]));
    }

    private function resource(array $attributes = []): Resource
    {
        return Resource::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Chair', 'capacity' => 1,
        ]);
    }

    // ------------------------------------------------------------ numbering

    public function test_the_first_resource_is_numbered_one(): void
    {
        $this->assertSame('RES-001', ResourceCode::next($this->tenant));
    }

    public function test_the_next_code_follows_the_highest_already_issued(): void
    {
        $this->resource(['code' => 'RES-001']);
        $this->resource(['code' => 'RES-007']);

        $this->assertSame('RES-008', ResourceCode::next($this->tenant));
    }

    /**
     * Counted from the highest number issued, not from how many exist.
     *
     * Deleting the seventh of seven must not hand the next one number 7
     * again: the old label may still be on the old chair, and the archived
     * row still holds the code.
     */
    public function test_a_deleted_resource_does_not_free_its_number(): void
    {
        $this->resource(['code' => 'RES-001']);
        $seventh = $this->resource(['code' => 'RES-007']);

        $seventh->delete();

        $this->assertSame('RES-008', ResourceCode::next($this->tenant));
    }

    /**
     * A number somebody typed by hand is skipped rather than collided with.
     */
    public function test_a_hand_typed_code_in_the_way_is_stepped_over(): void
    {
        $this->resource(['code' => 'RES-002']);
        $this->resource(['code' => 'RES-003']);

        $this->assertSame('RES-004', ResourceCode::next($this->tenant));
    }

    /**
     * A business that used to number things another way is starting again,
     * not continuing from a scheme it has left behind.
     */
    public function test_codes_under_another_prefix_are_ignored(): void
    {
        $this->resource(['code' => 'OLD-42']);

        $this->assertSame('RES-001', ResourceCode::next($this->tenant));
    }

    public function test_the_business_sets_the_prefix_and_the_width(): void
    {
        $this->tenant->forceFill([
            'resource_code_prefix' => 'Rsrc-', 'resource_code_padding' => 4,
        ])->save();

        $this->assertSame('Rsrc-0001', ResourceCode::next($this->tenant->fresh()));
    }

    public function test_the_add_form_arrives_with_the_next_code_in_it(): void
    {
        $this->resource(['code' => 'RES-004']);

        $this->actingAs($this->owner)
            ->get(route('resources.create'))
            ->assertOk()
            ->assertSee('RES-005', false);
    }

    public function test_a_blank_code_is_numbered_on_save(): void
    {
        $this->resource(['code' => 'RES-001']);

        $this->actingAs($this->owner)
            ->post(route('resources.store'), $this->payload(['code' => '']))
            ->assertRedirect();

        $this->assertSame('RES-002', Resource::withoutGlobalScopes()
            ->where('name', 'Treatment room 2')->value('code'));
    }

    /**
     * A code the reader typed is theirs; it is not replaced by a generated one.
     */
    public function test_a_typed_code_is_kept(): void
    {
        $this->actingAs($this->owner)
            ->post(route('resources.store'), $this->payload(['code' => 'CHAIR-A']))
            ->assertRedirect();

        $this->assertSame('CHAIR-A', Resource::withoutGlobalScopes()
            ->where('name', 'Treatment room 2')->value('code'));
    }

    // ----------------------------------------------------------- uniqueness

    public function test_a_duplicate_code_is_refused(): void
    {
        $this->resource(['code' => 'RES-001']);

        $this->actingAs($this->owner)
            ->from(route('resources.create'))
            ->post(route('resources.store'), $this->payload(['code' => 'RES-001']))
            ->assertSessionHasErrors('code');
    }

    /**
     * Unique within the business and only there: two salons both numbering
     * their first chair RES-001 is not a conflict.
     */
    public function test_another_business_may_use_the_same_code(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-codes']);
        Resource::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(), 'name' => 'Chair', 'capacity' => 1, 'code' => 'RES-001',
        ]);

        $this->actingAs($this->owner)
            ->post(route('resources.store'), $this->payload(['code' => 'RES-001']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_resource_is_not_a_duplicate_of_itself(): void
    {
        $resource = $this->resource(['code' => 'RES-001', 'resource_category_id' => $this->category()->id, 'location_id' => $this->location()->id]);

        $this->actingAs($this->owner)
            ->patch(route('resources.update', $resource), $this->payload(['code' => 'RES-001', 'name' => 'Chair']))
            ->assertSessionHasNoErrors();
    }

    /**
     * The name is what a resource cannot be saved without.
     */
    public function test_a_resource_without_a_name_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from(route('resources.create'))
            ->post(route('resources.store'), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    // -------------------------------------------------- the live check

    public function test_the_form_carries_the_live_validation_rules(): void
    {
        $page = $this->actingAs($this->owner)
            ->get(route('resources.create'))
            ->assertOk();

        $page->assertSee('data-validate-form', false);
        $page->assertSee('data-rules="required|max:120"', false);
        $page->assertSee('data-remote-check', false);
    }

    public function test_the_code_check_reports_a_code_already_in_use(): void
    {
        $this->resource(['code' => 'RES-001']);

        $this->actingAs($this->owner)
            ->getJson(route('resources.code-in-use', ['value' => 'RES-001']))
            ->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_the_code_check_passes_a_free_code(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('resources.code-in-use', ['value' => 'RES-999']))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_the_code_check_does_not_report_the_resource_being_edited(): void
    {
        $resource = $this->resource(['code' => 'RES-001']);

        $this->actingAs($this->owner)
            ->getJson(route('resources.code-in-use', ['value' => 'RES-001', 'ignore' => $resource->id]))
            ->assertJson(['ok' => true]);
    }

    /**
     * Another business's codes are not visible through this endpoint, which
     * would otherwise answer "does this salon have a RES-001".
     */
    public function test_the_code_check_only_sees_this_business(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-check']);
        Resource::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(), 'name' => 'Chair', 'capacity' => 1, 'code' => 'RES-001',
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('resources.code-in-use', ['value' => 'RES-001']))
            ->assertJson(['ok' => true]);
    }

    // ------------------------------------------------------------- settings

    public function test_the_numbering_format_is_saved_from_app_settings(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.resources.code-format'), [
                'resource_code_prefix' => 'Rsrc-',
                'resource_code_padding' => 4,
            ])
            ->assertRedirect(route('settings.resources.index'));

        $this->tenant->refresh();

        $this->assertSame('Rsrc-', $this->tenant->resource_code_prefix);
        $this->assertSame(4, (int) $this->tenant->resource_code_padding);
        $this->assertSame('Rsrc-0001', ResourceCode::next($this->tenant));
    }

    public function test_a_prefix_with_punctuation_in_it_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from(route('settings.resources.index'))
            ->patch(route('settings.resources.code-format'), [
                'resource_code_prefix' => 'RES/#',
                'resource_code_padding' => 3,
            ])
            ->assertSessionHasErrors('resource_code_prefix');
    }

    /**
     * Blank means "the product's default", not an empty prefix.
     */
    public function test_clearing_the_prefix_goes_back_to_the_default(): void
    {
        $this->tenant->forceFill(['resource_code_prefix' => 'Rsrc-'])->save();

        $this->actingAs($this->owner)
            ->patch(route('settings.resources.code-format'), [
                'resource_code_prefix' => '',
                'resource_code_padding' => 3,
            ]);

        $this->assertNull($this->tenant->fresh()->resource_code_prefix);
        $this->assertSame('RES-001', ResourceCode::next($this->tenant->fresh()));
    }

    /**
     * Changing the format leaves the codes already given out alone.
     */
    public function test_existing_codes_are_not_rewritten(): void
    {
        $chair = $this->resource(['code' => 'RES-001']);

        $this->actingAs($this->owner)->patch(route('settings.resources.code-format'), [
            'resource_code_prefix' => 'Rsrc-',
            'resource_code_padding' => 4,
        ]);

        $this->assertSame('RES-001', $chair->fresh()->code);
    }

    public function test_the_settings_screen_shows_the_next_code(): void
    {
        $this->resource(['code' => 'RES-004']);

        $this->actingAs($this->owner)
            ->get(route('settings.resources.index'))
            ->assertOk()
            ->assertSee('RES-005');
    }
}
