<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance criteria from the Service Category specification.
 */
class ServiceCategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function business(string $slug, string $email, string $role = 'owner'): array
    {
        $user = User::create([
            'first_name' => 'Owner',
            'last_name' => ucfirst($slug),
            'email' => $email,
            'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();

        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug]);

        if ($role === 'owner') {
            $tenant->update(['owner_user_id' => $user->id]);
        }

        $user->tenant_id = $tenant->getTenantKey();
        $user->save();

        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'complete',
            'completed_at' => now(),
        ]);

        if ($role !== 'owner') {
            Staff::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'user_id' => $user->id,
                'first_name' => 'Staff',
                'last_name' => 'Member',
                'role' => $role,
            ]);
        }

        return [$tenant, $user->fresh()];
    }

    public function test_a_new_tenant_receives_the_default_categories(): void
    {
        [$tenant] = $this->business('acme', 'acme@styledesk.test');

        $this->assertSame(
            count(config('service_categories')),
            ServiceCategory::withoutGlobalScopes()->where('tenant_id', $tenant->getTenantKey())->count()
        );
    }

    public function test_an_owner_can_create_a_custom_category(): void
    {
        [$tenant, $user] = $this->business('acme', 'acme@styledesk.test');

        $this->actingAs($user)
            ->postJson('http://styledesk.test/service-categories', ['name' => 'ayurvedic therapy'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ayurvedic therapy');

        $this->assertDatabaseHas('service_categories', [
            'tenant_id' => $tenant->getTenantKey(),
            'name' => 'Ayurvedic therapy',
            'created_by' => $user->id,
        ]);
    }

    public function test_one_tenants_custom_category_is_invisible_to_another(): void
    {
        [, $acmeUser] = $this->business('acme', 'acme@styledesk.test');
        [, $betaUser] = $this->business('beta', 'beta@styledesk.test');

        $this->actingAs($acmeUser)
            ->postJson('http://styledesk.test/service-categories', ['name' => 'Ayurvedic therapy'])
            ->assertCreated();

        $names = $this->actingAs($betaUser)
            ->getJson('http://styledesk.test/service-categories')
            ->assertOk()
            ->json('data.*.name');

        $this->assertNotContains('Ayurvedic therapy', $names);
    }

    public function test_the_same_name_is_allowed_across_tenants(): void
    {
        [, $acmeUser] = $this->business('acme', 'acme@styledesk.test');
        [, $betaUser] = $this->business('beta', 'beta@styledesk.test');

        $this->actingAs($acmeUser)
            ->postJson('http://styledesk.test/service-categories', ['name' => 'Medical spa'])
            ->assertCreated();

        $this->actingAs($betaUser)
            ->postJson('http://styledesk.test/service-categories', ['name' => 'Medical spa'])
            ->assertCreated();

        $this->assertSame(2, ServiceCategory::withoutGlobalScopes()->where('name', 'Medical spa')->count());
    }

    public function test_duplicate_names_within_a_tenant_are_refused_case_insensitively(): void
    {
        [, $user] = $this->business('acme', 'acme@styledesk.test');

        // "Massage" is already there as a seeded default.
        foreach (['Massage', 'massage', 'MASSAGE'] as $attempt) {
            $this->actingAs($user)
                ->postJson('http://styledesk.test/service-categories', ['name' => $attempt])
                ->assertStatus(422)
                ->assertJsonValidationErrors('name');
        }
    }

    public function test_another_tenants_category_cannot_be_edited_or_deleted(): void
    {
        [, $acmeUser] = $this->business('acme', 'acme@styledesk.test');
        [$beta] = $this->business('beta', 'beta@styledesk.test');

        $foreign = ServiceCategory::withoutGlobalScopes()
            ->where('tenant_id', $beta->getTenantKey())->first();

        // Hand-crafting the id must not reach across the boundary. Scoped
        // route binding never resolves it, so the request 404s rather than
        // leaking whether the row exists.
        $this->actingAs($acmeUser)
            ->patchJson('http://styledesk.test/service-categories/'.$foreign->id, ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($acmeUser)
            ->deleteJson('http://styledesk.test/service-categories/'.$foreign->id)
            ->assertNotFound();

        $this->assertSame($foreign->name, $foreign->fresh()->name);
    }

    public function test_a_category_in_use_is_not_deleted_by_accident(): void
    {
        [$tenant, $user] = $this->business('acme', 'acme@styledesk.test');

        tenancy()->initialize($tenant);
        $category = ServiceCategory::first();
        Service::create(['name' => 'Cut', 'duration_minutes' => 30, 'service_category_id' => $category->id]);
        tenancy()->end();

        $this->actingAs($user)
            ->deleteJson('http://styledesk.test/service-categories/'.$category->id)
            ->assertStatus(409)
            ->assertJsonPath('can_archive', true);

        $this->assertNotSoftDeleted('service_categories', ['id' => $category->id]);

        // Archiving is the offered way out, and keeps the label on history.
        $this->actingAs($user)
            ->deleteJson('http://styledesk.test/service-categories/'.$category->id.'?archive=1')
            ->assertOk();

        $this->assertSoftDeleted('service_categories', ['id' => $category->id]);
    }

    public function test_a_service_provider_may_view_but_not_manage(): void
    {
        [, $user] = $this->business('acme', 'acme@styledesk.test', 'service-provider');

        $this->actingAs($user)
            ->getJson('http://styledesk.test/service-categories')
            ->assertOk();

        $this->actingAs($user)
            ->postJson('http://styledesk.test/service-categories', ['name' => 'Sneaky'])
            ->assertForbidden();
    }

    public function test_a_manager_may_add_but_not_delete(): void
    {
        [$tenant, $user] = $this->business('acme', 'acme@styledesk.test', 'manager');

        $this->actingAs($user)
            ->postJson('http://styledesk.test/service-categories', ['name' => 'Couples massage'])
            ->assertCreated();

        $category = ServiceCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())->where('name', 'Couples massage')->first();

        $this->actingAs($user)
            ->deleteJson('http://styledesk.test/service-categories/'.$category->id)
            ->assertForbidden();
    }

    public function test_inactive_categories_are_not_offered_for_assignment(): void
    {
        [$tenant, $user] = $this->business('acme', 'acme@styledesk.test');

        tenancy()->initialize($tenant);
        $category = ServiceCategory::first();
        tenancy()->end();

        $this->actingAs($user)
            ->patchJson('http://styledesk.test/service-categories/'.$category->id, [
                'name' => $category->name,
                'status' => 'inactive',
            ])->assertOk();

        $names = $this->actingAs($user)
            ->getJson('http://styledesk.test/service-categories')
            ->json('data.*.name');

        $this->assertNotContains($category->name, $names);
    }
}
