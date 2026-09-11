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
            'location_id' => $this->location->id,
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

    // ----------------------------------------------------------- location

    /**
     * A branch is chosen, not left blank.
     *
     * The field offered "All locations" as its empty answer and nothing in
     * the product means it — a person works somewhere. Left blank they also
     * turned up on every location's calendar, per Calendar::staffFor().
     */
    public function test_a_staff_member_must_be_given_a_location(): void
    {
        $this->actingAs($this->owner())
            ->postJson('http://styledesk.test/settings/staff', $this->payload(['location_id' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('location_id');

        $this->assertSame(0, Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->count());
    }

    public function test_the_form_no_longer_offers_all_locations(): void
    {
        $this->actingAs($this->owner())
            ->get('http://styledesk.test/settings/staff/create')
            ->assertOk()
            ->assertDontSee('All locations');
    }

    /**
     * With one branch there is nothing to choose, so it is chosen already.
     *
     * Most businesses have exactly one, and making them open a list of one
     * to pick the only answer is a required field for its own sake.
     */
    public function test_a_single_location_is_selected_by_default(): void
    {
        $html = $this->actingAs($this->owner())
            ->get('http://styledesk.test/settings/staff/create')
            ->assertOk()
            ->getContent();

        /* Read out of the island's own props rather than matched as a string:
           the directive escapes quotes, and asserting against that encoding
           would break on a Blade change that has nothing to do with this. */
        preg_match_all("/data-props='([^']*)'/", $html, $found);

        $location = collect($found[1])
            ->map(fn (string $json) => json_decode(html_entity_decode($json), true))
            ->firstWhere('name', 'location_id');

        $this->assertSame([(string) $this->location->id], $location['modelValue']);
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

    // --------------------------------------------- contact fields and rules

    /**
     * The form checks itself as it is filled in, with the same module and the
     * same messages as the resource and location forms.
     */
    public function test_the_form_carries_the_live_validation_rules(): void
    {
        $page = $this->actingAs($this->owner())
            ->get('http://styledesk.test/settings/staff/create')
            ->assertOk();

        $page->assertSee('data-validate-form', false);
        $page->assertSee('data-rules="required|email|max:255"', false);
        $page->assertSee('data-rules="required|max:100"', false);
        $page->assertSee('data-phone-country-value', false);
    }

    /**
     * Every field carrying rules has somewhere to print them.
     *
     * A rule with no message box beside it fails silently in the browser: the
     * module paints into [data-error-for="<the field's id>"], and without one
     * the reader is refused with nothing said.
     */
    public function test_every_validated_field_has_a_message_box(): void
    {
        $html = $this->actingAs($this->owner())
            ->get('http://styledesk.test/settings/staff/create')
            ->getContent();

        preg_match_all('/id="([^"]+)"[^>]*data-rules=/', $html, $withRules);
        preg_match_all('/data-rules=[^>]*id="([^"]+)"/', $html, $rulesFirst);

        $ids = array_unique(array_merge($withRules[1], $rulesFirst[1]));

        $this->assertNotEmpty($ids);

        foreach ($ids as $id) {
            $this->assertStringContainsString('data-error-for="'.$id.'"', $html,
                "The field {$id} declares rules but has nowhere to print the message.");
        }
    }

    /**
     * All three numbers are entered with a searchable country picker beside
     * them, and all three codes are saved. None of the columns existed before
     * — a mobile written as "07700 900461" was a number nobody outside the UK
     * could dial, with nothing recording where it was from.
     */
    public function test_each_number_keeps_its_dialling_code(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'phone' => '7700 900461',
                'phone_country' => 'GB',
                'secondary_phone' => '512 555 0111',
                'secondary_phone_country' => 'US',
                'emergency_contact_phone' => '416 555 0199',
                'emergency_contact_phone_country' => 'CA',
            ]))
            ->assertSessionHasNoErrors();

        $staff = Staff::withoutGlobalScopes()->where('email', 'kit@acme.test')->firstOrFail();

        $this->assertSame('GB', $staff->phone_country);
        $this->assertSame('US', $staff->secondary_phone_country);
        $this->assertSame('CA', $staff->emergency_contact_phone_country);
    }

    /**
     * The address the browser refuses is the address the server refuses.
     *
     * The invitation to join is sent to this address, so one that reaches
     * nobody is a colleague who never arrives.
     */
    public function test_an_address_with_no_domain_is_refused(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->from('http://styledesk.test/settings/staff/create')
            ->post('http://styledesk.test/settings/staff', $this->payload(['email' => 'kit@acme']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('staff', ['email' => 'kit@acme']);
    }

    public function test_a_work_address_with_no_domain_is_refused(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->from('http://styledesk.test/settings/staff/create')
            ->post('http://styledesk.test/settings/staff', $this->payload(['work_email' => 'kit@acme']))
            ->assertSessionHasErrors('work_email');
    }

    public function test_a_real_address_is_accepted(): void
    {
        Queue::fake();

        $this->actingAs($this->owner())
            ->post('http://styledesk.test/settings/staff', $this->payload([
                'email' => 'kit@acme.co.uk',
                'work_email' => 'kit.wu@acme.co.uk',
            ]))
            ->assertSessionHasNoErrors();
    }
}
