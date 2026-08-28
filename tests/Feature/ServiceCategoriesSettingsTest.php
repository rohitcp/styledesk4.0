<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
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
class ServiceCategoriesSettingsTest extends TestCase
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

    private function system(string $key = 'hair'): ServiceCategory
    {
        return ServiceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)
            ->firstOrFail();
    }

    private function custom(string $name = 'Photo booth'): ServiceCategory
    {
        return ServiceCategory::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'is_system' => false,
            'status' => ServiceCategory::STATUS_ACTIVE, 'display_order' => 99,
        ]);
    }

    // ------------------------------------------------------------- defaults

    /**
     * A salon already has styling chairs and treatment rooms; naming thirty
     * of them is not the work anyone signed up for.
     */
    public function test_a_new_business_starts_with_the_whole_catalogue(): void
    {
        $categories = ServiceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->get();

        $this->assertSame(count(config('service_categories')), $categories->count());
        $this->assertTrue($categories->every(fn (ServiceCategory $c) => $c->isSystem()));
        $this->assertNotNull($categories->firstWhere('key', 'hair'));
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
        $hair = $this->system();
        $hair->forceFill(['name' => 'Hair services'])->save();

        ServiceCategory::seedDefaultsFor($this->tenant);

        $this->assertSame(
            count(config('service_categories')),
            ServiceCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())->count(),
        );
        $this->assertSame('Hair services', $hair->fresh()->name);
    }

    // ------------------------------------------------------------- the page

    public function test_the_screen_lists_categories_with_their_type_and_status(): void
    {
        $this->custom();

        $this->actingAs($this->owner)
            ->get(route('settings.services.index'))
            ->assertOk()
            ->assertSee('Hair')
            ->assertSee('Photo booth')
            ->assertSee(__('services.categories_ui.system'))
            ->assertSee(__('services.categories_ui.custom'));
    }

    public function test_a_custom_category_can_be_added(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.services.store'), [
                'name' => 'Photo booth', 'description' => 'For the ones with a camera.',
            ])
            ->assertRedirect();

        $created = ServiceCategory::withoutGlobalScopes()->where('name', 'Photo booth')->firstOrFail();

        $this->assertFalse($created->isSystem());
        $this->assertSame('For the ones with a camera.', $created->description);
    }

    /** Two categories with one name is a dropdown nobody can choose from. */
    public function test_a_duplicate_name_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.services.store'), [
                'name' => 'Hair',
            ])
            ->assertSessionHasErrors('name');
    }

    /** A system category is StyleDesk's name for it, not every business's. */
    public function test_a_system_category_can_be_renamed_and_deactivated(): void
    {
        $hair = $this->system();

        $this->actingAs($this->owner)
            ->patch(route('settings.services.update', $hair), ['name' => 'Hair services'])
            ->assertRedirect();

        $this->assertSame('Hair services', $hair->fresh()->name);

        $this->actingAs($this->owner)
            ->patch(route('settings.services.toggle', $hair))
            ->assertRedirect();

        $this->assertFalse($hair->fresh()->isActive());
    }

    /**
     * A default that can be removed is one a business has no way to get
     * back, so it never is.
     */
    public function test_a_system_category_cannot_be_deleted(): void
    {
        $hair = $this->system();

        $this->actingAs($this->owner)
            ->delete(route('settings.services.destroy', $hair))
            ->assertRedirect();

        $this->assertNotNull($hair->fresh());
    }

    public function test_an_unused_custom_category_can_be_deleted(): void
    {
        $category = $this->custom();

        $this->actingAs($this->owner)
            ->delete(route('settings.services.destroy', $category))
            ->assertRedirect();

        /* Soft-deleted, like everything else in StyleDesk that a business
           removes: gone from every list, still on the row of any record that
           referred to it. */
        $this->assertNull(ServiceCategory::query()->find($category->id));
        $this->assertNotNull($category->fresh()?->deleted_at ?? ServiceCategory::withoutGlobalScopes()->find($category->id)?->deleted_at);
    }

    /**
     * A category in use is the label on somebody's chairs; deleting it would
     * leave them uncategorised without anyone being asked.
     */
    public function test_a_custom_category_in_use_is_refused_and_kept(): void
    {
        $category = $this->custom();

        Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Photo session', 'duration_minutes' => 30,
            'service_category_id' => $category->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('settings.services.destroy', $category))
            ->assertRedirect();

        $this->assertNotNull(ServiceCategory::withoutGlobalScopes()->find($category->id));
    }

    /**
     * The order is the record, so it is stated in full: two people dragging
     * at once would otherwise produce a list neither of them arranged.
     */
    public function test_categories_can_be_reordered(): void
    {
        $first = $this->system('hair');
        $second = $this->system('barber');

        $this->actingAs($this->owner)
            ->post(route('settings.services.reorder'), ['order' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertSame(0, $second->fresh()->display_order);
        $this->assertSame(1, $first->fresh()->display_order);
    }

    /**
     * Off means "not offered for anything new". What is already in it keeps
     * it, which is the whole reason this is not a delete.
     */
    public function test_a_deactivated_category_leaves_existing_resources_alone(): void
    {
        $category = $this->custom();

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Photo session', 'duration_minutes' => 30,
            'service_category_id' => $category->id,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('settings.services.toggle', $category))
            ->assertRedirect();

        $this->assertSame($category->id, $service->fresh()->service_category_id);
        $this->assertFalse(
            ServiceCategory::query()->assignable()->get()->contains('id', $category->id),
        );
    }
}
