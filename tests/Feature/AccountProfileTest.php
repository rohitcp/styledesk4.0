<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\VerifyEmailChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * My Profile: what a person may change about themselves, and what they may not.
 *
 * The address is the interesting half. It is the credential they sign in with,
 * so a change to it is treated as a security act: proved by password, proved
 * again from the new address, and reversible until then.
 */
class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Pass';

    private function member(): User
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-account']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'first_name' => 'Nadia',
            'last_name' => 'Khan',
            'email' => 'nadia@styledesk.test',
            'password' => self::PASSWORD,
        ]);

        $tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    public function test_the_profile_screen_opens_for_any_signed_in_user(): void
    {
        $this->actingAs($this->member())
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('Nadia');
    }

    public function test_signed_out_visitors_are_sent_to_login(): void
    {
        $this->get(route('account.profile'))->assertRedirect(route('login'));
    }

    public function test_the_editable_fields_are_saved(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'first_name' => 'Nadia',
                'last_name' => 'Khan-Reyes',
                'display_name' => 'Nadia K.',
                'job_title' => 'Senior stylist',
                'phone' => '5551234567',
                'phone_country' => 'US',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('toast');

        $user->refresh();

        $this->assertSame('Khan-Reyes', $user->last_name);
        $this->assertSame('Nadia K.', $user->display_name);
        $this->assertSame('Senior stylist', $user->job_title);
        $this->assertSame('5551234567', $user->phone);
    }

    /**
     * A blank display name is not a display name of "".
     */
    public function test_a_blank_display_name_falls_back_to_the_real_name(): void
    {
        $user = $this->member();
        $user->forceFill(['display_name' => 'Nadia K.'])->save();

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'first_name' => 'Nadia',
            'last_name' => 'Khan',
            'display_name' => '',
        ]);

        $user->refresh();

        $this->assertNull($user->display_name);
        $this->assertSame('Nadia Khan', $user->displayName());
    }

    public function test_a_name_is_required(): void
    {
        $this->actingAs($this->member())
            ->from(route('account.profile'))
            ->patch(route('account.profile.update'), ['first_name' => '', 'last_name' => ''])
            ->assertSessionHasErrors(['first_name', 'last_name']);
    }

    // ------------------------------------------------------------- the photo

    public function test_a_photo_is_stored_through_the_storage_component(): void
    {
        Storage::fake('local');
        $user = $this->member();

        $this->actingAs($user)
            ->post(route('account.photo.store'), [
                'photo' => UploadedFile::fake()->image('me.jpg', 300, 300),
            ])
            ->assertRedirect(route('account.profile'));

        $user->refresh();

        $this->assertNotNull($user->avatar_file_id);
        $this->assertDatabaseHas('stored_files', [
            'id' => $user->avatar_file_id,
            'category' => 'profile-image',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_a_document_is_refused_as_a_photo(): void
    {
        Storage::fake('local');

        $this->actingAs($this->member())
            ->from(route('account.profile'))
            ->post(route('account.photo.store'), [
                'photo' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');
    }

    public function test_removing_the_photo_clears_it(): void
    {
        Storage::fake('local');
        $user = $this->member();

        $this->actingAs($user)->post(route('account.photo.store'), [
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $this->actingAs($user->fresh())
            ->delete(route('account.photo.destroy'))
            ->assertRedirect(route('account.profile'));

        $this->assertNull($user->fresh()->avatar_file_id);
    }

    // ------------------------------------------------------- the email change

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $user = $this->member();

        Notification::fake();

        $this->actingAs($user)
            ->from(route('account.profile'))
            ->post(route('account.email.request'), [
                'email' => 'new@styledesk.test',
                'current_password' => 'not-my-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_the_old_address_keeps_working_until_the_new_one_is_confirmed(): void
    {
        Notification::fake();
        $user = $this->member();

        $this->actingAs($user)->post(route('account.email.request'), [
            'email' => 'new@styledesk.test',
            'current_password' => self::PASSWORD,
        ])->assertRedirect(route('account.profile'));

        $user->refresh();

        $this->assertSame('nadia@styledesk.test', $user->email);
        $this->assertSame('new@styledesk.test', $user->pending_email);

        /* The link goes to the address being claimed and nowhere else: sent to
           the old one it would prove nothing about the new one. */
        Notification::assertSentOnDemand(
            VerifyEmailChange::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@styledesk.test'
        );
    }

    public function test_the_confirmation_link_moves_the_address_across(): void
    {
        $user = $this->member();
        $token = 'a-token-worth-48-characters-for-this-test-purpose';

        $user->forceFill([
            'pending_email' => 'new@styledesk.test',
            'pending_email_token' => hash('sha256', $token),
            'pending_email_expires_at' => now()->addHour(),
        ])->save();

        /* Deliberately signed out: the mail client opens the link in whichever
           browser it likes, and that is very often not the one with a session. */
        $this->get(route('account.email.confirm', ['user' => $user->id, 'token' => $token]))
            ->assertRedirect(route('account.profile'));

        $user->refresh();

        $this->assertSame('new@styledesk.test', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_an_expired_link_changes_nothing(): void
    {
        $user = $this->member();
        $token = 'a-token-worth-48-characters-for-this-test-purpose';

        $user->forceFill([
            'pending_email' => 'new@styledesk.test',
            'pending_email_token' => hash('sha256', $token),
            'pending_email_expires_at' => now()->subMinute(),
        ])->save();

        $this->get(route('account.email.confirm', ['user' => $user->id, 'token' => $token]));

        $this->assertSame('nadia@styledesk.test', $user->fresh()->email);
    }

    public function test_a_wrong_token_changes_nothing(): void
    {
        $user = $this->member();

        $user->forceFill([
            'pending_email' => 'new@styledesk.test',
            'pending_email_token' => hash('sha256', 'the-real-token'),
            'pending_email_expires_at' => now()->addHour(),
        ])->save();

        $this->get(route('account.email.confirm', ['user' => $user->id, 'token' => 'a-guess']));

        $this->assertSame('nadia@styledesk.test', $user->fresh()->email);
        $this->assertSame('new@styledesk.test', $user->fresh()->pending_email);
    }

    public function test_an_address_somebody_else_holds_is_refused(): void
    {
        $user = $this->member();
        User::factory()->create(['email' => 'taken@styledesk.test']);

        $this->actingAs($user)
            ->from(route('account.profile'))
            ->post(route('account.email.request'), [
                'email' => 'taken@styledesk.test',
                'current_password' => self::PASSWORD,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_a_pending_change_can_be_cancelled(): void
    {
        $user = $this->member();

        $user->forceFill([
            'pending_email' => 'new@styledesk.test',
            'pending_email_token' => hash('sha256', 'x'),
            'pending_email_expires_at' => now()->addHour(),
        ])->save();

        $this->actingAs($user)->delete(route('account.email.cancel'));

        $this->assertNull($user->fresh()->pending_email);
    }

    /**
     * The whole permission model, stated as a test: there is no URL that
     * names somebody else, so nobody can be edited but yourself.
     */
    public function test_nothing_here_can_name_another_user(): void
    {
        $other = User::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);
        $user = $this->member();

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'first_name' => 'Hijacked',
                'last_name' => 'Name',
                'user_id' => $other->id,
                'id' => $other->id,
            ])->assertRedirect(route('account.profile'));

        $this->assertSame('Someone', $other->fresh()->first_name);
        $this->assertSame('Hijacked', $user->fresh()->first_name);
    }
}
