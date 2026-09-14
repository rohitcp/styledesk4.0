<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingLead;
use App\Models\Client;
use App\Models\ClientSettings;
use App\Models\Location;
use App\Models\LoyaltySettings;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LoyaltyEnrollment;
use App\Support\LoyaltyPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The walk-in reaches the client list while the booking is still a lead.
 *
 * The record used to be written only once the appointment was confirmed, so
 * the walk-ins who never got that far — the ones who gave a number at the
 * desk and left — were lost entirely, and the same person next week was a
 * stranger again. That is the case these tests are about: what is on file
 * before anybody finishes anything.
 */
class WalkInLeadClientTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme-walkin', 'country_code' => 'US']);

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

    private function service(string $name = 'Cut & Finish', int $minor = 4500): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'duration_minutes' => 45, 'is_active' => true,
        ]);

        $service->prices()->create(['currency_code' => 'USD', 'price_minor' => $minor]);

        return $service;
    }

    /** A client already on file, with whichever contact details are given. */
    private function existing(string $first, string $last, ?string $mobile = null, ?string $email = null): Client
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => $first, 'last_name' => $last, 'status' => 'active',
        ]);

        if ($mobile !== null) {
            $client->syncPhones([['number' => $mobile, 'type' => 'mobile', 'is_primary' => true]]);
        }

        if ($email !== null) {
            $client->syncEmails([['email' => $email, 'type' => 'personal', 'is_primary' => true]]);
        }

        return $client->refresh();
    }

    /**
     * Typing into the walk-in card: the auto-save, and nothing else.
     *
     * @param  array<string, mixed>  $extra
     */
    private function walkIn(array $extra = []): TestResponse
    {
        return $this->postJson(route('bookings.draft'), array_merge([
            'source' => 'walk-in',
            'location_id' => $this->location->id,
            'services' => [$this->service()->id],
        ], $extra));
    }

    /**
     * Pressing Save walk-in details.
     *
     * @param  array<string, mixed>  $guest
     */
    private function saveWalkIn(int $leadId, array $guest): TestResponse
    {
        return $this->postJson(route('bookings.walk-in.client'), array_merge(['lead_id' => $leadId], $guest));
    }

    /**
     * The whole walk-in card: details typed, then the button pressed.
     *
     * @param  array<string, mixed>  $guest
     */
    private function typeAndSave(array $guest): TestResponse
    {
        $leadId = $this->walkIn($guest)->assertSuccessful()->json('lead.id');

        return $this->saveWalkIn($leadId, $guest);
    }

    // ------------------------------------------------------ the whole point

    /**
     * Typing changes nothing on the client list.
     *
     * The bug this replaces: the auto-save fired on a debounce as somebody
     * typed, and it created the client. A number is half-typed for most of
     * the time it is being entered, so the list filled from the front door
     * with strangers nobody could ring, and the duplicate lookup attached
     * bookings to whoever the first digits happened to match.
     */
    public function test_typing_walk_in_details_creates_nobody(): void
    {
        $this->walkIn([
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
            'guest_email' => 'tom@example.com',
        ])->assertCreated();

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
        $this->assertNull(BookingLead::withoutGlobalScopes()->sole()->client_id);
    }

    public function test_typing_never_attaches_the_lead_to_an_existing_client(): void
    {
        $existing = $this->existing('Marie', 'Love', '+1 281 206 3165');

        $this->walkIn([
            'guest_name' => 'Marie Love',
            'guest_phone' => '281-206-3165',
        ])->assertCreated();

        /* The match may be shown; it may not be acted on. */
        $this->assertNull(BookingLead::withoutGlobalScopes()->sole()->client_id);
        $this->assertSame(1, Client::withoutGlobalScopes()->count());
        $this->assertSame('Marie', $existing->fresh()->first_name);
    }

    /**
     * A half-typed address is not refused while it is being typed.
     *
     * "The guest email field must be a valid email address." arrived once per
     * keystroke on the way to a whole address, and took the rest of the
     * booking's auto-save down with it.
     */
    public function test_a_half_typed_contact_detail_does_not_refuse_the_auto_save(): void
    {
        $this->walkIn([
            'guest_name' => 'Tom Fletcher',
            'guest_email' => 'tom@ex',
            'guest_phone' => '973555',
        ])->assertCreated();

        $lead = BookingLead::withoutGlobalScopes()->sole();

        /* Kept as typed. The lead is a scratchpad; what is on it is held to
           its shape when it is used, not while it is being written. */
        $this->assertSame('tom@ex', $lead->guest_email);
        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }

    /** The lookup runs as they type, and reads. */
    public function test_the_duplicate_lookup_shows_a_match_without_touching_it(): void
    {
        $existing = $this->existing('Marie', 'Love', '+1 281 206 3165');

        $this->postJson(route('bookings.clients.match'), [
            'name' => 'Marie Love',
            'mobile' => '281-206-3165',
        ])->assertOk()->assertJsonPath('matches.0.id', $existing->id);

        $this->assertSame(1, Client::withoutGlobalScopes()->count());
        $this->assertSame(0, BookingLead::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------- save walk-in details

    public function test_saving_walk_in_details_puts_them_on_the_client_list(): void
    {
        $response = $this->typeAndSave([
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
        ])->assertOk();

        $client = Client::withoutGlobalScopes()->sole();

        $this->assertSame('Tom', $client->first_name);
        $this->assertSame('Fletcher', $client->last_name);
        $this->assertSame('walk_in', $client->source);
        $this->assertSame('(973) 555-1234', $client->mobile, 'The number is kept as the desk typed it.');

        $this->assertSame($client->id, BookingLead::withoutGlobalScopes()->sole()->client_id);
        $this->assertTrue($response->json('walk_in_client.created'));

        /* And nothing has been booked. That is the case this exists for. */
        $this->assertSame(0, Booking::withoutGlobalScopes()->count());
    }

    public function test_the_saved_client_appears_on_the_clients_screen(): void
    {
        $this->typeAndSave(['guest_name' => 'Tom Fletcher', 'guest_phone' => '(973) 555-1234'])->assertOk();

        /* The listing itself, not the page around it: /clients renders a grid
           and fetches its rows from clients.data, so asserting on the HTML
           would pass whether or not the client was ever in the list. */
        $this->getJson(route('clients.data'))
            ->assertOk()
            ->assertSee('Tom Fletcher');
    }

    public function test_the_walk_in_is_recorded_against_the_location_it_walked_into(): void
    {
        $this->typeAndSave(['guest_name' => 'Tom Fletcher', 'guest_phone' => '(973) 555-1234'])->assertOk();

        $this->assertSame($this->location->id, Client::withoutGlobalScopes()->sole()->preferred_location_id);
    }

    // --------------------------------------------------------- what is kept

    public function test_the_number_is_stored_normalised_beside_the_typed_one(): void
    {
        $this->typeAndSave(['guest_name' => 'Tom Fletcher', 'guest_phone' => '(973) 555-1234'])->assertOk();

        $phone = Client::withoutGlobalScopes()->sole()->phones()->sole();

        $this->assertSame('(973) 555-1234', $phone->number);
        $this->assertSame('+19735551234', $phone->number_e164);
    }

    public function test_the_email_is_stored_lowercased_and_trimmed(): void
    {
        $this->typeAndSave([
            'guest_name' => 'Tom Fletcher',
            'guest_email' => '  Tom.Fletcher@Example.COM  ',
        ])->assertOk();

        $this->assertSame('tom.fletcher@example.com', Client::withoutGlobalScopes()->sole()->email);
    }

    // ------------------------------- validation, at the moment it is pressed

    public function test_a_half_typed_number_is_refused_on_save_and_puts_nobody_on_file(): void
    {
        $leadId = $this->walkIn(['guest_name' => 'Tom Fletcher'])->assertCreated()->json('lead.id');

        $this->saveWalkIn($leadId, ['guest_name' => 'Tom Fletcher', 'guest_phone' => '973555'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('guest_phone');

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
        /* The lead survives the refusal: only the client record did not
           happen, and the rest of the booking is still being written. */
        $this->assertSame(1, BookingLead::withoutGlobalScopes()->count());
    }

    public function test_an_address_that_is_not_an_address_is_refused_on_save(): void
    {
        $leadId = $this->walkIn(['guest_name' => 'Tom Fletcher'])->assertCreated()->json('lead.id');

        $this->saveWalkIn($leadId, ['guest_name' => 'Tom Fletcher', 'guest_email' => 'tom@salon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('guest_email');

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }

    public function test_the_refusal_says_what_is_wrong_in_words_a_receptionist_can_act_on(): void
    {
        $leadId = $this->walkIn(['guest_name' => 'Tom Fletcher'])->assertCreated()->json('lead.id');

        $this->saveWalkIn($leadId, ['guest_name' => 'Tom Fletcher', 'guest_phone' => '973555'])
            ->assertStatus(422)
            ->assertJsonPath('errors.guest_phone.0', 'Enter a valid mobile number.');
    }

    public function test_a_name_on_its_own_saves_the_lead_and_creates_nobody(): void
    {
        $this->typeAndSave(['guest_name' => 'Tom Fletcher'])->assertOk();

        $this->assertSame(1, BookingLead::withoutGlobalScopes()->count());
        $this->assertNull(BookingLead::withoutGlobalScopes()->sole()->client_id);
        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------ duplicates

    public function test_a_number_already_on_file_links_rather_than_duplicates(): void
    {
        $existing = $this->existing('Thomas', 'Fletcher', '+1 973 555 1234');

        $response = $this->typeAndSave([
            'guest_name' => 'Tom Fletcher',
            /* The same number, written the way a different receptionist
               writes it. Matching on the digits alone used to miss this. */
            'guest_phone' => '(973) 555-1234',
        ])->assertOk();

        $this->assertSame(1, Client::withoutGlobalScopes()->count());
        $this->assertSame($existing->id, BookingLead::withoutGlobalScopes()->sole()->client_id);
        $this->assertFalse($response->json('walk_in_client.created'));

        /* Their record is left as it was: the name on file stands. */
        $this->assertSame('Thomas', $existing->fresh()->first_name);
    }

    public function test_an_address_already_on_file_links_rather_than_duplicates(): void
    {
        $existing = $this->existing('Thomas', 'Fletcher', null, 'tom@example.com');

        $this->typeAndSave([
            'guest_name' => 'Tom Fletcher',
            'guest_email' => 'TOM@example.com',
        ])->assertOk();

        $this->assertSame(1, Client::withoutGlobalScopes()->count());
        $this->assertSame($existing->id, BookingLead::withoutGlobalScopes()->sole()->client_id);
    }

    public function test_a_matched_client_gains_what_their_record_was_missing(): void
    {
        $existing = $this->existing('Thomas', 'Fletcher', '+1 973 555 1234');

        $this->typeAndSave([
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
            'guest_email' => 'tom@example.com',
        ])->assertOk();

        $this->assertSame('tom@example.com', $existing->fresh()->email, 'A blank is filled in.');
    }

    public function test_a_matched_clients_existing_number_is_never_overwritten(): void
    {
        $existing = $this->existing('Thomas', 'Fletcher', '+1 973 555 1234', 'tom@example.com');

        $this->typeAndSave([
            'guest_name' => 'Tom Fletcher',
            'guest_email' => 'tom@example.com',
            /* A different number entirely. It might be their new phone, or
               their partner's, or a typo — not something to decide here. */
            'guest_phone' => '(305) 555 0100',
        ])->assertOk();

        $this->assertSame('+1 973 555 1234', $existing->fresh()->mobile);
        $this->assertSame(1, $existing->fresh()->phones()->count());
    }

    // -------------------------------------------------------------- conflict

    public function test_an_address_and_a_number_belonging_to_two_people_links_neither(): void
    {
        $byEmail = $this->existing('Sarah', 'Chen', null, 'sarah@example.com');
        $byPhone = $this->existing('S', 'Chen', '+1 973 555 1234');

        $response = $this->typeAndSave([
            'guest_name' => 'Sarah Chen',
            'guest_email' => 'sarah@example.com',
            'guest_phone' => '(973) 555-1234',
        ])->assertOk();

        /* Nothing created, nothing merged, nothing attached. */
        $this->assertSame(2, Client::withoutGlobalScopes()->count());
        $this->assertNull(BookingLead::withoutGlobalScopes()->sole()->client_id);

        $response->assertJsonPath('walk_in_client.conflict', true);

        $offered = collect($response->json('walk_in_client.matches'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$byEmail->id, $byPhone->id], $offered);
    }

    // ------------------------------------------------------------ completion

    public function test_completing_the_booking_keeps_the_client_the_save_made(): void
    {
        $service = $this->service();

        $leadId = $this->walkIn([
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
            'services' => [$service->id],
        ])->assertCreated()->json('lead.id');

        $this->saveWalkIn($leadId, [
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
        ])->assertOk();

        $client = Client::withoutGlobalScopes()->sole();

        $this->post(route('bookings.store'), [
            'lead_id' => $leadId,
            'source' => 'walk-in',
            'guest_name' => 'Tom Fletcher',
            /* Corrected at the desk on the way to confirming. Resolving a
               second time on this would have made a second record. */
            'guest_phone' => '(973) 555-9999',
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '09:30',
            'services' => [$service->id],
        ])->assertRedirect();

        $this->assertSame(1, Client::withoutGlobalScopes()->count(), 'No second record for the same person.');
        $this->assertSame($client->id, Booking::withoutGlobalScopes()->sole()->client_id);
    }

    /**
     * Confirming the booking is the other explicit act.
     *
     * A receptionist who never pressed Save walk-in details and went straight
     * to Confirm still meant it, and the walk-in still came in — so the
     * record is made then, exactly as it was before any of this. What must
     * not create one is typing.
     */
    public function test_confirming_without_pressing_save_still_puts_them_on_file(): void
    {
        $service = $this->service();

        $leadId = $this->walkIn([
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
            'services' => [$service->id],
        ])->assertCreated()->json('lead.id');

        $this->assertSame(0, Client::withoutGlobalScopes()->count());

        $this->post(route('bookings.store'), [
            'lead_id' => $leadId,
            'source' => 'walk-in',
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
            'location_id' => $this->location->id,
            'date' => '2026-09-10',
            'starts_at' => '09:30',
            'services' => [$service->id],
        ])->assertRedirect();

        $this->assertSame(1, Client::withoutGlobalScopes()->count());
    }

    /**
     * The screen carries the words it needs to refuse a detail.
     *
     * The inline errors are rendered by the Vue component from the labels
     * shipped in its props, so a key added to lang and left out of the
     * whitelist in create.blade.php is a field that fails silently.
     */
    public function test_the_booking_screen_ships_the_walk_in_validation_wording(): void
    {
        $this->get(route('bookings.create', ['walk-in' => 1]))
            ->assertOk()
            ->assertSee('guest_phone_invalid', false)
            ->assertSee('guest_email_invalid', false)
            ->assertSee('guest_conflict', false)
            /* And the endpoint the button posts to. */
            ->assertSee('saveWalkInUrl', false);
    }

    // ------------------------------------------------- country and loyalty

    /**
     * The dialling code the desk chose, not the tenant's.
     *
     * A salon in Austin taking a London client's number has to be able to say
     * so, or the number is normalised against the wrong country and matches
     * nobody ever again.
     */
    public function test_the_chosen_country_decides_what_a_bare_number_means(): void
    {
        $leadId = $this->walkIn(['guest_name' => 'Tom Fletcher'])->assertCreated()->json('lead.id');

        $this->saveWalkIn($leadId, [
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '020 7946 0100',
            'guest_country' => 'GB',
        ])->assertOk();

        $this->assertSame('+442079460100', Client::withoutGlobalScopes()->sole()->phones()->sole()->number_e164);
    }

    public function test_a_walk_in_can_be_enrolled_in_the_rewards_scheme(): void
    {
        LoyaltySettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            array_merge(LoyaltySettings::defaults(), ['is_enabled' => true, 'welcome_points' => 50]),
        );

        $leadId = $this->walkIn(['guest_name' => 'Tom Fletcher'])->assertCreated()->json('lead.id');

        $response = $this->saveWalkIn($leadId, [
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
            'loyalty_enroll' => true,
        ])->assertOk();

        $client = Client::withoutGlobalScopes()->sole();

        $this->assertTrue($response->json('walk_in_client.enrolled'));
        $this->assertTrue($client->isEnrolledInLoyalty());
        $this->assertSame('walk_in', $client->loyalty_enrollment_source);
        $this->assertSame(50, LoyaltyPoints::balanceFor($client));
    }

    public function test_a_walk_in_saved_without_the_toggle_joins_nothing(): void
    {
        LoyaltySettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            array_merge(LoyaltySettings::defaults(), ['is_enabled' => true, 'welcome_points' => 50]),
        );

        $leadId = $this->walkIn(['guest_name' => 'Tom Fletcher'])->assertCreated()->json('lead.id');

        $this->saveWalkIn($leadId, [
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
        ])->assertOk();

        $this->assertFalse(Client::withoutGlobalScopes()->sole()->isEnrolledInLoyalty());
    }

    /** A walk-in who turns out to be a member keeps the account they have. */
    public function test_a_matched_member_is_never_given_a_second_account(): void
    {
        LoyaltySettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            array_merge(LoyaltySettings::defaults(), ['is_enabled' => true, 'welcome_points' => 50]),
        );

        $existing = $this->existing('Thomas', 'Fletcher', '+1 973 555 1234');
        LoyaltyEnrollment::enroll($existing);

        $memberId = $existing->fresh()->loyalty_member_id;
        $joined = $existing->fresh()->loyalty_enrolled_at;

        $leadId = $this->walkIn(['guest_name' => 'Tom Fletcher'])->assertCreated()->json('lead.id');

        $this->saveWalkIn($leadId, [
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
            'loyalty_enroll' => true,
        ])->assertOk();

        $existing = $existing->fresh();

        $this->assertSame(1, Client::withoutGlobalScopes()->count());
        $this->assertSame($memberId, $existing->loyalty_member_id);
        $this->assertEquals($joined, $existing->loyalty_enrolled_at);
        $this->assertSame(50, LoyaltyPoints::balanceFor($existing), 'One welcome bonus, not two.');
    }

    // ----------------------------------------------------------- the setting

    public function test_a_business_that_has_turned_walk_in_creation_off_gets_no_client(): void
    {
        ClientSettings::forTenant($this->tenant)->forceFill([
            'creation_sources' => ['client_list', 'booking'],
        ])->save();

        $this->typeAndSave(['guest_name' => 'Tom Fletcher', 'guest_phone' => '(973) 555-1234'])->assertOk();

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
        $this->assertNull(BookingLead::withoutGlobalScopes()->sole()->client_id);
    }

    public function test_a_lead_already_linked_to_a_chosen_client_is_left_alone(): void
    {
        $chosen = $this->existing('Mia', 'Baker', '+1 305 555 0100');

        $leadId = $this->walkIn(['client_id' => $chosen->id])->assertCreated()->json('lead.id');

        /* Guest details typed afterwards must not retarget the lead: the
           receptionist searched and chose, which answers the question. */
        $this->saveWalkIn($leadId, [
            'guest_name' => 'Someone Else',
            'guest_phone' => '(973) 555-1234',
        ])->assertOk();

        $this->assertSame(1, Client::withoutGlobalScopes()->count());
        $this->assertSame($chosen->id, BookingLead::withoutGlobalScopes()->sole()->client_id);
    }

    /**
     * A settled lead is not a booking being worked on.
     *
     * The endpoint writes, so it takes the same view of which leads are open
     * as the auto-save does — otherwise a stale tab could put somebody on the
     * client list against a booking that was cancelled last week.
     */
    public function test_the_save_refuses_a_lead_that_is_no_longer_being_worked_on(): void
    {
        $leadId = $this->walkIn(['guest_name' => 'Tom Fletcher'])->assertCreated()->json('lead.id');

        BookingLead::withoutGlobalScopes()->find($leadId)->forceFill(['status' => 'cancelled'])->save();

        $this->saveWalkIn($leadId, [
            'guest_name' => 'Tom Fletcher',
            'guest_phone' => '(973) 555-1234',
        ])->assertNotFound();

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }
}
