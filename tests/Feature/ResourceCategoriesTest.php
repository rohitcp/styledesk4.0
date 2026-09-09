<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App Settings → Resources: the catalogue of resource categories.
 *
 * Two rules carry the screen. A category StyleDesk supplied may be switched
 * off and reordered but never deleted, because there is no way to get it
 * back. A category anyone created may be deleted, but only while nothing is
 * using it — the alternative is quietly uncategorising somebody's chairs.
 */
class ResourceCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio', 'slug' => 'nadia', 'business_email' => 'hello@nadia.test',
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
    }

    private function system(string $key = 'styling-chair'): ResourceCategory
    {
        return ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)
            ->firstOrFail();
    }

    private function custom(string $name = 'Photo booth'): ResourceCategory
    {
        return ResourceCategory::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'is_system' => false, 'is_active' => true,
            'default_capacity' => 1, 'position' => 99,
        ]);
    }

    // ------------------------------------------------------------- defaults

    /**
     * A salon already has styling chairs and treatment rooms; naming thirty
     * of them is not the work anyone signed up for.
     */
    public function test_a_new_business_starts_with_the_whole_catalogue(): void
    {
        $categories = ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->get();

        $this->assertSame(count(config('resources.seed_categories')), $categories->count());
        $this->assertTrue($categories->every(fn (ResourceCategory $c) => $c->isSystem()));
        $this->assertNotNull($categories->firstWhere('key', 'couples-massage-room'));
    }

    /**
     * The couples room is the reason capacity is a number: it is not a
     * different kind of thing from a massage room, it holds two people.
     */
    public function test_a_couples_room_seeds_with_a_capacity_of_two(): void
    {
        $this->assertSame(2, $this->system('couples-massage-room')->default_capacity);
    }

    /**
     * Seeding again after a rename adds nothing.
     *
     * Matched on key rather than name: matching on the name gave a business
     * that renamed a category a second copy of it the next time defaults
     * were seeded.
     */
    public function test_seeding_again_after_a_rename_does_not_duplicate(): void
    {
        $chair = $this->system();
        $chair->forceFill(['name' => 'Big chair'])->save();

        ResourceCategory::seedDefaultsFor($this->tenant);

        $this->assertSame(
            count(config('resources.seed_categories')),
            ResourceCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())->count(),
        );
        $this->assertSame('Big chair', $chair->fresh()->name);
    }

    // ------------------------------------------------------------- the page

    public function test_the_screen_lists_categories_with_their_type_and_status(): void
    {
        $this->custom();

        $this->actingAs($this->owner)
            ->get(route('settings.resources.index'))
            ->assertOk()
            ->assertSee(__('resources.categories.styling-chair'))
            ->assertSee('Photo booth')
            ->assertSee(__('resources.categories_ui.system'))
            ->assertSee(__('resources.categories_ui.custom'));
    }

    public function test_a_custom_category_can_be_added(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.resources.store'), [
                'name' => 'Photo booth', 'group' => 'general', 'default_capacity' => 2,
            ])
            ->assertRedirect();

        $created = ResourceCategory::withoutGlobalScopes()->where('name', 'Photo booth')->firstOrFail();

        $this->assertFalse($created->isSystem());
        $this->assertSame(2, $created->default_capacity);
    }

    /** Two categories with one name is a dropdown nobody can choose from. */
    public function test_a_duplicate_name_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.resources.store'), [
                'name' => __('resources.categories.styling-chair'), 'default_capacity' => 1,
            ])
            ->assertSessionHasErrors('name');
    }

    /** A system category is StyleDesk's name for it, not every business's. */
    public function test_a_system_category_can_be_renamed_and_deactivated(): void
    {
        $chair = $this->system();

        $this->actingAs($this->owner)
            ->patch(route('settings.resources.update', $chair), [
                'name' => 'Cutting chair', 'group' => 'chairs', 'default_capacity' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('Cutting chair', $chair->fresh()->name);

        $this->actingAs($this->owner)
            ->patch(route('settings.resources.toggle', $chair))
            ->assertRedirect();

        $this->assertFalse($chair->fresh()->is_active);
    }

    /**
     * A default that can be removed is one a business has no way to get
     * back, so it never is.
     */
    public function test_a_system_category_cannot_be_deleted(): void
    {
        $chair = $this->system();

        $this->actingAs($this->owner)
            ->delete(route('settings.resources.destroy', $chair))
            ->assertRedirect();

        $this->assertNotNull($chair->fresh());
    }

    public function test_an_unused_custom_category_can_be_deleted(): void
    {
        $category = $this->custom();

        $this->actingAs($this->owner)
            ->delete(route('settings.resources.destroy', $category))
            ->assertRedirect();

        /* Soft-deleted, like everything else in StyleDesk that a business
           removes: gone from every list, still on the row of any record that
           referred to it. */
        $this->assertNull(ResourceCategory::query()->find($category->id));
        $this->assertNotNull($category->fresh()?->deleted_at ?? ResourceCategory::withoutGlobalScopes()->find($category->id)?->deleted_at);
    }

    /**
     * A category in use is the label on somebody's chairs; deleting it would
     * leave them uncategorised without anyone being asked.
     */
    public function test_a_custom_category_in_use_is_refused_and_kept(): void
    {
        $category = $this->custom();

        Resource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Booth 1', 'capacity' => 1,
            'resource_category_id' => $category->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('settings.resources.destroy', $category))
            ->assertRedirect();

        $this->assertNotNull(ResourceCategory::withoutGlobalScopes()->find($category->id));
    }

    /**
     * The order is the record, so it is stated in full: two people dragging
     * at once would otherwise produce a list neither of them arranged.
     */
    public function test_categories_can_be_reordered(): void
    {
        $first = $this->system('styling-chair');
        $second = $this->system('barber-chair');

        $this->actingAs($this->owner)
            ->post(route('settings.resources.reorder'), ['order' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    /**
     * Off means "not offered for anything new". What is already in it keeps
     * it, which is the whole reason this is not a delete.
     */
    public function test_a_deactivated_category_leaves_existing_resources_alone(): void
    {
        $category = $this->custom();

        $resource = Resource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Booth 1', 'capacity' => 1,
            'resource_category_id' => $category->id,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('settings.resources.toggle', $category))
            ->assertRedirect();

        $this->assertSame($category->id, $resource->fresh()->resource_category_id);
        $this->assertFalse(
            ResourceCategory::withoutGlobalScopes()->assignable()->get()->contains('id', $category->id),
        );
    }

    /**
     * A resource whose category was switched off still shows it on its own
     * form — dropping it would blank the field, and saving any other change
     * would then quietly uncategorise the chair.
     */
    public function test_an_inactive_category_still_appears_on_a_resource_that_uses_it(): void
    {
        $category = $this->custom();

        $resource = Resource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Booth 1', 'capacity' => 1,
            'resource_category_id' => $category->id,
        ]);

        $category->forceFill(['is_active' => false])->save();

        $this->actingAs($this->owner)
            ->get(route('resources.edit', $resource))
            ->assertOk()
            ->assertSee('Photo booth');
    }
}
