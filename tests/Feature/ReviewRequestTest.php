<?php

namespace Tests\Feature;

use App\Jobs\SendReviewRequest;
use App\Mail\ReviewRequestMail;
use App\Models\Booking;
use App\Models\BookingReview;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\Location;
use App\Models\ReviewSettings;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Asking a client what they thought, and what they answer.
 *
 * Two halves that meet at a token. The business's half is the Complete button
 * and everything it sets off; the client's half is a page they reach without
 * signing in, holding a link that is the only thing identifying them.
 *
 * What most of these tests are really guarding is that nobody is asked twice
 * and that an unhappy client is never pushed at a public listing — the two
 * failures that would cost a salon more than the feature is worth.
 */
class ReviewRequestTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-10-12';

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 14:00:00');

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ----------------------------------------------------------- the set-up

    private function owner(): User
    {
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

    private function reviewsOn(array $overrides = []): ReviewSettings
    {
        return ReviewSettings::create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'is_enabled' => true,
            'delay' => '1h',
            'channel' => 'email',
            'google_enabled' => true,
        ]);
    }

    private function booking(string $status = 'arrived', array $clientOverrides = []): Booking
    {
        /* The overrides on the left: `+` keeps the left operand's keys, so
           putting the defaults there would silently ignore every override. */
        $client = Client::withoutGlobalScopes()->create($clientOverrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Mia', 'last_name' => 'Baker', 'status' => 'active',
            'email' => 'mia@example.test', 'comm_email' => true,
        ]);

        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Susan', 'last_name' => 'Pena',
            'email' => 'susan@acme.test', 'role' => 'service-provider',
            'location_id' => $this->location->id,
            'is_active' => true, 'provides_services' => true,
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut', 'duration_minutes' => 60, 'is_active' => true,
        ]);

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'date' => self::DATE,
            'starts_at' => '11:30', 'ends_at' => '12:30', 'minutes' => 60,
            'status' => $status, 'total_minor' => 4500, 'currency_code' => 'USD',
        ]);

        $booking->services()->create([
            'service_id' => $service->id, 'name' => 'Cut',
            'minutes' => 60, 'price_minor' => 4500,
        ]);

        return $booking->fresh();
    }

    /** A review already sitting there, answered or not. */
    private function review(array $overrides = []): BookingReview
    {
        $booking = $overrides['booking'] ?? $this->booking('completed');
        unset($overrides['booking']);

        return BookingReview::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'staff_id' => $booking->staff_id,
            'location_id' => $booking->location_id,
            'token' => BookingReview::newToken(),
            'channel' => 'email',
            'scheduled_for' => now(),
            'status' => 'new',
        ]);
    }

    // -------------------------------------------------------- completing it

    public function test_completing_an_arrived_booking_schedules_a_review_request(): void
    {
        Queue::fake();
        $this->reviewsOn();
        $booking = $this->booking('arrived');

        $this->actingAs($this->owner())
            ->post(route('bookings.complete', $booking))
            ->assertRedirect();

        $this->assertSame('completed', $booking->fresh()->status);

        $review = BookingReview::withoutGlobalScopes()->where('booking_id', $booking->id)->first();

        $this->assertNotNull($review);
        $this->assertNull($review->rating, 'A request nobody has answered is not a one-star review.');
        $this->assertSame(64, strlen((string) $review->token));
        /* Copied off the booking, not read through it: the review is about
           the visit that happened. */
        $this->assertSame($booking->staff_id, $review->staff_id);
        $this->assertSame($booking->location_id, $review->location_id);
        $this->assertNotNull($review->service_id);
        $this->assertTrue($review->scheduled_for->equalTo(now()->addHour()));

        Queue::assertPushed(SendReviewRequest::class);
    }

    public function test_completing_writes_the_clients_own_history(): void
    {
        Queue::fake();
        $booking = $this->booking('arrived');

        $this->actingAs($this->owner())->post(route('bookings.complete', $booking));

        $this->assertTrue(
            ClientActivity::withoutGlobalScopes()
                ->where('client_id', $booking->client_id)
                ->where('type', 'booking.completed')
                ->exists(),
        );
    }

    public function test_a_booking_that_was_never_checked_in_cannot_be_completed(): void
    {
        Queue::fake();
        $this->reviewsOn();
        $booking = $this->booking('confirmed');

        $this->actingAs($this->owner())
            ->post(route('bookings.complete', $booking))
            ->assertStatus(422);

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(0, BookingReview::withoutGlobalScopes()->count());
    }

    public function test_nothing_is_scheduled_when_reviews_are_switched_off(): void
    {
        Queue::fake();
        $booking = $this->booking('arrived');

        $this->actingAs($this->owner())->post(route('bookings.complete', $booking));

        /* The appointment still finished. Only the asking is off. */
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(0, BookingReview::withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_client_with_no_address_is_not_asked(): void
    {
        Queue::fake();
        $this->reviewsOn();
        $booking = $this->booking('arrived', ['email' => null, 'comm_email' => false]);

        $this->actingAs($this->owner())->post(route('bookings.complete', $booking));

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(0, BookingReview::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------ sending it

    public function test_the_job_sends_the_request_once_and_stamps_it(): void
    {
        Mail::fake();
        $this->reviewsOn();
        $review = $this->review();

        (new SendReviewRequest($review))->handle();

        Mail::assertSent(ReviewRequestMail::class);
        $this->assertNotNull($review->fresh()->sent_at);

        /* Run again — a manual resend queued behind this one, say. Nothing
           goes out: two identical emails in one inbox is the failure. */
        Mail::fake();
        (new SendReviewRequest($review->fresh()))->handle();
        Mail::assertNothingSent();
    }

    public function test_the_job_honours_reviews_being_switched_off_after_it_was_queued(): void
    {
        Mail::fake();
        $settings = $this->reviewsOn();
        $review = $this->review();

        $settings->forceFill(['is_enabled' => false])->save();

        (new SendReviewRequest($review))->handle();

        Mail::assertNothingSent();
        $this->assertNull($review->fresh()->sent_at);
    }

    // ----------------------------------------------------- the client's page

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->get(route('reviews.show', ['token' => str_repeat('a', 64)]))->assertNotFound();
    }

    public function test_the_page_asks_the_question_and_does_not_need_a_login(): void
    {
        $review = $this->review();

        $this->get(route('reviews.show', ['token' => $review->token]))
            ->assertOk()
            ->assertSee(__('reviews.page.question'))
            ->assertSee('name="rating"', false);
    }

    public function test_a_star_tapped_in_the_email_is_preselected_but_not_stored(): void
    {
        $review = $this->review();

        $this->get(route('reviews.show', ['token' => $review->token, 'rating' => 5]))
            ->assertOk()
            ->assertSee(__('reviews.rating_labels.5'));

        /* A GET must never write a review: every mail scanner between the
           salon and the client would leave five stars behind. */
        $this->assertNull($review->fresh()->rating);
        $this->assertNull($review->fresh()->submitted_at);
    }

    public function test_a_happy_review_is_stored_and_offered_the_google_link(): void
    {
        $this->reviewsOn();
        $this->location->forceFill(['google_review_url' => 'https://g.page/r/acme'])->save();
        $review = $this->review();

        $this->post(route('reviews.store', ['token' => $review->token]), [
            'rating' => 5,
            'comment' => 'Susan was wonderful.',
            'recommend' => 'yes',
        ])->assertRedirect(route('reviews.show', ['token' => $review->token]));

        $review = $review->fresh();
        $this->assertSame(5, $review->rating);
        $this->assertSame('Susan was wonderful.', $review->comment);
        $this->assertSame('yes', $review->recommend);
        $this->assertNotNull($review->submitted_at);
        /* The column the client panel has always read, kept in step. */
        $this->assertNotNull($review->reviewed_at);

        $this->get(route('reviews.show', ['token' => $review->token]))
            ->assertOk()
            ->assertSee(__('reviews.page.google_cta'));
    }

    public function test_an_unhappy_review_is_never_pushed_at_a_public_listing(): void
    {
        $this->reviewsOn();
        $this->location->forceFill(['google_review_url' => 'https://g.page/r/acme'])->save();
        $review = $this->review();

        $this->post(route('reviews.store', ['token' => $review->token]), [
            'rating' => 2,
            'comment' => 'Waited forty minutes.',
            'contact_requested' => 1,
        ])->assertRedirect();

        $review = $review->fresh();
        $this->assertSame(2, $review->rating);
        $this->assertTrue($review->contact_requested);
        $this->assertTrue($review->needsAttention());

        $this->get(route('reviews.show', ['token' => $review->token]))
            ->assertOk()
            ->assertDontSee(__('reviews.page.google_cta'))
            ->assertSee(__('reviews.page.thanks_contact'));

        /* And the route itself refuses, not only the button. */
        $this->get(route('reviews.google', ['token' => $review->token]))->assertNotFound();
    }

    public function test_the_link_cannot_be_used_to_write_a_second_review(): void
    {
        $review = $this->review();

        $this->post(route('reviews.store', ['token' => $review->token]), ['rating' => 5]);
        $this->post(route('reviews.store', ['token' => $review->token]), ['rating' => 1])
            ->assertRedirect();

        $this->assertSame(5, $review->fresh()->rating);

        $this->get(route('reviews.show', ['token' => $review->token]))
            ->assertOk()
            ->assertSee(__('reviews.page.already'));
    }

    public function test_going_to_google_is_recorded(): void
    {
        $this->reviewsOn();
        $this->location->forceFill(['google_review_url' => 'https://g.page/r/acme'])->save();
        $review = $this->review();

        $this->post(route('reviews.store', ['token' => $review->token]), ['rating' => 5]);

        $this->get(route('reviews.google', ['token' => $review->token]))
            ->assertRedirect('https://g.page/r/acme');

        $this->assertNotNull($review->fresh()->google_opened_at);
    }

    // ------------------------------------------------------------- settings

    public function test_the_settings_screen_renders(): void
    {
        $this->reviewsOn();

        $this->actingAs($this->owner())
            ->get(route('settings.reviews.index'))
            ->assertOk()
            ->assertSee(__('reviews.settings.enable'))
            /* Offered and inert: the screen says what is planned without
               letting anybody switch on a message that never arrives. */
            ->assertSee(__('reviews.settings.coming_soon'))
            /* The Google card is held back. Everything behind it still works
               — see the review page tests — but no branch can be given a URL
               from here, so no client is offered the button. */
            ->assertDontSee(__('reviews.settings.google'));
    }

    public function test_the_settings_screen_shows_nothing_but_the_switch_until_reviews_are_on(): void
    {
        /* No settings row at all — a business that has never opened the
           screen, which is how everybody arrives here the first time. */
        $this->actingAs($this->owner())
            ->get(route('settings.reviews.index'))
            ->assertOk()
            ->assertSee(__('reviews.settings.enable'))
            ->assertSee(__('reviews.settings.disabled_note'))
            ->assertDontSee(__('reviews.settings.timing'))
            ->assertDontSee(__('reviews.settings.channel'));
    }

    public function test_switching_reviews_off_keeps_the_timing_that_was_chosen(): void
    {
        $this->reviewsOn(['delay' => 'next_day']);

        /* What the switch's own form posts: the toggle, and the rest as
           hidden fields. Turning the asking off must not lose the answer. */
        $this->actingAs($this->owner())
            ->patch(route('settings.reviews.update'), [
                'is_enabled' => 0,
                'delay' => 'next_day',
                'channel' => 'email',
                'google_enabled' => 1,
            ])->assertRedirect();

        $settings = ReviewSettings::forTenant($this->tenant->fresh());

        $this->assertFalse($settings->is_enabled);
        $this->assertSame('next_day', $settings->delay);
    }

    /**
     * The endpoint, not the screen.
     *
     * The Google controls are held back from the view, so nothing posts these
     * fields today. The endpoint keeps accepting them and this keeps proving
     * it, because the card coming back must not need the server rewritten.
     */
    public function test_the_settings_screen_saves_the_timing_channel_and_google_links(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.reviews.update'), [
                'is_enabled' => 1,
                'delay' => 'next_day',
                'channel' => 'email',
                'google_enabled' => 1,
                'google_urls' => [$this->location->id => 'https://g.page/r/acme'],
            ])->assertRedirect();

        $settings = ReviewSettings::forTenant($this->tenant->fresh());

        $this->assertTrue($settings->is_enabled);
        $this->assertSame('next_day', $settings->delay);
        $this->assertSame('https://g.page/r/acme', $this->location->fresh()->google_review_url);
    }

    public function test_a_channel_nothing_can_deliver_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.reviews.update'), [
                'is_enabled' => 1,
                'delay' => '1h',
                'channel' => 'sms',
            ])->assertSessionHasErrors('channel');
    }
}
