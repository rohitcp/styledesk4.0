<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureBusinessIsActive;
use App\Models\BackofficeAdmin;
use App\Models\BackofficeAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Switching a business off from the platform console.
 *
 * The button is the easy half. The half worth testing is that "cannot sign in"
 * is true through every door: the login form, a session that was already open,
 * and a role that should never have been able to press it.
 */
class BackofficeDisableClientTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super-owner'): BackofficeAdmin
    {
        /* One administrator per role, reused: several of these tests call this
           twice for the same role, and the email is unique. */
        return BackofficeAdmin::query()->firstOrCreate(
            ['email' => $role.'@styledesk.test'],
            [
                'name' => 'Rohit Philip',
                'password' => 'Str0ng!Passw0rd!',
                'role' => $role,
                'status' => BackofficeAdmin::STATUS_ACTIVE,
            ],
        );
    }

    private function business(array $attributes = []): Tenant
    {
        return Tenant::create($attributes + ['name' => 'Velvet Wellness Studio', 'slug' => 'velvetwellnessstudio']);
    }

    /**
     * Emulate the next HTTP request being a new process.
     *
     * The test client keeps one container for the whole test, so the session
     * guard holds on to the User it resolved — and to the tenant relation that
     * User loaded, which then still reads "active" after the row changed. A
     * real second request boots a fresh guard and reloads both.
     */
    private function forgetResolvedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function staffMember(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'email' => 'stylist@velvet.test',
            'password' => Hash::make('Str0ng!Passw0rd!'),
        ]);
    }

    // ------------------------------------------------------- the console

    public function test_an_administrator_can_disable_a_business(): void
    {
        $admin = $this->admin();
        $tenant = $this->business();

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), ['reason' => 'non_payment'])
            ->assertRedirect(route('backoffice.clients.show', $tenant));

        $tenant->refresh();

        $this->assertSame(Tenant::STATUS_DISABLED, $tenant->status);
        $this->assertTrue($tenant->isDisabled());
        $this->assertSame('non_payment', $tenant->disabled_reason);
        $this->assertSame($admin->id, $tenant->disabled_by);
        $this->assertNotNull($tenant->disabled_at);
    }

    public function test_disabling_requires_a_reason_from_the_list(): void
    {
        $tenant = $this->business();

        foreach (['', 'whatever-i-typed'] as $reason) {
            $this->actingAs($this->admin(), 'backoffice')
                ->post(route('backoffice.clients.disable', $tenant), ['reason' => $reason])
                ->assertSessionHasErrors('reason');
        }

        $this->assertTrue($tenant->fresh()->isActive());
    }

    /** "Other" with nothing beside it says nothing at all. */
    public function test_other_requires_a_note(): void
    {
        $tenant = $this->business();

        $this->actingAs($this->admin(), 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), ['reason' => 'other'])
            ->assertSessionHasErrors('note');

        $this->assertTrue($tenant->fresh()->isActive());

        $this->actingAs($this->admin(), 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), [
                'reason' => 'other',
                'note' => 'Merged into the parent group account.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($tenant->fresh()->isDisabled());
    }

    /** Any other reason stands on its own; the note stays optional. */
    public function test_the_note_is_optional_for_every_other_reason(): void
    {
        $tenant = $this->business();

        $this->actingAs($this->admin(), 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), ['reason' => 'trial_expired'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($tenant->fresh()->isDisabled());
    }

    public function test_it_stores_the_note_and_the_status_it_replaced(): void
    {
        $admin = $this->admin();
        $tenant = $this->business(['subscription_status' => 'past_due']);

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), [
                'reason' => 'non_payment',
                'note' => 'Invoice #INV-2048 unpaid for 45 days.',
            ]);

        $tenant->refresh();

        $this->assertSame('Invoice #INV-2048 unpaid for 45 days.', $tenant->disabled_note);
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->previous_status);
        $this->assertSame('disabled', $tenant->displayStatus());
    }

    /**
     * A business disabled while its bill was overdue comes back overdue, not
     * flatly active. Access lives in `status` and billing in
     * `subscription_status`, so enabling restores the first and leaves the
     * second exactly as it was — which is what makes Active → Past Due →
     * Disabled → Past Due work without a second bookkeeping column.
     */
    public function test_enabling_puts_the_business_back_where_it_was(): void
    {
        $admin = $this->admin();
        $tenant = $this->business(['subscription_status' => 'past_due']);

        $this->assertSame('past_due', $tenant->displayStatus());

        $tenant->disable($admin, 'non_payment');
        $this->assertSame('disabled', $tenant->fresh()->displayStatus());

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.enable', $tenant), ['note' => 'Invoice settled']);

        $this->assertSame('past_due', $tenant->fresh()->displayStatus());
    }

    public function test_enabling_records_who_and_why(): void
    {
        $admin = $this->admin();
        $tenant = $this->business();
        $tenant->disable($admin, 'non_payment');

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.enable', $tenant), ['note' => 'Invoice settled']);

        $tenant->refresh();

        $this->assertSame($admin->id, $tenant->enabled_by);
        $this->assertSame('Invoice settled', $tenant->enable_note);
        $this->assertNotNull($tenant->enabled_at);

        $entry = BackofficeAuditLog::query()->where('action', 'client.enabled')->first();
        $this->assertSame('Invoice settled', $entry->after['note']);
    }

    /** The history on the client page is the audit log, filtered to them. */
    public function test_the_details_page_shows_the_disable_in_the_activity_history(): void
    {
        $admin = $this->admin();
        $tenant = $this->business();

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), [
                'reason' => 'non_payment',
                'note' => 'Invoice #INV-2048 unpaid for 45 days.',
            ]);

        $this->get(route('backoffice.clients.show', $tenant))
            ->assertOk()
            ->assertSee(__('backoffice.audit.actions.client_disabled'))
            ->assertSee(__('backoffice.clients.reasons.non_payment'))
            ->assertSee('Invoice #INV-2048 unpaid for 45 days.');
    }

    /** Another client's entries are not this client's history. */
    public function test_the_activity_history_is_scoped_to_the_client(): void
    {
        $admin = $this->admin();
        $mine = $this->business();
        $theirs = Tenant::create(['name' => 'Other Salon', 'slug' => 'other']);

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.disable', $theirs), ['reason' => 'fraud']);

        $this->get(route('backoffice.clients.show', $mine))
            ->assertOk()
            ->assertSee(__('backoffice.clients.no_activity'));
    }

    /** The reason and the note are the platform's, not the salon's. */
    public function test_the_internal_reason_is_never_shown_to_the_client(): void
    {
        $tenant = $this->business();
        $this->staffMember($tenant);
        $tenant->disable($this->admin(), 'fraud', 'Suspected card testing.');

        /* from() so the refusal redirects straight back to the login form.
           Without it `back()` has no previous URL, the response redirects
           through the home page first, and the flashed errors are spent on
           that hop before the form is ever rendered. */
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'stylist@velvet.test',
            'password' => 'Str0ng!Passw0rd!',
        ]);

        $this->followRedirects($response)
            ->assertSee(__('auth.business_disabled'))
            ->assertDontSee('Suspected card testing.')
            ->assertDontSee(__('backoffice.clients.reasons.fraud'));
    }

    public function test_it_records_who_disabled_the_business_and_why(): void
    {
        $admin = $this->admin();
        $tenant = $this->business();

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), ['reason' => 'non_payment']);

        $entry = BackofficeAuditLog::query()->where('action', 'client.disabled')->first();

        $this->assertNotNull($entry);
        $this->assertSame($admin->id, $entry->admin_id);
        $this->assertSame($tenant->getTenantKey(), $entry->subject_id);
        $this->assertSame('Velvet Wellness Studio', $entry->subject_label);
        $this->assertSame('non_payment', $entry->after['reason']);
    }

    public function test_enabling_clears_the_columns_that_said_it_was_shut(): void
    {
        $admin = $this->admin();
        $tenant = $this->business();
        $tenant->disable($admin, 'non_payment');

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.enable', $tenant), ['note' => 'Invoice settled'])
            ->assertRedirect(route('backoffice.clients.show', $tenant));

        $tenant->refresh();

        $this->assertTrue($tenant->isActive());
        $this->assertNull($tenant->disabled_at);
        $this->assertNull($tenant->disabled_by);
        $this->assertNull($tenant->disabled_reason);
    }

    /** Reading the list is not permission to take a salon offline. */
    public function test_a_role_that_may_only_view_cannot_disable(): void
    {
        $tenant = $this->business();

        $this->actingAs($this->admin('read-only'), 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), ['reason' => 'non_payment'])
            ->assertForbidden();

        $this->assertTrue($tenant->fresh()->isActive());
    }

    public function test_the_button_is_hidden_from_a_role_that_may_not_press_it(): void
    {
        $tenant = $this->business();

        $this->actingAs($this->admin('read-only'), 'backoffice')
            ->get(route('backoffice.clients.show', $tenant))
            ->assertOk()
            ->assertDontSee(__('backoffice.clients.disable_action'));

        $this->actingAs($this->admin('admin'), 'backoffice')
            ->get(route('backoffice.clients.show', $tenant))
            ->assertOk()
            ->assertSee(__('backoffice.clients.disable_action'));
    }

    // ------------------------------------------------- what it actually does

    public function test_a_staff_member_of_a_disabled_business_cannot_sign_in(): void
    {
        $tenant = $this->business();
        $this->staffMember($tenant);
        $tenant->disable($this->admin(), 'non_payment');

        $this->post(route('login.store'), [
            'email' => 'stylist@velvet.test',
            'password' => 'Str0ng!Passw0rd!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** The right password is not the question once the business is off. */
    public function test_the_owner_of_a_disabled_business_cannot_sign_in_either(): void
    {
        $tenant = $this->business();
        $owner = $this->staffMember($tenant);
        $tenant->update(['owner_user_id' => $owner->id]);
        $tenant->disable($this->admin(), 'non_payment');

        $this->post(route('login.store'), [
            'email' => $owner->email,
            'password' => 'Str0ng!Passw0rd!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * The door that a login check alone would leave open: somebody already
     * signed in when the business was switched off.
     */
    public function test_an_open_session_ends_on_the_next_request(): void
    {
        $tenant = $this->business();
        $this->staffMember($tenant);

        /* Signed in through the form rather than with actingAs: actingAs holds
           one model instance for the whole test, so the tenant relation it
           carries would still say "active" after the row changed underneath
           it. The session holds only an id, which is what a real second
           request resolves from. */
        $this->post(route('login.store'), [
            'email' => 'stylist@velvet.test',
            'password' => 'Str0ng!Passw0rd!',
        ]);
        $this->assertAuthenticated();

        $tenant->disable($this->admin(), 'non_payment');
        $this->forgetResolvedUser();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_login_screen_says_why(): void
    {
        $tenant = $this->business();
        $this->staffMember($tenant);

        $this->post(route('login.store'), [
            'email' => 'stylist@velvet.test',
            'password' => 'Str0ng!Passw0rd!',
        ]);

        $tenant->disable($this->admin(), 'non_payment');
        $this->forgetResolvedUser();

        $this->get(route('dashboard'))->assertSessionHas(EnsureBusinessIsActive::FLAG);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('auth.business_disabled'));
    }

    public function test_enabling_lets_them_back_in(): void
    {
        $tenant = $this->business();
        $this->staffMember($tenant);
        $tenant->disable($this->admin(), 'non_payment');
        $tenant->enable($this->admin());

        $this->post(route('login.store'), [
            'email' => 'stylist@velvet.test',
            'password' => 'Str0ng!Passw0rd!',
        ]);

        $this->assertAuthenticated();
    }

    /** The console runs on its own guard and must not lock itself out. */
    public function test_disabling_a_business_does_not_sign_the_administrator_out(): void
    {
        $admin = $this->admin();
        $tenant = $this->business();

        $this->actingAs($admin, 'backoffice')
            ->post(route('backoffice.clients.disable', $tenant), ['reason' => 'non_payment']);

        $this->get(route('backoffice.clients.show', $tenant))->assertOk();
    }
}
