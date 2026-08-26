<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Permissions;
use App\Support\RoleGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance criteria for the permission foundation (§18-§24, §32-§37).
 */
class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // TenantCreated provisions the roles, so this is the real path.
        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete',
            'completed_at' => now(),
        ]);
    }

    private function roleKeyed(string $key): Role
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)
            ->firstOrFail();
    }

    private function member(string $role, ?string $email = null): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $email ?? $role.'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        if ($role === 'owner') {
            $this->tenant->forceFill(['owner_user_id' => $user->id])->save();
        } else {
            Staff::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->getTenantKey(),
                'user_id' => $user->id,
                'first_name' => 'Sam', 'last_name' => 'Person',
                'email' => $user->email, 'role' => $role,
            ]);
        }

        return $user->fresh();
    }

    // ------------------------------------------------------- provisioning

    public function test_a_new_business_is_given_its_own_copy_of_the_system_roles(): void
    {
        $roles = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())->get();

        $this->assertSame(
            ['owner', 'administrator', 'manager', 'front-desk', 'service-provider'],
            $roles->sortBy('display_order')->pluck('key')->all()
        );

        $this->assertTrue($roles->every->is_system);
    }

    /**
     * Roles are per tenant, not global: one business editing Manager must not
     * change what Manager means for every other business.
     */
    public function test_editing_one_business_s_role_does_not_touch_another_s(): void
    {
        $other = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);

        $mine = $this->roleKeyed('manager');
        $theirs = Role::withoutGlobalScopes()
            ->where('tenant_id', $other->getTenantKey())->where('key', 'manager')->firstOrFail();

        $this->assertNotSame($mine->id, $theirs->id);

        app(ProvisionSystemRoles::class)->syncPermissions($mine, ['bookings.view' => 'own']);

        $this->assertSame(['bookings.view' => 'own'], $mine->fresh()->permissionMap());
        $this->assertTrue($theirs->fresh()->grants('bookings.view', 'location'));
    }

    public function test_every_default_matrix_only_names_permissions_that_exist(): void
    {
        // A default naming a permission the catalogue does not define would be
        // a grant nothing ever checks — invisible until someone wondered why a
        // role could not do the thing its matrix says it can.
        foreach (config('role_defaults') as $key => $definition) {
            if ($definition['permissions'] === '*') {
                continue;
            }

            foreach ($definition['permissions'] as $permission => $scope) {
                $this->assertTrue(
                    Permissions::exists($permission),
                    "[{$key}] grants unknown permission [{$permission}]."
                );

                $this->assertContains(
                    $scope,
                    Permissions::scopesFor($permission),
                    "[{$key}] grants [{$permission}] at a scope it does not accept."
                );
            }
        }
    }

    public function test_an_unknown_permission_is_dropped_rather_than_stored(): void
    {
        $role = $this->roleKeyed('manager');

        app(ProvisionSystemRoles::class)->syncPermissions($role, [
            'bookings.view' => 'all',
            'bookings.teleport' => 'all',
        ]);

        $this->assertSame(['bookings.view' => 'all'], $role->fresh()->permissionMap());
    }

    // ------------------------------------------------------------- scopes

    public function test_a_wider_scope_satisfies_a_narrower_requirement(): void
    {
        $manager = $this->roleKeyed('manager');

        // Manager holds bookings.view at location.
        $this->assertTrue($manager->grants('bookings.view', 'own'));
        $this->assertTrue($manager->grants('bookings.view', 'location'));
        $this->assertFalse($manager->grants('bookings.view', 'all'));
    }

    public function test_a_service_provider_sees_only_their_own_work(): void
    {
        $provider = $this->roleKeyed('service-provider');

        $this->assertTrue($provider->grants('calendar.view', 'own'));
        $this->assertFalse($provider->grants('calendar.view', 'location'));
        $this->assertFalse($provider->grants('staff.create'));
        $this->assertFalse($provider->grants('settings.view'));
        $this->assertFalse($provider->grants('roles.view'));
    }

    public function test_sensitive_client_notes_are_withheld_from_front_desk_by_default(): void
    {
        // §23 names this one explicitly.
        $this->assertTrue($this->roleKeyed('front-desk')->grants('clients.view_notes'));
        $this->assertFalse($this->roleKeyed('front-desk')->grants('clients.view_sensitive_notes'));
    }

    public function test_the_owner_grants_everything_including_permissions_added_later(): void
    {
        $owner = $this->roleKeyed('owner');

        foreach (array_keys(Permissions::all()) as $permission) {
            $this->assertTrue($owner->grants($permission, 'all'), "Owner cannot [{$permission}].");
        }

        // Even one the catalogue has never heard of: the role that has to be
        // able to fix everything must not be short a grant because a deploy
        // added a permission after the tenant was seeded.
        $this->assertTrue($owner->grants('something.invented.tomorrow', 'all'));
    }

    public function test_admin_cannot_edit_the_permission_matrix_by_default(): void
    {
        // §21 makes role administration Owner-controlled. A default that can
        // edit the matrix is a default that can grant itself everything else.
        $this->assertFalse($this->roleKeyed('administrator')->grants('roles.manage_matrix'));
        $this->assertFalse($this->roleKeyed('administrator')->grants('business.transfer_ownership'));
        $this->assertFalse($this->roleKeyed('administrator')->grants('business.delete'));
    }

    // -------------------------------------------------- privilege escalation

    public function test_a_manager_cannot_promote_anyone_to_admin(): void
    {
        $manager = $this->member('manager');

        $this->assertFalse(RoleGuard::canAssignRole($manager, $this->roleKeyed('administrator')));
    }

    public function test_nobody_can_assign_the_owner_role(): void
    {
        // Ownership moves through its own workflow, never by picking it from
        // a role list.
        $owner = $this->member('owner');

        $this->assertFalse(RoleGuard::canAssignRole($owner, $this->roleKeyed('owner')));
    }

    public function test_an_admin_granted_matrix_rights_still_cannot_hand_out_what_they_lack(): void
    {
        $admin = $this->member('administrator');

        app(ProvisionSystemRoles::class)->syncPermissions(
            $this->roleKeyed('administrator'),
            array_merge(
                $this->roleKeyed('administrator')->permissionMap(),
                ['roles.manage_matrix' => 'all', 'staff.assign_role' => 'all'],
            )
        );

        $admin = $admin->fresh();

        // They may edit a role that stays within their own authority...
        $this->assertTrue(RoleGuard::canEditRole($admin, $this->roleKeyed('front-desk')));

        // ...but never the Owner role, whose authority exceeds theirs.
        $this->assertFalse(RoleGuard::canEditRole($admin, $this->roleKeyed('owner')));

        // And the permissions they may grant exclude the owner-only ones.
        $grantable = RoleGuard::grantablePermissions($admin);
        $this->assertNotContains('business.transfer_ownership', $grantable);
        $this->assertNotContains('billing.cancel_subscription', $grantable);
    }

    public function test_even_an_owner_cannot_grant_owner_only_permissions_to_a_role(): void
    {
        $owner = $this->member('owner');

        $grantable = RoleGuard::grantablePermissions($owner);

        $this->assertNotContains('business.delete', $grantable);
        $this->assertNotContains('business.transfer_ownership', $grantable);
    }

    public function test_nobody_changes_their_own_role(): void
    {
        $this->assertFalse(RoleGuard::canChangeOwnRole());
    }

    // ---------------------------------------------------- owner protection

    public function test_the_only_owner_cannot_be_left_without_a_business(): void
    {
        $owner = $this->member('owner');

        $this->assertTrue(RoleGuard::wouldLeaveTenantWithoutOwner($owner));

        $manager = $this->member('manager');
        $this->assertFalse(RoleGuard::wouldLeaveTenantWithoutOwner($manager));
    }

    public function test_a_staff_member_cannot_deactivate_themselves(): void
    {
        $admin = $this->member('administrator');
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $admin->id)->firstOrFail();

        $this->assertFalse(RoleGuard::canDeactivate($admin, $ownStaff));
    }

    // ------------------------------------------------------- staff linkage

    public function test_writing_a_role_name_resolves_the_role_record(): void
    {
        // Every existing caller writes the string. If that stopped resolving,
        // a staff row would carry a role name and no permissions at all.
        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Amara', 'last_name' => 'Hart',
            'email' => 'amara@styledesk.test', 'role' => 'front-desk',
        ]);

        $this->assertNotNull($staff->role_id);
        $this->assertSame('front-desk', $staff->roleRecord->key);
    }

    public function test_permissions_never_cross_a_tenant_boundary(): void
    {
        $otherTenant = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);

        $intruder = User::create([
            'first_name' => 'Rival', 'last_name' => 'Owner',
            'email' => 'rival@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $intruder->forceFill(['tenant_id' => $otherTenant->getTenantKey()])->save();
        $otherTenant->forceFill(['owner_user_id' => $intruder->id])->save();

        // Owner of one business, and nothing at all in the other.
        $this->assertTrue($intruder->fresh()->hasPermission('settings.view', 'all'));
        $this->assertSame($otherTenant->getTenantKey(), $intruder->fresh()->role()->tenant_id);
        $this->assertNotSame($this->tenant->getTenantKey(), $intruder->fresh()->role()->tenant_id);
    }

    // ------------------------------------------------------------- audit

    public function test_a_change_is_recorded_with_both_halves(): void
    {
        $owner = $this->member('owner');
        $role = $this->roleKeyed('manager');

        AuditLog::record('roles.permission_removed', $owner, $role,
            ['bookings.cancel' => 'location'], [], 'Manager');

        $entry = AuditLog::withoutGlobalScopes()->latest('id')->first();

        $this->assertSame('roles.permission_removed', $entry->action);
        $this->assertSame($owner->id, $entry->actor_id);
        // The actor's name is stored, not looked up, so history survives the
        // deletion of the account it names.
        $this->assertSame('Sam Person', $entry->actor_name);
        $this->assertSame(['bookings.cancel' => 'location'], $entry->old_values);
        $this->assertSame($this->tenant->getTenantKey(), $entry->tenant_id);
    }

    // -------------------------------------------------------------- cards

    public function test_the_settings_directory_shows_staff_and_roles_as_separate_cards(): void
    {
        $owner = $this->member('owner');
        $this->member('manager');

        $response = $this->actingAs($owner)->get('http://styledesk.test/settings');

        // §38 is explicit that these must not be combined.
        $response->assertOk()
            ->assertSee('Staff Members')
            ->assertSee('Roles &amp; Permissions', false)
            // The number and its label are separate elements, so assert on
            // the label rather than on a string that only reads contiguously.
            ->assertSee('active member', false)
            ->assertSee('roles', false);

        // §2 makes the invitations count optional, so a business with none
        // shows nothing rather than a zero — a "0 pending invites" line is
        // noise on every card that has never sent one.
        $this->assertStringNotContainsString('pending invite', $response->getContent());
    }
}
