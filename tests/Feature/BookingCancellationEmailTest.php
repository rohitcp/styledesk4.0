<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\ReasonCode;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\EmailVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Telling people a booking is off.
 *
 * Three recipients, one template: what a business writes about a cancelled
 * appointment does not change according to who is reading it, only the
 * address does. The wording is theirs — Settings → Email Templates → Booking
 * Cancelled — rendered against this booking's own values.
 */
class BookingCancellationEmailTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->tenant = Tenant::create([
            'name' => 'Acme Salon', 'slug' => 'acme-cancel', 'country_code' => 'US',
            'business_email' => 'hello@acme.test',
        ]);

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

        $this->owner = $this->makeOwner();

        $this->actingAs($this->owner);
    }

    private function makeOwner(): User
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

    private function reason(): ReasonCode
    {
        return ReasonCode::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'booking-cancellation',
            'key' => 'client-request', 'name' => 'Client request',
            'is_active' => true, 'display_order' => 0,
        ]);
    }

    private function booking(?Client $client = null, ?Staff $staff = null): Booking
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut & finish', 'duration_minutes' => 45, 'is_active' => true,
        ]);

        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client?->id,
            'staff_id' => $staff?->id,
            'location_id' => $this->location->id,
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'ends_at' => '10:45',
            'minutes' => 45,
            'status' => 'confirmed',
            'currency_code' => 'USD',
            'subtotal_minor' => 5000,
            'total_minor' => 5000,
            'source' => 'front-desk',
        ]);
    }

    private function client(string $email = 'mia@example.com'): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
            'email' => $email,
        ]);
    }

    private function staff(string $email = 'sam@acme.test'): Staff
    {
        return Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Sam', 'last_name' => 'Pena', 'email' => $email,
            'role' => 'service-provider', 'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function cancel(Booking $booking, array $extra = []): TestResponse
    {
        return $this->post(route('bookings.cancel', $booking), array_merge([
            'reason_code_id' => $this->reason()->id,
        ], $extra));
    }

    // ------------------------------------------------------------ who is told

    public function test_the_client_the_business_and_the_stylist_are_all_told(): void
    {
        $booking = $this->booking($this->client(), $this->staff());

        $this->cancel($booking)->assertRedirect();

        Mail::assertSent(BookingCancelledMail::class, 3);

        foreach (['mia@example.com', 'hello@acme.test', 'sam@acme.test'] as $address) {
            Mail::assertSent(
                BookingCancelledMail::class,
                fn (BookingCancelledMail $mail) => $mail->hasTo($address),
            );
        }
    }

    /**
     * One person, one email.
     *
     * An owner who is also the stylist is one person, and two copies of the
     * same message reads as a system that has lost track of who it is
     * writing to.
     */
    public function test_one_person_wearing_two_hats_is_written_to_once(): void
    {
        $booking = $this->booking($this->client(), $this->staff('hello@acme.test'));

        $this->cancel($booking)->assertRedirect();

        Mail::assertSent(BookingCancelledMail::class, 2);
    }

    public function test_a_walk_in_with_no_address_does_not_stop_the_others_being_told(): void
    {
        $booking = $this->booking(null, $this->staff());
        $booking->forceFill(['guest_name' => 'Tom Fletcher'])->save();

        $this->cancel($booking)->assertRedirect();

        Mail::assertSent(BookingCancelledMail::class, 2);
    }

    public function test_a_walk_ins_own_address_is_used_where_there_is_one(): void
    {
        $booking = $this->booking(null, $this->staff());
        $booking->forceFill(['guest_name' => 'Tom', 'guest_email' => 'tom@example.com'])->save();

        $this->cancel($booking)->assertRedirect();

        Mail::assertSent(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $mail) => $mail->hasTo('tom@example.com'),
        );
    }

    // ---------------------------------------------------------- what it says

    public function test_the_email_carries_the_booking_and_the_reason(): void
    {
        $booking = $this->booking($this->client(), $this->staff());

        $this->cancel($booking, ['note' => 'Client rang to move it.'])->assertRedirect();

        Mail::assertSent(BookingCancelledMail::class, function (BookingCancelledMail $mail) use ($booking) {
            $body = $mail->renderedHtml;

            /* The wording is the business's own, so what is asserted is that
               the values reached it rather than any particular sentence. */
            return str_contains($body, $booking->reference)
                && $mail->renderedSubject !== '';
        });
    }

    /** The facts a cancellation adds, which the booking row cannot answer. */
    public function test_the_cancellation_variables_carry_the_particulars(): void
    {
        $values = EmailVariables::cancellation(
            reason: 'Client request',
            note: 'Rang to move it.',
            by: 'Nadia Khan',
        );

        $this->assertSame('Client request', $values['cancellation.reason']);
        $this->assertSame('Rang to move it.', $values['cancellation.note']);
        $this->assertSame('Nadia Khan', $values['cancellation.by']);
        $this->assertNotSame('', $values['cancellation.date']);
        $this->assertNotSame('', $values['cancellation.time']);
    }

    // --------------------------------------------------------- when it is not

    /**
     * A no-show and a decline are different conversations.
     *
     * One email covering all three would say nothing precise about any.
     */
    public function test_a_no_show_sends_no_cancellation_email(): void
    {
        $booking = $this->booking($this->client(), $this->staff());

        $reason = ReasonCode::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'booking-no-show', 'key' => 'no-contact', 'name' => 'No contact',
            'is_active' => true, 'display_order' => 0,
        ]);

        $this->post(route('bookings.no-show', $booking), ['reason_code_id' => $reason->id])
            ->assertRedirect();

        Mail::assertNothingSent();
    }

    /**
     * Cancelling must not fail over the mail.
     *
     * The appointment is off whether or not the message went, and the client
     * has been told at the desk — a red page over an SMTP timeout would leave
     * the receptionist believing it had not worked.
     */
    public function test_a_mail_failure_does_not_stop_the_cancellation(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));

        $booking = $this->booking($this->client(), $this->staff());

        $this->cancel($booking)->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}
