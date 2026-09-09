<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The client's number and address, wherever they were written.
 *
 * `clients.mobile` and `clients.email` are a cache of the primary contact;
 * the record is `client_phones` and `client_emails`. Anything that filled the
 * columns without going through the sync methods produced a client whose
 * number showed in the listing — which reads the cache — and nowhere on their
 * profile, which reads the record.
 *
 * These guard the repair and the shape it repaired to.
 */
class ClientContactBackfillTest extends TestCase
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

    /** A client written the way the old seeder wrote one: cache, no record. */
    private function cachedOnly(): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Olivia', 'last_name' => 'Bennett', 'status' => 'active',
            'email' => 'olivia@example.test',
            'mobile' => '+1 201 555 5845',
        ]);
    }

    /** Run the repair the migration performs. */
    private function backfill(): void
    {
        require_once database_path('migrations/2026_09_09_105456_backfill_client_contact_rows.php');

        (include database_path('migrations/2026_09_09_105456_backfill_client_contact_rows.php'))->up();
    }

    public function test_a_client_with_only_cached_contacts_has_none_to_show(): void
    {
        $client = $this->cachedOnly();

        /* The bug as it was: the listing has an answer and the profile does
           not, from the same client. */
        $this->assertSame('+1 201 555 5845', $client->mobile);
        $this->assertNull($client->primaryPhone());
        $this->assertNull($client->primaryEmail());
    }

    public function test_the_backfill_writes_the_record_the_cache_implied(): void
    {
        $client = $this->cachedOnly();

        $this->backfill();

        $client = $client->fresh(['phones', 'emails']);

        $this->assertSame('+1 201 555 5845', $client->primaryPhone()?->number);
        $this->assertSame('olivia@example.test', $client->primaryEmail()?->email);
        /* One primary, always — the shape the sync methods keep. */
        $this->assertTrue($client->primaryPhone()->is_primary);
        $this->assertTrue($client->primaryEmail()->is_primary);
    }

    public function test_the_profile_shows_them_afterwards(): void
    {
        $client = $this->cachedOnly();

        $this->backfill();

        $this->actingAs($this->owner())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('+1 201 555 5845')
            ->assertSee('olivia@example.test');
    }

    /* A client who already has contacts has a record, and the cache is
       downstream of it. Overwriting from a cache would be the tail wagging
       the dog. */
    public function test_a_client_who_already_has_a_record_is_left_alone(): void
    {
        $client = $this->cachedOnly();

        $client->syncPhones([
            ['number' => '+1 201 555 1111', 'type' => 'mobile', 'is_primary' => true],
            ['number' => '+1 201 555 2222', 'type' => 'work', 'is_primary' => false],
        ]);

        $this->backfill();

        $client = $client->fresh('phones');

        $this->assertCount(2, $client->phones);
        $this->assertSame('+1 201 555 1111', $client->primaryPhone()->number);
    }

    public function test_running_the_backfill_twice_does_not_duplicate_anything(): void
    {
        $client = $this->cachedOnly();

        $this->backfill();
        $this->backfill();

        $this->assertSame(1, DB::table('client_phones')->where('client_id', $client->id)->count());
        $this->assertSame(1, DB::table('client_emails')->where('client_id', $client->id)->count());
    }

    public function test_a_client_with_nothing_recorded_gains_nothing(): void
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Walk', 'last_name' => 'In', 'status' => 'active',
        ]);

        $this->backfill();

        $this->assertSame(0, DB::table('client_phones')->where('client_id', $client->id)->count());
        $this->assertSame(0, DB::table('client_emails')->where('client_id', $client->id)->count());
    }
}
