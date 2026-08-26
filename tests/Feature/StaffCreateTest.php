<?php

namespace Tests\Feature;

use App\Actions\Team\AcceptTeamInvitation;
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
use App\Support\RoleGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Acceptance criteria for Add Staff Member (§4) and its invitation (§15).
 */
class StaffCreateTest extends TestCase
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

        return $user->fresh();
    }

    private function member(string $role): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $role.'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => $role,
        ]);

        return $user->fresh();
    }

    private function roleId(string $key): int
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', $key)->value('id');
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'katherine',
            'last_name' => 'wu',
            'email' => 'kit@acme.test',
            'role_id' => $this->roleId('service-provider'),
            'account_status' => 'active',
        ], $overrides);
    }

    // ------------------------------------------------------------- create

    public function test_a_staff_member_is_created_with_their_details(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'preferred_name' => 'kit',
                'job_title' => 'senior stylist',
                'phone' => '+15125559999',
                'phone_type' => 'mobile',
                'location_id' => $this->location->id,
                'employment_type' => 'commission',
                'provider_type' => 'service-provider',
                'specialities' => ['colourist'],
                'login_enabled' => '0',
            ]))
            ->assertRedirect(route('settings.staff.index'));

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        // The project capitalisation rule: first character only.
        $this->assertSame('Katherine', $staff->first_name);
        $this->assertSame('Kit', $staff->preferred_name);
        $this->assertSame('Senior stylist', $staff->job_title);
        // Preferred name is what a human reads.
        $this->assertSame('Kit Wu', $staff->displayName());
        $this->assertSame('commission', $staff->employment_type);
        $this->assertSame(['colourist'], $staff->specialities);
        $this->assertSame('service-provider', $staff->roleRecord->key);
    }

    public function test_creating_a_member_records_it_in_the_audit_history(): void
    {
        Queue::fake();

        $owner = $this->owner();

        $this->actingAs($owner)->post('http://styledesk.test/settings/staff', $this->payload(['login_enabled' => '0']));

        $entry = AuditLog::withoutGlobalScopes()->where('action', 'staff.created')->firstOrFail();

        $this->assertSame($owner->id, $entry->actor_id);
        $this->assertSame('Katherine Wu', $entry->subject_label);
        $this->assertSame('service-provider', $entry->new_values['role']);
    }

    public function test_assigned_services_make_the_member_bookable(): void
    {
        Queue::fake();

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Balayage', 'duration_minutes' => 150,
        ]);

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'service_ids' => [$service->id],
                'provider_type' => 'service-provider',
                'login_enabled' => '0',
            ]));

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $this->assertSame([$service->id], $staff->services()->pluck('services.id')->all());
        $this->assertTrue($staff->provides_services);
    }

    // -------------------------------------------------------- validation

    public function test_required_fields_say_what_to_do(): void
    {
        $this->actingAs($this->owner())
            ->postJson('http://styledesk.test/settings/staff', ['account_status' => 'active'])
            ->assertStatus(422)
            ->assertJsonPath('errors.first_name.0', 'First name is required.')
            ->assertJsonPath('errors.last_name.0', 'Last name is required.')
            ->assertJsonPath('errors.email.0', 'Primary email is required.')
            ->assertJsonPath('errors.role_id.0', 'Choose a role for this person.');
    }

    public function test_an_email_already_on_the_team_is_refused(): void
    {
        Queue::fake();
        $this->actingAs($this->owner());

        $this->post('http://styledesk.test/settings/staff', $this->payload(['login_enabled' => '0']));

        $this->postJson('http://styledesk.test/settings/staff', $this->payload(['login_enabled' => '0']))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Someone on your team already uses that email address.');

        $this->assertSame(1, Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->count());
    }

    /**
     * The same person can work for two salons on the platform, so the
     * uniqueness is per business rather than global.
     */
    public function test_the_same_address_may_be_staff_at_another_business(): void
    {
        Queue::fake();

        $rival = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);
        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $rival->getTenantKey(),
            'first_name' => 'Kit', 'last_name' => 'Wu',
            'email' => 'kit@acme.test', 'role' => 'service-provider',
        ]);

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload(['login_enabled' => '0']))
            ->assertRedirect(route('settings.staff.index'));

        $this->assertSame(2, Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->count());
    }

    public function test_pronouns_come_from_the_offered_list(): void
    {
        Queue::fake();

        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'pronouns' => 'they/them',
                'login_enabled' => '0',
            ]));

        $this->assertSame('they/them', Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->value('pronouns'));

        // Free text would record the same person as "she/her", "She/Her" and
        // "shehers" across three screens.
        $this->actingAs($owner)
            ->postJson('http://styledesk.test/settings/staff', $this->payload([
                'email' => 'other@acme.test',
                'pronouns' => 'whatever i typed',
                'login_enabled' => '0',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('pronouns');
    }

    // ------------------------------------------------- invitation delivery

    /**
     * An invitation is not "sent" until something has actually sent it.
     *
     * Marking it at queue time was a small lie with a real cost: with no
     * worker running, the directory reported an invitation as sent that was
     * still sitting in the jobs table.
     */
    public function test_a_queued_invitation_is_not_reported_as_sent(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'login_enabled' => '1',
                'send_invitation' => '1',
            ]));

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $this->assertSame('pending', $staff->invite_status);
        $this->assertSame('invite-queued', $staff->status());
        $this->assertSame('Invite queued', $staff->statusLabel());
    }

    public function test_delivering_the_invitation_marks_it_sent(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'login_enabled' => '1',
                'send_invitation' => '1',
            ]));

        $invitation = TeamInvitation::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $token = $invitation->regenerateToken();
        $invitation->save();

        Mail::fake();
        (new SendTeamInvitationEmail($invitation->fresh(), $token))->handle();

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $this->assertSame('sent', $staff->invite_status);
        $this->assertSame('pending-invite', $staff->status());
    }

    public function test_a_failed_delivery_is_visible_on_the_record(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'login_enabled' => '1',
                'send_invitation' => '1',
            ]));

        $invitation = TeamInvitation::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        (new SendTeamInvitationEmail($invitation, 'irrelevant'))->failed(new \RuntimeException('SMTP down'));

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        // Visible on the screen rather than only in the logs, so nobody has to
        // report never receiving it before anyone notices.
        $this->assertSame('invite-failed', $staff->status());
        $this->assertSame('Invite failed', $staff->statusLabel());
    }

    // ---------------------------------------------------- profile image

    public function test_a_profile_image_uploads_on_its_own_and_returns_a_path(): void
    {
        Storage::fake('brand');

        $response = $this->actingAs($this->owner())
            ->postJson('http://styledesk.test/settings/staff/avatar', [
                'image' => UploadedFile::fake()->image('kit.png', 400, 400),
            ]);

        $response->assertOk()->assertJsonStructure(['path', 'url']);

        Storage::disk('brand')->assertExists($response->json('path'));
    }

    public function test_an_oversized_image_is_refused_with_a_plain_message(): void
    {
        Storage::fake('brand');

        $this->actingAs($this->owner())
            ->postJson('http://styledesk.test/settings/staff/avatar', [
                'image' => UploadedFile::fake()->create('huge.png', 4096, 'image/png'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'The profile image must be 2 MB or smaller.');
    }

    public function test_an_already_uploaded_image_is_used_rather_than_re_stored(): void
    {
        Queue::fake();
        Storage::fake('brand');

        $path = $this->actingAs($this->owner())
            ->postJson('http://styledesk.test/settings/staff/avatar', [
                'image' => UploadedFile::fake()->image('kit.png'),
            ])->json('path');

        $this->post('http://styledesk.test/settings/staff', $this->payload([
            'avatar_path' => $path,
            'login_enabled' => '0',
        ]));

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        // The same file, not a second copy of it.
        $this->assertSame($path, $staff->avatar_path);
        $this->assertSame(1, count(Storage::disk('brand')->files('staff')));
    }

    /**
     * The path comes back from the browser, so a crafted value must not be
     * able to point the avatar at any file on the disk.
     */
    public function test_a_forged_image_path_is_refused(): void
    {
        Queue::fake();

        $owner = $this->owner();

        foreach (['../../.env', '/etc/passwd', 'logos/someone-elses.png'] as $forged) {
            $this->actingAs($owner)
                ->postJson('http://styledesk.test/settings/staff', $this->payload([
                    'avatar_path' => $forged,
                    'login_enabled' => '0',
                ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('avatar_path');
        }

        $this->assertSame(0, Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->count());
    }

    public function test_a_service_provider_cannot_upload_an_image(): void
    {
        Storage::fake('brand');

        $provider = $this->member('service-provider');

        $this->actingAs($provider)
            ->post('http://styledesk.test/settings/staff/avatar', [
                'image' => UploadedFile::fake()->image('kit.png'),
            ])
            ->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------- privilege escalation

    public function test_nobody_can_create_someone_as_owner(): void
    {
        $this->actingAs($this->owner())
            ->postJson('http://styledesk.test/settings/staff', $this->payload(['role_id' => $this->roleId('owner')]))
            ->assertStatus(422)
            ->assertJsonPath('errors.role_id.0', 'You cannot assign that role.');

        $this->assertSame(0, Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->count());
    }

    public function test_the_form_only_offers_roles_the_user_may_assign(): void
    {
        $response = $this->actingAs($this->owner())->get('http://styledesk.test/settings/staff/create');

        $response->assertOk()
            ->assertSee('Service Provider')
            ->assertSee('Admin');

        // Owner is never an option: ownership moves by transfer, not by
        // picking it from a list.
        $this->assertStringNotContainsString('>Owner<', $response->getContent());
    }

    /**
     * A Manager posting straight at the endpoint cannot mint an Admin.
     *
     * They are turned away by the App Settings gate before the policy is
     * consulted, which is the stronger guarantee: the request never reaches
     * the code that would decide, so there is nothing there to get wrong.
     */
    public function test_a_role_beyond_the_creator_s_own_authority_is_refused(): void
    {
        $manager = $this->member('manager');

        $this->actingAs($manager)
            ->post('http://styledesk.test/settings/staff', $this->payload(['role_id' => $this->roleId('administrator')]))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->count());

        // And the guard itself refuses the same thing, so a future screen that
        // is not behind the settings gate inherits the rule rather than the
        // accident of where it was mounted.
        $this->assertFalse(
            RoleGuard::canAssignRole($manager, Role::withoutGlobalScopes()->find($this->roleId('administrator')))
        );
    }

    public function test_a_service_provider_cannot_reach_the_form(): void
    {
        $provider = $this->member('service-provider');

        $this->actingAs($provider)->get('http://styledesk.test/settings/staff/create')
            ->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------- invitations

    public function test_an_invitation_is_sent_when_asked_for(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'login_enabled' => '1',
                'send_invitation' => '1',
                'invitation_message' => 'Delighted to have you.',
            ]));

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();
        $invitation = TeamInvitation::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        Queue::assertPushed(SendTeamInvitationEmail::class);

        // The two are bound, which is what stops acceptance creating a second
        // person with the same name.
        $this->assertSame($staff->id, $invitation->staff_id);
        // Queued, not delivered — the job flips this once the provider has
        // accepted it.
        $this->assertSame('pending', $staff->invite_status);
        $this->assertSame('invite-queued', $staff->status());
    }

    public function test_no_invitation_is_sent_without_a_login(): void
    {
        Queue::fake();

        // An invitation without a login is an email inviting someone to an
        // account they cannot have.
        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'login_enabled' => '0',
                'send_invitation' => '1',
            ]));

        Queue::assertNotPushed(SendTeamInvitationEmail::class);

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();
        $this->assertSame('not-sent', $staff->invite_status);
        $this->assertSame('active', $staff->status());
    }

    /**
     * §15: accepting must complete the existing record, never add a second.
     */
    public function test_accepting_completes_the_staff_record_rather_than_duplicating_it(): void
    {
        Queue::fake();

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Balayage', 'duration_minutes' => 150,
        ]);

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'preferred_name' => 'kit',
                'job_title' => 'senior stylist',
                'service_ids' => [$service->id],
                'login_enabled' => '1',
                'send_invitation' => '1',
            ]));

        $before = Staff::withoutGlobalScopes()->count();
        $invitation = TeamInvitation::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $joiner = User::create([
            'first_name' => 'Kit', 'last_name' => 'Wu',
            'email' => 'kit@acme.test', 'password' => 'Str0ng!Pass',
        ]);

        app(AcceptTeamInvitation::class)->accept($invitation, $joiner);

        $this->assertSame($before, Staff::withoutGlobalScopes()->count(), 'Accepting created a second staff row.');

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $this->assertSame($joiner->id, $staff->user_id);
        $this->assertSame('accepted', $staff->invite_status);
        $this->assertSame('active', $staff->status());

        // What the administrator set up survives acceptance.
        $this->assertSame('Kit', $staff->preferred_name);
        $this->assertSame('Senior stylist', $staff->job_title);
        $this->assertSame([$service->id], $staff->services()->pluck('services.id')->all());
    }
}
