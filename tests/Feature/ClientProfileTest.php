<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Role;
use App\Models\Service;
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
     * No invented figures.
     *
     * This client has no bookings, so all four cards say what is true rather
     * than printing a number nobody could trace back to an appointment — and
     * each says it in its own words, because "no previous visits" and
     * "nothing paid yet" are different facts about the same person.
     */
    public function test_counts_that_need_bookings_say_so_instead_of_guessing(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('No previous visits')
            ->assertSee('No upcoming appointment')
            ->assertSee('No completed visits yet')
            ->assertSee('Nothing paid yet');
    }

    /**
     * The four figures, counted from the diary and the till.
     *
     * Each counts something different and each refuses something different,
     * which is the whole reason they are four cards rather than one.
     */
    public function test_the_summary_cards_count_visits_and_money_actually_taken(): void
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Balayage', 'duration_minutes' => 90, 'is_active' => true,
        ]);

        $reference = 0;

        $make = function (string $status, string $date, int $total) use ($service, &$reference) {
            $booking = Booking::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->getTenantKey(),
                'reference' => 'BK-TEST-'.++$reference,
                'client_id' => $this->client->id,
                'date' => $date, 'starts_at' => '10:00', 'ends_at' => '11:30', 'minutes' => 90,
                'status' => $status, 'total_minor' => $total, 'currency_code' => 'USD',
            ]);

            $booking->services()->create([
                'service_id' => $service->id, 'name' => 'Balayage',
                'minutes' => 90, 'price_minor' => $total, 'sort_order' => 0,
            ]);

            return $booking;
        };

        $visited = $make('completed', '2026-08-01', 10000);
        $make('completed', '2026-07-01', 5000);
        /* None of these is a visit: one has not happened, one was called
           off, and one is somebody who did not come. */
        $make('confirmed', now()->addDays(7)->toDateString(), 8000);
        $make('cancelled', '2026-06-01', 9000);
        $make('no-show', '2026-05-01', 9000);

        /* Money actually taken, less what went back out. A booking's total
           is what somebody was billed, which is not what they paid. */
        $visited->payments()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'method' => 'cash', 'status' => 'paid', 'amount_minor' => 10000,
            'currency_code' => 'USD', 'paid_at' => now(),
        ]);
        $visited->payments()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'method' => 'cash', 'status' => 'refunded', 'amount_minor' => -2500,
            'currency_code' => 'USD', 'paid_at' => now(),
        ]);

        $summary = $this->actingAs($this->owner)
            ->getJson(route('clients.visit-summary', $this->client))
            ->assertOk()
            ->json('summary');

        /* The last time they were actually in, and what for. */
        $this->assertSame('1 Aug 2026', $summary['last_visit']['value']);
        $this->assertSame('Balayage', $summary['last_visit']['detail']);

        /* The next thing in the diary, with the time on it. */
        $this->assertStringContainsString('10:00', $summary['next_appointment']['value']);

        /* Two completed. The future, cancelled and no-show ones are not
           visits however many rows they make. */
        $this->assertSame('2', $summary['total_visits']['value']);

        /* $100 taken, $25 given back. Not the $410 they were billed. */
        $this->assertSame('$75.00', $summary['lifetime_spend']['value']);
    }

    /**
     * Two lists, never merged.
     *
     * The history is arithmetic over the diary. A favourite is a statement
     * somebody made at the desk — and a service booked six times does not
     * become one on its own, because a favourite nobody chose is one nobody
     * can be asked about.
     */
    public function test_service_history_is_grouped_and_favourites_stay_separate(): void
    {
        $colour = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Balayage', 'duration_minutes' => 180, 'is_active' => true,
        ]);
        $cut = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut & Finish', 'duration_minutes' => 45, 'is_active' => true,
        ]);

        $reference = 0;

        $book = function (Service $service, string $status, string $date) use (&$reference) {
            $booking = Booking::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->getTenantKey(),
                'reference' => 'BK-SVC-'.++$reference,
                'client_id' => $this->client->id,
                'date' => $date, 'starts_at' => '10:00', 'ends_at' => '11:00', 'minutes' => 60,
                'status' => $status, 'total_minor' => 5000, 'currency_code' => 'USD',
            ]);

            $booking->services()->create([
                'service_id' => $service->id, 'name' => $service->name,
                'minutes' => 60, 'price_minor' => 5000, 'sort_order' => 0,
            ]);
        };

        $book($colour, 'completed', '2026-07-01');
        $book($colour, 'completed', '2026-08-01');
        $book($cut, 'completed', '2026-06-01');
        /* Neither of these happened, so neither counts. */
        $book($cut, 'cancelled', '2026-05-01');
        $book($cut, 'draft', '2026-04-01');

        $tab = $this->actingAs($this->owner)
            ->getJson(route('clients.services', $this->client))
            ->assertOk()
            ->json();

        /* One row per service, most booked first — not one row per
           appointment. */
        $this->assertSame(['Balayage', 'Cut & Finish'], collect($tab['history'])->pluck('name')->all());
        $this->assertSame(2, $tab['history'][0]['visits']);
        $this->assertSame('1 Aug 2026', $tab['history'][0]['last_booked']);
        $this->assertSame(1, $tab['history'][1]['visits']);

        /* Booked twice, and still not a favourite. */
        $this->assertFalse($tab['history'][0]['is_favorite']);
        $this->assertSame([], $tab['favorites']);

        /* Somebody says so, and now it is one. */
        $after = $this->actingAs($this->owner)
            ->postJson(route('clients.favorite-services.store', $this->client), ['services' => [$colour->id]])
            ->assertOk()
            ->json();

        $this->assertSame(['Balayage'], collect($after['favorites'])->pluck('name')->all());
        $this->assertTrue(collect($after['history'])->firstWhere('id', $colour->id)['is_favorite']);

        /* Said twice is the same statement, not a second one. */
        $this->actingAs($this->owner)
            ->postJson(route('clients.favorite-services.store', $this->client), ['services' => [$colour->id]])
            ->assertOk();

        $this->assertSame(1, $this->client->favoriteServices()->count());

        /* And taken off by hand as well. */
        $removed = $this->actingAs($this->owner)
            ->deleteJson(route('clients.favorite-services.destroy', [$this->client, $colour]))
            ->assertOk()
            ->json();

        $this->assertSame([], $removed['favorites']);
    }

    /**
     * A favourite can be marked for a service this client has never booked —
     * "this is what I always have", said before there is any history to read
     * it from.
     */
    public function test_a_favourite_can_be_marked_without_any_booking_history(): void
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Balayage', 'duration_minutes' => 180, 'is_active' => true,
        ]);

        $tab = $this->actingAs($this->owner)
            ->postJson(route('clients.favorite-services.store', $this->client), ['services' => [$service->id]])
            ->assertOk()
            ->json();

        $this->assertSame(['Balayage'], collect($tab['favorites'])->pluck('name')->all());
        $this->assertSame([], $tab['history']);
    }

    /**
     * Create Booking from a profile opens the booking screen on that client,
     * carrying only the id — so a tab left open since Tuesday cannot bring
     * Tuesday's phone number into today's booking.
     */
    public function test_create_booking_from_the_profile_preselects_the_client(): void
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Balayage', 'duration_minutes' => 180, 'is_active' => true,
        ]);

        $this->client->favoriteServices()->attach($service->id, [
            'tenant_id' => $this->tenant->getTenantKey(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee(route('bookings.create', ['client' => $this->client->id]), false);

        /* Either spelling of the parameter reaches the same client. */
        foreach (['client', 'client_id'] as $parameter) {
            $this->actingAs($this->owner)
                ->get(route('bookings.create').'?'.$parameter.'='.$this->client->id)
                ->assertOk()
                ->assertSee($this->client->displayName())
                ->assertSee('"favorite_service_ids":['.$service->id.']', false);
        }
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
     * they have been a client, then the two facts that are looked up and
     * have nowhere else to be: the birthday, and which branch they are seen
     * at. The reference is not among them — it belongs beside the name, and
     * a test that only counted chips would not notice it drifting down here.
     *
     * The phone and the email are deliberately absent. Both live in the
     * Contact card a column away, under labels and beside the copy buttons
     * that make them usable; repeated here they only crowded the header.
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
            '#Active.*?Client since.*?18 Aug 1987.*?Downtown#s',
            $response->getContent(),
        );

        /* Not in the header, and still on the page: the Contact card is
           where a number is read from, because that is where it is labelled
           and where the copy button is. */
        $header = substr($response->getContent(), 0, strpos($response->getContent(), '</header>'));

        $this->assertStringNotContainsString('+1 202-555-1043', $header);
        $this->assertStringNotContainsString('amelia@example.test', $header);
        $response->assertSee('+1 202-555-1043');
        $response->assertSee('amelia@example.test');

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

    /**
     * The edit screen opens.
     *
     * The route and the view both existed; the controller method did not, so
     * every Edit link 500'd with "Call to undefined method".
     */
    public function test_the_edit_screen_opens_with_the_client_filled_in(): void
    {
        $client = $this->client;

        $this->actingAs($this->owner)
            ->get(route('clients.edit', $client))
            ->assertOk()
            ->assertSee(__('clients.module.edit_title'))
            /* Filled in from the record, not a blank create form — which is
               what an array union would have produced, since formState
               returns a null client for the create screen to use. */
            ->assertViewHas('client', fn ($viewClient) => $viewClient !== null
                && $viewClient->is($client))
            ->assertSee($client->first_name, false);
    }

    /**
     * Another business's client cannot be opened for editing.
     *
     * Every system role holds clients.edit — the guard that matters here is
     * ownership, not permission, and a form that opened on somebody else's
     * record would be one save away from rewriting it.
     */
    public function test_the_edit_screen_refuses_another_businesss_client(): void
    {
        $other = Tenant::create(['name' => 'Other Salon', 'slug' => 'other-edit']);

        $theirs = $other->clients()->create([
            'client_ref' => Client::nextRef($other->getTenantKey()),
            'first_name' => 'Someone', 'last_name' => 'Else',
        ]);

        $this->actingAs($this->owner)
            ->get(route('clients.edit', $theirs))
            ->assertNotFound();
    }
}
