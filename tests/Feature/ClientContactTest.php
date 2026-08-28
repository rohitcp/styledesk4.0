<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSettings;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A client's phone numbers and email addresses.
 *
 * The rules worth holding are the ones a form cannot enforce on its own:
 * exactly one primary at a time, the primary reaching every screen that shows
 * "the client's number", and the same number twice being refused rather than
 * quietly collapsed into one.
 */
class ClientContactTest extends TestCase
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

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Amelia',
            'last_name' => 'Hart',
            'status' => Client::STATUS_ACTIVE,
            'confirm_duplicate' => 1,
            'phones' => [
                'r0' => ['number' => '+1 202-555-1043', 'country' => 'US', 'type' => 'mobile', 'priority' => 'primary'],
                'r1' => ['number' => '+1 202-555-2298', 'country' => 'US', 'type' => 'work', 'priority' => 'secondary'],
            ],
            'emails' => [
                'r0' => ['email' => 'amelia.hart@example.com', 'type' => 'personal', 'priority' => 'primary'],
                'r1' => ['email' => 'amelia@company.com', 'type' => 'work', 'priority' => 'secondary'],
            ],
        ], $overrides);
    }

    public function test_a_client_keeps_every_number_and_address_given(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload())
            ->assertRedirect();

        $client = Client::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(['+1 202-555-1043', '+1 202-555-2298'], $client->phones->pluck('number')->all());
        $this->assertSame(['mobile', 'work'], $client->phones->pluck('type')->all());
        $this->assertSame(['amelia.hart@example.com', 'amelia@company.com'], $client->emails->pluck('email')->all());
    }

    /**
     * The primary is copied onto the client, so every screen that shows one
     * number shows the same one.
     */
    public function test_the_primary_reaches_the_client_record(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload([
                'phones' => [
                    'r0' => ['number' => '+1 202-555-1043', 'country' => 'US', 'type' => 'mobile', 'priority' => 'secondary'],
                    'r1' => ['number' => '+1 202-555-2298', 'country' => 'US', 'type' => 'work', 'priority' => 'primary'],
                ],
                'emails' => [
                    'r0' => ['email' => 'amelia.hart@example.com', 'type' => 'personal', 'priority' => 'secondary'],
                    'r1' => ['email' => 'amelia@company.com', 'type' => 'work', 'priority' => 'primary'],
                ],
            ]))
            ->assertRedirect();

        $client = Client::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('+1 202-555-2298', $client->mobile);
        $this->assertSame('amelia@company.com', $client->email);
        $this->assertSame('+1 202-555-2298', $client->primaryPhone()->number);
    }

    /** Exactly one, whatever was ticked. */
    public function test_marking_a_new_primary_demotes_the_old_one(): void
    {
        $this->actingAs($this->owner)->post(route('clients.store'), $this->payload());

        $client = Client::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->owner)
            ->patch(route('clients.update', $client), $this->payload([
                'phones' => [
                    'r0' => ['number' => '+1 202-555-1043', 'country' => 'US', 'type' => 'mobile', 'priority' => 'secondary'],
                    'r1' => ['number' => '+1 202-555-2298', 'country' => 'US', 'type' => 'work', 'priority' => 'primary'],
                ],
            ]))
            ->assertRedirect();

        $client = $client->fresh();

        $this->assertSame(1, $client->phones->where('is_primary', true)->count());
        $this->assertSame('+1 202-555-2298', $client->phones->firstWhere('is_primary', true)->number);
    }

    /**
     * Nothing marked still leaves a primary.
     *
     * A client with three numbers and no primary would leave every screen
     * downstream picking one for itself.
     */
    public function test_a_client_always_has_a_primary(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload([
                'phones' => [
                    'r0' => ['number' => '+1 202-555-1043', 'country' => 'US', 'type' => 'mobile', 'priority' => 'secondary'],
                    'r1' => ['number' => '+1 202-555-2298', 'country' => 'US', 'type' => 'work', 'priority' => 'secondary'],
                ],
            ]))
            ->assertRedirect();

        $client = Client::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(1, $client->phones->where('is_primary', true)->count());
        $this->assertSame('+1 202-555-1043', $client->mobile);
    }

    /**
     * The same number twice is refused, not silently merged.
     *
     * The person typing meant to record two contacts; keeping one without
     * saying so would leave them believing both were saved.
     */
    public function test_the_same_number_twice_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload([
                'phones' => [
                    'r0' => ['number' => '+1 202-555-1043', 'country' => 'US', 'type' => 'mobile', 'priority' => 'primary'],
                    'r1' => ['number' => '12025551043', 'country' => 'US', 'type' => 'work', 'priority' => 'secondary'],
                ],
            ]))
            ->assertSessionHasErrors('phones.r1');

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }

    public function test_the_same_address_twice_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload([
                'emails' => [
                    'r0' => ['email' => 'amelia.hart@example.com', 'type' => 'personal', 'priority' => 'primary'],
                    'r1' => ['email' => 'Amelia.Hart@example.com', 'type' => 'work', 'priority' => 'secondary'],
                ],
            ]))
            ->assertSessionHasErrors('emails.r1');
    }

    /**
     * A number on another client is a warning, never a block: two people can
     * share a phone, and §7 asks for a warning rather than a refusal.
     */
    public function test_a_number_already_on_another_client_warns(): void
    {
        $this->actingAs($this->owner)->post(route('clients.store'), $this->payload());

        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload([
                'first_name' => 'Different',
                'confirm_duplicate' => null,
                'emails' => [],
                'phones' => ['r0' => ['number' => '+1 202-555-2298', 'country' => 'US', 'type' => 'home', 'priority' => 'primary']],
            ]))
            ->assertRedirect()
            ->assertSessionHas('duplicates');

        $this->assertSame(1, Client::withoutGlobalScopes()->count());
    }

    /**
     * Every recorded address finds its client, not only the primary one.
     *
     * Asked of the grid's endpoint rather than the page: the rows live there
     * now, and this is a test about which clients a search returns.
     */
    public function test_search_finds_a_client_by_a_secondary_address(): void
    {
        $this->actingAs($this->owner)->post(route('clients.store'), $this->payload());

        $this->actingAs($this->owner)
            ->getJson(route('clients.data', ['search' => 'amelia@company.com']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Amelia Hart');
    }

    /**
     * A required contact field is required of the rows.
     *
     * The client's own column is written from them, so checking it would pass
     * an empty form on a new client.
     */
    public function test_a_required_number_is_asked_for(): void
    {
        $settings = ClientSettings::forTenant($this->tenant);
        $fields = $settings->fields;
        $fields['mobile']['required'] = true;
        $settings->forceFill(['fields' => $fields])->save();

        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload(['phones' => []]))
            ->assertSessionHasErrors('phones');
    }
}
