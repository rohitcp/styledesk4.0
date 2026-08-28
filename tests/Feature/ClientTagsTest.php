<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientTag;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client tags: what a business starts with, and what it may do to them.
 *
 * The rule worth holding is the last one — a tag a client carries cannot be
 * deleted, because deleting it would rewrite that client's record.
 */
class ClientTagsTest extends TestCase
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

    /**
     * A new business starts with the tags every salon would have written out
     * for itself, and only the ones it will use on day one are switched on.
     */
    public function test_a_new_business_starts_with_the_default_tags(): void
    {
        // TenantCreated fired in setUp, so they are already there.
        $tags = $this->tenant->clientTags()->inOrder()->get();

        $this->assertSame(count(config('clients.seed_tags')), $tags->count());
        $this->assertTrue($tags->firstWhere('label', 'VIP')->is_active);
        $this->assertFalse($tags->firstWhere('label', 'No-show risk')->is_active);

        // Position follows the catalogue rather than the alphabet.
        $this->assertSame('VIP', $tags->first()->label);
    }

    /** Seeding again adds what is missing and leaves edits alone. */
    public function test_seeding_again_does_not_overwrite_an_edited_tag(): void
    {
        $vip = $this->tenant->clientTags()->where('label', 'VIP')->firstOrFail();
        $vip->forceFill(['color' => 'rose', 'is_active' => false])->save();

        ClientTag::seedDefaultsFor($this->tenant);

        $this->assertSame('rose', $vip->fresh()->color);
        $this->assertFalse($vip->fresh()->is_active);
        $this->assertSame(count(config('clients.seed_tags')), $this->tenant->clientTags()->count());
    }

    public function test_a_tag_can_be_renamed_and_recoloured(): void
    {
        $tag = $this->tenant->clientTags()->where('label', 'Bridal')->firstOrFail();

        $this->actingAs($this->owner)
            ->patch(route('settings.clients.tags.update', $tag), ['label' => 'Wedding party', 'color' => 'plum'])
            ->assertRedirect();

        $this->assertSame('Wedding party', $tag->fresh()->label);
        $this->assertSame('plum', $tag->fresh()->color);
    }

    public function test_tags_can_be_reordered(): void
    {
        $tags = $this->tenant->clientTags()->inOrder()->take(3)->pluck('id');

        $this->actingAs($this->owner)
            ->patch(route('settings.clients.tags.reorder'), ['order' => $tags->reverse()->values()->all()])
            ->assertRedirect();

        $this->assertSame(
            $tags->reverse()->values()->all(),
            $this->tenant->clientTags()->inOrder()->take(3)->pluck('id')->all(),
        );
    }

    public function test_an_unused_tag_can_be_deleted(): void
    {
        $tag = $this->tenant->clientTags()->where('label', 'Corporate')->firstOrFail();

        $this->actingAs($this->owner)
            ->delete(route('settings.clients.tags.destroy', $tag))
            ->assertRedirect();

        $this->assertNull($tag->fresh());
    }

    /**
     * A tag a client carries is part of that client's record.
     *
     * Refused rather than cascaded: deleting it would quietly rewrite every
     * client holding it, and deactivating does what the reader meant.
     */
    public function test_a_tag_in_use_cannot_be_deleted(): void
    {
        $tag = $this->tenant->clientTags()->where('label', 'VIP')->firstOrFail();

        $client = $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amara',
        ]);
        $client->tags()->attach($tag->id);

        $this->actingAs($this->owner)
            ->delete(route('settings.clients.tags.destroy', $tag))
            ->assertRedirect();

        $this->assertNotNull($tag->fresh());
        $this->assertSame(1, $client->tags()->count());
    }

    public function test_a_tag_can_be_deactivated_instead(): void
    {
        $tag = $this->tenant->clientTags()->where('label', 'VIP')->firstOrFail();

        $this->actingAs($this->owner)
            ->patch(route('settings.clients.tags.toggle', $tag))
            ->assertRedirect();

        $this->assertFalse($tag->fresh()->is_active);
    }

    /** Another business's tag is not reachable. */
    public function test_another_businesss_tag_is_out_of_reach(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $theirs = $other->clientTags()->where('label', 'VIP')->firstOrFail();

        $this->actingAs($this->owner)
            ->delete(route('settings.clients.tags.destroy', $theirs))
            ->assertNotFound();
    }

    /**
     * Assigning from the profile: the modal posts the whole set, so what
     * arrives is the answer to "which tags apply", not a list of changes.
     */
    public function test_client_tags_are_assigned_as_a_whole_set(): void
    {
        $client = $this->client();
        $vip = $this->tag('VIP');
        $referral = $this->tag('Referral');

        $client->tags()->attach($vip->id);

        $this->actingAs($this->owner)
            ->patch(route('clients.tags', $client), ['tags' => [$referral->id]])
            ->assertRedirect();

        // VIP was not in the set, so it came off; Referral was, so it went on.
        $this->assertSame([$referral->id], $client->fresh()->tags->pluck('id')->all());
    }

    /** Posting no tags at all takes every tag off the client. */
    public function test_posting_an_empty_set_clears_a_clients_tags(): void
    {
        $client = $this->client();
        $client->tags()->attach($this->tag('VIP')->id);

        $this->actingAs($this->owner)
            ->patch(route('clients.tags', $client), [])
            ->assertRedirect();

        $this->assertCount(0, $client->fresh()->tags);
    }

    /**
     * A tag the business has switched off stays on the clients already
     * carrying it — the modal never offered it, so its absence from the post
     * is not a removal.
     */
    public function test_a_deactivated_tag_already_on_a_client_survives_a_save(): void
    {
        $client = $this->client();
        $retired = $this->tag('VIP');
        $keep = $this->tag('Referral');

        $client->tags()->attach([$retired->id, $keep->id]);
        $retired->forceFill(['is_active' => false])->save();

        $this->actingAs($this->owner)
            ->patch(route('clients.tags', $client), ['tags' => [$keep->id]])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$retired->id, $keep->id],
            $client->fresh()->tags->pluck('id')->all(),
        );
    }

    /** A deactivated tag cannot be newly put on a client. */
    public function test_a_deactivated_tag_cannot_be_assigned(): void
    {
        $client = $this->client();
        $tag = $this->tag('VIP');
        $tag->forceFill(['is_active' => false])->save();

        $this->actingAs($this->owner)
            ->patch(route('clients.tags', $client), ['tags' => [$tag->id]])
            ->assertSessionHasErrors('tags.0');

        $this->assertCount(0, $client->fresh()->tags);
    }

    /** Another business's tag is not something to put on our client. */
    public function test_another_businesss_tag_cannot_be_assigned(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other-assign', 'business_email' => 'hi@other-assign.test',
        ]);
        $theirs = $other->clientTags()->where('label', 'VIP')->firstOrFail();

        $client = $this->client();

        $this->actingAs($this->owner)
            ->patch(route('clients.tags', $client), ['tags' => [$theirs->id]])
            ->assertSessionHasErrors('tags.0');

        $this->assertCount(0, $client->fresh()->tags);
    }

    /**
     * The modal saves without a page load, so the endpoint answers with the
     * set it stored — the card is redrawn from the record, not from what the
     * browser hoped it had sent.
     */
    public function test_the_endpoint_answers_json_with_the_stored_set(): void
    {
        $client = $this->client();
        $vip = $this->tag('VIP');

        $response = $this->actingAs($this->owner)
            ->patchJson(route('clients.tags', $client), ['tags' => [$vip->id]])
            ->assertOk();

        $response->assertJsonPath('tags.0.label', 'VIP');
        $response->assertJsonPath('message', __('clients.module.workspace.tags.updated'));
        $this->assertNotEmpty($response->json('tags.0.hex'));
    }

    /**
     * Assignment is gated on clients.edit, so a role that may read a client
     * but not change one cannot put a tag on them either.
     */
    public function test_a_role_without_clients_edit_cannot_assign_tags(): void
    {
        $client = $this->client();
        $tag = $this->tag('VIP');

        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'stylist@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => 'service-provider',
        ]);

        // Off the role, which is where the permission lives — not off the person.
        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'service-provider')
            ->firstOrFail()
            ->permissions()->where('permission', 'clients.edit')->delete();

        $this->actingAs($user->fresh())
            ->patch(route('clients.tags', $client), ['tags' => [$tag->id]])
            ->assertForbidden();

        $this->assertCount(0, $client->fresh()->tags);
    }

    private function client(): Client
    {
        return $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amara',
        ]);
    }

    private function tag(string $label): ClientTag
    {
        return $this->tenant->clientTags()->where('label', $label)->firstOrFail();
    }
}
