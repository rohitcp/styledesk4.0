<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SmsMessage;
use App\Models\SmsSettings;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App Settings → SMS.
 *
 * What these guard is that the screen cannot promise something StyleDesk
 * does not do. A business must not be able to switch on a reminder nothing
 * produces, and switching texting off for a difficult week must not lose the
 * choices it took somebody twenty minutes to make.
 */
class SmsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Spa', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);
    }

    public function test_the_screen_opens_with_texting_off(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.sms.index'))
            ->assertOk()
            ->assertSee(__('sms.settings.title'))
            ->assertSee(__('sms.settings.disabled_note'));
    }

    public function test_the_settings_can_be_saved(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.sms.update'), [
                'is_enabled' => 1,
                'messages' => ['booking_confirmation'],
                'reminder_hours' => [24, 2],
                'birthday_send_at' => '10:30',
                'monthly_limit' => 1000,
                'alert_percent' => 75,
            ])
            ->assertSessionHasNoErrors();

        $settings = SmsSettings::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($settings->is_enabled);
        $this->assertSame(['booking_confirmation'], $settings->messages);
        /* Sorted, so the screen reads in order and two reminders cannot be
           written the same way twice. */
        $this->assertSame([2, 24], $settings->reminder_hours);
        $this->assertSame(1000, $settings->monthly_limit);
        $this->assertSame(75, $settings->alert_percent);
    }

    /**
     * A stray character in the environment is not a source number.
     *
     * It reaches the carrier as "the source phone number was deemed invalid",
     * a refusal that names neither the file nor the line it came from.
     */
    public function test_a_malformed_platform_number_is_cleaned(): void
    {
        config()->set('services.clicksend.from', '+15856651465test');

        $this->assertSame('+15856651465', (new SmsSettings)->senderNumber());
    }

    // ---------------------------------------------------------- the test send

    /**
     * One message, through the ordinary path.
     *
     * A test that took a shortcut would prove the shortcut works, so this
     * goes through the same service and lands in the same log as a booking
     * confirmation — and the toast names the provider, because "sent" means
     * something different when nothing is connected.
     */
    public function test_a_test_message_goes_through_the_ordinary_path(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.sms.test'), ['to' => '+1 201 555 0142'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $message = SmsMessage::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('test', $message->type);
        /* Punctuation stripped: a carrier wants digits. */
        $this->assertSame('+12015550142', $message->to_number);
        $this->assertSame('sent', $message->status);
        $this->assertSame(1, $message->segments);
        $this->assertStringContainsString('Acme Spa', $message->body);
    }

    /** It works before the business has switched texting on. */
    public function test_a_test_can_be_sent_while_sms_is_off(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.sms.test'), ['to' => '+12015550142'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SmsMessage::withoutGlobalScopes()->count());
    }

    public function test_a_number_that_is_not_one_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.sms.test'), ['to' => 'nope'])
            ->assertSessionHasErrors('to');

        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    /** Spending money is its own authority, like the settings above it. */
    public function test_the_test_send_is_behind_the_sms_permission(): void
    {
        $this->actingAs($this->administratorWithoutSms())
            ->post(route('settings.sms.test'), ['to' => '+12015550142'])
            ->assertForbidden();

        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------- fixtures

    private function owner(): User
    {
        /* One owner per test, however many times it is asked for: a helper
           that inserts a second row on the second call fails on the email
           rather than on the thing being tested. */
        if ($existing = User::where('email', 'owner@styledesk.test')->first()) {
            return $existing;
        }

        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    /**
     * Somebody who may open App Settings and nothing more.
     *
     * `settings.view` is the whole of what the group asks for, so this is the
     * reader the controller's own check exists for: allowed through the door,
     * and refused at this particular screen.
     */
    private function administratorWithoutSms(): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Ito',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'settings-only', 'name' => 'Settings only',
        ]);
        $role->permissions()->create(['permission' => 'settings.view', 'scope' => 'all']);

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id, 'role_id' => $role->id,
            'first_name' => 'Sam', 'last_name' => 'Ito',
            'email' => 'sam@styledesk.test', 'role' => 'administrator',
            'is_active' => true,
        ]);

        return $user->fresh();
    }

    private function userWithout(): User
    {
        $user = User::create([
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'limited', 'name' => 'Limited',
        ]);

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id, 'role_id' => $role->id,
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'role' => 'front-desk',
            'is_active' => true,
        ]);

        return $user->fresh();
    }
}
