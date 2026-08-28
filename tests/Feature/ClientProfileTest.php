<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client profile — the workspace, not the form.
 *
 * What is worth holding here is what the page promises: the record's own
 * facts are shown, notes are governed by their own permissions, and nothing
 * about bookings is asserted while there are no bookings to read.
 */
class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Client $client;

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

        $this->client = $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amelia',
            'last_name' => 'Hart',
        ]);
    }

    private function member(string $role): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $role.'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => $role,
        ]);

        return $user->fresh();
    }

    public function test_the_profile_shows_the_client_and_its_workspace(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('Amelia Hart')
            ->assertSee($this->client->client_ref)
            ->assertSee('Activity')
            ->assertSee('Bookings')
            ->assertSee('Notes')
            ->assertSee('Files');
    }

    /**
     * The page is a page, not a fragment.
     *
     * Written after the profile was reported as missing its footer: the
     * layout renders one for every screen, and the only way this page could
     * lose it is by breaking out of the shell — which a test can notice
     * before a reader does.
     */
    public function test_the_profile_renders_inside_the_application_shell(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('styledesk_shell', false)
            ->assertSee('All rights reserved')
            ->assertSee('</footer>', false);
    }

    /**
     * The Clients icon stays lit on a client's own page.
     *
     * The nav marks the section, not the one screen it starts on: an icon
     * that goes dark the moment a record is opened leaves the reader without
     * an answer to "where am I".
     */
    public function test_the_clients_icon_is_marked_active_on_a_profile(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*class="sd-navicon[^"]*is-active[^"]*"[^>]*data-tip="Clients"#',
            $response->getContent(),
        );
    }

    /**
     * Behavioural tags are set as a whole set.
     *
     * The modal shows every tag with a tick, so what it posts is the answer
     * to "which of these" — applying that as a replacement is the only way
     * the screen and the record cannot disagree.
     */
    public function test_behavioural_tags_are_replaced_by_what_was_ticked(): void
    {
        $this->client->syncBehavioralTags(['frequent_booker', 'no_show_risk']);

        $this->actingAs($this->owner)
            ->patch(route('clients.behavioral', $this->client), ['tags' => ['high_value_client']])
            ->assertRedirect();

        $this->assertSame(['high_value_client'], $this->client->behavioralTags()->pluck('key')->all());
    }

    /** Unticking everything leaves the client with none. */
    public function test_behavioural_tags_can_all_be_removed(): void
    {
        $this->client->syncBehavioralTags(['frequent_booker']);

        $this->actingAs($this->owner)
            ->patch(route('clients.behavioral', $this->client), [])
            ->assertRedirect();

        $this->assertTrue($this->client->behavioralTags()->isEmpty());
    }

    /**
     * A tag the business has switched off cannot be put on a client.
     *
     * Validating against the catalogue alone would let a stale form assign
     * one this business decided it does not use.
     */
    public function test_a_tag_the_business_switched_off_is_refused(): void
    {
        $this->tenant->behavioralTags()
            ->where('tag_key', 'no_show_risk')
            ->update(['is_active' => false]);

        $this->actingAs($this->owner)
            ->patch(route('clients.behavioral', $this->client), ['tags' => ['no_show_risk']])
            ->assertSessionHasErrors('tags.0');

        $this->assertTrue($this->client->behavioralTags()->isEmpty());
    }

    /**
     * No invented figures.    /**
     * No invented figures.
     *
     * There are no bookings, so the two counts say so rather than printing a
     * number nobody could trace back to an appointment.
     */
    public function test_counts_that_need_bookings_say_so_instead_of_guessing(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('Counts appear once bookings arrive.')
            ->assertSee('No visits yet');
    }

    public function test_a_note_can_be_added_and_appears_on_the_profile(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => 'Books every four weeks.',
            ])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('Books every four weeks.')
            // Every note is an event on the timeline as well as a row in the tab.
            ->assertSee('Note added');
    }

    /**
     * An important note is shown on the profile itself, not only in its tab:
     * a warning nobody sees before the appointment is a warning that did not
     * happen.
     */
    public function test_an_important_note_is_raised_on_the_profile(): void
    {
        $this->actingAs($this->owner)->post(route('clients.notes.store', $this->client), [
            'body' => 'Sensitive scalp — avoid high heat on roots.',
            'is_important' => 1,
        ]);

        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('Important')
            ->assertSee('Sensitive scalp — avoid high heat on roots.');
    }

    /**
     * Notes are their own permission: seeing a client is not the same as
     * reading what colleagues wrote about them.
     */
    public function test_a_role_without_the_notes_permission_is_told_rather_than_shown(): void
    {
        $this->actingAs($this->owner)->post(route('clients.notes.store', $this->client), [
            'body' => 'Sensitive scalp — avoid high heat on roots.',
            'is_important' => 1,
        ]);

        $user = $this->member('front-desk');

        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->firstOrFail()
            ->permissions()
            ->whereIn('permission', ['clients.view_notes', 'clients.add_notes'])
            ->delete();

        $this->actingAs($user->fresh())
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('Amelia Hart')
            ->assertDontSee('Sensitive scalp — avoid high heat on roots.');
    }

    public function test_a_note_needs_a_body(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), ['body' => ' '])
            ->assertSessionHasErrors('body');
    }

    public function test_the_status_action_moves_a_client_between_active_and_inactive(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('clients.status', $this->client), ['status' => Client::STATUS_INACTIVE])
            ->assertRedirect();

        $this->assertSame(Client::STATUS_INACTIVE, $this->client->fresh()->status);
    }

    /**
     * Archiving has its own action, and this one must not be a way around it:
     * archive carries consequences — the client leaves booking search — that
     * a status dropdown should not be able to trigger.
     */
    public function test_the_status_action_refuses_to_archive(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('clients.status', $this->client), ['status' => Client::STATUS_ARCHIVED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Client::STATUS_ACTIVE, $this->client->fresh()->status);
    }

    /**
     * The header's three actions, on one row, in one order.
     *
     * The order is the promise: leaving, the primary action, then everything
     * else behind the overflow menu. Asserted as a sequence rather than as
     * three separate assertSee calls, because "all three are somewhere on the
     * page" is exactly what a wrapped or reordered row would also satisfy.
     */
    public function test_the_header_actions_share_one_row_in_order(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '#<div class="styledesk_actionrow[^"]*">.*?Back to clients.*?Create booking.*?More actions#s',
            $response->getContent(),
        );
    }

    /**
     * The header's parts, in the order the small layout reads them.
     *
     * Actions, photo, name, chips. That is the source order because it is
     * the order a phone shows, and the 1024px rule reorders it back to
     * photo-name-actions with `order` rather than with a second copy of the
     * markup. The sequence is what a test can hold; which of the two layouts
     * a viewport gets is the stylesheet's business.
     */
    public function test_the_header_is_ordered_for_the_small_layout(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '#<header class="styledesk_identity[^"]*">'
            .'.*?<div class="styledesk_actionrow styledesk_identity__actions">'
            .'.*?styledesk_identity__avatar'
            .'.*?<h1[^>]*>Amelia Hart</h1>'
            .'.*?styledesk_metachip--since#s',
            $response->getContent(),
        );
    }

    /**
     * The header carries the hooks both layouts are built from.
     *
     * The layout itself is two media queries deep, where no request can
     * reach it; what a request can hold is that every element the
     * stylesheet reorders is present and named. A renamed or dropped hook
     * leaves the header stacked in source order at every width — which
     * looks deliberate enough to ship unnoticed.
     */
    public function test_the_header_carries_its_layout_hooks(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('class="styledesk_identity ', false)
            ->assertSee('styledesk_identity__actions', false)
            ->assertSee('styledesk_identity__avatar', false)
            ->assertSee('styledesk_identity__body', false);
    }

    /**
     * Back keeps a word beside its arrow, and the word is the part that goes
     * when the cluster runs out of width.
     *
     * The narrow state is a stylesheet's job, so what a request can hold is
     * that the label is rendered at all and carries the class the media
     * query hides.
     */
    public function test_back_is_labelled_and_its_label_is_the_part_that_collapses(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('styledesk_action--shrinklabel', false)
            ->assertSee('<span class="styledesk_action__label">Back</span>', false);
    }

    /**
     * The identity chips, in the order the header promises.
     *
     * Status first because it changes how the rest is read, then how long
     * they have been a client, then the facts that are looked up: birthday,
     * phone, email, where they are seen. The reference is not among them —
     * it belongs beside the name, and a test that only counted chips would
     * not notice it drifting down here.
     */
    public function test_the_identity_chips_keep_their_order(): void
    {
        $location = $this->tenant->locations()->create([
            'name' => 'Downtown', 'address_line1' => '1 High Street', 'city' => 'Leeds',
            'postal_code' => 'LS1 1AA', 'country' => 'GB', 'timezone' => 'Europe/London',
        ]);

        $this->client->forceFill([
            'date_of_birth' => '1987-08-18',
            'preferred_location_id' => $location->id,
        ])->save();

        $tenantId = $this->tenant->getTenantKey();
        $this->client->phones()->create([
            'tenant_id' => $tenantId, 'number' => '+1 202-555-1043', 'is_primary' => true,
        ]);
        $this->client->emails()->create([
            'tenant_id' => $tenantId, 'email' => 'amelia@example.test', 'is_primary' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '#Active.*?Client since.*?18 Aug 1987.*?\+1 202-555-1043.*?amelia@example\.test.*?Downtown#s',
            $response->getContent(),
        );

        /* The reference sits with the name, above every chip. */
        $this->assertMatchesRegularExpression(
            '#'.preg_quote($this->client->client_ref, '#').'.*?styledesk_metachip--since#s',
            $response->getContent(),
        );
    }

    /** A client belonging to another business is not found, not forbidden. */
    public function test_another_business_client_is_not_reachable(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $theirs = $other->clients()->create([
            'client_ref' => Client::nextRef($other->getTenantKey()),
            'first_name' => 'Someone',
        ]);

        $this->actingAs($this->owner)
            ->get(route('clients.show', $theirs))
            ->assertNotFound();
    }
}
