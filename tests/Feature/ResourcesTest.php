<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resources: the chairs, rooms and equipment a booking needs as well as a
 * person.
 *
 * Two rules carry the module. A resource is never deleted, because it appears
 * in appointments that already happened; and "unavailable" is a period with a
 * reason, not a flag, because a calendar has to say when the room is back.
 */
class ResourcesTest extends TestCase
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

    private function resource(array $attributes = []): Resource
    {
        return Resource::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Chair 1',
            'capacity' => 1,
        ]);
    }

    // ------------------------------------------------------------- defaults

    /**
     * A salon already has styling chairs and treatment rooms; naming them is
     * not the work anyone signed up for.
     */
    public function test_a_new_business_starts_with_resource_categories(): void
    {
        $categories = ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->get();

        $this->assertSame(count(config('resources.seed_categories')), $categories->count());
        $this->assertNotNull($categories->firstWhere('name', __('resources.categories.styling-chair')));
    }

    /**
     * The couples room is the reason capacity is a number: it is not a
     * different kind of thing from a massage room, it holds two people.
     */
    public function test_a_couples_room_seeds_with_a_capacity_of_two(): void
    {
        $couples = ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('name', __('resources.categories.couples-massage-room'))
            ->firstOrFail();

        $this->assertSame(2, $couples->default_capacity);
    }

    // ------------------------------------------------------------- the page

    public function test_the_listing_shows_resources_grouped_by_category(): void
    {
        $chairs = ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('name', __('resources.categories.styling-chair'))
            ->firstOrFail();

        $this->resource(['name' => 'Window chair', 'resource_category_id' => $chairs->id]);

        $this->actingAs($this->owner)
            ->get(route('resources.index'))
            ->assertOk()
            ->assertSee('Window chair')
            ->assertSee(__('resources.categories.styling-chair'));
    }

    /** An empty module explains itself rather than offering filters over nothing. */
    public function test_a_business_with_no_resources_sees_the_empty_state(): void
    {
        $this->actingAs($this->owner)
            ->get(route('resources.index'))
            ->assertOk()
            ->assertSee(__('resources.none_yet'))
            ->assertDontSee(__('resources.search'));
    }

    public function test_the_listing_can_be_searched(): void
    {
        $this->resource(['name' => 'Window chair']);
        $this->resource(['name' => 'Sauna']);

        $this->actingAs($this->owner)
            ->get(route('resources.index', ['search' => 'sauna']))
            ->assertOk()
            ->assertSee('Sauna')
            ->assertDontSee('Window chair');
    }

    // ------------------------------------------------------------- writing

    public function test_a_resource_can_be_added(): void
    {
        $location = Location::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())->first();

        $this->actingAs($this->owner)
            ->post(route('resources.store'), [
                'name' => 'Treatment room 2',
                'capacity' => 1,
                'location_id' => $location?->id,
            ])
            ->assertRedirect();

        $this->assertNotNull(Resource::withoutGlobalScopes()->where('name', 'Treatment room 2')->first());
    }

    public function test_capacity_must_be_at_least_one(): void
    {
        $this->actingAs($this->owner)
            ->post(route('resources.store'), ['name' => 'Nothing room', 'capacity' => 0])
            ->assertSessionHasErrors('capacity');
    }

    /**
     * Retired, never deleted: the chair is on every appointment it was used
     * for, and removing the row would rewrite them.
     */
    public function test_a_resource_is_retired_rather_than_deleted(): void
    {
        $resource = $this->resource();

        $this->actingAs($this->owner)
            ->patch(route('resources.toggle', $resource))
            ->assertRedirect();

        $this->assertFalse($resource->fresh()->is_active);
        $this->assertNotNull(Resource::withoutGlobalScopes()->find($resource->id));
    }

    // -------------------------------------------------------- availability

    public function test_a_block_makes_a_resource_unavailable_and_says_why(): void
    {
        $resource = $this->resource();

        $this->actingAs($this->owner)
            ->post(route('resources.block', $resource), [
                'reason' => 'maintenance',
                'starts_at' => now()->subHour()->toDateTimeString(),
                'ends_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();

        $resource = $resource->fresh()->load('blocks');

        $this->assertSame('blocked', $resource->availabilityStatus());
        $this->assertFalse($resource->isBookableAt());
        $this->assertSame(__('resources.block_reasons.maintenance'), $resource->blockAt()->reasonLabel());
    }

    /** A block that has not started yet does not make anything unavailable today. */
    public function test_a_future_block_does_not_affect_today(): void
    {
        $resource = $this->resource();

        $resource->blocks()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reason' => 'cleaning',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(2),
        ]);

        $this->assertTrue($resource->fresh()->load('blocks')->isBookableAt());
    }

    /** A block that has ended clears itself; nobody has to remember to undo it. */
    public function test_a_finished_block_clears_itself(): void
    {
        $resource = $this->resource();

        $resource->blocks()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reason' => 'repair',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($resource->fresh()->load('blocks')->isBookableAt());
    }

    /**
     * "Out for repair, no date yet" is a real state, and forcing an invented
     * end date would put a lie in the calendar.
     */
    public function test_a_block_with_no_end_runs_until_it_is_removed(): void
    {
        $resource = $this->resource();

        $block = $resource->blocks()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reason' => 'repair',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $this->assertFalse($resource->fresh()->load('blocks')->isBookableAt());
        $this->assertFalse($resource->fresh()->load('blocks')->isBookableAt(now()->addYear()));

        $this->actingAs($this->owner)
            ->delete(route('resources.unblock', [$resource, $block]))
            ->assertRedirect();

        $this->assertTrue($resource->fresh()->load('blocks')->isBookableAt());
    }

    public function test_a_block_cannot_end_before_it_starts(): void
    {
        $resource = $this->resource();

        $this->actingAs($this->owner)
            ->post(route('resources.block', $resource), [
                'reason' => 'cleaning',
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('ends_at');
    }

    /** A retired resource is gone, not under maintenance. */
    public function test_a_retired_resource_reads_as_retired_rather_than_unavailable(): void
    {
        $resource = $this->resource(['is_active' => false]);

        $this->assertSame('inactive', $resource->load('blocks')->availabilityStatus());
    }

    // ---------------------------------------------------------- permissions

    public function test_a_role_without_the_permission_cannot_open_the_module(): void
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'stylist@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => 'service-provider',
        ]);

        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'service-provider')
            ->firstOrFail()
            ->permissions()->where('permission', 'like', 'resources.%')->delete();

        $this->actingAs($user->fresh())
            ->get(route('resources.index'))
            ->assertForbidden();
    }

    /** Another business's chair is not something to edit by guessing an id. */
    public function test_another_businesss_resource_is_out_of_reach(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $theirs = Resource::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(), 'name' => 'Their chair', 'capacity' => 1,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('resources.update', $theirs), ['name' => 'Mine now', 'capacity' => 1])
            ->assertNotFound();

        $this->assertSame('Their chair', $theirs->fresh()->name);
    }
}
