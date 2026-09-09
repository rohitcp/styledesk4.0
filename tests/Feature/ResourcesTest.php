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

    /**
     * The search reaches past the name.
     *
     * "Riverside" is how somebody looks for the chairs at Riverside, and
     * "maintenance" is how they look for what is out of action. Neither word
     * is in any resource's name, and a search that only read the name would
     * answer nothing to both.
     */
    public function test_the_search_matches_more_than_the_resource_name(): void
    {
        $riverside = $this->tenant->locations()->create([
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
        ]);

        $category = ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->firstOrFail();

        $chair = $this->resource([
            'name' => 'Chair 1',
            'code' => 'CH-001',
            'location_id' => $riverside->id,
            'description' => 'By the window.',
        ]);

        $bar = $this->resource([
            'name' => 'Colour bar',
            'resource_category_id' => $category->id,
            'availability_status' => 'maintenance',
        ]);

        $retired = $this->resource(['name' => 'Old basin', 'is_active' => false]);

        $find = fn (string $term) => collect(
            $this->actingAs($this->owner)
                ->getJson(route('resources.data', ['search' => $term]))
                ->assertOk()
                ->json('data')
        )->pluck('name')->sort()->values()->all();

        /* The code, the branch and the description — none of them the name. */
        $this->assertSame(['Chair 1'], $find('CH-001'));
        $this->assertSame(['Chair 1'], $find('riverside'));
        $this->assertSame(['Chair 1'], $find('window'));

        /* The category it is filed under. */
        $this->assertContains('Colour bar', $find($category->name));

        /* Availability and status, typed as the words on the screen. */
        $this->assertSame(['Colour bar'], $find(__('resources.form.availability.maintenance')));
        $this->assertSame([$retired->name], $find(__('resources.form.inactive')));

        /* Case-insensitive and partial. */
        $this->assertSame(['Chair 1'], $find('ch-0'));
        $this->assertSame(['Colour bar'], $find('COLOUR B'));
    }

    // ------------------------------------------------------------- the page

    /**
     * The page mounts the shared grid; the rows come from their own endpoint.
     *
     * The same split the clients and services listings use, so all three are
     * one component with different data.
     */
    public function test_the_listing_mounts_the_shared_grid(): void
    {
        $this->resource();

        $this->actingAs($this->owner)
            ->get(route('resources.index'))
            ->assertOk()
            ->assertSee('data-grid', false)
            ->assertSee(route('resources.data'), false);
    }

    public function test_a_row_carries_its_columns_already_worded(): void
    {
        $chairs = ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'styling-chair')
            ->firstOrFail();

        $this->resource([
            'name' => 'Window chair',
            'resource_category_id' => $chairs->id,
            'capacity' => 2,
        ]);

        $row = $this->actingAs($this->owner)
            ->getJson(route('resources.data'))
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Window chair', $row['name']);
        $this->assertSame(__('resources.categories.styling-chair'), $row['category']);
        $this->assertSame(__('resources.holds_many', ['count' => 2]), $row['capacity']);
        $this->assertSame(__('resources.availability.available'), $row['availability']);
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

    public function test_the_rows_can_be_searched(): void
    {
        $this->resource(['name' => 'Window chair']);
        $this->resource(['name' => 'Sauna']);

        $names = $this->actingAs($this->owner)
            ->getJson(route('resources.data', ['search' => 'sauna']))
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Sauna'], $names);
    }

    // ------------------------------------------------------------- writing

    /** What a resource needs before it can be saved at all. */
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

    private function category(string $key = 'treatment-room'): ResourceCategory
    {
        return ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)
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

    public function test_a_resource_can_be_added(): void
    {
        $this->actingAs($this->owner)
            ->post(route('resources.store'), $this->payload())
            ->assertRedirect();

        $this->assertNotNull(Resource::withoutGlobalScopes()->where('name', 'Treatment room 2')->first());
    }

    /**
     * A resource nobody can categorise cannot be asked for by category,
     * which is the whole way a service claims one.
     */
    public function test_a_resource_needs_a_category_and_a_location(): void
    {
        $this->actingAs($this->owner)
            ->post(route('resources.store'), [
                'name' => 'Nowhere room', 'capacity' => 1,
                'availability_status' => 'available', 'availability_type' => 'location',
            ])
            ->assertSessionHasErrors(['resource_category_id', 'location_id']);
    }

    /**
     * Its own week is kept only when it keeps one.
     *
     * Switching back to the location's hours clears the days rather than
     * leaving them: a half-remembered week is one the diary would still
     * believe in the next time somebody switched back.
     */
    public function test_custom_hours_are_kept_only_while_the_resource_keeps_its_own(): void
    {
        $this->actingAs($this->owner)
            ->post(route('resources.store'), $this->payload([
                'availability_type' => 'custom',
                'hours' => [
                    1 => ['is_available' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00'],
                    0 => ['is_available' => 0],
                ],
            ]))
            ->assertRedirect();

        $resource = Resource::withoutGlobalScopes()->where('name', 'Treatment room 2')->firstOrFail();

        $monday = $resource->hours()->where('day', 1)->firstOrFail();
        $this->assertTrue($monday->is_available);
        $this->assertSame('09:00', substr((string) $monday->starts_at, 0, 5));

        /* A day marked unavailable keeps no times: two stored hours behind an
           unticked day is a day the diary could still be read as open. */
        $sunday = $resource->hours()->where('day', 0)->firstOrFail();
        $this->assertFalse($sunday->is_available);
        $this->assertNull($sunday->starts_at);

        $this->actingAs($this->owner)
            ->patch(route('resources.update', $resource), $this->payload(['availability_type' => 'location']))
            ->assertRedirect();

        $this->assertSame(0, $resource->fresh()->hours()->count());
    }

    /** A day's closing time has to come after it opens. */
    public function test_a_day_that_ends_before_it_starts_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('resources.store'), $this->payload([
                'availability_type' => 'custom',
                'hours' => [1 => ['is_available' => 1, 'starts_at' => '17:00', 'ends_at' => '09:00']],
            ]))
            ->assertSessionHasErrors('hours.1.ends_at');
    }

    /** Saving lands on the resource, which is what the reader wants to see. */
    public function test_saving_opens_the_resource(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('resources.store'), $this->payload());

        $resource = Resource::withoutGlobalScopes()->where('name', 'Treatment room 2')->firstOrFail();

        $response->assertRedirect(route('resources.show', $resource));
    }

    public function test_the_view_page_shows_the_resource(): void
    {
        $resource = $this->resource(['name' => 'Room A', 'code' => 'ROOM-A']);

        $this->actingAs($this->owner)
            ->get(route('resources.show', $resource))
            ->assertOk()
            ->assertSee('Room A')
            ->assertSee('ROOM-A')
            ->assertSee(route('resources.edit', $resource), false);
    }

    /**
     * Delete removes it from every list without rewriting history.
     *
     * Soft, always: a chair appears in appointments that already happened,
     * and a hard delete would take those with it. Distinct from retiring,
     * which is a chair the business still owns.
     */
    public function test_a_resource_can_be_deleted_without_losing_its_history(): void
    {
        $resource = $this->resource(['name' => 'Old chair']);

        $this->actingAs($this->owner)
            ->delete(route('resources.destroy', $resource))
            ->assertRedirect(route('resources.index'));

        $this->assertNull(Resource::query()->find($resource->id));
        $this->assertNotNull(Resource::withoutGlobalScopes()->find($resource->id)->deleted_at);
    }

    /** The edit page offers it, behind the shared confirmation. */
    public function test_the_edit_page_offers_delete_behind_a_confirmation(): void
    {
        $resource = $this->resource(['name' => 'Old chair']);

        $this->actingAs($this->owner)
            ->get(route('resources.edit', $resource))
            ->assertOk()
            ->assertSee(__('resources.delete'))
            ->assertSee('data-confirm-title="'.e(__('resources.delete_title')).'"', false)
            ->assertSee(route('resources.destroy', $resource), false);
    }

    public function test_capacity_must_be_at_least_one(): void
    {
        $this->actingAs($this->owner)
            ->post(route('resources.store'), $this->payload(['name' => 'Nothing room', 'capacity' => 0]))
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

    /**
     * A resource carries no timings of its own.
     *
     * Interval, preparation, cleanup and buffer used to be asked here and
     * read nowhere: the lead and trail around a booking come from the service
     * being performed, which is what ResourceAllocator uses. Four questions
     * that changed nothing are four chances to be wrong about the diary.
     */
    public function test_the_form_does_not_ask_about_booking_timings(): void
    {
        $resource = Resource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Chair 1', 'capacity' => 1,
        ]);

        foreach ([route('resources.create'), route('resources.edit', $resource), route('resources.show', $resource)] as $url) {
            $page = $this->actingAs($this->owner)->get($url)->assertOk();

            foreach (['booking_interval_minutes', 'preparation_minutes', 'cleanup_minutes', 'buffer_minutes'] as $field) {
                $page->assertDontSee('"name":"'.$field.'"', false)
                    ->assertDontSee('name="'.$field.'"', false);
            }

            $page->assertDontSee(__('resources.form.booking'));
        }
    }
}
