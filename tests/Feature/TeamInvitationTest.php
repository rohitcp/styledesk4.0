<?php

namespace Tests\Feature;

use App\Jobs\SendTeamInvitationEmail;
use App\Mail\TeamInvitationMail;
use App\Models\Location;
use App\Models\Staff;
use App\Models\TeamInvitation;
use App\Models\TeamInvitationDelivery;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Acceptance criteria from the Team Invite — Email Sending requirements.
 */
class TeamInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'owner@styledesk.test',
            'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

        // owner_user_id is what makes hasRole('owner') true, and every invite
        // endpoint is gated on that role.
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();

        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->owner = $this->owner->fresh();

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'team',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function invitePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Amelia',
            'last_name' => 'Hart',
            'email' => 'amelia@example.com',
            'role' => 'manager',
        ], $overrides);
    }

    /** Creates an invitation the way the app does, returning the plain token. */
    private function inviteAmelia(array $overrides = []): array
    {
        Queue::fake();

        $response = $this->actingAs($this->owner)
            ->postJson('http://styledesk.test/team/invitations', $this->invitePayload($overrides));

        $invitation = TeamInvitation::withoutGlobalScopes()->latest('id')->first();

        // The plain token exists only in the dispatched job, exactly as it
        // does in production — nothing reads it back off the row.
        $token = null;
        Queue::assertPushed(SendTeamInvitationEmail::class, function ($job) use (&$token) {
            $token = $job->plainToken;

            return true;
        });

        /**
         * Sign out before returning.
         *
         * actingAs() persists across later requests in the same test, and
         * every follow-up here is made *as the invited person* — usually a
         * visitor with no session at all. Leaving the owner signed in makes
         * the invite page correctly render "wrong account", which looks like
         * a product bug and is really a test artefact.
         */
        $this->signOut();

        return [$response, $invitation, $token];
    }

    private function signOut(): void
    {
        Auth::logout();
        $this->app['auth']->forgetGuards();
    }

    // ------------------------------------------------------------- sending

    public function test_adding_a_coworker_creates_a_pending_tenant_scoped_invitation(): void
    {
        [$response, $invitation] = $this->inviteAmelia();

        $response->assertCreated();

        $this->assertSame($this->tenant->getTenantKey(), $invitation->tenant_id);
        $this->assertSame('amelia@example.com', $invitation->email);
        $this->assertSame('manager', $invitation->role);
        $this->assertSame(TeamInvitation::STATUS_PENDING, $invitation->status);
        $this->assertSame($this->owner->id, $invitation->invited_by);
        $this->assertNotNull($invitation->sent_at);
    }

    public function test_the_invitation_expires_in_seven_days(): void
    {
        [, $invitation] = $this->inviteAmelia();

        $this->assertEqualsWithDelta(
            7 * 24 * 60,
            $invitation->sent_at->diffInMinutes($invitation->expires_at),
            1,
            'The spec fixes the invitation window at seven days.'
        );
    }

    public function test_the_email_is_queued_rather_than_sent_during_the_request(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)
            ->postJson('http://styledesk.test/team/invitations', $this->invitePayload())
            ->assertCreated();

        // Queued, so a slow or unreachable mail provider cannot hold the
        // onboarding screen open behind it.
        Queue::assertPushed(SendTeamInvitationEmail::class);
    }

    public function test_the_email_carries_a_working_join_link_and_the_invitation_details(): void
    {
        Mail::fake();

        [, $invitation, $token] = $this->inviteAmelia();

        // Run the job the way the queue worker would.
        (new SendTeamInvitationEmail($invitation, $token))->handle();

        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($invitation, $token) {
            $rendered = $mail->render();

            return $mail->hasTo($invitation->email)
                && str_contains($rendered, 'Join Team')
                && str_contains($rendered, 'Acme Salon')
                && str_contains($rendered, 'Rohit Philip')
                && str_contains($rendered, 'Manager')
                && str_contains($rendered, route('team-invite.show', $token));
        });
    }

    public function test_the_plain_token_is_never_stored(): void
    {
        [, $invitation, $token] = $this->inviteAmelia();

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);

        // Nothing anywhere on the row equals the plain token.
        $this->assertStringNotContainsString($token, json_encode($invitation->getAttributes()));
    }

    public function test_the_response_never_leaks_the_token(): void
    {
        [$response, , $token] = $this->inviteAmelia();

        $this->assertStringNotContainsString($token, $response->getContent());
        $this->assertStringNotContainsString('token', $response->getContent());
    }

    public function test_every_delivery_attempt_is_recorded(): void
    {
        Mail::fake();

        [, $invitation, $token] = $this->inviteAmelia();

        $this->assertDatabaseHas('team_invitation_deliveries', [
            'team_invitation_id' => $invitation->id,
            'status' => TeamInvitationDelivery::STATUS_QUEUED,
        ]);

        (new SendTeamInvitationEmail($invitation, $token))->handle();

        $this->assertDatabaseHas('team_invitation_deliveries', [
            'team_invitation_id' => $invitation->id,
            'status' => TeamInvitationDelivery::STATUS_SENT,
        ]);
    }

    public function test_a_provider_failure_is_logged_internally_and_leaves_the_invite_pending(): void
    {
        [, $invitation, $token] = $this->inviteAmelia();

        (new SendTeamInvitationEmail($invitation, $token))
            ->failed(new \RuntimeException('SMTP 535: authentication failed for user postmaster@internal'));

        $delivery = TeamInvitationDelivery::where('team_invitation_id', $invitation->id)
            ->where('status', TeamInvitationDelivery::STATUS_FAILED)
            ->first();

        $this->assertNotNull($delivery, 'A failed send must leave a record to explain the missing email.');
        $this->assertStringContainsString('SMTP 535', $delivery->error);

        // Still pending, so the team screen can offer Resend rather than
        // stranding the member in a failed state.
        $this->assertSame(TeamInvitation::STATUS_PENDING, $invitation->fresh()->status);
    }

    // -------------------------------------------------------- duplicates

    public function test_a_second_pending_invitation_to_the_same_address_is_refused(): void
    {
        $this->inviteAmelia();

        Queue::fake();

        $this->actingAs($this->owner)
            ->postJson('http://styledesk.test/team/invitations', $this->invitePayload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'An invitation has already been sent to this email address.');

        $this->assertSame(1, TeamInvitation::withoutGlobalScopes()->count());
        Queue::assertNotPushed(SendTeamInvitationEmail::class);
    }

    public function test_a_revoked_invitation_does_not_block_a_new_one(): void
    {
        [, $invitation] = $this->inviteAmelia();
        $invitation->revoke();

        Queue::fake();

        // Revoking is a decision that has been undone; refusing here would
        // leave an owner unable to re-invite someone they removed by mistake.
        $this->actingAs($this->owner)
            ->postJson('http://styledesk.test/team/invitations', $this->invitePayload())
            ->assertCreated();
    }

    public function test_case_only_differences_in_the_address_are_treated_as_the_same_inbox(): void
    {
        $this->inviteAmelia();

        Queue::fake();

        $this->actingAs($this->owner)
            ->postJson('http://styledesk.test/team/invitations', $this->invitePayload(['email' => 'Amelia@Example.com']))
            ->assertStatus(409);
    }

    // ---------------------------------------------------- resend / revoke

    public function test_resending_replaces_the_token_so_the_old_link_stops_working(): void
    {
        [, $invitation, $oldToken] = $this->inviteAmelia();

        Queue::fake();

        $this->actingAs($this->owner)
            ->postJson("http://styledesk.test/team/invitations/{$invitation->id}/resend")
            ->assertOk();

        $this->signOut();

        $this->get('http://styledesk.test/invite/team/'.$oldToken)
            ->assertOk()
            ->assertSee('no longer available', false);

        $newToken = null;
        Queue::assertPushed(SendTeamInvitationEmail::class, function ($job) use (&$newToken) {
            $newToken = $job->plainToken;

            return true;
        });

        $this->assertNotSame($oldToken, $newToken);
        $this->get('http://styledesk.test/invite/team/'.$newToken)->assertOk()->assertSee('Acme Salon');
    }

    public function test_resending_moves_the_expiry_window_forward(): void
    {
        [, $invitation] = $this->inviteAmelia();

        $invitation->forceFill(['expires_at' => now()->addHour()])->save();

        Queue::fake();

        $this->actingAs($this->owner)
            ->postJson("http://styledesk.test/team/invitations/{$invitation->id}/resend")
            ->assertOk();

        // A fresh link that expires in an hour is not a fresh link.
        $this->assertTrue($invitation->fresh()->expires_at->gt(now()->addDays(6)));
    }

    public function test_revoking_stops_the_link_immediately(): void
    {
        [, $invitation, $token] = $this->inviteAmelia();

        $this->actingAs($this->owner)
            ->deleteJson("http://styledesk.test/team/invitations/{$invitation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', TeamInvitation::STATUS_REVOKED);

        $this->get('http://styledesk.test/invite/team/'.$token)
            ->assertOk()
            ->assertSee('no longer available', false);

        $this->post('http://styledesk.test/invite/team/'.$token.'/register', [
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass', 'terms' => '1',
        ])->assertGone();
    }

    // ------------------------------------------------------- new user flow

    public function test_a_new_user_can_create_an_account_and_join(): void
    {
        [, $invitation, $token] = $this->inviteAmelia(['location_id' => null]);

        $this->get('http://styledesk.test/invite/team/'.$token)
            ->assertOk()
            ->assertSee('Acme Salon')
            ->assertSee('Manager')
            ->assertSee('amelia@example.com');

        $this->post('http://styledesk.test/invite/team/'.$token.'/register', [
            'first_name' => 'amelia',
            'last_name' => 'hart',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
            'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'amelia@example.com')->first();

        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertSame($this->tenant->getTenantKey(), $user->tenant_id);
        // The project capitalisation rule applies to names typed here too.
        $this->assertSame('Amelia', $user->first_name);
        // Arriving through the emailed link proves control of the inbox.
        $this->assertNotNull($user->email_verified_at);

        $invitation->refresh();
        $this->assertSame(TeamInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame($user->id, $invitation->accepted_user_id);

        $staff = Staff::withoutGlobalScopes()->where('user_id', $user->id)->first();
        $this->assertNotNull($staff, 'Accepting must make the person a staff member of the business.');
        $this->assertSame('manager', $staff->role);
        $this->assertSame($this->tenant->getTenantKey(), $staff->tenant_id);
    }

    public function test_the_assigned_location_is_applied_on_acceptance(): void
    {
        $location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
        ]);

        [, , $token] = $this->inviteAmelia(['location_id' => $location->id]);

        $this->post('http://styledesk.test/invite/team/'.$token.'/register', [
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass', 'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $staff = Staff::withoutGlobalScopes()->where('email', 'amelia@example.com')->first();

        $this->assertSame($location->id, $staff->location_id);
    }

    public function test_the_email_on_the_form_cannot_be_swapped_for_another_address(): void
    {
        [, , $token] = $this->inviteAmelia();

        $this->post('http://styledesk.test/invite/team/'.$token.'/register', [
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'email' => 'attacker@example.com',
            'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass', 'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        // The address is taken from the invitation, never from the body.
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'amelia@example.com']);
    }

    // -------------------------------------------------- existing user flow

    public function test_an_existing_user_is_asked_to_sign_in_rather_than_given_a_signup_form(): void
    {
        User::create([
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'email' => 'amelia@example.com', 'password' => 'Str0ng!Pass',
        ]);

        [, , $token] = $this->inviteAmelia();

        $this->get('http://styledesk.test/invite/team/'.$token)
            ->assertOk()
            ->assertSee('Sign in to accept')
            ->assertDontSee('Create Account &amp; Join Team', false);
    }

    public function test_an_existing_user_joins_without_a_second_account_being_created(): void
    {
        $existing = User::create([
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'email' => 'amelia@example.com', 'password' => 'Str0ng!Pass',
        ]);
        $existing->markEmailAsVerified();

        [, $invitation, $token] = $this->inviteAmelia();

        $this->actingAs($existing->fresh())
            ->post('http://styledesk.test/invite/team/'.$token.'/accept')
            ->assertRedirect(route('dashboard'));

        $this->assertSame(1, User::where('email', 'amelia@example.com')->count());
        $this->assertSame($this->tenant->getTenantKey(), $existing->fresh()->tenant_id);
        $this->assertSame(TeamInvitation::STATUS_ACCEPTED, $invitation->fresh()->status);
    }

    public function test_signing_in_as_someone_else_cannot_accept_the_invitation(): void
    {
        $other = User::create([
            'first_name' => 'Someone', 'last_name' => 'Else',
            'email' => 'someone@example.com', 'password' => 'Str0ng!Pass',
        ]);
        $other->markEmailAsVerified();

        [, $invitation, $token] = $this->inviteAmelia();

        $this->actingAs($other->fresh())
            ->get('http://styledesk.test/invite/team/'.$token)
            ->assertOk()
            ->assertSee('different account');

        $this->actingAs($other->fresh())
            ->post('http://styledesk.test/invite/team/'.$token.'/accept')
            ->assertSessionHasErrors('invitation');

        $this->assertNull($other->fresh()->tenant_id);
        $this->assertSame(TeamInvitation::STATUS_PENDING, $invitation->fresh()->status);
    }

    // ------------------------------------------------------------- expiry

    public function test_an_expired_invitation_shows_a_plain_explanation_and_no_error_code(): void
    {
        [, $invitation, $token] = $this->inviteAmelia();

        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        $this->get('http://styledesk.test/invite/team/'.$token)
            ->assertOk()
            ->assertSee('This invitation has expired.')
            ->assertSee('ask your administrator to send you a new invitation')
            ->assertDontSee('Exception')
            ->assertDontSee('410');
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        [, $invitation, $token] = $this->inviteAmelia();

        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        $this->post('http://styledesk.test/invite/team/'.$token.'/register', [
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass', 'terms' => '1',
        ])->assertGone();

        $this->assertDatabaseMissing('users', ['email' => 'amelia@example.com']);
    }

    public function test_a_token_cannot_be_reused_after_acceptance(): void
    {
        [, , $token] = $this->inviteAmelia();

        $this->post('http://styledesk.test/invite/team/'.$token.'/register', [
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass', 'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->post('http://styledesk.test/invite/team/'.$token.'/accept')->assertGone();
    }

    // --------------------------------------------------- tenant isolation

    public function test_an_invitation_cannot_be_resent_or_revoked_from_another_business(): void
    {
        [, $invitation] = $this->inviteAmelia();

        $intruderTenant = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);
        $intruder = User::create([
            'first_name' => 'Rival', 'last_name' => 'Owner',
            'email' => 'rival@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $intruder->markEmailAsVerified();
        $intruderTenant->forceFill(['owner_user_id' => $intruder->id])->save();
        $intruder->forceFill(['tenant_id' => $intruderTenant->getTenantKey()])->save();

        // Not merely unauthorised: the tenant scope means the id does not
        // resolve to a row at all from inside another business.
        $this->actingAs($intruder->fresh())
            ->postJson("http://styledesk.test/team/invitations/{$invitation->id}/resend")
            ->assertNotFound();

        $this->actingAs($intruder->fresh())
            ->deleteJson("http://styledesk.test/team/invitations/{$invitation->id}")
            ->assertNotFound();

        $this->assertSame(TeamInvitation::STATUS_PENDING, $invitation->fresh()->status);
    }

    public function test_accepting_only_ever_joins_the_tenant_named_on_the_invitation(): void
    {
        $otherTenant = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);

        [, , $token] = $this->inviteAmelia();

        $this->post('http://styledesk.test/invite/team/'.$token.'/register', [
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'password' => 'Str0ng!Pass', 'password_confirmation' => 'Str0ng!Pass', 'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'amelia@example.com')->first();

        $this->assertSame($this->tenant->getTenantKey(), $user->tenant_id);
        $this->assertNotSame($otherTenant->getTenantKey(), $user->tenant_id);
        $this->assertSame(0, Staff::withoutGlobalScopes()->where('tenant_id', $otherTenant->getTenantKey())->count());
    }

    public function test_a_user_who_already_belongs_to_another_business_is_refused(): void
    {
        $otherTenant = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);
        $existing = User::create([
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'email' => 'amelia@example.com', 'password' => 'Str0ng!Pass',
        ]);
        $existing->markEmailAsVerified();
        $existing->forceFill(['tenant_id' => $otherTenant->getTenantKey()])->save();

        [, , $token] = $this->inviteAmelia();

        $this->actingAs($existing->fresh())
            ->post('http://styledesk.test/invite/team/'.$token.'/accept')
            ->assertSessionHasErrors('invitation');

        // Moving them silently would cut them off from the business they are
        // already part of.
        $this->assertSame($otherTenant->getTenantKey(), $existing->fresh()->tenant_id);
    }

    // ---------------------------------------------------- authorisation

    public function test_a_service_provider_cannot_invite_anyone(): void
    {
        $member = User::create([
            'first_name' => 'Sam', 'last_name' => 'Stylist',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $member->markEmailAsVerified();
        $member->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $member->id,
            'first_name' => 'Sam', 'last_name' => 'Stylist',
            'email' => 'sam@styledesk.test', 'role' => 'service-provider',
        ]);

        $this->actingAs($member->fresh())
            ->postJson('http://styledesk.test/team/invitations', $this->invitePayload())
            ->assertForbidden();

        $this->assertSame(0, TeamInvitation::withoutGlobalScopes()->count());
    }

    public function test_nobody_can_be_invited_as_owner(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)
            ->postJson('http://styledesk.test/team/invitations', $this->invitePayload(['role' => 'owner']))
            ->assertJsonValidationErrors('role');
    }

    public function test_a_location_belonging_to_another_business_is_refused(): void
    {
        $otherTenant = Tenant::create(['name' => 'Rival Spa', 'slug' => 'rival']);
        $foreign = Location::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->getTenantKey(),
            'name' => 'Rival Main', 'address_line1' => '9 Rival Rd', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
        ]);

        $this->actingAs($this->owner)
            ->postJson('http://styledesk.test/team/invitations', $this->invitePayload(['location_id' => $foreign->id]))
            ->assertJsonValidationErrors('location_id');
    }
}
