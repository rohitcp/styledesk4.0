<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Booking;
use App\Models\BookingStatusChange;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\Location;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\ActivityStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What has been happening across the business.
 *
 * The feed writes no history of its own — it reads the records the app
 * already keeps — so what is pinned here is the two things that makes hard:
 * that one event is told once however many places recorded it, and that
 * nobody is shown a line about a record they could not open.
 */
class ActivityStreamTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Spa', 'slug' => 'nadia-activity', 'business_email' => 'hi@nadia.test',
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

        app(ProvisionSystemRoles::class)->forTenant($this->tenant);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);

        $this->actingAs($this->owner);
    }

    // ------------------------------------------------------------ helpers

    private function client(string $first = 'Sarah'): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => $first, 'last_name' => 'Johnson',
        ]);
    }

    private function member(array $attributes = []): Staff
    {
        return Staff::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'location_id' => $this->location->id,
            'first_name' => 'Emily', 'last_name' => 'Davis',
            'is_active' => true, 'provides_services' => true,
        ]);
    }

    private function booking(?Staff $staff = null, ?Client $client = null): Booking
    {
        return Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'location_id' => $this->location->id,
            'staff_id' => $staff?->id,
            'client_id' => $client?->id,
            'reference' => Booking::nextReference(),
            'date' => Carbon::today()->toDateString(),
            'starts_at' => '10:00', 'ends_at' => '11:00', 'minutes' => 60,
            'status' => 'confirmed',
        ]);
    }

    private function activity(array $attributes): ClientActivity
    {
        return ClientActivity::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => 'booking.created',
            'category' => 'bookings',
            'description' => 'Something happened.',
            'is_private' => false,
            'created_at' => now(),
        ]);
    }

    /** A login for a member of staff, in a given role. */
    private function userFor(Staff $member, string $email, string $roleKey): User
    {
        $user = User::create([
            'first_name' => $member->first_name, 'last_name' => $member->last_name,
            'email' => $email, 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $member->forceFill([
            'user_id' => $user->id,
            'role_id' => Role::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant->getTenantKey())
                ->where('key', $roleKey)
                ->value('id'),
        ])->save();

        return $user->fresh();
    }

    // -------------------------------------------------------------- the feed

    /**
     * The sentence that was stored is the sentence that is shown.
     *
     * The feed adds the one-word type, the icon and the link. It does not
     * rewrite what happened — a record described one way on the client's own
     * timeline and another way here would be two accounts of one event.
     */
    public function test_it_reads_what_the_application_already_recorded(): void
    {
        $client = $this->client();
        $booking = $this->booking(null, $client);

        $this->activity([
            'client_id' => $client->id,
            'booking_id' => $booking->id,
            'user_id' => $this->owner->id,
            'description' => 'New booking created for Sarah Johnson.',
        ]);

        $feed = ActivityStream::feed($this->owner);
        $item = $feed['items'][0];

        $this->assertSame('booking', $item['kind']);
        $this->assertSame('Booking', $item['kind_label']);
        $this->assertSame('calendar-days', $item['icon']);
        $this->assertSame('bookings', $item['group']);
        $this->assertSame('New booking created for Sarah Johnson.', $item['description']);
        $this->assertSame('Nadia Khan', $item['actor']);
        $this->assertSame('today', $item['day']);
        $this->assertSame(route('bookings.show', $booking->id), $item['url']);
        $this->assertSame(__('activity.links.booking'), $item['link_label']);
    }

    /**
     * One event, told once.
     *
     * Checking a client in writes to the client's timeline AND to the
     * booking's status history. Both are correct; showing both is the same
     * check-in twice on one screen, which reads as a broken product.
     */
    public function test_one_event_recorded_twice_is_shown_once(): void
    {
        $client = $this->client();
        $booking = $this->booking(null, $client);

        $this->activity([
            'client_id' => $client->id,
            'booking_id' => $booking->id,
            'type' => 'booking.checked_in',
            'description' => 'Deep Tissue Massage · Emily Davis',
        ]);

        BookingStatusChange::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'booking_id' => $booking->id,
            'from_status' => 'confirmed',
            'to_status' => 'arrived',
            'changed_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        $items = collect(ActivityStream::feed($this->owner)['items'])
            ->where('kind', 'checkin');

        $this->assertCount(1, $items);
        /* And it is the better of the two: the one that names the booking. */
        $this->assertStringContainsString($booking->reference, $items->first()['description']);
    }

    /** The reason somebody gave is half of what a cancellation says. */
    public function test_a_cancellation_carries_the_reason_it_was_given(): void
    {
        $booking = $this->booking();

        BookingStatusChange::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'booking_id' => $booking->id,
            'from_status' => 'confirmed',
            'to_status' => 'cancelled',
            'reason_label' => 'Client requested cancellation',
            'changed_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        $item = collect(ActivityStream::feed($this->owner)['items'])->firstWhere('kind', 'cancel');

        $this->assertNotNull($item);
        $this->assertStringContainsString('Client requested cancellation', $item['description']);
        $this->assertSame('calendar-xmark', $item['icon']);
    }

    /** A private note is on the client's own timeline, and is not business news. */
    public function test_a_private_note_never_reaches_the_business_feed(): void
    {
        $client = $this->client();

        $this->activity([
            'client_id' => $client->id,
            'type' => 'note.added',
            'category' => 'notes',
            'description' => 'A confidential note.',
            'is_private' => true,
        ]);

        $this->assertSame([], ActivityStream::feed($this->owner)['items']);
    }

    /** Grouped the way a reader asks the question: today, yesterday, before. */
    public function test_activity_is_grouped_by_the_day_it_happened(): void
    {
        $client = $this->client();

        $this->activity(['client_id' => $client->id, 'description' => 'Today.', 'created_at' => now()]);
        $this->activity(['client_id' => $client->id, 'description' => 'Yesterday.', 'created_at' => now()->subDay()]);
        $this->activity(['client_id' => $client->id, 'description' => 'Ages ago.', 'created_at' => now()->subWeek()]);

        $days = collect(ActivityStream::feed($this->owner)['items'])->pluck('day')->all();

        $this->assertSame(['today', 'yesterday', 'earlier'], $days);
    }

    // ----------------------------------------------------------- permission

    /**
     * A service provider sees their own work and nobody else's.
     *
     * Applied in the query rather than after it: a filter applied afterwards
     * is one somebody can page past.
     */
    public function test_a_service_provider_sees_only_activity_on_their_own_appointments(): void
    {
        $client = $this->client();
        $mine = $this->member(['first_name' => 'Emily']);
        $theirs = $this->member(['first_name' => 'Marco', 'last_name' => 'Rossi']);

        $this->activity([
            'client_id' => $client->id,
            'booking_id' => $this->booking($mine, $client)->id,
            'description' => 'Mine.',
        ]);

        $this->activity([
            'client_id' => $client->id,
            'booking_id' => $this->booking($theirs, $client)->id,
            'description' => 'Not mine.',
        ]);

        $provider = $this->userFor($mine, 'emily@styledesk.test', 'service-provider');

        $descriptions = collect(ActivityStream::feed($provider)['items'])->pluck('description')->all();

        $this->assertSame(['Mine.'], $descriptions);
    }

    /** The owner, on the same records, sees the business. */
    public function test_the_owner_sees_everybody(): void
    {
        $client = $this->client();
        $mine = $this->member(['first_name' => 'Emily']);
        $theirs = $this->member(['first_name' => 'Marco', 'last_name' => 'Rossi']);

        $this->activity(['client_id' => $client->id, 'booking_id' => $this->booking($mine, $client)->id, 'description' => 'Mine.']);
        $this->activity(['client_id' => $client->id, 'booking_id' => $this->booking($theirs, $client)->id, 'description' => 'Not mine.']);

        $this->assertCount(2, ActivityStream::feed($this->owner)['items']);
    }

    // -------------------------------------------------------------- the panel

    public function test_the_page_renders(): void
    {
        $client = $this->client();
        $this->activity(['client_id' => $client->id, 'description' => 'New booking created.']);

        $this->get(route('activity.index'))
            ->assertOk()
            ->assertSee(__('activity.title'))
            ->assertSee('data-vue-component="ActivityFeed"', false);
    }

    public function test_the_page_asks_for_its_rows_as_json(): void
    {
        $client = $this->client();
        $this->activity(['client_id' => $client->id, 'description' => 'New booking created.']);

        $this->getJson(route('activity.feed'))
            ->assertOk()
            ->assertJsonPath('items.0.description', 'New booking created.')
            ->assertJsonPath('items.0.kind_label', 'Booking');
    }

    public function test_a_filter_narrows_the_feed_to_one_kind_of_thing(): void
    {
        $client = $this->client();

        $this->activity(['client_id' => $client->id, 'description' => 'A booking.']);
        $this->activity([
            'client_id' => $client->id,
            'type' => 'note.added', 'category' => 'notes', 'description' => 'A note.',
        ]);

        $this->getJson(route('activity.feed', ['group' => 'clients']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.description', 'A note.');
    }

    /**
     * "Since I last looked" is the only question the badge answers.
     */
    public function test_marking_all_as_read_stops_the_badge(): void
    {
        $client = $this->client();
        $this->activity(['client_id' => $client->id, 'description' => 'Something.']);

        $this->assertSame(1, ActivityStream::unread($this->owner->fresh()));

        $this->postJson(route('activity.read'))
            ->assertOk()
            ->assertJsonPath('unread', 0);

        $this->assertNotNull($this->owner->fresh()->activity_seen_at);
        $this->assertSame(0, ActivityStream::unread($this->owner->fresh()));
    }

    public function test_the_feed_is_closed_to_anybody_not_signed_in(): void
    {
        auth()->logout();

        $this->getJson(route('activity.feed'))->assertUnauthorized();
    }
}
