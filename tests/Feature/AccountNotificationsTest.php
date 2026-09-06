<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\NotificationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notifications: the defaults, the exceptions, and the ones that cannot be
 * switched off.
 *
 * The rule the whole feature rests on is that a stored row is an exception
 * and the absence of one is the catalogue's default — so a notification type
 * added next year reaches the people it was designed for rather than being
 * silently off for everybody who ever saved this screen.
 */
class AccountNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-notify']);

        $user = User::factory()->create(['tenant_id' => $tenant->getTenantKey()]);
        $tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    /**
     * The names on the screen are names, not keys.
     *
     * The label is looked up by indexing the whole `types` array, never as
     * `__('...types.booking.assigned')` — the translator splits on dots, and a
     * catalogue key that CONTAINS one ("booking.assigned" is one key, not two)
     * comes back as the key itself. This test used to assert exactly that
     * string, so it passed while the page printed
     * "account.notifications.types.booking.assigned" down the whole first
     * column.
     */
    public function test_the_screen_opens_with_readable_names_and_an_explanation_each(): void
    {
        $labels = (array) __('account.notifications.types');
        $hints = (array) __('account.notifications.types_hint');

        $this->actingAs($this->member())
            ->get(route('account.notifications'))
            ->assertOk()
            ->assertSee($labels['booking.assigned'])
            ->assertSee($hints['booking.assigned'], false)
            ->assertDontSee('account.notifications.types');
    }

    /** Every switch on the screen says what sets it off. */
    public function test_every_notification_type_has_an_explanation(): void
    {
        $hints = (array) __('account.notifications.types_hint');

        $missing = NotificationCatalog::types()->keys()
            ->reject(fn (string $key) => filled($hints[$key] ?? null))
            ->values()
            ->all();

        $this->assertSame([], $missing, 'Every notification type needs a types_hint entry.');
    }

    public function test_someone_who_has_never_saved_gets_the_catalogue_defaults(): void
    {
        $user = $this->member();

        /* On by default. */
        $this->assertTrue(NotificationCatalog::wants($user, 'booking.assigned', 'email'));
        /* Off by default — a busy salon does not want an email per completed
           appointment. */
        $this->assertFalse(NotificationCatalog::wants($user, 'booking.completed', 'email'));
    }

    public function test_switches_are_saved(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->patch(route('account.notifications.update'), [
                'notifications' => [
                    'booking.assigned' => ['in_app' => '1', 'email' => '0'],
                    'booking.completed' => ['in_app' => '1', 'email' => '1'],
                ],
            ])
            ->assertRedirect(route('account.notifications'))
            ->assertSessionHas('toast');

        $user->refresh();

        $this->assertFalse(NotificationCatalog::wants($user, 'booking.assigned', 'email'));
        $this->assertTrue(NotificationCatalog::wants($user, 'booking.completed', 'email'));
    }

    /**
     * The security types are never stored and never off.
     */
    public function test_a_security_alert_cannot_be_switched_off(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch(route('account.notifications.update'), [
            'notifications' => [
                'security.password_changed' => ['in_app' => '0', 'email' => '0'],
                'security.new_login' => ['in_app' => '0', 'email' => '0'],
            ],
        ]);

        $user->refresh();

        $this->assertTrue(NotificationCatalog::wants($user, 'security.password_changed', 'email'));
        $this->assertTrue(NotificationCatalog::wants($user, 'security.new_login', 'in_app'));

        $this->assertDatabaseMissing('user_notification_preferences', [
            'user_id' => $user->id,
            'type_key' => 'security.password_changed',
        ]);
    }

    /**
     * A channel that does not exist yet cannot be switched on.
     */
    public function test_an_unavailable_channel_is_not_stored(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch(route('account.notifications.update'), [
            'notifications' => ['booking.created' => ['sms' => '1', 'push' => '1']],
        ]);

        $this->assertDatabaseMissing('user_notification_preferences', [
            'user_id' => $user->id,
            'channel' => 'sms',
        ]);

        $this->assertFalse(NotificationCatalog::wants($user->fresh(), 'booking.created', 'sms'));
    }

    /**
     * A key nobody can send is a switch that does nothing, so it is not saved.
     */
    public function test_an_unknown_notification_key_is_ignored(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch(route('account.notifications.update'), [
            'notifications' => ['invented.thing' => ['email' => '1']],
        ]);

        $this->assertDatabaseMissing('user_notification_preferences', [
            'user_id' => $user->id,
            'type_key' => 'invented.thing',
        ]);
    }

    public function test_saving_twice_updates_rather_than_duplicates(): void
    {
        $user = $this->member();

        $payload = ['notifications' => ['booking.created' => ['in_app' => '1', 'email' => '1']]];

        $this->actingAs($user)->patch(route('account.notifications.update'), $payload);
        $this->actingAs($user->fresh())->patch(route('account.notifications.update'), $payload);

        $this->assertSame(1, $user->notificationPreferences()
            ->where('type_key', 'booking.created')
            ->where('channel', 'email')
            ->count());
    }

    public function test_reset_removes_the_exceptions(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch(route('account.notifications.update'), [
            'notifications' => ['booking.assigned' => ['in_app' => '0', 'email' => '0']],
        ]);

        $this->assertFalse(NotificationCatalog::wants($user->fresh(), 'booking.assigned', 'email'));

        $this->actingAs($user->fresh())
            ->post(route('account.notifications.reset'))
            ->assertRedirect(route('account.notifications'));

        $this->assertSame(0, $user->notificationPreferences()->count());
        $this->assertTrue(NotificationCatalog::wants($user->fresh(), 'booking.assigned', 'email'));
    }

    public function test_notification_settings_belong_to_the_person_who_saved_them(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-notify-two']);
        $mine = User::factory()->create(['tenant_id' => $tenant->getTenantKey()]);
        $theirs = User::factory()->create(['tenant_id' => $tenant->getTenantKey()]);

        $this->actingAs($mine)->patch(route('account.notifications.update'), [
            'notifications' => ['booking.assigned' => ['email' => '0']],
        ]);

        $this->assertFalse(NotificationCatalog::wants($mine->fresh(), 'booking.assigned', 'email'));
        $this->assertTrue(NotificationCatalog::wants($theirs->fresh(), 'booking.assigned', 'email'));
    }
}
