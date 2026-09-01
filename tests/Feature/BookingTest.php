<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\BookingLead;
use App\Models\BookingReview;
use App\Models\Client;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Taking an appointment.
 *
 * The screen is one page rather than a wizard, and these tests are about what
 * it writes: the length and the price of a booking are the sum of its
 * services, and both are copied onto the booking rather than read back
 * through a price list that will be edited later.
 */
class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
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

    private function service(string $name, int $minutes, int $minor): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name,
            'duration_minutes' => $minutes,
            'is_active' => true,
        ]);

        $service->prices()->create(['currency_code' => 'USD', 'price_minor' => $minor]);

        return $service;
    }

    private function client(): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker',
            'mobile' => '+1 305 555 0100', 'email' => 'mia@acme.test',
            'status' => 'active',
        ]);
    }

    private function staff(): Staff
    {
        return Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Susan', 'last_name' => 'Pena',
            'email' => 'susan@acme.test', 'role' => 'service-provider',
            'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);
    }

    public function test_the_booking_screen_offers_the_businesss_own_services_and_team(): void
    {
        $owner = $this->owner();
        $this->service('Cut & Finish', 45, 4500);
        $this->staff();

        $this->actingAs($owner)->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('Cut \\u0026 Finish', false)
            ->assertSee('Susan Pena')
            ->assertSee('data-vue-component="BookingBuilder"', false);
    }

    /**
     * The length and the price are the sum of the services, worked out on the
     * server: a client quoted an hour must not be given forty minutes because
     * a form field said so.
     */
    public function test_a_booking_is_timed_and_priced_from_its_services(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $staff = $this->staff();

        $cut = $this->service('Cut & Finish', 45, 4500);
        $colour = $this->service('Colour', 90, 12000);

        $this->actingAs($owner)->post(route('bookings.store'), [
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$cut->id, $colour->id],
            'source' => 'phone',
            'confirmation' => 'both',
        ])->assertRedirect(route('bookings.index'));

        $booking = Booking::withoutGlobalScopes()->with('services')->first();

        $this->assertSame(135, $booking->minutes);
        $this->assertSame('10:00', $booking->startsAt());
        $this->assertSame('12:15', $booking->endsAt());
        $this->assertSame(16500, $booking->total_minor);
        $this->assertSame('confirmed', $booking->status);
        $this->assertNotNull($booking->confirmed_at);
        $this->assertNotNull($booking->reference);

        /* The services are copied onto their own rows, in the order they were
           chosen, with the price and the length they had on the day. */
        $this->assertSame(['Cut & Finish', 'Colour'], $booking->services->pluck('name')->all());
        $this->assertSame([4500, 12000], $booking->services->pluck('price_minor')->all());
    }

    /**
     * A walk-in is a booking with a name and no record behind it. Forcing one
     * to exist would fill the client list with people who came in once.
     */
    public function test_a_walk_in_is_booked_without_a_client_record(): void
    {
        $owner = $this->owner();
        $service = $this->service('Beard trim', 20, 2000);

        $this->actingAs($owner)->post(route('bookings.store'), [
            'guest_name' => 'Tom at the door',
            'guest_phone' => '+1 305 555 0199',
            'date' => '2026-09-10',
            'starts_at' => '09:30',
            'services' => [$service->id],
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->first();

        $this->assertNull($booking->client_id);
        $this->assertTrue($booking->is_walk_in);
        $this->assertSame('Tom at the door', $booking->clientName());
        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }

    /** Somebody has to be named: a client on file, or a walk-in's name. */
    public function test_a_booking_needs_somebody_to_be_for(): void
    {
        $owner = $this->owner();
        $service = $this->service('Beard trim', 20, 2000);

        $this->actingAs($owner)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'date' => '2026-09-10',
                'starts_at' => '09:30',
                'services' => [$service->id],
            ])
            ->assertRedirect(route('bookings.create'))
            ->assertSessionHasErrors('client_id');

        $this->assertSame(0, Booking::withoutGlobalScopes()->count());
    }

    /**
     * The note about the person goes on the person, which is a different
     * thing from the note about the appointment.
     */
    public function test_a_client_note_is_kept_on_the_client(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut & Finish', 45, 4500);

        $this->actingAs($owner)->post(route('bookings.store'), [
            'client_id' => $client->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'notes' => 'Wants it shorter than last time.',
            'client_note' => 'Sensitive scalp — no heat on the roots.',
        ])->assertRedirect();

        $this->assertSame('Wants it shorter than last time.', Booking::withoutGlobalScopes()->first()->notes);
        $this->assertSame(
            'Sensitive scalp — no heat on the roots.',
            $client->clientNotes()->first()->body,
        );
    }

    /** A draft has been promised to nobody, so it is not confirmed. */
    public function test_a_draft_is_saved_unconfirmed(): void
    {
        $owner = $this->owner();
        $service = $this->service('Cut & Finish', 45, 4500);

        $this->actingAs($owner)->post(route('bookings.store'), [
            'guest_name' => 'Someone',
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'draft' => 1,
        ])->assertSessionHas('toast.message', __('bookings.saved_draft'));

        $booking = Booking::withoutGlobalScopes()->first();

        $this->assertSame('draft', $booking->status);
        $this->assertNull($booking->confirmed_at);
    }

    public function test_the_listing_returns_the_bookings_it_is_asked_for(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $staff = $this->staff();
        $service = $this->service('Cut & Finish', 45, 4500);

        $this->actingAs($owner)->post(route('bookings.store'), [
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
        ]);

        /* Asked for every booking rather than the default.

           The listing now opens on Today, because the question a front desk
           opens it with is "who is coming in" — so an appointment ten days
           out is deliberately not in the default answer, and a test about
           what the listing returns has to say which view it means. */
        $row = $this->actingAs($owner)->getJson(route('bookings.data', ['tab' => 'all']))
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Mia Baker', $row['name']);
        $this->assertSame('Cut & Finish', $row['services']);
        $this->assertSame('Susan Pena', $row['staff']);
        $this->assertSame(__('bookings.statuses.confirmed.label'), $row['status']);

        /* And the filters narrow it: a status nothing is in returns nothing. */
        $this->assertSame(
            0,
            $this->actingAs($owner)->getJson(route('bookings.data', ['tab' => 'all', 'status' => 'cancelled']))->json('total'),
        );
    }

    /**
     * The booking screen reads the business's own clock.
     *
     * A screen offering 14:15 beside a summary saying 2:15 PM is a fault the
     * reader is right to notice, so the times are formatted from the same
     * setting the rest of the app formats from — and the value posted stays
     * 24-hour either way.
     */
    public function test_the_start_times_follow_the_business_clock(): void
    {
        $owner = $this->owner();

        /* Nothing set: twelve-hour, which is what App\Support\TimeFormat
           falls back to and what the settings screen now shows. */
        $this->actingAs($owner)->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('"use12Hours":true', false);

        $this->tenant->forceFill(['time_format' => '24'])->save();

        /* Acted as a fresh instance: the first request left the tenant
           relation loaded on this one, and it would answer with the setting
           as it stood before the save. */
        $this->actingAs($owner->fresh())->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('"use12Hours":false', false);
    }

    /**
     * A booking somebody started leaves a trace.
     *
     * The desk takes an appointment in five steps and a caller can hang up
     * between any two of them. The lead is written as soon as the services
     * are settled, with its own reference to quote back.
     */
    public function test_settling_the_services_writes_a_lead(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $lead = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$service->id],
            'date' => now()->addDay()->toDateString(),
        ])->assertCreated()->json('lead');

        $row = BookingLead::withoutGlobalScopes()->findOrFail($lead['id']);

        $this->assertStringStartsWith('BL-', $row->reference);
        $this->assertSame('new', $row->status);
        $this->assertSame('service', $row->current_step);
        $this->assertSame(8500, (int) $row->total_minor);
        /* A snapshot, so a service deleted next month cannot empty it. */
        $this->assertSame('Cut', $row->services[0]['name']);
    }

    /** Saving the step twice is one conversation, so it is one lead. */
    public function test_saving_the_service_step_again_edits_the_same_lead(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $cut = $this->service('Cut', 45, 8500);
        $colour = $this->service('Colour', 90, 12000);

        $first = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$cut->id],
        ])->assertCreated()->json('lead');

        $again = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'lead_id' => $first['id'],
            'client_id' => $client->id,
            'services' => [$cut->id, $colour->id],
        ])->assertCreated()->json('lead');

        $this->assertSame($first['reference'], $again['reference']);
        $this->assertSame(1, BookingLead::withoutGlobalScopes()->count());
        $this->assertSame(20500, (int) BookingLead::withoutGlobalScopes()->first()->total_minor);
    }

    /**
     * The lead that became a booking says so.
     *
     * Marked rather than deleted: leads that converted are the useful half of
     * any answer about the ones that did not.
     */
    public function test_a_lead_is_marked_converted_by_the_booking_it_becomes(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $lead = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$service->id],
        ])->assertCreated()->json('lead');

        $booking = $this->actingAs($owner)->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'lead_id' => $lead['id'],
        ])->assertCreated()->json('booking');

        $row = BookingLead::withoutGlobalScopes()->findOrFail($lead['id']);

        $this->assertSame('converted', $row->status);
        $this->assertSame('completed', $row->current_step);
        $this->assertSame($booking['id'], $row->booking_id);
        $this->assertNotNull($row->converted_at);
    }

    /**
     * The leads screen lists what was started and never finished.
     *
     * Newest first, because a lead is a call to return and the one taken an
     * hour ago is the one still worth returning.
     */
    public function test_the_leads_screen_lists_them_newest_first(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $cut = $this->service('Cut', 45, 8500);

        $older = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'guest_name' => 'Walk-in caller',
            'services' => [$cut->id],
        ])->assertCreated()->json('lead');

        $newer = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$cut->id],
        ])->assertCreated()->json('lead');

        $this->actingAs($owner)->get(route('bookings.leads'))
            ->assertOk()
            ->assertSee(__('leads.title'));

        $rows = $this->actingAs($owner)->getJson(route('bookings.leads.data'))
            ->assertOk()
            ->json('data');

        $this->assertSame($newer['id'], $rows[0]['id']);
        $this->assertSame($older['id'], $rows[1]['id']);
        $this->assertSame('Mia Baker', $rows[0]['name']);
        $this->assertSame($newer['reference'], $rows[0]['primary_badge']);
        $this->assertSame(__('leads.statuses.new.label'), $rows[0]['status']);

        /* A walk-in with no record of their own is still a lead: the name
           given on the phone is what there is to call back. */
        $this->assertSame('Walk-in caller', $rows[1]['name']);

        /* Filtered by where it got to. */
        $this->assertCount(2, $this->actingAs($owner)
            ->getJson(route('bookings.leads.data', ['status' => 'new']))
            ->json('data'));

        $this->assertCount(0, $this->actingAs($owner)
            ->getJson(route('bookings.leads.data', ['status' => 'contacted']))
            ->json('data'));
    }

    /**
     * A lead reopens the booking it was.
     *
     * The whole point of writing one down is not having to ask the client
     * everything again, so the row goes back into the booking screen with
     * what they had already chosen — and finishing it converts that lead
     * rather than filing a second one.
     */
    public function test_an_open_lead_reopens_the_booking_screen(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $lead = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$service->id],
            'date' => now()->addDay()->toDateString(),
        ])->assertCreated()->json('lead');

        $row = collect($this->actingAs($owner)->getJson(route('bookings.leads.data'))->json('data'))
            ->firstWhere('id', $lead['id']);

        /* The row opens the drawer over the listing rather than leaving it;
           completing the booking is one of the actions on it. */
        $this->assertSame(route('bookings.leads.show', $lead['id']), $row['drawer_url']);
        $this->assertSame(route('bookings.create', ['lead' => $lead['id']]), $row['menu'][0]['url']);

        $this->actingAs($owner)->get($row['menu'][0]['url'])
            ->assertOk()
            /* Handed to the screen, which opens with them already chosen. */
            ->assertSee('"reference":"'.$lead['reference'].'"', false)
            ->assertSee('"service_ids":['.$service->id.']', false);

        /* A lead that already became a booking goes to the booking instead. */
        $booking = $this->actingAs($owner)->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$service->id],
            'lead_id' => $lead['id'],
        ])->assertCreated()->json('booking');

        /* And leaves the queue: a booking that was taken is not work still
           to do. It is still there to be asked for by name, which is what
           the conversion reporting will do. */
        $this->assertNull(collect($this->actingAs($owner)->getJson(route('bookings.leads.data'))->json('data'))
            ->firstWhere('id', $lead['id']));

        $converted = collect($this->actingAs($owner)
            ->getJson(route('bookings.leads.data', ['status' => 'converted']))
            ->json('data'))
            ->firstWhere('id', $lead['id']);

        $this->assertSame(route('bookings.show', $booking['id']), $converted['menu'][0]['url']);
        $this->assertSame(__('leads.statuses.converted.label'), $converted['status']);
    }

    /**
     * What happened, and where they stopped.
     *
     * Two fields rather than one. Folded together you get a list that is half
     * workflow and half progress bar, where "abandoned at payment" and
     * "abandoned at service" cannot be told apart — and those are two very
     * different calls to make.
     */
    public function test_a_lead_records_the_step_the_client_stopped_at(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $lead = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$service->id],
            'current_step' => 'when',
        ])->assertCreated()->json('lead');

        $row = BookingLead::withoutGlobalScopes()->findOrFail($lead['id']);

        $this->assertSame('new', $row->status);
        $this->assertSame('when', $row->current_step);

        /* Working past the first card is the difference between a booking
           somebody started and one they are getting on with. */
        $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'lead_id' => $lead['id'],
            'client_id' => $client->id,
            'services' => [$service->id],
            'current_step' => 'payment',
        ])->assertCreated();

        $row->refresh();

        $this->assertSame('in-progress', $row->status);
        $this->assertSame('payment', $row->current_step);
    }

    /**
     * A lead nobody has touched becomes a call to make, then a call missed.
     *
     * Both are facts about time passing rather than decisions anybody made,
     * so they are written down rather than waited for — a status that only
     * exists while a page renders is one no queue can be filtered by.
     */
    public function test_untouched_leads_age_into_follow_up_and_then_abandoned(): void
    {
        $owner = $this->owner();
        $service = $this->service('Cut', 45, 8500);

        $quiet = fn (string $name, int $hoursAgo) => tap(
            BookingLead::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->getTenantKey(),
                'reference' => BookingLead::nextReference(),
                'guest_name' => $name,
                'services' => [['id' => $service->id, 'name' => 'Cut']],
                'status' => 'new',
                'current_step' => 'service',
                'last_activity_at' => now()->subHours($hoursAgo),
            ]),
        );

        $fresh = $quiet('Just now', 1);
        $stale = $quiet('Yesterday', 30);
        $cold = $quiet('Last week', 100);

        /* A lead somebody has already called is not a lead nobody has looked
           at, however long ago the call was. */
        $called = $quiet('Rung already', 100);
        $called->update(['status' => 'contacted']);

        $this->artisan('bookings:age-leads')->assertSuccessful();

        $this->assertSame('new', $fresh->refresh()->status);
        $this->assertSame('follow-up', $stale->refresh()->status);
        $this->assertSame('abandoned', $cold->refresh()->status);
        $this->assertSame('contacted', $called->refresh()->status);
    }

    /**
     * The drawer's own answer: one lead, whole.
     *
     * Everything the front desk needs to pick up somebody else's call, in one
     * round trip — and the fields nobody has filled in yet say so rather than
     * coming back empty, because what is missing is what the call is about.
     */
    public function test_a_lead_answers_the_drawer_with_everything_about_it(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $lead = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$service->id],
            'current_step' => 'when',
        ])->assertCreated()->json('lead');

        $drawer = $this->actingAs($owner)->getJson(route('bookings.leads.show', $lead['id']))
            ->assertOk()
            ->json();

        $this->assertSame('Mia Baker', $drawer['name']);
        $this->assertSame($lead['reference'], $drawer['reference']);
        $this->assertSame(__('leads.drawer.not_selected'), $drawer['booking'][__('leads.drawer.time')]);
        $this->assertSame('Nadia Khan', $drawer['summary'][__('leads.drawer.created_by')]);

        /* Five cards, with the one they stopped on marked as current. */
        $this->assertCount(5, $drawer['journey']);
        $this->assertTrue($drawer['journey'][0]['done']);
        $this->assertTrue($drawer['journey'][1]['current']);

        /* And what has happened, newest first. */
        $this->assertSame(__('leads.events.created'), collect($drawer['events'])->last()['label']);
    }

    /**
     * A note written while chasing a lead.
     *
     * Kept on the client, because that is where the next person to look this
     * one up will read it — and tagged with the lead, because "rang, no
     * answer" means something different against a wedding enquiry than
     * against a walk-in. Writing one is contact, so the queue stops chasing.
     */
    public function test_a_note_is_kept_on_the_client_and_tagged_with_the_lead(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $lead = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$service->id],
        ])->assertCreated()->json('lead');

        $this->actingAs($owner)->postJson(route('bookings.leads.notes', $lead['id']), [
            'body' => 'Rang at 4pm, no answer.',
        ])->assertOk();

        $note = $client->clientNotes()->first();

        $this->assertSame('Rang at 4pm, no answer.', $note->body);
        $this->assertSame($lead['id'], $note->booking_lead_id);

        $row = BookingLead::withoutGlobalScopes()->find($lead['id']);

        $this->assertSame('contacted', $row->status);
        $this->assertSame($owner->id, $row->contacted_by);
        $this->assertNotNull($row->contacted_at);
    }

    /** Cancelling asks why, and keeps the lead. */
    public function test_a_lead_can_be_cancelled_with_a_reason(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $lead = $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$service->id],
        ])->assertCreated()->json('lead');

        $this->actingAs($owner)
            ->postJson(route('bookings.leads.cancel', $lead['id']), ['reason' => 'nonsense'])
            ->assertStatus(422);

        $this->actingAs($owner)
            ->postJson(route('bookings.leads.cancel', $lead['id']), [
                'reason' => 'price',
                'note' => 'Thought it was dear.',
            ])
            ->assertOk()
            ->assertJsonPath('status', __('leads.statuses.cancelled.label'));

        $row = BookingLead::withoutGlobalScopes()->find($lead['id']);

        /* Kept, not deleted: a cancelled call is half of every answer about
           how many calls convert. */
        $this->assertSame('cancelled', $row->status);
        $this->assertSame('price', $row->reason_code);
        $this->assertSame('Thought it was dear.', $row->reason_note);
    }

    /**
     * The client as they were, kept with the booking.
     *
     * Tags and insights are worked out from the diary, so they move as the
     * person does. An appointment from March should still say what was true
     * in March, or every historical record quietly rewrites itself.
     */
    public function test_a_booking_keeps_a_snapshot_of_the_client_it_was_taken_for(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $client->syncBehavioralTags(['high_value_client']);

        $booking = Booking::withoutGlobalScopes()->findOrFail(
            $this->actingAs($owner)->postJson(route('bookings.store'), [
                'client_id' => $client->id,
                'date' => now()->addDay()->toDateString(),
                'starts_at' => '14:30',
                'services' => [$service->id],
            ])->assertCreated()->json('booking.id'),
        );

        $snapshot = $booking->client_snapshot;

        $this->assertNotNull($snapshot['taken_at']);
        $this->assertSame([config('behavioral_tags.tags.high_value_client.label')], $snapshot['tags']);

        /* The client changes; the booking does not. */
        $client->syncBehavioralTags([]);

        $this->actingAs($owner)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee(config('behavioral_tags.tags.high_value_client.label'));
    }

    /**
     * One client's own bookings and leads, for the panel on their profile.
     *
     * Two segments over one answer, because they are counted differently by
     * everybody who reads them: a lead is not an appointment.
     */
    public function test_a_clients_bookings_and_leads_are_answered_for_their_profile(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $cut = $this->service('Cut', 45, 8500);
        $colour = $this->service('Colour', 90, 12000);

        $this->actingAs($owner)->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'services' => [$cut->id],
        ])->assertCreated();

        $this->actingAs($owner)->postJson(route('bookings.leads.store'), [
            'client_id' => $client->id,
            'services' => [$colour->id],
        ])->assertCreated();

        $panel = $this->actingAs($owner)->getJson(route('bookings.for-client', $client))
            ->assertOk()
            ->json();

        /* Grouped by the month they happened in, which is how a history is
           read. */
        $this->assertCount(1, $panel['groups']);
        $this->assertSame(now()->addDay()->translatedFormat('F Y'), $panel['groups'][0]['label']);
        $this->assertSame('Cut', $panel['groups'][0]['bookings'][0]['services']);

        /* The lead is in its own segment, never among the bookings. */
        $this->assertCount(1, $panel['leads']);
        $this->assertSame('Colour', $panel['leads'][0]['services']);

        /* The filters offer only what this client has, so no combination of
           them can find nothing. */
        $this->assertSame(['Cut'], collect($panel['services'])->pluck('label')->all());

        /* Filtered by a service they have never booked: an empty history,
           not an error. */
        $this->assertCount(0, $this->actingAs($owner)
            ->getJson(route('bookings.for-client', ['client' => $client, 'service' => $colour->id]))
            ->json('groups'));
    }

    /** The drawer a booking card opens, over whatever listing opened it. */
    public function test_a_booking_answers_the_drawer_in_the_same_shape_a_lead_does(): void
    {
        $owner = $this->owner();
        $booking = $this->takenBooking($owner);

        $drawer = $this->actingAs($owner)->getJson(route('bookings.drawer', $booking))
            ->assertOk()
            ->json();

        $this->assertSame($booking->reference, $drawer['reference']);
        $this->assertSame('Mia Baker', $drawer['name']);
        $this->assertCount(2, $drawer['sections']);
        $this->assertSame(route('bookings.show', $booking), $drawer['urls']['show']);
    }

    /* ------------------------------------------------- the third column --- */

    /**
     * Confirm Booking takes the appointment without leaving the page.
     *
     * The slot is what a receptionist is protecting while they ask how
     * somebody is paying, so it is written down before the money is, and the
     * screen gets the booking back rather than a redirect.
     */
    public function test_confirming_a_booking_answers_the_panel_in_json(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $this->tenant->forceFill(['default_tax_behavior' => 'exclusive', 'default_tax_rate' => 8])->save();

        $panel = $this->actingAs($owner)->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '14:30',
            'services' => [$service->id],
        ])->assertCreated()->json('booking');

        $booking = Booking::withoutGlobalScopes()->find($panel['id']);

        /* The bill is worked out once and written down: every screen after
           this reads what was stored rather than the price list. */
        $this->assertSame(8500, (int) $booking->subtotal_minor);
        $this->assertSame(680, (int) $booking->tax_minor);
        $this->assertSame(9180, (int) $booking->total_minor);
        $this->assertSame('unpaid', $booking->payment_status);

        $this->assertSame('$91.80', $panel['due']);
        $this->assertNotNull($panel['reference']);

        /* Read from the reply rather than from a second query, so a column
           the database filled in but the instance never saw would show up
           here as a raw translation key. It did, once. */
        $this->assertSame('Unpaid', $panel['payment_status_label']);
    }

    /**
     * Cash: what was handed over, and what goes back.
     *
     * The change is worked out and kept, because the drawer has to balance at
     * the end of the day and "paid $91.80" does not say a hundred was
     * tendered.
     */
    public function test_a_cash_payment_is_recorded_with_the_change_given(): void
    {
        $owner = $this->owner();
        $booking = $this->takenBooking($owner);

        $panel = $this->actingAs($owner)->postJson(route('bookings.pay', $booking), [
            'method' => 'cash',
            'amount' => '91.80',
            'received' => '100',
        ])->assertCreated();

        $panel->assertJsonPath('payment.change', '$8.20');

        $booking->refresh()->load('payments');

        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(9180, $booking->paidMinor());
        $this->assertSame(0, $booking->dueMinor());
        $this->assertSame(820, (int) $booking->payments->first()->change_minor);
    }

    /**
     * Half now, half later.
     *
     * A part payment is an ordinary thing at a desk, and the booking has to
     * say so rather than round to paid or unpaid.
     */
    public function test_a_part_payment_leaves_the_rest_owing(): void
    {
        $owner = $this->owner();
        $booking = $this->takenBooking($owner);

        $this->actingAs($owner)->postJson(route('bookings.pay', $booking), [
            'method' => 'zelle',
            'amount' => '40',
        ])->assertCreated()->assertJsonPath('booking.payment_status', 'partial');

        $this->assertSame(5180, $booking->refresh()->dueMinor());
    }

    /**
     * A card StyleDesk cannot charge is not charged.
     *
     * With no provider connected, the panel offers the terminal beside the
     * till instead — and that is a record of money that arrived, so it is
     * accepted the way cash is.
     */
    public function test_a_card_is_refused_without_a_provider_but_the_terminal_is_not(): void
    {
        $owner = $this->owner();
        $booking = $this->takenBooking($owner);

        config(['bookings.card_provider' => null]);

        $this->actingAs($owner)->postJson(route('bookings.pay', $booking), [
            'method' => 'card',
            'amount' => '91.80',
        ])->assertStatus(422)->assertJsonValidationErrors('method');

        $this->actingAs($owner)->postJson(route('bookings.pay', $booking), [
            'method' => 'card',
            'amount' => '91.80',
            'manual' => true,
            'reference' => 'TERM-4417',
        ])->assertCreated();

        $this->assertSame('paid', $booking->refresh()->payment_status);
    }

    /**
     * Back to booking summary moves the booking rather than making another.
     *
     * And stops once money is against it: a paid booking whose total quietly
     * changed is a receipt that no longer matches what was charged.
     */
    public function test_a_held_booking_is_edited_rather_than_taken_twice(): void
    {
        $owner = $this->owner();
        $booking = $this->takenBooking($owner);
        $longer = $this->service('Colour', 90, 12000);

        $this->actingAs($owner)->patchJson(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'date' => $booking->date->toDateString(),
            'starts_at' => '15:00',
            'services' => [$longer->id],
        ])->assertOk()->assertJsonPath('booking.total_minor', 12960);

        $this->assertSame(1, Booking::withoutGlobalScopes()->count());
        $this->assertSame(90, (int) $booking->refresh()->minutes);

        /* Paid for, and now beyond editing. */
        $this->actingAs($owner)->postJson(route('bookings.pay', $booking), ['method' => 'cash', 'amount' => '120'])
            ->assertCreated();

        $this->actingAs($owner)->patchJson(route('bookings.update', $booking), [
            'client_id' => $booking->client_id,
            'date' => $booking->date->toDateString(),
            'starts_at' => '16:00',
            'services' => [$longer->id],
        ])->assertStatus(422);
    }

    /** The client's copy, and the channel that is not connected yet. */
    public function test_the_confirmation_is_emailed_and_sms_says_why_it_cannot_be(): void
    {
        Mail::fake();

        $owner = $this->owner();
        $booking = $this->takenBooking($owner);

        $this->actingAs($owner)
            ->postJson(route('bookings.confirmation', $booking), ['channel' => 'email'])
            ->assertOk()
            ->assertJsonPath('sent_to', 'mia@acme.test');

        Mail::assertSent(BookingConfirmationMail::class);

        $this->actingAs($owner)
            ->postJson(route('bookings.confirmation', $booking), ['channel' => 'sms'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('channel');
    }

    /** The two pages a confirmed booking is read on. */
    public function test_a_booking_can_be_read_and_printed(): void
    {
        $owner = $this->owner();
        $booking = $this->takenBooking($owner);

        $this->actingAs($owner)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee($booking->reference)
            ->assertSee('$91.80')
            /* Three columns: the person, the work, and what to do next. */
            ->assertSee(__('bookings.detail.services'))
            ->assertSee(__('bookings.detail.transactions'))
            ->assertSee(__('clients.behavioral.title'));

        $this->actingAs($owner)->get(route('bookings.receipt', $booking))
            ->assertOk()
            ->assertSee($booking->reference);
    }

    /** A booking taken through the panel, ready to be paid for. */
    private function takenBooking(User $owner): Booking
    {
        $client = $this->client();
        $service = $this->service('Cut', 45, 8500);

        $this->tenant->forceFill(['default_tax_behavior' => 'exclusive', 'default_tax_rate' => 8])->save();

        $id = $this->actingAs($owner)->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '14:30',
            'services' => [$service->id],
        ])->assertCreated()->json('booking.id');

        return Booking::withoutGlobalScopes()->findOrFail($id);
    }

    /**
     * A client added from inside the booking screen.
     *
     * Four fields, because that is what taking a booking needs — and the
     * contact rows the rest of the app reads, so a client added here is not
     * one with a number the phones table has never heard of.
     */
    public function test_a_client_can_be_added_from_the_booking_screen(): void
    {
        $owner = $this->owner();

        $created = $this->actingAs($owner)->postJson(route('bookings.clients.store'), [
            'first_name' => 'Rosa',
            'last_name' => 'Delgado',
            'mobile' => '+1 305 555 0188',
        ])->assertCreated()->json('client');

        $client = Client::withoutGlobalScopes()->find($created['id']);

        $this->assertSame('Rosa Delgado', $client->displayName());
        $this->assertNotNull($client->client_ref);
        $this->assertSame(1, $client->phones()->count());
        $this->assertSame(Client::STATUS_ACTIVE, $client->status);
    }

    /** One of the two, so the confirmation has somewhere to go. */
    public function test_a_new_client_needs_a_mobile_or_an_email(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->postJson(route('bookings.clients.store'), ['first_name' => 'Rosa'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mobile');

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }

    /**
     * The duplicate check warns and never blocks.
     *
     * Taking a booking is exactly when a second record for an existing client
     * gets created, and it is the worst moment for it — their history is what
     * makes this screen fast. But two people can share a phone, so the reader
     * decides.
     */
    public function test_a_possible_duplicate_is_offered_before_a_second_record_is_made(): void
    {
        $owner = $this->owner();
        $existing = $this->client();

        $again = ['first_name' => 'Mia', 'last_name' => 'Baker', 'email' => $existing->email];

        $warned = $this->actingAs($owner)->postJson(route('bookings.clients.store'), $again)
            ->assertStatus(409)
            ->json('duplicates');

        $this->assertSame($existing->id, $warned[0]['id']);
        $this->assertSame(1, Client::withoutGlobalScopes()->count());

        /* Having read it, the reader may still be right. */
        $this->actingAs($owner)
            ->postJson(route('bookings.clients.store'), $again + ['confirm_duplicate' => true])
            ->assertCreated();

        $this->assertSame(2, Client::withoutGlobalScopes()->count());
    }

    /**
     * The panel beside the booking, once a client is chosen.
     *
     * Two kinds of fact sit in it and are labelled apart: what the client
     * asked for, and what the diary noticed. Flattening the two would have
     * the desk quoting the software back to people as though they had said
     * it.
     */
    public function test_the_client_panel_reads_the_history_and_the_preferences(): void
    {
        $owner = $this->owner();
        $client = $this->client();
        $staff = $this->staff();
        $cut = $this->service('Cut & Finish', 45, 4500);

        $client->forceFill(['preferred_staff_id' => $staff->id])->save();
        $client->bookingPreferences()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'label' => 'No fragranced products',
            'source' => 'client',
        ]);

        /* Four visits, four weeks apart, all in the afternoon — which is what
           the panel reads a cadence and a time of day from. */
        foreach ([28, 56, 84, 112] as $index => $back) {
            $booking = Booking::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->getTenantKey(),
                'client_id' => $client->id,
                'staff_id' => $staff->id,
                'date' => now()->subDays($back)->toDateString(),
                'starts_at' => '14:00', 'ends_at' => '14:45', 'minutes' => 45,
                'status' => 'completed', 'total_minor' => 4500, 'currency_code' => 'USD',
            ]);

            $booking->services()->create([
                'service_id' => $cut->id, 'name' => 'Cut & Finish',
                'minutes' => 45, 'price_minor' => 4500,
            ]);

            /* Not every visit is reviewed, which is why only the reviewed
               ones carry stars. */
            if ($index < 2) {
                BookingReview::withoutGlobalScopes()->create([
                    'tenant_id' => $this->tenant->getTenantKey(),
                    'booking_id' => $booking->id,
                    'client_id' => $client->id,
                    'staff_id' => $staff->id,
                    'rating' => 5,
                ]);
            }
        }

        $panel = $this->actingAs($owner)
            ->getJson(route('bookings.clients.context', $client))
            ->assertOk()
            ->json();

        /* The person they asked for, said as a request rather than as a
           count. */
        $this->assertSame('Susan Pena', $panel['preferred'][0]['name']);
        $this->assertSame(__('bookings.context.kinds.asked_for'), $panel['preferred'][0]['why']);
        $this->assertSame(4, $panel['preferred'][0]['visits']);

        /* The last one, and enough of it to book it again. */
        $this->assertSame('Cut & Finish', $panel['last']['services']);
        $this->assertSame([$cut->id], $panel['last']['again']['service_ids']);
        $this->assertSame($staff->id, $panel['last']['again']['staff_id']);

        $this->assertCount(3, $panel['recent']);
        $this->assertSame(5, $panel['recent'][0]['rating']);
        $this->assertNull($panel['recent'][2]['rating']);
        $this->assertSame('5.0', $panel['rating']);

        $labels = collect($panel['preferences']);

        $this->assertSame('client', $labels->firstWhere('label', 'No fragranced products')['source']);
        $this->assertSame('system', $labels->firstWhere('label', trans_choice('bookings.context.cadence', 4, ['count' => 4]))['source']);
        $this->assertSame('system', $labels->firstWhere('label', __('bookings.context.windows.afternoon'))['source']);
    }

    /** A first-time client has no history, and the panel says so rather than inventing one. */
    public function test_the_panel_is_empty_for_a_client_with_no_visits(): void
    {
        $owner = $this->owner();
        $client = $this->client();

        $panel = $this->actingAs($owner)
            ->getJson(route('bookings.clients.context', $client))
            ->assertOk()
            ->json();

        $this->assertSame([], $panel['preferred']);
        $this->assertSame([], $panel['also_seen']);
        $this->assertNull($panel['last']);
        $this->assertNull($panel['rating']);
        $this->assertSame([], $panel['preferences']);
    }

    /** Booking preferences are kept on the client, added and removed one at a time. */
    public function test_a_booking_preference_can_be_added_and_removed(): void
    {
        $owner = $this->owner();
        $client = $this->client();

        $this->actingAs($owner)
            ->post(route('clients.booking-preferences.store', $client), ['label' => 'Avoid stairs'])
            ->assertRedirect();

        $preference = $client->bookingPreferences()->first();

        $this->assertSame('Avoid stairs', $preference->label);
        /* What a person said, not what the diary noticed. */
        $this->assertSame('client', $preference->source);

        $this->actingAs($owner)
            ->delete(route('clients.booking-preferences.destroy', [$client, $preference]))
            ->assertRedirect();

        $this->assertSame(0, $client->bookingPreferences()->count());
    }

    /** The client search behind the first column, which is the one thing the screen asks the server for. */
    public function test_the_client_search_finds_people_by_name_or_number(): void
    {
        $owner = $this->owner();
        $this->client();

        $this->assertSame(
            'Mia Baker',
            $this->actingAs($owner)->getJson(route('bookings.clients').'?q=mia')->json('data.0.name'),
        );

        $this->assertSame(
            'Mia Baker',
            $this->actingAs($owner)->getJson(route('bookings.clients').'?q=0100')->json('data.0.name'),
        );

        /* One letter is everybody, which is not a search. */
        $this->assertSame([], $this->actingAs($owner)->getJson(route('bookings.clients').'?q=m')->json('data'));
    }

    // ------------------------------------------------- the header location card

    /**
     * The branch is chosen from the page header, not from inside the time
     * step: it decides the hours, the rota, the services and the chairs, so
     * it belongs where it can be seen without going looking.
     */
    public function test_the_header_carries_a_slot_for_the_location_card(): void
    {
        $this->actingAs($this->owner())
            ->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('id="bookingLocationSlot"', false);
    }

    /**
     * The screen is told where each service is offered and where each person
     * works, so changing branch can narrow both without another request.
     *
     * Empty means everywhere in each case — a service naming no locations is
     * offered at all of them, and staff with no location work across all of
     * them — which is the convention Service::isOfferedAt() already reads.
     */
    public function test_the_screen_is_told_which_branch_services_and_staff_belong_to(): void
    {
        $owner = $this->owner();
        $service = $this->service('Cut', 45, 4500);
        $service->locations()->sync([$this->location->id]);

        $staff = $this->staff();

        $html = $this->actingAs($owner)->get(route('bookings.create'))->assertOk()->getContent();

        $this->assertStringContainsString('location_ids', $html);
        $this->assertStringContainsString('location_id', $html);

        /* The service's own branch travels with it, so a service offered at
           one location only disappears from the list at the others. */
        $this->assertStringContainsString((string) $this->location->id, $html);
    }
}
