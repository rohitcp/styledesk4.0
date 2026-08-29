<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The dashboard's getting-started list, against what the business actually
 * has.
 *
 * Every item is read from real records rather than from a stored flag, so
 * these tests do the thing and then look at the list — never the other way
 * round. An item that ticked itself off from a flag could claim a service
 * exists on a business that has none.
 */
class GettingStartedChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio', 'slug' => 'nadia', 'business_email' => 'hello@nadia.test',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->owner = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();
        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();
        $this->owner = $this->owner->fresh();
    }

    /** The markup a finished item is drawn with. */
    private function struck(string $label): string
    {
        return '<span class="text-[13px] text-sub line-through">'.$label.'</span>';
    }

    /** And the markup one still to do is drawn with. */
    private function open(string $label): string
    {
        return '<span class="text-[13px] text-ink">'.$label.'</span>';
    }

    private function dashboard(): TestResponse
    {
        return $this->actingAs($this->owner->fresh())
            ->get('http://styledesk.test/dashboard')
            ->assertOk();
    }

    /** The owner's own staff row, which onboarding seeds for every business. */
    private function seedOwnerAsStaff(): Staff
    {
        return Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $this->owner->id,
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => $this->owner->email, 'role' => 'owner',
        ]);
    }

    public function test_a_business_with_nothing_on_it_has_nothing_struck_out(): void
    {
        $this->seedOwnerAsStaff();

        $this->dashboard()
            ->assertSee($this->open('Add your first service'), false)
            ->assertSee($this->open('Add your first client'), false)
            ->assertSee($this->open('Add team members'), false);
    }

    // ------------------------------------------------------------ services

    public function test_adding_a_service_strikes_that_item_out(): void
    {
        Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut and finish', 'duration_minutes' => 60,
        ]);

        $this->dashboard()
            ->assertSee($this->struck('Add your first service'), false)
            /* And only that one: an item must not tick off its neighbours. */
            ->assertSee($this->open('Add your first client'), false);
    }

    // ------------------------------------------------------------- clients

    public function test_adding_a_client_strikes_that_item_out(): void
    {
        $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amara',
        ]);

        $this->dashboard()
            ->assertSee($this->struck('Add your first client'), false)
            ->assertSee($this->open('Add your first service'), false);
    }

    // ---------------------------------------------------------------- team

    public function test_the_owners_own_staff_row_does_not_count_as_a_team(): void
    {
        $this->seedOwnerAsStaff();

        /* Onboarding seeds the owner as staff, so "are there any staff" is
           true for every business from the moment it exists — it would tick
           this off before anybody had been added. */
        $this->dashboard()->assertSee($this->open('Add team members'), false);
    }

    public function test_adding_a_colleague_strikes_the_team_item_out(): void
    {
        $this->seedOwnerAsStaff();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'sam@styledesk.test', 'role' => 'stylist',
        ]);

        $this->dashboard()->assertSee($this->struck('Add team members'), false);
    }

    public function test_a_colleague_who_has_accepted_an_invitation_counts_too(): void
    {
        $this->seedOwnerAsStaff();

        $colleague = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'sam@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $colleague->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        /* A staff row with a user_id of its own — which the old check, looking
           only for staff without one, walked straight past. */
        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $colleague->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $colleague->email, 'role' => 'stylist',
        ]);

        $this->dashboard()->assertSee($this->struck('Add team members'), false);
    }

    public function test_an_invitation_that_is_still_pending_counts(): void
    {
        $this->seedOwnerAsStaff();

        TeamInvitation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'email' => 'sam@styledesk.test',
            'first_name' => 'Sam', 'last_name' => 'Person',
            'role' => 'stylist',
            'invited_by' => $this->owner->id,
            'token_hash' => hash('sha256', 'token'),
            'status' => TeamInvitation::STATUS_PENDING,
            'expires_at' => now()->addWeek(),
        ]);

        /* The reader cannot make somebody accept, and an item that stayed
           open until they did would nag about someone else's inbox. */
        $this->dashboard()->assertSee($this->struck('Add team members'), false);
    }

    public function test_a_revoked_invitation_does_not_count(): void
    {
        $this->seedOwnerAsStaff();

        TeamInvitation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'email' => 'sam@styledesk.test',
            'first_name' => 'Sam', 'last_name' => 'Person',
            'role' => 'stylist',
            'invited_by' => $this->owner->id,
            'token_hash' => hash('sha256', 'token'),
            'status' => TeamInvitation::STATUS_REVOKED,
            'expires_at' => now()->addWeek(),
            'revoked_at' => now(),
        ]);

        $this->dashboard()->assertSee($this->open('Add team members'), false);
    }

    // ---------------------------------------------------------- one tenant

    public function test_another_businesss_records_do_not_tick_this_ones_list(): void
    {
        $this->seedOwnerAsStaff();

        $other = Tenant::create(['name' => 'Rival Salon', 'slug' => 'rival']);

        Service::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'name' => 'Theirs', 'duration_minutes' => 30,
        ]);
        $other->clients()->create([
            'client_ref' => Client::nextRef($other->getTenantKey()),
            'first_name' => 'Theirs',
        ]);

        $this->dashboard()
            ->assertSee($this->open('Add your first service'), false)
            ->assertSee($this->open('Add your first client'), false);
    }
}
