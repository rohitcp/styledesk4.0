<?php

namespace Tests\Feature;

use App\Jobs\SendTeamInvitationEmail;
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
use Illuminate\Support\Facades\Queue;
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

    /**
     * One row's actions menu, from where the listing grid reads it.
     *
     * Which entries a row offers is a permission question the server answers
     * — so it is asserted against the payload rather than against markup.
     *
     * @return array<int, array<string, mixed>>
     */
    private function menuFor(User $viewer, Staff $member): array
    {
        $rows = $this->actingAs($viewer)
            ->get('http://styledesk.test/settings/staff/data')
            ->assertOk()
            ->json('data');

        $row = collect($rows)->firstWhere('id', $member->id);

        return $row['menu'] ?? [];
    }

    public function test_each_row_offers_the_actions_the_viewer_may_take(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        $menu = $this->menuFor($owner, $amara);
        $urls = array_column($menu, 'url');

        $this->assertContains(route('settings.staff.show', $amara), $urls);
        $this->assertContains(route('settings.staff.edit', $amara), $urls);
        // show and destroy are the same URL with different verbs, so delete
        // is identified by its method rather than by a URL that also matches
        // the profile link.
        $this->assertContains('DELETE', array_column($menu, 'method'));
    }

    /**
     * The Roles & Permissions access matrix marks Admin as full on Staff
     * Members, so they may delete — where the earlier §21 list enumerated
     * their rights without it. The newer matrix is explicit and this is a
     * permissions specification, so it wins; the guards that matter are
     * unchanged, and Admin still cannot delete themselves or the last owner.
     */
    public function test_an_admin_may_delete_staff_but_never_themselves(): void
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

        $admin = $adminUser->fresh();
        $menu = $this->menuFor($admin, $amara);

        $this->assertContains(route('settings.staff.edit', $amara), array_column($menu, 'url'));
        $this->assertContains('DELETE', array_column($menu, 'method'));

        // But never their own row, and never the last owner's.
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $admin->id)->firstOrFail();
        $this->assertFalse($admin->can('delete', $ownStaff));
    }

    public function test_nobody_is_offered_delete_on_their_own_row(): void
    {
        $owner = $this->owner();
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();

        $menu = $this->menuFor($owner, $ownStaff);

        $this->assertNotContains('DELETE', array_column($menu, 'method'));
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

    /**
     * Two rules a few pixels apart read as a doubled divider, not a
     * separator: the last fact drew its own bottom border and the "not set"
     * line added a top one.
     */
    public function test_the_not_set_line_is_separated_by_a_single_rule(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager', ['job_title' => 'Salon Manager']);

        $content = $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$amara->id)
            ->getContent();

        $about = substr($content, strpos($content, '>About<'), 1800);

        $this->assertStringContainsString('Not set:', $about);
        // The summary line carries no border of its own.
        $this->assertStringNotContainsString('border-t border-line', $about);
    }

    /**
     * A field withheld on purpose is not a field somebody forgot.
     *
     * The expiry stops meaning anything once an invitation is accepted, so it
     * is omitted rather than passed as null — which the facts list would
     * report as "Not set: Expires", blaming an administrator for a decision
     * the page made.
     */
    public function test_an_accepted_invitation_does_not_report_a_missing_expiry(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        $invitation = TeamInvitation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $amara->id,
            'email' => 'amara@acme.test',
            'first_name' => 'Amara', 'last_name' => 'Osei',
            'role' => 'manager',
            'token_hash' => str_repeat('b', 64),
            'status' => TeamInvitation::STATUS_ACCEPTED,
            'sent_at' => now()->subDay(),
            'accepted_at' => now()->subHours(3),
            'expires_at' => now()->addDays(6),
        ]);

        $content = $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$amara->id)
            ->getContent();

        $card = substr($content, strpos($content, '>Invitation<'), 1400);

        $this->assertStringContainsString('Accepted 3 hours ago', $card);
        $this->assertStringNotContainsString('Not set', $card);
        $this->assertStringNotContainsString('Expires', $card);

        // A pending one still shows when it runs out.
        $invitation->forceFill(['status' => TeamInvitation::STATUS_PENDING, 'accepted_at' => null])->save();

        $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$amara->id)
            ->assertSee('Expires');
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
                'location_id' => $this->location->id, 'account_status' => 'active',
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
            'role_id' => $this->roleId('front-desk'),
                'location_id' => $this->location->id, 'account_status' => 'active',
        ]);

        $entry = AuditLog::withoutGlobalScopes()->where('action', 'staff.role_changed')->firstOrFail();

        $this->assertSame('manager', $entry->old_values['role']);
        $this->assertSame('front-desk', $entry->new_values['role']);
    }

    /**
     * §32's narrowest escalation path: open your own record, pick a bigger
     * role. Refused whatever else the person is allowed to do.
     *
     * An administrator, not the owner: an owner's role is locked outright —
     * the field is not on the screen and a posted one is ignored rather than
     * refused, which the owner tests above cover. An administrator may hand
     * out their own role, so for them the field is genuinely editable and
     * this rule is the only thing standing in the way.
     */
    public function test_nobody_changes_their_own_role(): void
    {
        $this->owner();

        $adminUser = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $adminUser->markEmailAsVerified();
        $adminUser->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $ownStaff = $this->member('administrator', [
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@acme.test', 'user_id' => $adminUser->id,
        ]);

        $this->actingAs($adminUser->fresh())
            ->patchJson('http://styledesk.test/settings/staff/'.$ownStaff->id, [
                'first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@acme.test',
                'role_id' => $this->roleId('front-desk'),
                'location_id' => $this->location->id, 'account_status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role_id');

        $this->assertSame('administrator', $ownStaff->fresh()->role);
    }

    // --------------------------------------------------- an owner's role

    /**
     * An owner's role reads as "Owner", not as its id.
     *
     * The list offers only roles the reader may hand out and Owner is never
     * one of them, so the control's value was not among its options and fell
     * back to the raw code — "16" where the role name belonged.
     */
    public function test_an_owners_role_is_shown_by_name_not_by_id(): void
    {
        $owner = $this->owner();
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();

        $page = $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$ownStaff->id.'/edit')
            ->assertOk();

        $page->assertSee('Owner');
        $page->assertDontSee('>'.$ownStaff->role_id.'<', false);
    }

    /**
     * And it is read-only: no control, nothing posted.
     */
    public function test_an_owners_role_is_read_only(): void
    {
        $owner = $this->owner();
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->get('http://styledesk.test/settings/staff/'.$ownStaff->id.'/edit')
            ->assertOk()
            ->assertDontSee('name="role_id"', false);
    }

    /**
     * An owner's record can be saved at all.
     *
     * It could not: the form posted the owner's own role id into a field the
     * server then refused as unassignable, so every edit to an owner's
     * profile — a phone number, a job title — failed on a role nobody was
     * changing.
     */
    public function test_an_owners_profile_can_be_saved(): void
    {
        $owner = $this->owner();
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->patch('http://styledesk.test/settings/staff/'.$ownStaff->id, [
                'first_name' => 'Nadia', 'last_name' => 'Khan',
                'email' => 'owner@styledesk.test',
                'job_title' => 'Founder',
                'location_id' => $this->location->id, 'account_status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $ownStaff->refresh();

        $this->assertSame('Founder', $ownStaff->job_title);
        $this->assertSame('owner', $ownStaff->role);
    }

    /**
     * A posted role_id is ignored on a locked record, not obeyed.
     *
     * The field is not on the screen, so anything arriving under its name was
     * put there by hand.
     */
    public function test_a_posted_role_is_ignored_on_an_owners_record(): void
    {
        $owner = $this->owner();
        $ownStaff = Staff::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->patch('http://styledesk.test/settings/staff/'.$ownStaff->id, [
                'first_name' => 'Nadia', 'last_name' => 'Khan',
                'email' => 'owner@styledesk.test',
                'role_id' => $this->roleId('front-desk'),
                'location_id' => $this->location->id, 'account_status' => 'active',
            ])
            ->assertSessionHasNoErrors();

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
                'role_id' => $this->roleId('manager'),
                'location_id' => $this->location->id, 'account_status' => 'active',
            ])
            ->assertRedirect(route('settings.staff.show', $amara));
    }

    /**
     * Sending an invitation is an act, not a detail of the record.
     *
     * On the edit screen the checkbox did nothing — update() never reads
     * send_invitation — which is worse than absent: a control that looks like
     * it will do something and does not.
     */
    public function test_the_edit_form_does_not_offer_to_send_an_invitation(): void
    {
        $owner = $this->owner();
        $amara = $this->member('manager');

        $edit = $this->actingAs($owner)->get('http://styledesk.test/settings/staff/'.$amara->id.'/edit');

        $edit->assertOk()
            ->assertDontSee('Send the invitation now')
            ->assertDontSee('name="send_invitation"', false)
            ->assertDontSee('name="invitation_message"', false)
            // Allow staff login stays: it is a real field on the record.
            ->assertSee('Allow staff login');

        // And adding someone still offers it.
        $this->actingAs($owner)->get('http://styledesk.test/settings/staff/create')
            ->assertOk()
            ->assertSee('Send the invitation now');
    }

    public function test_editing_never_sends_an_invitation(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $amara = $this->member('manager');

        // Even posted directly, the field is not part of an update.
        $this->actingAs($owner)->patch('http://styledesk.test/settings/staff/'.$amara->id, [
            'first_name' => 'Amara', 'last_name' => 'Osei', 'email' => 'amara@acme.test',
            'role_id' => $this->roleId('manager'),
                'location_id' => $this->location->id, 'account_status' => 'active',
            'login_enabled' => '1', 'send_invitation' => '1',
        ])->assertRedirect(route('settings.staff.show', $amara));

        Queue::assertNotPushed(SendTeamInvitationEmail::class);
        $this->assertSame('not-sent', $amara->fresh()->invite_status);
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
