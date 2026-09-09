<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingStatusChange;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\Location;
use App\Models\ReasonCode;
use App\Models\Role;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What happens to a booking after it has been taken.
 *
 * Four acts — nobody came, it was called off, it was turned down, it moved —
 * and each one has to leave behind an account of itself that nobody can
 * quietly edit later. That is what these tests are mostly about: not that the
 * status changed, but that the reason, the note, the person and the time are
 * all still there afterwards, in the words they had on the day.
 */
class BookingStatusActionsTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-10-12';

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 09:00:00');

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

        $this->location->allHours()->create([
            'effective_from' => '2000-01-01',
            'day_of_week' => (int) Carbon::parse(self::DATE)->dayOfWeek,
            'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00', 'sort_order' => 0,
        ]);

        ReasonCode::seedDefaultsFor($this->tenant);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function owner(): User
    {
        /* One owner per test, however many times it is asked for: a helper
           that inserts a second row on the second call fails on the email
           rather than on the thing being tested. */
        if ($existing = User::where('email', 'owner@styledesk.test')->first()) {
            return $existing;
        }

        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
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

    /**
     * A member of staff with exactly the permissions named, and no others.
     *
     * Their role is reached through their staff row rather than set on the
     * user, which is how StyleDesk resolves it for everybody who is not the
     * owner.
     *
     * @param  array<string, string>  $permissions
     */
    private function userWithOnly(array $permissions): User
    {
        $user = User::create([
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'limited', 'name' => 'Limited',
        ]);

        foreach ($permissions as $permission => $scope) {
            $role->permissions()->create(['permission' => $permission, 'scope' => $scope]);
        }

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id, 'role_id' => $role->id,
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'role' => 'front-desk',
            'location_id' => $this->location->id, 'is_active' => true,
        ]);

        return $user->fresh();
    }

    private function booking(string $status = 'confirmed', ?string $date = null): Booking
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut', 'duration_minutes' => 60, 'is_active' => true,
        ]);

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client->id,
            'staff_id' => $this->staff()->id,
            'location_id' => $this->location->id,
            'date' => $date ?? self::DATE,
            'starts_at' => '11:30', 'ends_at' => '12:30', 'minutes' => 60,
            'status' => $status, 'total_minor' => 4500, 'currency_code' => 'USD',
        ]);

        $booking->services()->create([
            'service_id' => $service->id, 'name' => 'Cut',
            'minutes' => 60, 'price_minor' => 4500,
        ]);

        return $booking->fresh();
    }

    private function reason(string $type, ?bool $requiresDetails = null): ReasonCode
    {
        $query = ReasonCode::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('type', $type);

        if ($requiresDetails !== null) {
            $query->where('requires_details', $requiresDetails);
        }

        return $query->orderBy('display_order')->firstOrFail();
    }

    // ------------------------------------------------------- what is offered

    /**
     * The header offers what can be done from here, and nothing else.
     */
    public function test_a_confirmed_booking_offers_no_show_cancel_and_reschedule(): void
    {
        $actions = $this->booking('confirmed')->availableActions($this->owner());

        $this->assertEqualsCanonicalizing(['no-show', 'cancelled', 'reschedule'], $actions);
    }

    /**
     * A request is turned down or moved; it is not marked absent, because
     * nobody had agreed to be anywhere yet.
     */
    public function test_a_pending_booking_offers_decline_cancel_and_reschedule(): void
    {
        $actions = $this->booking('pending')->availableActions($this->owner());

        $this->assertEqualsCanonicalizing(['cancelled', 'declined', 'reschedule'], $actions);
    }

    /**
     * Work that was done cannot be undone from a header button.
     */
    public function test_a_completed_booking_offers_nothing(): void
    {
        $this->assertSame([], $this->booking('completed')->availableActions($this->owner()));
    }

    public function test_a_cancelled_booking_offers_nothing(): void
    {
        $this->assertSame([], $this->booking('cancelled')->availableActions($this->owner()));
    }

    /**
     * An action a reader may not take is absent rather than disabled: a
     * button they can never enable is furniture.
     */
    public function test_an_action_the_reader_lacks_the_permission_for_is_not_offered(): void
    {
        $booking = $this->booking('confirmed');

        $user = $this->userWithOnly(['calendar.view' => 'all', 'appointments.cancel' => 'all']);

        $this->assertSame(['cancelled'], $booking->availableActions($user));
    }

    public function test_the_booking_page_shows_the_actions_it_offers(): void
    {
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee(__('bookings.status.no-show.action'))
            ->assertSee(__('bookings.status.cancelled.action'))
            ->assertSee(__('bookings.status.reschedule.action'))
            ->assertDontSee(__('bookings.status.declined.action'));
    }

    // --------------------------------------------------------- ending it

    public function test_a_booking_can_be_marked_as_a_no_show(): void
    {
        $booking = $this->booking('confirmed');
        $owner = $this->owner();
        $reason = $this->reason('no-show');

        $this->actingAs($owner)
            ->post(route('bookings.no-show', $booking), [
                'reason_code_id' => $reason->id,
                'note' => 'Called client but no response.',
            ])
            ->assertRedirect();

        $this->assertSame('no-show', $booking->fresh()->status);

        $entry = BookingStatusChange::withoutGlobalScopes()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame('confirmed', $entry->from_status);
        $this->assertSame('no-show', $entry->to_status);
        $this->assertSame($reason->id, $entry->reason_code_id);
        $this->assertSame($reason->name, $entry->reason_label);
        $this->assertSame('Called client but no response.', $entry->note);
        $this->assertSame($owner->id, $entry->changed_by);
        $this->assertNotNull($entry->created_at);
    }

    public function test_a_booking_can_be_cancelled(): void
    {
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->post(route('bookings.cancel', $booking), [
                'reason_code_id' => $this->reason('booking-cancellation')->id,
                'note' => 'Client is travelling and will rebook.',
            ])
            ->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_a_pending_booking_can_be_declined(): void
    {
        $booking = $this->booking('pending');

        $this->actingAs($this->owner())
            ->post(route('bookings.decline', $booking), [
                'reason_code_id' => $this->reason('booking-declined')->id,
            ])
            ->assertRedirect();

        $this->assertSame('declined', $booking->fresh()->status);
    }

    /**
     * They are allowed to do this, to a booking that is not in a state where
     * it means anything — which is a different answer from "you may not".
     */
    public function test_a_completed_booking_cannot_be_marked_as_a_no_show(): void
    {
        $booking = $this->booking('completed');

        $this->actingAs($this->owner())
            ->post(route('bookings.no-show', $booking), [
                'reason_code_id' => $this->reason('no-show')->id,
            ])
            ->assertStatus(422);

        $this->assertSame('completed', $booking->fresh()->status);
    }

    /** A confirmed booking that is called off was accepted first. */
    public function test_a_confirmed_booking_cannot_be_declined(): void
    {
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->post(route('bookings.decline', $booking), [
                'reason_code_id' => $this->reason('booking-declined')->id,
            ])
            ->assertStatus(422);
    }

    public function test_the_action_is_refused_without_the_permission(): void
    {
        $booking = $this->booking('confirmed');

        $user = $this->userWithOnly(['calendar.view' => 'all']);

        $this->actingAs($user)
            ->post(route('bookings.cancel', $booking), [
                'reason_code_id' => $this->reason('booking-cancellation')->id,
            ])
            ->assertForbidden();

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    // ------------------------------------------------------------- reasons

    public function test_a_reason_is_required(): void
    {
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->post(route('bookings.cancel', $booking), ['note' => 'No reason given.'])
            ->assertSessionHasErrors('reason_code_id');

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    /** A reason from another list is not a reason for this act. */
    public function test_a_reason_from_another_list_is_refused(): void
    {
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->post(route('bookings.cancel', $booking), [
                'reason_code_id' => $this->reason('no-show')->id,
            ])
            ->assertSessionHasErrors('reason_code_id');

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    /** Switched off is not on offer, whatever a stale page still shows. */
    public function test_a_switched_off_reason_is_refused(): void
    {
        $booking = $this->booking('confirmed');
        $reason = $this->reason('booking-cancellation');
        $reason->forceFill(['is_active' => false])->save();

        $this->actingAs($this->owner())
            ->post(route('bookings.cancel', $booking), ['reason_code_id' => $reason->id])
            ->assertSessionHasErrors('reason_code_id');
    }

    /** "Other" is not an answer on its own, so choosing it asks for one. */
    public function test_a_reason_that_asks_for_details_will_not_be_saved_without_them(): void
    {
        $booking = $this->booking('confirmed');
        $other = ReasonCode::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('type', 'booking-cancellation')
            ->where('requires_details', true)
            ->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('bookings.cancel', $booking), ['reason_code_id' => $other->id])
            ->assertSessionHasErrors('details');

        $this->assertSame('confirmed', $booking->fresh()->status);

        $this->actingAs($this->owner())
            ->post(route('bookings.cancel', $booking), [
                'reason_code_id' => $other->id,
                'details' => 'Client emigrated.',
            ])
            ->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    /**
     * A business that renames "Client Did Not Arrive" next spring has renamed
     * their list, not last March's no-show.
     */
    public function test_renaming_a_reason_afterwards_does_not_rewrite_the_history(): void
    {
        $booking = $this->booking('confirmed');
        $reason = $this->reason('no-show');

        $this->actingAs($this->owner())
            ->post(route('bookings.no-show', $booking), ['reason_code_id' => $reason->id]);

        $reason->forceFill(['name' => 'Ghosted us'])->save();

        $entry = BookingStatusChange::withoutGlobalScopes()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertNotSame('Ghosted us', $entry->reason_label);
    }

    /** An audit trail somebody can edit is not one anybody can rely on. */
    public function test_a_history_entry_cannot_be_changed_or_removed(): void
    {
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->post(route('bookings.cancel', $booking), [
                'reason_code_id' => $this->reason('booking-cancellation')->id,
            ]);

        $entry = BookingStatusChange::withoutGlobalScopes()->where('booking_id', $booking->id)->firstOrFail();

        $entry->update(['note' => 'Something else entirely']);
        $entry->delete();

        $this->assertNull($entry->fresh()->note);
        $this->assertNotNull(BookingStatusChange::withoutGlobalScopes()->find($entry->id));
    }

    // ------------------------------------------------------------ the client

    /**
     * The client's own timeline as well as the booking's: a receptionist
     * reading a profile is asking what happened to this person, not what
     * happened to a reference number.
     */
    public function test_the_client_timeline_records_it_too(): void
    {
        $booking = $this->booking('confirmed');
        $reason = $this->reason('no-show');

        $this->actingAs($this->owner())
            ->post(route('bookings.no-show', $booking), [
                'reason_code_id' => $reason->id,
                'note' => 'Called client but no response.',
            ]);

        $event = ClientActivity::withoutGlobalScopes()
            ->where('client_id', $booking->client_id)
            ->where('type', 'booking.no_show')
            ->firstOrFail();

        $this->assertSame($reason->name, $event->meta['reason']);
        $this->assertSame('Called client but no response.', $event->meta['note']);
    }

    // ----------------------------------------------------------- rescheduling

    /**
     * The same booking, at a different time. A reschedule that cancelled one
     * booking and wrote another would leave the desk chasing a deposit
     * against a reference the client was never given.
     */
    public function test_rescheduling_moves_the_appointment_and_keeps_the_booking(): void
    {
        $booking = $this->booking('confirmed');
        $reference = $booking->reference;

        $this->actingAs($this->owner())
            ->post(route('bookings.reschedule', $booking), [
                'reason_code_id' => $this->reason('booking-reschedule')->id,
                'date' => self::DATE,
                'starts_at' => '14:00',
                'note' => 'Client requested an afternoon appointment.',
            ])
            ->assertRedirect();

        $moved = $booking->fresh();

        $this->assertSame($reference, $moved->reference);
        $this->assertSame('confirmed', $moved->status);
        $this->assertSame('14:00', $moved->startsAt());
        /* The appointment moved; its length did not. */
        $this->assertSame(60, (int) $moved->minutes);
        $this->assertSame('15:00', substr((string) $moved->ends_at, 0, 5));

        $entry = BookingStatusChange::withoutGlobalScopes()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertTrue($entry->movedTheAppointment());
        $this->assertSame('11:30', substr((string) $entry->from_starts_at, 0, 5));
        $this->assertSame('14:00', substr((string) $entry->to_starts_at, 0, 5));
    }

    /** A time nobody can work is not an option; it is a rejection waiting. */
    public function test_a_time_outside_the_working_day_is_refused(): void
    {
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->post(route('bookings.reschedule', $booking), [
                'reason_code_id' => $this->reason('booking-reschedule')->id,
                'date' => self::DATE,
                'starts_at' => '22:00',
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame('11:30', $booking->fresh()->startsAt());
    }

    /**
     * Moving an appointment by half an hour must not be found to clash with
     * where it already is.
     */
    public function test_a_booking_does_not_clash_with_its_own_current_slot(): void
    {
        $booking = $this->booking('confirmed');

        $slots = $this->actingAs($this->owner())
            ->get(route('bookings.slots', $booking).'?date='.self::DATE)
            ->assertOk()
            ->json('slots');

        $this->assertContains('11:30', $slots);
    }

    public function test_the_past_cannot_be_rescheduled_into(): void
    {
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->post(route('bookings.reschedule', $booking), [
                'reason_code_id' => $this->reason('booking-reschedule')->id,
                'date' => now()->subDay()->toDateString(),
                'starts_at' => '11:30',
            ])
            ->assertSessionHasErrors('date');
    }

    // ------------------------------------------------------------- check-in

    /**
     * The front desk checks somebody in on the day they are due, and only
     * then. Checking in for Thursday's appointment on Tuesday is not early —
     * it is the wrong booking.
     */
    public function test_check_in_is_offered_only_on_the_day(): void
    {
        $today = $this->booking('confirmed', now()->toDateString());

        $this->assertContains('check-in', $today->availableActions($this->owner()));
        $this->assertNotContains('check-in', $this->booking('confirmed')->availableActions($this->owner()));
    }

    /**
     * Within the day itself nothing is enforced: a client ten minutes early
     * and one twenty minutes late are both simply here.
     */
    public function test_check_in_does_not_care_what_time_of_day_it_is(): void
    {
        $booking = $this->booking('confirmed', now()->toDateString());

        /* The appointment is at half eleven; it is nine in the morning. */
        $this->assertContains('check-in', $booking->availableActions($this->owner()));
    }

    public function test_checking_in_records_the_time_the_person_and_the_note(): void
    {
        $booking = $this->booking('confirmed', now()->toDateString());
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('bookings.check-in', $booking), ['note' => 'Client arrived 10 minutes early.'])
            ->assertRedirect();

        $this->assertSame('arrived', $booking->fresh()->status);

        $entry = $booking->fresh()->checkIn();

        $this->assertNotNull($entry);
        $this->assertSame('confirmed', $entry->from_status);
        $this->assertSame('arrived', $entry->to_status);
        $this->assertSame('Client arrived 10 minutes early.', $entry->note);
        $this->assertSame($owner->id, $entry->changed_by);
        $this->assertNotNull($entry->created_at);
        /* Arriving needs no explanation, so none was asked for. */
        $this->assertNull($entry->reason_code_id);
    }

    /** A client who is standing in the salon did not arrive twice. */
    public function test_a_booking_cannot_be_checked_in_twice(): void
    {
        $booking = $this->booking('confirmed', now()->toDateString());

        $this->actingAs($this->owner())->post(route('bookings.check-in', $booking))->assertRedirect();

        /* Refused, and not because they may not: the booking is no longer in
           a state where checking in means anything. */
        $this->actingAs($this->owner())->post(route('bookings.check-in', $booking))->assertStatus(422);

        $this->assertSame(1, BookingStatusChange::withoutGlobalScopes()
            ->where('booking_id', $booking->id)->where('to_status', 'arrived')->count());
    }

    public function test_a_cancelled_booking_cannot_be_checked_in(): void
    {
        $booking = $this->booking('cancelled', now()->toDateString());

        $this->actingAs($this->owner())
            ->post(route('bookings.check-in', $booking))
            ->assertStatus(422);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_checking_in_is_refused_without_the_permission(): void
    {
        $booking = $this->booking('confirmed', now()->toDateString());

        $this->actingAs($this->userWithOnly(['calendar.view' => 'all']))
            ->post(route('bookings.check-in', $booking))
            ->assertForbidden();

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    /**
     * The button is replaced by what happened, rather than joined by it: a
     * Check In button beside "checked in at 9:52" is an invitation to record
     * the same arrival twice.
     */
    public function test_the_page_replaces_the_button_with_the_check_in_line(): void
    {
        $booking = $this->booking('confirmed', now()->toDateString());
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertSee(__('bookings.status.check-in.action'));

        $this->actingAs($owner)->post(route('bookings.check-in', $booking));

        $entry = $booking->fresh()->checkIn();

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertSee(__('bookings.status.check-in.done_at', [
                'time' => $entry->created_at->isoFormat('h:mm A'),
                'name' => $owner->name,
            ]))
            ->assertDontSee(__('bookings.status.check-in.action'));
    }

    /**
     * Somebody who has been checked in is standing in the salon.
     */
    public function test_a_checked_in_booking_cannot_be_marked_as_a_no_show(): void
    {
        $booking = $this->booking('arrived', now()->toDateString());

        $this->assertNotContains('no-show', $booking->availableActions($this->owner()));

        $this->actingAs($this->owner())
            ->post(route('bookings.no-show', $booking), [
                'reason_code_id' => $this->reason('no-show')->id,
            ])
            ->assertStatus(422);
    }

    /** A client who arrived and then had to leave is a cancellation. */
    public function test_a_checked_in_booking_can_still_be_cancelled(): void
    {
        $booking = $this->booking('arrived', now()->toDateString());

        $this->assertContains('cancelled', $booking->availableActions($this->owner()));
    }

    /** A checked-in booking is not painted like a confirmed one. */
    public function test_checked_in_has_its_own_badge(): void
    {
        $this->assertNotSame(
            config('bookings.statuses.confirmed.class'),
            config('bookings.statuses.arrived.class'),
        );
    }

    public function test_the_client_timeline_records_the_check_in(): void
    {
        $booking = $this->booking('confirmed', now()->toDateString());

        $this->actingAs($this->owner())
            ->post(route('bookings.check-in', $booking), ['note' => 'Client arrived 10 minutes early.']);

        $event = ClientActivity::withoutGlobalScopes()
            ->where('client_id', $booking->client_id)
            ->where('type', 'booking.checked_in')
            ->firstOrFail();

        $this->assertSame('Client arrived 10 minutes early.', $event->meta['note']);
    }
}
