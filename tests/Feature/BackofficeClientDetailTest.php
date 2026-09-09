<?php

namespace Tests\Feature;

use App\Models\BackofficeAdmin;
use App\Models\Client;
use App\Models\ClientEmailMessage;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * One client record, across its six tabs.
 *
 * The tabs are URLs rather than panels a script shows and hides, which is what
 * makes them testable at all — and what these tests hold them to: each tab
 * reads only its own rows, an unknown tab is refused rather than quietly
 * showing the Overview, and the quick-action control offers an administrator
 * nothing their role could not do.
 */
class BackofficeClientDetailTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'super-owner'): BackofficeAdmin
    {
        $admin = BackofficeAdmin::query()->create([
            'name' => 'Rohit Philip',
            'email' => $role.'@styledesk.test',
            'password' => 'Str0ng!Passw0rd!',
            'role' => $role,
            'status' => BackofficeAdmin::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin, 'backoffice');

        return $admin;
    }

    private function tenant(string $name = 'Smile Spa', string $slug = 'smilespa'): Tenant
    {
        return Tenant::create(['name' => $name, 'slug' => $slug]);
    }

    /** A branch, with the columns the table insists on. */
    private function location(Tenant $tenant, string $name): Location
    {
        return Location::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'name' => $name,
            'address_line1' => '1 River Street',
            'city' => 'Austin',
            'state' => 'Texas',
            'postal_code' => '78701',
            'country' => 'US',
            'timezone' => 'America/Chicago',
        ]);
    }

    private function service(Tenant $tenant, string $name, bool $active = true, int $minutes = 45): Service
    {
        return Service::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'name' => $name,
            'duration_minutes' => $minutes,
            'is_active' => $active,
        ]);
    }

    private function member(Tenant $tenant, string $first, string $last, array $extra = []): Staff
    {
        return Staff::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->getTenantKey(),
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first).'@'.$tenant->slug.'.test',
            'is_active' => true,
        ], $extra));
    }

    /** A salon's own customer — the person an email in the log went to. */
    private function guest(Tenant $tenant): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'client_ref' => 'C'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'first_name' => 'Robin',
            'last_name' => 'Ellis',
            'email' => 'guest@example.test',
        ]);
    }

    private function email(Tenant $tenant, array $extra = []): ClientEmailMessage
    {
        return ClientEmailMessage::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->getTenantKey(),
            'client_id' => $this->guest($tenant)->id,
            'recipient_email' => 'guest@example.test',
            'sender_email' => 'salon@example.test',
            'sender_name' => 'Smile Spa',
            'subject' => 'Your appointment',
            'message' => 'See you on Tuesday.',
            'provider' => 'smtp',
            'status' => ClientEmailMessage::STATUS_SENT,
        ], $extra));
    }

    // ---------------------------------------------------------------- tabs

    public function test_the_page_opens_on_the_overview_with_every_tab_named(): void
    {
        $this->signIn();

        $tenant = $this->tenant();

        $this->get(route('backoffice.clients.show', $tenant))
            ->assertOk()
            ->assertViewHas('tab', 'overview')
            ->assertSee('Overview')
            ->assertSee('Services')
            ->assertSee('Email Log')
            ->assertSee('SMS Log')
            ->assertSee('Team Members')
            ->assertSee('Subscription');
    }

    /**
     * The Overview must not pay for the listing tabs.
     *
     * A client with four thousand emails would otherwise load all of them to
     * show a summary that never mentions one.
     */
    public function test_a_tab_reads_only_its_own_rows(): void
    {
        $this->signIn();

        $tenant = $this->tenant();
        $this->service($tenant, 'Cut and finish');
        $this->email($tenant, ['subject' => 'Your appointment on Tuesday']);

        $this->get(route('backoffice.clients.show', $tenant))
            ->assertOk()
            ->assertViewMissing('services')
            ->assertViewMissing('emails')
            ->assertViewMissing('team');

        $this->get(route('backoffice.clients.show', ['tenant' => $tenant, 'tab' => 'services']))
            ->assertOk()
            ->assertViewHas('services')
            ->assertViewMissing('emails');
    }

    /** A bookmarked tab must be the tab it was, or nothing at all. */
    public function test_an_unknown_tab_is_refused_rather_than_shown_as_the_overview(): void
    {
        $this->signIn();

        $this->get(route('backoffice.clients.index').'/'.$this->tenant()->slug.'/invoices')
            ->assertNotFound();
    }

    // ------------------------------------------------------------ overview

    public function test_the_overview_summarises_the_account(): void
    {
        $this->signIn();

        $owner = User::factory()->create([
            'first_name' => 'Dana',
            'last_name' => 'Reeves',
            'email' => 'dana@smilespa.test',
            'last_login_at' => now()->subDay(),
        ]);

        $tenant = $this->tenant();
        $tenant->forceFill(['owner_user_id' => $owner->id])->save();
        $owner->forceFill(['tenant_id' => $tenant->getTenantKey()])->save();

        $this->service($tenant, 'Cut and finish');
        $this->member($tenant, 'Priya', 'Nair');

        $response = $this->get(route('backoffice.clients.show', $tenant))->assertOk();

        $usage = $response->viewData('usage');

        $this->assertSame(1, $usage['services']);
        $this->assertSame(1, $usage['team']);
        $this->assertSame(1, $usage['users']);

        $response->assertSee('Dana Reeves')
            ->assertSee('dana@smilespa.test')
            ->assertSee('Last activity');

        $this->assertNotNull($response->viewData('lastActivity'));
    }

    // ------------------------------------------------------------ services

    public function test_the_services_tab_lists_searches_and_filters(): void
    {
        $this->signIn();

        $tenant = $this->tenant();
        $category = ServiceCategory::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'name' => 'Colour',
        ]);

        $this->service($tenant, 'Balayage')->forceFill(['service_category_id' => $category->id])->save();
        $this->service($tenant, 'Dry cut', active: false);

        $url = fn (array $query = []) => route('backoffice.clients.show', array_merge(
            ['tenant' => $tenant, 'tab' => 'services'],
            $query,
        ));

        $this->get($url())->assertOk()->assertSee('Balayage')->assertSee('Dry cut');

        $this->get($url(['search' => 'Balay']))->assertOk()
            ->assertSee('Balayage')->assertDontSee('Dry cut');

        /* Found through the category rather than through its own name, which
           is what a reader searching "Colour" expects. */
        $this->get($url(['search' => 'Colour']))->assertOk()
            ->assertSee('Balayage')->assertDontSee('Dry cut');

        $this->get($url(['status' => 'inactive']))->assertOk()
            ->assertSee('Dry cut')->assertDontSee('Balayage');
    }

    /** One business's services, never another's. */
    public function test_the_services_tab_does_not_leak_another_clients_rows(): void
    {
        $this->signIn();

        $smile = $this->tenant();
        $acme = $this->tenant('Acme Salon', 'acme');

        $this->service($smile, 'Balayage');
        $this->service($acme, 'Beard trim');

        $this->get(route('backoffice.clients.show', ['tenant' => $smile, 'tab' => 'services']))
            ->assertOk()
            ->assertSee('Balayage')
            ->assertDontSee('Beard trim');
    }

    /** A sort key reaches an ORDER BY, so it may only ever be one on offer. */
    public function test_an_unknown_sort_or_page_size_falls_back_instead_of_being_trusted(): void
    {
        $this->signIn();

        $tenant = $this->tenant();
        $this->service($tenant, 'Balayage');

        $response = $this->get(route('backoffice.clients.show', [
            'tenant' => $tenant,
            'tab' => 'services',
            'sort' => 'is_active); drop table services;--',
            'per_page' => 9999,
        ]))->assertOk();

        $filters = $response->viewData('serviceFilters');

        $this->assertSame('name', $filters['sort']);
        $this->assertSame(25, $filters['per_page']);
    }

    // ----------------------------------------------------------- email log

    public function test_the_email_log_lists_and_filters_by_status(): void
    {
        $this->signIn();

        $tenant = $this->tenant();
        $this->email($tenant, ['subject' => 'Booking confirmed', 'template_key' => 'booking_confirmation']);
        $this->email($tenant, [
            'subject' => 'Reminder',
            'status' => ClientEmailMessage::STATUS_FAILED,
            'failure_reason' => 'Mailbox does not exist',
        ]);

        $url = fn (array $query = []) => route('backoffice.clients.show', array_merge(
            ['tenant' => $tenant, 'tab' => 'email-log'],
            $query,
        ));

        $this->get($url())->assertOk()
            ->assertSee('Booking confirmed')
            ->assertSee('Reminder')
            ->assertSee('Mailbox does not exist');

        $this->get($url(['status' => ClientEmailMessage::STATUS_FAILED]))->assertOk()
            ->assertSee('Reminder')
            ->assertDontSee('Booking confirmed');

        $this->get($url(['search' => 'confirmed']))->assertOk()
            ->assertSee('Booking confirmed')
            ->assertDontSee('Reminder');
    }

    // --------------------------------------------------------------- team

    public function test_the_team_tab_lists_and_filters_by_location(): void
    {
        $this->signIn();

        $tenant = $this->tenant();
        $north = $this->location($tenant, 'North');
        $this->location($tenant, 'South');

        $this->member($tenant, 'Priya', 'Nair', ['location_id' => $north->id]);
        $this->member($tenant, 'Marcus', 'Hale');

        $url = fn (array $query = []) => route('backoffice.clients.show', array_merge(
            ['tenant' => $tenant, 'tab' => 'team'],
            $query,
        ));

        $this->get($url())->assertOk()->assertSee('Priya')->assertSee('Marcus');

        $this->get($url(['search' => 'Marcus Hale']))->assertOk()
            ->assertSee('Marcus')->assertDontSee('Priya');

        $this->get($url(['location' => $north->id]))->assertOk()
            ->assertSee('Priya')->assertDontSee('Marcus');
    }

    // ------------------------------------------------------- coming soon

    public function test_the_unbuilt_tabs_say_so_rather_than_showing_an_empty_table(): void
    {
        $this->signIn();

        $tenant = $this->tenant();

        foreach (['sms-log', 'subscription'] as $tab) {
            $this->get(route('backoffice.clients.show', ['tenant' => $tenant, 'tab' => $tab]))
                ->assertOk()
                ->assertSee('Coming soon');
        }
    }

    // ------------------------------------------------------ quick actions

    public function test_the_quick_action_control_offers_the_actions_the_brief_names(): void
    {
        $this->signIn();

        $tenant = $this->tenant();

        $keys = collect($this->get(route('backoffice.clients.show', $tenant))
            ->assertOk()
            ->viewData('quickActions'))
            ->pluck('key')
            ->all();

        foreach ([
            'open_app', 'view_profile', 'view_services', 'view_team', 'view_email_logs',
            'copy_url', 'copy_id', 'send_email', 'resend_welcome', 'deactivate',
        ] as $expected) {
            $this->assertContains($expected, $keys);
        }
    }

    /**
     * An action that cannot be carried out is shown and refused rather than
     * offered. The alternative is a control that appears to send an email the
     * product cannot send.
     */
    public function test_the_actions_with_nothing_behind_them_yet_are_marked_unavailable(): void
    {
        $this->signIn();

        $actions = collect($this->get(route('backoffice.clients.show', $this->tenant()))
            ->viewData('quickActions'))
            ->keyBy('key');

        $this->assertTrue($actions['send_email']['disabled']);
        $this->assertTrue($actions['resend_welcome']['disabled']);
    }

    /** Enabling and disabling are offered to the role that may do them. */
    public function test_a_read_only_administrator_is_offered_no_account_changes(): void
    {
        $this->signIn('read-only');

        $keys = collect($this->get(route('backoffice.clients.show', $this->tenant()))
            ->assertOk()
            ->viewData('quickActions'))
            ->pluck('key')
            ->all();

        $this->assertNotContains('deactivate', $keys);
        $this->assertNotContains('activate', $keys);
        $this->assertNotContains('send_password_reset', $keys);

        /* Reading is still theirs. */
        $this->assertContains('view_services', $keys);
    }

    /** A disabled client is offered the way back on, not the way off again. */
    public function test_a_disabled_client_is_offered_activation_instead_of_deactivation(): void
    {
        $this->signIn();

        $tenant = $this->tenant();
        $tenant->forceFill(['status' => Tenant::STATUS_DISABLED])->save();

        $keys = collect($this->get(route('backoffice.clients.show', $tenant))
            ->assertOk()
            ->viewData('quickActions'))
            ->pluck('key')
            ->all();

        $this->assertContains('activate', $keys);
        $this->assertNotContains('deactivate', $keys);
    }

    // ------------------------------------------------------ password reset

    public function test_it_emails_the_owner_a_password_reset_link(): void
    {
        Notification::fake();

        $this->signIn();

        $owner = User::factory()->create(['email' => 'dana@smilespa.test']);
        $tenant = $this->tenant();
        $tenant->forceFill(['owner_user_id' => $owner->id])->save();

        $this->post(route('backoffice.clients.password-reset', $tenant))
            ->assertRedirect(route('backoffice.clients.show', ['tenant' => $tenant, 'tab' => 'overview']))
            ->assertSessionHas('status');

        Notification::assertSentTo($owner, ResetPassword::class);
    }

    /** Nothing to reset, and nothing pretending otherwise. */
    public function test_a_client_with_no_owner_is_told_so_rather_than_told_it_worked(): void
    {
        Notification::fake();

        $this->signIn();

        $this->post(route('backoffice.clients.password-reset', $this->tenant()))
            ->assertRedirect()
            ->assertSessionHas('status', __('backoffice.clients.reset_no_owner'));

        Notification::assertNothingSent();
    }

    /** Acting on a customer's account is `clients.manage`, not `clients.view`. */
    public function test_a_read_only_administrator_cannot_send_a_password_reset(): void
    {
        Notification::fake();

        $this->signIn('read-only');

        $owner = User::factory()->create();
        $tenant = $this->tenant();
        $tenant->forceFill(['owner_user_id' => $owner->id])->save();

        $this->post(route('backoffice.clients.password-reset', $tenant))->assertForbidden();

        Notification::assertNothingSent();
    }
}
