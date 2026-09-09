<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientNote;
use App\Models\ClientTag;
use App\Models\Location;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\ClientActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The client activity tracker.
 *
 * An audit trail, not a summary. The profile used to reconstruct its timeline
 * from whatever still existed, which can only ever describe the present: a
 * note somebody wrote and deleted left no trace, and "who changed this
 * number" had no answer. These rows are written when the things happen, by
 * whoever does them, and they outlive what they describe.
 */
class ClientActivityTrackerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Client $client;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio', 'slug' => 'nadia-activity', 'business_email' => 'hi@nadia.test',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->owner = User::create([
            'first_name' => 'Sarah', 'last_name' => 'Miller',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();
        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();
        $this->owner = $this->owner->fresh();

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);

        $this->client = $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amelia', 'last_name' => 'Hart',
            'mobile' => '+1 201-555-0188',
        ]);

        $this->actingAs($this->owner);
    }

    private function service(string $name = 'All-Over Color', int $minor = 27000): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'duration_minutes' => 150, 'is_active' => true,
        ]);

        $service->prices()->create(['currency_code' => 'USD', 'price_minor' => $minor]);

        return $service;
    }

    /** @return Collection<int, ClientActivity> */
    private function activity(?string $type = null)
    {
        return ClientActivity::withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->newest()
            ->get();
    }

    // ---------------------------------------------------------- bookings

    /**
     * Taking a booking writes its own history, and the balance it leaves.
     */
    public function test_taking_a_booking_records_it_and_the_money_it_owes(): void
    {
        $service = $this->service();

        $this->postJson(route('bookings.store'), [
            'client_id' => $this->client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10', 'starts_at' => '10:30',
            'services' => [$service->id],
        ])->assertCreated();

        $created = $this->activity('booking.created')->first();

        $this->assertNotNull($created);
        $this->assertSame('bookings', $created->category);
        $this->assertStringContainsString('All-Over Color', $created->description);
        $this->assertSame($this->owner->id, $created->user_id);
        $this->assertNotNull($created->booking_id);

        /* The bill is part of the history the day it is owed, not the day
           somebody notices. */
        $due = $this->activity('payment.due')->first();

        $this->assertNotNull($due);
        $this->assertSame('payments', $due->category);
    }

    /**
     * Moving a booking keeps both times. "Rescheduled" without the previous
     * one answers half the question.
     */
    public function test_moving_a_booking_records_the_old_time_and_the_new(): void
    {
        $service = $this->service();

        $panel = $this->postJson(route('bookings.store'), [
            'client_id' => $this->client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10', 'starts_at' => '10:30',
            'services' => [$service->id],
        ])->assertCreated()->json('booking');

        $this->patchJson(route('bookings.update', $panel['id']), [
            'client_id' => $this->client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-11', 'starts_at' => '13:00',
            'services' => [$service->id],
        ])->assertOk();

        $moved = $this->activity('booking.rescheduled')->first();

        $this->assertNotNull($moved);
        $this->assertStringContainsString('10 Sep 2026', $moved->changes[0]['from']);
        $this->assertStringContainsString('11 Sep 2026', $moved->changes[0]['to']);

        /* Editing the services is not a reschedule, and must not claim to be. */
        $this->patchJson(route('bookings.update', $panel['id']), [
            'client_id' => $this->client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-11', 'starts_at' => '13:00',
            'services' => [$service->id, $this->service('Blow Dry', 3500)->id],
        ])->assertOk();

        $this->assertCount(1, $this->activity('booking.rescheduled'));
    }

    // ---------------------------------------------------------- payments

    /** Part paid and settled are two different things to read on a Monday. */
    public function test_a_part_payment_and_a_settlement_are_recorded_apart(): void
    {
        $service = $this->service('Balayage', 24000);

        $panel = $this->postJson(route('bookings.store'), [
            'client_id' => $this->client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10', 'starts_at' => '10:30',
            'services' => [$service->id],
        ])->assertCreated()->json('booking');

        $this->postJson(route('bookings.pay', $panel['id']), [
            'method' => 'cash', 'amount' => '100.00', 'manual' => true,
        ])->assertCreated();

        $partial = $this->activity('payment.partial')->first();

        $this->assertNotNull($partial);
        $this->assertSame('$100.00', $partial->description);
        $this->assertNotNull($partial->payment_id);
        $this->assertArrayHasKey('due', $partial->meta);

        $this->postJson(route('bookings.pay', $panel['id']), [
            'method' => 'cash', 'amount' => $panel['total_minor'] / 100 - 100, 'manual' => true,
        ])->assertCreated();

        $this->assertNotNull($this->activity('payment.received')->first());
    }

    // ------------------------------------------------------------- notes

    /**
     * The note goes; the record of it stays. That is the whole point.
     */
    public function test_a_note_added_and_deleted_leaves_both_entries(): void
    {
        $this->post(route('clients.notes.store', $this->client), [
            'body' => 'Client prefers fragrance-free products.',
        ]);

        $note = ClientNote::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame(
            'Client prefers fragrance-free products.',
            $this->activity('note.added')->first()->description,
        );

        $this->delete(route('clients.notes.destroy', [$this->client, $note]));

        $this->assertSame(0, ClientNote::withoutGlobalScopes()->count());

        /* Both entries, in the order they happened. */
        $this->assertNotNull($this->activity('note.added')->first());
        $this->assertNotNull($this->activity('note.deleted')->first());
    }

    /** Marking a note important and rewording it are different events. */
    public function test_a_notes_flags_are_recorded_apart_from_its_wording(): void
    {
        $this->post(route('clients.notes.store', $this->client), ['body' => 'First wording.']);

        $note = ClientNote::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->patch(route('clients.notes.update', [$this->client, $note]), [
            'body' => 'First wording.',
            'is_important' => 1,
        ]);

        $this->assertNotNull($this->activity('note.marked_important')->first());
        /* The wording did not change, so nothing claims it did. */
        $this->assertCount(0, $this->activity('note.updated'));

        $this->patch(route('clients.notes.update', [$this->client, $note]), [
            'body' => 'Second wording.',
            'is_important' => 1,
            'is_private' => 1,
        ]);

        $this->assertNotNull($this->activity('note.updated')->first());
        $this->assertNotNull($this->activity('note.made_private')->first());
    }

    /**
     * A private note's body never reaches the timeline, and a reader without
     * the notes permission never reads any note body there.
     */
    public function test_note_bodies_are_withheld_from_the_timeline(): void
    {
        $this->post(route('clients.notes.store', $this->client), [
            'body' => 'A confidential observation.',
            'is_private' => 1,
        ]);

        $entry = $this->activity('note.added')->first();

        /* Stored, because the audit trail is the record. Withheld, because
           the timeline is not where a private note is read. */
        $this->assertSame('A confidential observation.', $entry->description);
        $this->assertTrue($entry->is_private);
        $this->assertNull($entry->readableDescription(true));

        /* The author can still read their own note where notes are read —
           the Notes tab. What the timeline must never do is repeat it. */
        $this->assertNull($this->activity('note.added')->first()->readableDescription(false));
    }

    // -------------------------------------------------------------- tags

    public function test_tags_record_what_went_on_and_what_came_off(): void
    {
        $high = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'label' => 'Gold tier', 'is_active' => true,
        ]);
        $booker = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'label' => 'Books early', 'is_active' => true,
        ]);

        $this->patch(route('clients.tags', $this->client), ['tags' => [$high->id, $booker->id]]);

        $this->assertSame(
            ['Books early', 'Gold tier'],
            $this->activity('tag.added')->pluck('description')->sort()->values()->all(),
        );

        /* One comes off; the other is left alone and says nothing. */
        $this->patch(route('clients.tags', $this->client), ['tags' => [$high->id]]);

        $this->assertSame(['Books early'], $this->activity('tag.removed')->pluck('description')->all());
        $this->assertCount(2, $this->activity('tag.added'));
    }

    // ------------------------------------------------------------ client

    /**
     * One entry per save, not per field: somebody who changed three things
     * did one thing.
     */
    public function test_a_profile_edit_records_one_entry_with_every_field_that_moved(): void
    {
        /* The shape the form posts: contact rows keyed by row, and the
           duplicate warning already answered — without it the save comes
           back asking rather than writing. */
        $payload = [
            'first_name' => 'Amelia',
            'last_name' => 'Hartley',
            'status' => Client::STATUS_INACTIVE,
            'confirm_duplicate' => 1,
            'phones' => [
                'r0' => ['number' => '+1 201-555-0199', 'country' => 'US', 'type' => 'mobile', 'priority' => 'primary'],
            ],
        ];

        $this->patch(route('clients.update', $this->client), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('Hartley', $this->client->fresh()->last_name);

        $entries = $this->activity('client.updated');

        /* One entry, however many fields moved: somebody who changed a name
           and a status did one thing. */
        $this->assertCount(1, $entries);

        $changes = collect($entries->first()->changes);

        $this->assertTrue($changes->contains(fn (array $row) => $row['field'] === 'Last name'
            && $row['from'] === 'Hart'
            && $row['to'] === 'Hartley'));

        $this->assertTrue($changes->contains(fn (array $row) => $row['field'] === 'Client status'));

        /* The phone moved too, and the entry says what it was — which is the
           question "who changed this number" is actually asking. */
        $this->assertTrue($changes->contains(fn (array $row) => $row['field'] === 'Mobile number'
            && $row['from'] === '+1 201-555-0188'
            && $row['to'] === '+1 201-555-0199'));

        /* And only what moved: the first name was saved unchanged and says
           nothing. */
        $this->assertFalse($changes->contains(fn (array $row) => $row['field'] === 'First name'));

        /* Saving without changing anything records nothing at all. */
        $this->patch(route('clients.update', $this->client), $payload);

        $this->assertCount(1, $this->activity('client.updated'));
    }

    // ------------------------------------------------------- the timeline

    /** Newest first, filterable, and the actor named on every row. */
    public function test_the_timeline_reads_newest_first_and_names_who_did_it(): void
    {
        ClientActivityLog::tagAdded($this->client, 'High-Value Client', 'tag', $this->owner->id);
        ClientActivityLog::tagRemoved($this->client, 'Frequent Booker', 'tag', null);

        $entries = $this->activity();

        $this->assertSame('tag.removed', $entries->first()->type);
        $this->assertSame('Sarah Miller', $entries->last()->actor());

        /* Nobody did the second one, and the timeline says so rather than
           leaving a blank that reads as missing data. */
        $this->assertTrue($entries->first()->isSystem());
        $this->assertSame(__('clients.module.workspace.activity.system'), $entries->first()->actor());

        $this->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('Tag added')
            ->assertSee('High-Value Client')
            ->assertSee(__('clients.module.workspace.activity.filters.tags'));
    }

    /**
     * An audit trail somebody can tidy is not one anybody can rely on.
     */
    public function test_activity_cannot_be_edited_or_deleted(): void
    {
        ClientActivityLog::tagAdded($this->client, 'High-Value Client', 'tag', $this->owner->id);

        $entry = $this->activity()->first();
        $writtenAt = $entry->created_at;

        $entry->description = 'Something else';
        $entry->save();
        $entry->delete();

        $fresh = ClientActivity::withoutGlobalScopes()->find($entry->id);

        $this->assertNotNull($fresh);
        $this->assertSame('High-Value Client', $fresh->description);
        $this->assertEquals($writtenAt->toIso8601String(), $fresh->created_at->toIso8601String());
    }
}
