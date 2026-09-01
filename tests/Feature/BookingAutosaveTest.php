<?php

namespace Tests\Feature;

use App\Mail\BookingPaymentLinkMail;
use App\Models\Booking;
use App\Models\BookingLead;
use App\Models\BookingPaymentLink;
use App\Models\Client;
use App\Models\ClientSettings;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The booking screen saving itself as it is filled in.
 *
 * A booking is taken over the phone and a phone call is interrupted, so the
 * screen writes a booking lead the moment it knows who the appointment is
 * for and keeps writing into that same record. What these tests are about is
 * the three promises that makes: one lead per booking attempt, a reference
 * handed out once that survives all the way onto the confirmed appointment,
 * and a draft that is never an appointment until somebody confirms it.
 */
class BookingAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme-autosave']);

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

        $this->actingAs($this->owner());
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
            'name' => $name, 'duration_minutes' => $minutes, 'is_active' => true,
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
            'first_name' => 'Susan', 'last_name' => 'Pena', 'email' => 'susan@acme.test',
            'role' => 'service-provider', 'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);
    }

    /**
     * Nothing is written before there is a name to write it under.
     *
     * A row saved as soon as the screen opened would be a draft nobody could
     * match to a caller, and the desk would collect one for every time
     * somebody looked at the page and closed it.
     */
    public function test_nothing_is_saved_before_the_client_is_known(): void
    {
        $service = $this->service('Cut & Finish', 45, 4500);

        $this->postJson(route('bookings.draft'), [
            'services' => [$service->id],
            'date' => '2026-09-10',
        ])->assertStatus(422)->assertJsonValidationErrors('client_id');

        $this->assertSame(0, BookingLead::withoutGlobalScopes()->count());
        $this->assertSame(0, Booking::withoutGlobalScopes()->count());
    }

    /** A client on file is enough on its own; so is a walk-in's name. */
    public function test_a_client_is_enough_for_the_first_save(): void
    {
        $client = $this->client();

        $this->postJson(route('bookings.draft'), ['client_id' => $client->id])
            ->assertCreated();

        $this->postJson(route('bookings.draft'), ['guest_name' => 'Tom at the door'])
            ->assertCreated();

        $this->assertSame(2, BookingLead::withoutGlobalScopes()->count());
    }

    /**
     * The first save writes a lead, not a booking. A booking in progress is
     * not an appointment, and it has no business in the diary beside the ones
     * that were actually taken.
     */
    public function test_the_first_save_writes_a_lead_with_a_booking_reference(): void
    {
        $client = $this->client();
        $service = $this->service('Cut & Finish', 45, 4500);

        $saved = $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'services' => [$service->id],
            'date' => '2026-09-10',
        ])->assertCreated()->json('lead');

        $this->assertSame('draft', $saved['status']);
        /* A booking's own number, because the desk reads it out while it is
           still a draft and the appointment has to answer to it afterwards. */
        $this->assertMatchesRegularExpression('/^BK-\d{8}-\d{5}$/', $saved['reference']);

        $lead = BookingLead::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('draft', $lead->status);
        $this->assertSame('Draft', $lead->statusLabel());
        $this->assertSame(45, (int) $lead->minutes);
        $this->assertSame(4500, (int) $lead->total_minor);
        /* A snapshot, so a service deleted next month cannot empty it. */
        $this->assertSame('Cut & Finish', $lead->services[0]['name']);

        /* Nothing in the diary. This is the whole point of the change. */
        $this->assertSame(0, Booking::withoutGlobalScopes()->count());
    }

    /**
     * One record per booking attempt. Every later save writes into the same
     * lead under the same number — a reference that changed as somebody typed
     * is a number already read out over the phone that finds nothing.
     */
    public function test_later_saves_edit_the_same_lead_and_keep_the_reference(): void
    {
        $client = $this->client();
        $staff = $this->staff();
        $cut = $this->service('Cut & Finish', 45, 4500);
        $colour = $this->service('Colour', 90, 12000);

        $first = $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'services' => [$cut->id],
            'date' => '2026-09-10',
        ])->json('lead');

        $again = $this->postJson(route('bookings.draft'), [
            'lead_id' => $first['id'],
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'services' => [$cut->id, $colour->id],
            'date' => '2026-09-11',
            'starts_at' => '10:00',
            'notes' => 'Allergic to ammonia.',
            'current_step' => 'details',
        ])->assertOk()->json('lead');

        $this->assertSame($first['id'], $again['id']);
        $this->assertSame($first['reference'], $again['reference']);
        $this->assertSame(1, BookingLead::withoutGlobalScopes()->count());

        $lead = BookingLead::withoutGlobalScopes()->firstOrFail();

        /* Past the first card is a booking being worked through rather than
           one somebody merely started. */
        $this->assertSame('in-progress', $lead->status);
        $this->assertSame('details', $lead->current_step);
        $this->assertSame(135, (int) $lead->minutes);
        $this->assertSame(16500, (int) $lead->total_minor);
        $this->assertSame(['Cut & Finish', 'Colour'], collect($lead->services)->pluck('name')->all());
    }

    /**
     * Everything entered so far is preserved, because the whole reason to
     * pick a lead back up is not having to ask the same questions twice.
     */
    public function test_the_lead_keeps_every_answer_the_screen_was_given(): void
    {
        $client = $this->client();
        $staff = $this->staff();
        $service = $this->service('Colour', 90, 12000);

        $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'services' => [$service->id],
            'date' => '2026-09-11',
            'starts_at' => '14:30',
            'source' => 'phone',
            'payment_type' => 'deposit',
            'deposit' => '25.00',
            'collection_method' => 'link',
            'confirmation' => 'email',
            'notes' => 'Wants the corner chair.',
            'client_note' => 'Parking is difficult for her.',
        ])->assertCreated();

        $lead = BookingLead::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($client->id, $lead->client_id);
        $this->assertSame($staff->id, $lead->staff_id);
        $this->assertSame($this->location->id, $lead->location_id);
        $this->assertSame('2026-09-11', $lead->expected_date->toDateString());
        $this->assertSame('14:30', substr((string) $lead->starts_at, 0, 5));
        $this->assertSame('phone', $lead->source);
        $this->assertSame('deposit', $lead->payment_type);
        $this->assertSame(2500, (int) $lead->deposit_minor);
        $this->assertSame('link', $lead->collection_method);
        $this->assertSame('email', $lead->confirmation);
        $this->assertSame('Wants the corner chair.', $lead->notes);
        $this->assertSame('Parking is difficult for her.', $lead->client_note);
        $this->assertSame(12000, (int) $lead->subtotal_minor);
    }

    /**
     * Continue Booking reopens the screen on the same lead, with every answer
     * handed back to it — and without a second reference.
     */
    public function test_continue_booking_reopens_the_screen_with_everything_restored(): void
    {
        $client = $this->client();
        $staff = $this->staff();
        $service = $this->service('Colour', 90, 12000);

        $saved = $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'services' => [$service->id],
            'date' => '2026-09-11',
            'starts_at' => '14:30',
            'notes' => 'Wants the corner chair.',
            'current_step' => 'details',
        ])->json('lead');

        $page = $this->get(route('bookings.create', ['lead' => $saved['id']]))->assertOk();

        $page->assertSee($saved['reference'])
            ->assertSee('"starts_at":"14:30"', false)
            ->assertSee('"staff_id":'.$staff->id, false)
            ->assertSee('"location_id":'.$this->location->id, false)
            ->assertSee('"current_step":"details"', false)
            ->assertSee('Wants the corner chair.');

        /* Reopening writes nothing. A second reference for one attempt is
           exactly what the desk cannot be given. */
        $this->assertSame(1, BookingLead::withoutGlobalScopes()->count());
    }

    /**
     * Confirm Booking moves it out of the leads queue and into the diary,
     * under the number it has been quoted under all along.
     */
    public function test_confirming_converts_the_lead_and_keeps_its_reference(): void
    {
        $client = $this->client();
        $staff = $this->staff();
        $service = $this->service('Cut & Finish', 45, 4500);

        $saved = $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'services' => [$service->id],
            'date' => '2026-09-10',
        ])->json('lead');

        $panel = $this->postJson(route('bookings.store'), [
            'lead_id' => $saved['id'],
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'confirmation' => 'both',
        ])->assertCreated()->json('booking');

        $this->assertSame($saved['reference'], $panel['reference']);

        $booking = Booking::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($saved['reference'], $booking->reference);
        $this->assertSame('confirmed', $booking->status);
        $this->assertNotNull($booking->confirmed_at);
        $this->assertSame(1, Booking::withoutGlobalScopes()->count());

        $lead = BookingLead::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('converted', $lead->status);
        $this->assertSame('completed', $lead->current_step);
        $this->assertSame($booking->id, $lead->booking_id);
        $this->assertNotNull($lead->converted_at);
    }

    /**
     * An abandoned draft stays under Bookings → Leads, and never reaches the
     * diary. It is the whole point: a booking nobody finished must not read
     * as an appointment somebody was promised.
     */
    public function test_an_abandoned_draft_waits_under_leads_and_never_in_the_diary(): void
    {
        $client = $this->client();
        $service = $this->service('Cut & Finish', 45, 4500);

        $saved = $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'services' => [$service->id],
            'date' => '2026-09-10',
            'starts_at' => '10:00',
        ])->json('lead');

        /* Not in the diary, under any filter. */
        $this->assertSame([], $this->getJson(route('bookings.data'))->json('data'));

        /* In the leads queue, badged Draft, with the branch and the way back
           in beside it. */
        $rows = $this->getJson(route('bookings.leads.data'))->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($saved['reference'], $rows[0]['primary_badge']);
        $this->assertSame('Draft', $rows[0]['status']);
        $this->assertSame('Riverside', $rows[0]['location']);
        $this->assertContains(
            route('bookings.create', ['lead' => $saved['id']]),
            collect($rows[0]['menu'])->pluck('url')->all(),
        );
    }

    /**
     * The draft holds no slot. A time reserved by a call that was abandoned
     * is a time nobody could ever have.
     */
    public function test_a_draft_reserves_no_time(): void
    {
        /* 2026-09-10 is a Thursday. */
        $this->location->allHours()->create([
            'effective_from' => '2000-01-01',
            'day_of_week' => (int) CarbonImmutable::parse('2026-09-10')->dayOfWeek,
            'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00', 'sort_order' => 0,
        ]);

        $client = $this->client();
        $staff = $this->staff();
        $service = $this->service('Cut & Finish', 45, 4500);

        $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'services' => [$service->id],
            'date' => '2026-09-10',
            'starts_at' => '10:00',
        ])->assertCreated();

        $times = $this->getJson(route('bookings.availability', [
            'date' => '2026-09-10',
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'service_ids' => [$service->id],
        ]))->assertOk()->json('slots');

        $this->assertContains('10:00', $times);
    }

    /**
     * A lead the screen is allowed to reopen is one it is allowed to save
     * into. Any narrower and Continue Booking gives back a screen that says
     * "Not saved" at every answer typed into it.
     */
    public function test_a_lead_somebody_has_already_chased_can_still_be_saved_into(): void
    {
        $client = $this->client();
        $service = $this->service('Cut & Finish', 45, 4500);

        $saved = $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'services' => [$service->id],
        ])->json('lead');

        /* The desk rang them back, so somebody moved it on by hand. That is
           still the same booking, still waiting to be finished. */
        foreach (['contacted', 'follow-up', 'awaiting-deposit'] as $status) {
            BookingLead::withoutGlobalScopes()->findOrFail($saved['id'])->update(['status' => $status]);

            $this->get(route('bookings.create', ['lead' => $saved['id']]))
                ->assertOk()
                ->assertSee($saved['reference']);

            $again = $this->postJson(route('bookings.draft'), [
                'lead_id' => $saved['id'],
                'client_id' => $client->id,
                'services' => [$service->id],
            ])->assertOk()->json('lead');

            $this->assertSame($saved['reference'], $again['reference']);
            /* And the desk's own judgement is not overwritten by the screen
               being left open on it. */
            $this->assertSame($status, $again['status']);
        }

        $this->assertSame(1, BookingLead::withoutGlobalScopes()->count());
    }

    /**
     * A lead that has already become a booking, or that somebody wrote off,
     * is not written into again by a screen left open in another tab.
     */
    public function test_the_autosave_refuses_a_lead_that_is_no_longer_being_worked_on(): void
    {
        $client = $this->client();
        $service = $this->service('Cut & Finish', 45, 4500);

        $saved = $this->postJson(route('bookings.draft'), [
            'client_id' => $client->id,
            'services' => [$service->id],
        ])->json('lead');

        foreach (BookingLead::SETTLED as $status) {
            BookingLead::withoutGlobalScopes()->findOrFail($saved['id'])->update(['status' => $status]);

            $this->postJson(route('bookings.draft'), [
                'lead_id' => $saved['id'],
                'client_id' => $client->id,
                'services' => [$service->id],
            ])->assertNotFound();

            $this->assertSame($status, BookingLead::withoutGlobalScopes()->findOrFail($saved['id'])->status);
        }
    }

    /**
     * A walk-in who is already on the book.
     *
     * Booking a regular as a walk-in is easy to do and expensive to undo:
     * their history, preferences and preferred stylist stay on the record
     * nobody used, and the desk ends up holding two of the same person. So
     * the number and the address are checked as they are typed.
     */
    public function test_a_walk_ins_number_or_email_finds_the_client_already_on_file(): void
    {
        $client = $this->client();

        /* The number they are giving at the desk, written the way a person
           says it rather than the way it is stored. */
        $byPhone = $this->postJson(route('bookings.clients.match'), [
            'name' => 'Mia',
            'mobile' => '305 555 0100',
        ])->assertOk()->json('matches');

        $this->assertCount(1, $byPhone);
        $this->assertSame($client->id, $byPhone[0]['id']);
        $this->assertSame('Mia Baker', $byPhone[0]['name']);

        $byEmail = $this->postJson(route('bookings.clients.match'), [
            'email' => 'MIA@acme.test',
        ])->assertOk()->json('matches');

        $this->assertCount(1, $byEmail);
        $this->assertSame($client->id, $byEmail[0]['id']);
    }

    /**
     * A name is not enough to claim two people are one — this business has
     * more than one Sarah — so nothing is matched on it alone.
     */
    public function test_a_name_on_its_own_matches_nobody(): void
    {
        $this->client();

        $this->postJson(route('bookings.clients.match'), ['name' => 'Mia Baker'])
            ->assertOk()
            ->assertExactJson(['matches' => []]);
    }

    /** A genuine walk-in nobody has a record for is left alone. */
    public function test_an_unknown_walk_in_matches_nobody(): void
    {
        $this->client();

        $this->postJson(route('bookings.clients.match'), [
            'name' => 'Tom at the door',
            'mobile' => '+1 415 555 9999',
            'email' => 'tom@nowhere.test',
        ])->assertOk()->assertExactJson(['matches' => []]);
    }

    /**
     * The business turned the warning off. Answering anyway would be this one
     * screen overruling a setting every other screen obeys.
     */
    public function test_the_check_obeys_the_businesss_own_duplicate_setting(): void
    {
        $client = $this->client();

        ClientSettings::forTenant($this->tenant)->update(['duplicate_warning' => false]);

        $this->postJson(route('bookings.clients.match'), ['mobile' => $client->mobile])
            ->assertOk()
            ->assertExactJson(['matches' => []]);
    }

    /**
     * Three payment options, because they are three different acts: nothing
     * now and the whole bill owed later, part of it now, or all of it now.
     */
    public function test_a_booking_can_be_taken_with_no_payment_a_deposit_or_the_full_amount(): void
    {
        $client = $this->client();
        $service = $this->service('Colour', 90, 15000);

        $cases = [
            ['none', null, 0],
            ['deposit', '50.00', 5000],
            ['full', null, 0],
        ];

        foreach ($cases as [$type, $deposit, $expected]) {
            $panel = $this->postJson(route('bookings.store'), [
                'client_id' => $client->id,
                'location_id' => $this->location->id,
                'date' => '2026-09-10',
                'starts_at' => '10:00',
                'services' => [$service->id],
                'payment_type' => $type,
                'deposit' => $deposit,
            ])->assertCreated()->json('booking');

            $booking = Booking::withoutGlobalScopes()->findOrFail($panel['id']);

            $this->assertSame($type, $booking->payment_type);
            $this->assertSame($expected, (int) $booking->deposit_minor);

            /* Nothing has been taken yet whichever option was chosen: the
               option says what to collect, the payment says what arrived. */
            $this->assertSame('unpaid', $booking->payment_status);
            $this->assertSame(15000, $booking->dueMinor());
        }
    }

    /**
     * A deposit larger than the bill is money the desk would have to give
     * back before the appointment has even been worked.
     */
    public function test_a_deposit_cannot_exceed_the_booking_total(): void
    {
        $client = $this->client();
        $service = $this->service('Cut', 45, 5000);

        $this->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_type' => 'deposit',
            'deposit' => '80.00',
        ])->assertStatus(422)->assertJsonValidationErrors('deposit');

        $this->assertSame(0, Booking::withoutGlobalScopes()->count());
    }

    /**
     * What to ask for now is not always what is owed: a booking taken with a
     * deposit collects the deposit today and chases the balance later.
     */
    public function test_the_panel_asks_for_the_deposit_first_and_the_balance_after(): void
    {
        $client = $this->client();
        $service = $this->service('Colour', 90, 15000);

        $panel = $this->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_type' => 'deposit',
            'deposit' => '50.00',
        ])->assertCreated()->json('booking');

        $this->assertSame('50.00', $panel['collect_amount']);
        $this->assertSame('150.00', $panel['due_amount']);

        /* The deposit arrives. What is left is simply the balance, and that
           is what the panel offers next. */
        $after = $this->postJson(route('bookings.pay', $panel['id']), [
            'method' => 'cash', 'amount' => '50.00', 'manual' => true,
        ])->assertCreated()->json('booking');

        $this->assertSame('partial', $after['payment_status']);
        $this->assertSame('Partially paid', $after['payment_status_label']);
        $this->assertSame('100.00', $after['collect_amount']);
        $this->assertSame('100.00', $after['due_amount']);

        $settled = $this->postJson(route('bookings.pay', $panel['id']), [
            'method' => 'cash', 'amount' => '100.00', 'manual' => true,
        ])->assertCreated()->json('booking');

        $this->assertSame('paid', $settled['payment_status']);
        $this->assertSame(0, $settled['due_minor']);
    }

    /**
     * The booking's own page can take the balance. A booking is very often
     * paid for somewhere other than the screen it was taken on.
     */
    public function test_the_booking_page_offers_take_payment_until_it_is_settled(): void
    {
        $client = $this->client();
        $service = $this->service('Cut', 45, 5000);

        $panel = $this->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
        ])->assertCreated()->json('booking');

        $this->get(route('bookings.show', $panel['id']))
            ->assertOk()
            ->assertSee('data-vue-component="TakePayment"', false)
            ->assertSee('Take Payment');

        $this->postJson(route('bookings.pay', $panel['id']), [
            'method' => 'cash', 'amount' => '50.00', 'manual' => true,
        ])->assertCreated();

        /* Settled, so there is nothing left to ask for. The island decides
           which of the two states it shows from the balance it is handed. */
        $page = $this->get(route('bookings.show', $panel['id']))->assertOk();

        /* The island is handed a settled booking, so it renders "Paid in
           full" instead of the button. The payload is what the server
           decides; which of the two states it draws is the island's. */
        $page->assertSee('"due_minor":0', false)
            ->assertSee('"payment_status":"paid"', false);
    }

    /**
     * Take Deposit collects the deposit, never the whole bill.
     *
     * Charging the total because that is what the booking is worth would be
     * the screen taking money the client was told they did not owe yet.
     */
    public function test_take_deposit_collects_the_deposit_and_leaves_the_balance(): void
    {
        $client = $this->client();
        $service = $this->service('Balayage', 180, 24000);

        $panel = $this->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_type' => 'deposit',
            'deposit' => '64.80',
            'collection_method' => 'collect-now',
        ])->assertCreated()->json('booking');

        /* The booking is worth the whole bill and always says so. */
        $this->assertSame('$240.00', $panel['total']);
        /* What the till is asked for is the deposit alone. */
        $this->assertSame('64.80', $panel['collect_amount']);
        $this->assertSame(6480, $panel['collect_minor']);
        $this->assertSame('$175.20', $panel['remaining']);

        $after = $this->postJson(route('bookings.pay', $panel['id']), [
            'method' => 'cash', 'amount' => $panel['collect_amount'], 'manual' => true,
        ])->assertCreated()->json('booking');

        $this->assertSame('partial', $after['payment_status']);
        $this->assertSame('Partially paid', $after['payment_status_label']);
        $this->assertSame('$64.80', $after['paid']);
        $this->assertSame('$175.20', $after['due']);
        /* The deposit is collected once. What to collect now is simply what
           is left. */
        $this->assertSame('175.20', $after['collect_amount']);

        /* One transaction, for the deposit. Not the total. */
        $this->assertCount(1, $after['payments']);
        $this->assertSame('$64.80', $after['payments'][0]['amount']);
    }

    /** Full Payment asks for the whole bill, which is the point of it. */
    public function test_full_payment_collects_the_whole_bill(): void
    {
        $client = $this->client();
        $service = $this->service('Balayage', 180, 24000);

        $panel = $this->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_type' => 'full',
            'collection_method' => 'collect-now',
        ])->assertCreated()->json('booking');

        $this->assertSame('240.00', $panel['collect_amount']);
        $this->assertSame(0, $panel['remaining_minor']);
    }

    /**
     * Nothing to collect means no method to collect it by.
     */
    public function test_no_payment_now_clears_the_collection_method(): void
    {
        $client = $this->client();
        $service = $this->service('Cut', 45, 5000);

        $panel = $this->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_type' => 'none',
            /* The screen hides the question; a stale answer posted with it
               is still not written down. */
            'collection_method' => 'collect-now',
        ])->assertCreated()->json('booking');

        $this->assertNull(Booking::withoutGlobalScopes()->findOrFail($panel['id'])->collection_method);
        $this->assertSame(5000, $panel['due_minor']);
    }

    /**
     * Waiving is a decision somebody has to answer for, so it records who,
     * when and why — and needs the permission for letting people off money,
     * which is not the same as the one for taking a booking.
     */
    public function test_waiving_records_who_and_why_and_needs_the_permission(): void
    {
        $client = $this->client();
        $service = $this->service('Cut', 45, 5000);

        $body = [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_type' => 'deposit',
            'deposit' => '20.00',
            'collection_method' => 'waive',
        ];

        /* No reason given. */
        $this->postJson(route('bookings.store'), $body)
            ->assertStatus(422)
            ->assertJsonValidationErrors('waiver_reason');

        $panel = $this->postJson(route('bookings.store'), $body + [
            'waiver_reason' => 'Her colour went wrong last time.',
        ])->assertCreated()->json('booking');

        $booking = Booking::withoutGlobalScopes()->findOrFail($panel['id']);

        $this->assertSame('waive', $booking->collection_method);
        $this->assertSame('Her colour went wrong last time.', $booking->waiver_reason);
        $this->assertNotNull($booking->waived_at);
        $this->assertSame($this->tenant->owner_user_id, $booking->waived_by);
        $this->assertSame('Her colour went wrong last time.', $panel['waiver']['reason']);
    }

    /**
     * Send Payment Link emails the client a link for what is being collected
     * — the deposit, where there is one — and records where it got to.
     */
    public function test_send_payment_link_emails_a_link_for_the_amount_being_collected(): void
    {
        Mail::fake();

        $client = $this->client();
        $service = $this->service('Balayage', 180, 24000);

        $panel = $this->postJson(route('bookings.store'), [
            'client_id' => $client->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '10:00',
            'services' => [$service->id],
            'payment_type' => 'deposit',
            'deposit' => '64.80',
            'collection_method' => 'link',
        ])->assertCreated()->json('booking');

        Mail::assertSent(BookingPaymentLinkMail::class);

        $link = BookingPaymentLink::withoutGlobalScopes()->firstOrFail();

        /* The deposit, not the bill. */
        $this->assertSame(6480, (int) $link->amount_minor);
        $this->assertSame('sent', $link->currentStatus());
        $this->assertSame($client->email, $link->sent_to);
        $this->assertSame(64, strlen($link->token));
        $this->assertSame('Sent', $panel['links'][0]['status_label']);

        /* The client opens it. Outside auth — they are not a StyleDesk user
           and never will be — and it shows the appointment and nothing else. */
        $this->get(route('booking.pay-link', ['token' => $link->token]))
            ->assertOk()
            ->assertSee($panel['reference'])
            ->assertSee('$64.80');

        $this->assertSame('opened', $link->fresh()->currentStatus());

        /* Money arriving is the only thing that can make a link paid. */
        $this->postJson(route('bookings.pay', $panel['id']), [
            'method' => 'cash', 'amount' => '64.80', 'manual' => true,
        ])->assertCreated();

        $this->assertSame('paid', $link->fresh()->currentStatus());
    }

    /** A token nobody issued reaches nothing. */
    public function test_an_unknown_payment_link_is_not_found(): void
    {
        $this->get(route('booking.pay-link', ['token' => str_repeat('a', 64)]))->assertNotFound();
    }

    /** Somebody who may not take a booking may not look the book up either. */
    public function test_the_match_check_needs_permission_to_take_bookings(): void
    {
        $stranger = User::create([
            'first_name' => 'Ola', 'last_name' => 'Ade',
            'email' => 'ola-match@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $stranger->markEmailAsVerified();
        $stranger->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($stranger)
            ->postJson(route('bookings.clients.match'), ['mobile' => '+1 305 555 0100'])
            ->assertForbidden();
    }

    /** Somebody who may not take a booking may not start one either. */
    public function test_the_autosave_needs_permission_to_take_bookings(): void
    {
        $client = $this->client();

        $stranger = User::create([
            'first_name' => 'Ola', 'last_name' => 'Ade',
            'email' => 'ola@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $stranger->markEmailAsVerified();
        $stranger->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($stranger)
            ->postJson(route('bookings.draft'), ['client_id' => $client->id])
            ->assertForbidden();

        $this->assertSame(0, BookingLead::withoutGlobalScopes()->count());
    }
}
