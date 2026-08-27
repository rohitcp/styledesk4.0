<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Role;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Row actions, the staff profile (§14) and editing.
 */
class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
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

    private function owner(): User
    {
        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => $user->email, 'role' => 'owner',
        ]);

        return $user->fresh();
    }

    private function member(string $role, array $attributes = []): Staff
    {
        return Staff::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Amara', 'last_name' => 'Osei',
            'email' => 'amara@acme.test',
            'role' => $role,
            'location_id' => $this->location->id,
        ], $attributes));
    }

    private function roleId(string $key): int
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)->value('id');
    }

    // ------------------------------------------------------- row actions

    public function test_each_row_offers_the_actions_the_viewer_may_take(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        $content = $this->actingAs($owner)->get('http://styledesk.test/settings/staff')->getContent();

        $this->assertStringContainsString(route('settings.staff.show', $amara), $content);
        $this->assertStringContainsString(route('settings.staff.edit', $amara), $content);
        // show and destroy are the same URL with different verbs, so the
        // delete action is identified by its own marker rather than by a
        // route() string that also matches the profile link.
        $this->assertStringContainsString('data-name="'.$amara->displayName().'"', $content);
    }

    /**
     * §21 gives Admin view/create/edit/deactivate and no delete, so the menu
     * shows what they may do rather than a button that will be refused.
     */
    public function test_an_admin_sees_view_and_edit_but_not_delete(): void
    {
        $this->owner();

        $adminUser = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $adminUser->markEmailAsVerified();
        $adminUser->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->member('administrator', ['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@acme.test', 'user_id' => $adminUser->id]);

        $amara = $this->member('manager', ['email' => 'amara2@acme.test']);

        $content = $this->actingAs($adminUser->fresh())->get('http://styledesk.test/settings/staff')->getContent();

        $this->assertStringContainsString(route('settings.staff.edit', $amara), $content);

        // Not 'data-delete-staff': that literal also appears in the page's own
        // querySelectorAll call, so it is present whether or not any row
        // renders the button. The per-row data-name is the real marker.
        $this->assertStringNotContainsString('data-name="'.$amara->displayName().'"', $content);
    }

    public function test_nobody_is_offered_delete_on_their_own_row(): void
    {
        $owner = $this->owner();
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();

        $content = $this->actingAs($owner)->get('http://styledesk.test/settings/staff')->getContent();

        $this->assertStringNotContainsString('data-name="'.$ownStaff->displayName().'"', $content);
    }

    // ---------------------------------------------------------- profile

    public function test_the_profile_shows_the_person_s_information(): void
    {
        $owner = $this->owner();

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Balayage', 'duration_minutes' => 150,
        ]);

        $amara = $this->member('manager', [
            'preferred_name' => 'Ami',
            'job_title' => 'Salon Manager',
            'phone' => '+15125550001',
            'phone_type' => 'mobile',
            'employment_type' => 'full-time',
            'provider_type' => 'manager-provider',
            'specialities' => ['colourist'],
            'emergency_contact_name' => 'Jo Osei',
            'emergency_contact_phone' => '+15125550002',
        ]);
        $amara->services()->sync([$service->id]);

        $response = $this->actingAs($owner)->get('http://styledesk.test/settings/staff/'.$amara->id);

        $response->assertOk()
            // Preferred name is what a human reads.
            ->assertSee('Ami Osei')
            ->assertSee('Salon Manager')
            ->assertSee('Manager')
            ->assertSee('amara@acme.test')
            ->assertSee('+15125550001')
            ->assertSee('Riverside')
            ->assertSee('Full-time employee')
            ->assertSee('Manager + service provider')
            ->assertSee('Colourist')
            ->assertSee('Balayage')
            ->assertSee('Jo Osei');
    }

    public function test_the_profile_lists_the_record_s_history(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        AuditLog::record('staff.created', $owner, $amara, [], ['role' => 'manager'], $amara->displayName());

        $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$amara->id)
            ->assertOk()
            ->assertSee('created')
            ->assertSee('Nadia Khan');
    }

    /**
     * Optional fields that are unset are summarised, not given a row each.
     *
     * The first version drew an em-dash per empty field, so a record with six
     * blanks spent six rows saying nothing while looking exactly as important
     * as the bio beside it.
     */
    public function test_unset_fields_are_summarised_rather_than_listed(): void
    {
        $owner = $this->owner();

        $amara = $this->member('manager', ['job_title' => 'Salon Manager']);

        $response = $this->actingAs($owner)->get('http://styledesk.test/settings/staff/'.$amara->id);

        $response->assertOk()
            ->assertSee('Not set:')
            ->assertSee('Pronouns')
            ->assertSee('Staff ID');

        // The label is present in the summary; the empty-value dash is not.
        $this->assertStringNotContainsString('—</span>', $response->getContent());
    }

    /**
     * The invitation card describes the invitation, not the person.
     *
     * statusLabel() answers the team list's question and says "Active" for an
     * accepted invitation, which on this card reads as one still open.
     */
    public function test_an_accepted_invitation_reads_as_accepted(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        $invitation = TeamInvitation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $amara->id,
            'email' => 'amara@acme.test',
            'first_name' => 'Amara', 'last_name' => 'Osei',
            'role' => 'manager',
            'token_hash' => str_repeat('a', 64),
            'status' => TeamInvitation::STATUS_ACCEPTED,
            'sent_at' => now()->subDay(),
            'accepted_at' => now(),
            'expires_at' => now()->addDays(6),
        ]);

        $this->assertSame('Accepted', $invitation->outcomeLabel());

        $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$amara->id)
            ->assertOk()
            ->assertSee('Accepted');
    }

    public function test_the_header_carries_the_contact_details(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager', ['phone' => '+15125550001']);

        $content = $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$amara->id)
            ->getContent();

        // Scoped to the header's own strip rather than a fixed slice of
        // characters, which moves whenever the markup above it changes.
        $start = strpos($content, 'styledesk_profile__facts');
        $header = substr($content, $start, strpos($content, '</dl>', $start) - $start);

        $this->assertStringContainsString('mailto:amara@acme.test', $header);
        $this->assertStringContainsString('tel:+15125550001', $header);
        $this->assertStringContainsString('Riverside', $header);
    }

    public function test_another_business_s_staff_profile_is_not_reachable(): void
    {
        $owner = $this->owner();

        $rival = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);
        $theirs = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $rival->getTenantKey(),
            'first_name' => 'Rival', 'last_name' => 'Person',
            'email' => 'rival@rival.test', 'role' => 'manager',
        ]);

        // The tenant scope means the id does not resolve to a row at all.
        $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$theirs->id)
            ->assertNotFound();
    }

    // ------------------------------------------------------------- edit

    public function test_editing_updates_the_record(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        $this->actingAs($owner)
            ->patch('http://styledesk.test/settings/staff/'.$amara->id, [
                'first_name' => 'amara',
                'last_name' => 'osei',
                'email' => 'amara@acme.test',
                'job_title' => 'head of colour',
                'role_id' => $this->roleId('service-provider'),
                'account_status' => 'active',
            ])
            ->assertRedirect(route('settings.staff.show', $amara));

        $amara->refresh();

        $this->assertSame('Head of colour', $amara->job_title);
        $this->assertSame('service-provider', $amara->roleRecord->key);
        // The string column follows the id, or permissions and the directory
        // would disagree about what this person is.
        $this->assertSame('service-provider', $amara->role);
    }

    public function test_a_role_change_is_recorded_separately(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        $this->actingAs($owner)->patch('http://styledesk.test/settings/staff/'.$amara->id, [
            'first_name' => 'Amara', 'last_name' => 'Osei', 'email' => 'amara@acme.test',
            'role_id' => $this->roleId('front-desk'), 'account_status' => 'active',
        ]);

        $entry = AuditLog::withoutGlobalScopes()->where('action', 'staff.role_changed')->firstOrFail();

        $this->assertSame('manager', $entry->old_values['role']);
        $this->assertSame('front-desk', $entry->new_values['role']);
    }

    /**
     * §32's narrowest escalation path: open your own record, pick a bigger
     * role. Refused whatever else the person is allowed to do.
     */
    public function test_nobody_changes_their_own_role(): void
    {
        $owner = $this->owner();
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->patchJson('http://styledesk.test/settings/staff/'.$ownStaff->id, [
                'first_name' => 'Nadia', 'last_name' => 'Khan', 'email' => 'owner@styledesk.test',
                'role_id' => $this->roleId('front-desk'), 'account_status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role_id');

        $this->assertSame('owner', $ownStaff->fresh()->role);
    }

    public function test_editing_keeps_the_person_s_own_email(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        // The unique rule must not treat the record as colliding with itself.
        $this->actingAs($owner)
            ->patch('http://styledesk.test/settings/staff/'.$amara->id, [
                'first_name' => 'Amara', 'last_name' => 'Osei', 'email' => 'amara@acme.test',
                'role_id' => $this->roleId('manager'), 'account_status' => 'active',
            ])
            ->assertRedirect(route('settings.staff.show', $amara));
    }

    // ----------------------------------------------------------- delete

    public function test_deleting_removes_the_record_and_the_person_s_access(): void
    {
        $owner = $this->owner();

        $joiner = User::create([
            'first_name' => 'Amara', 'last_name' => 'Osei',
            'email' => 'amara@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $joiner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $amara = $this->member('manager', ['user_id' => $joiner->id]);

        $this->actingAs($owner)
            ->delete('http://styledesk.test/settings/staff/'.$amara->id)
            ->assertRedirect(route('settings.staff.index'));

        $this->assertNull(Staff::withoutGlobalScopes()->find($amara->id));

        // The membership is gone; the account is not — it may belong to
        // another business, and their login is not this business's to delete.
        $this->assertNotNull(User::find($joiner->id));
        $this->assertNull($joiner->fresh()->tenant_id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.deleted']);
    }

    public function test_the_only_owner_cannot_be_deleted(): void
    {
        $owner = $this->owner();
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->delete('http://styledesk.test/settings/staff/'.$ownStaff->id)
            ->assertForbidden();

        $this->assertNotNull(Staff::withoutGlobalScopes()->find($ownStaff->id));
    }
}
