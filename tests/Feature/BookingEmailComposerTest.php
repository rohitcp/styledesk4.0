<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Writing to the client from a booking's own page.
 *
 * The same composer the client profile opens, fixed to this appointment:
 * everything written from here is about it, so the templates render against
 * it from the start and the Related booking field has nothing left to ask.
 */
class BookingEmailComposerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Acme Salon', 'slug' => 'acme-bkemail', 'country_code' => 'US',
            'client_email_enabled' => true, 'email_provider' => 'styledesk',
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

    private function client(?string $email = 'mia@example.com'): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
            'email' => $email,
        ]);
    }

    private function booking(?Client $client): Booking
    {
        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client?->id,
            'guest_name' => $client === null ? 'Tom at the door' : null,
            'location_id' => $this->location->id,
            'date' => now()->toDateString(),
            'starts_at' => '10:00',
            'ends_at' => '10:45',
            'minutes' => 45,
            'status' => 'completed',
            'currency_code' => 'USD',
            'subtotal_minor' => 5000,
            'total_minor' => 5000,
            'source' => 'front-desk',
        ]);
    }

    public function test_the_booking_page_offers_the_composer(): void
    {
        $booking = $this->booking($this->client());

        $content = $this->get(route('bookings.show', $booking))->assertOk()->getContent();

        $this->assertStringContainsString('data-send-email', $content);
        $this->assertStringContainsString('data-email-drawer', $content);
        $this->assertStringContainsString(__('client_email.send.action'), $content);
    }

    /**
     * Fixed to this appointment.
     *
     * The script is handed the booking's id, which is what renders the
     * templates against it and attaches the message to it.
     */
    public function test_the_composer_is_scoped_to_this_booking(): void
    {
        $booking = $this->booking($this->client());

        $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('const fixedBooking = '.$booking->id, false);
    }

    /** The client profile's own composer asks instead, so it is fixed to none. */
    public function test_the_client_profile_composer_is_fixed_to_nothing(): void
    {
        $client = $this->client();

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('const fixedBooking = null', false);
    }

    /**
     * A walk-in nobody put on the book has nowhere to file it.
     *
     * Their address is on the booking rather than on a record this composer
     * can reach, and the email log is keyed to a client.
     */
    public function test_a_booking_with_no_client_record_offers_nothing(): void
    {
        $booking = $this->booking(null);

        $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('data-send-email', false);
    }

    /** And neither does a client with no address to write to. */
    public function test_a_client_with_no_address_offers_nothing(): void
    {
        $booking = $this->booking($this->client(null));

        $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('data-send-email', false);
    }

    /**
     * A reader who cannot open the booking never reaches the question.
     *
     * The composer is gated on `email.send` in the markup, but the page is
     * gated first and more broadly — so this asserts where the wall actually
     * is rather than pretending the inner check is what stops them.
     */
    public function test_a_reader_who_cannot_open_the_booking_never_sees_it(): void
    {
        $booking = $this->booking($this->client());

        $outsider = User::create([
            'first_name' => 'Robin', 'last_name' => 'Diaz',
            'email' => 'robin@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $outsider->markEmailAsVerified();
        $outsider->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $this->actingAs($outsider->fresh())
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
    }
}
