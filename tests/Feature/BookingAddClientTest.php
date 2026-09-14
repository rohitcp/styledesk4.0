<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\Location;
use App\Models\LoyaltySettings;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LoyaltyPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Add Client dialog on the booking screen.
 *
 * A shortcut past the full client form, not past its rules. The three things
 * it has to get right are the ones a receptionist cannot fix afterwards: a
 * number stored so it can never be matched again, an address nobody can be
 * reached at, and a second record for somebody already on file.
 */
class BookingAddClientTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme-addclient', 'country_code' => 'US']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        Location::withoutGlobalScopes()->create([
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function loyalty(array $overrides = []): LoyaltySettings
    {
        return LoyaltySettings::updateOrCreate(
            ['tenant_id' => $this->tenant->getTenantKey()],
            array_merge(LoyaltySettings::defaults(), ['is_enabled' => true], $overrides),
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function add(array $extra = []): TestResponse
    {
        return $this->postJson(route('bookings.clients.store'), array_merge([
            'first_name' => 'Mia',
            'last_name' => 'Baker',
            'mobile' => '(201) 555-1234',
            'mobile_country' => 'US',
        ], $extra));
    }

    private function existing(string $first, ?string $mobile = null, ?string $email = null): Client
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => $first, 'last_name' => 'Hart', 'status' => 'active',
        ]);

        if ($mobile !== null) {
            $client->syncPhones([['number' => $mobile, 'country' => 'US', 'type' => 'mobile', 'is_primary' => true]]);
        }

        if ($email !== null) {
            $client->syncEmails([['email' => $email, 'type' => 'personal', 'is_primary' => true]]);
        }

        return $client->refresh();
    }

    // ------------------------------------------------------------- the number

    public function test_the_number_is_stored_normalised_beside_what_was_typed(): void
    {
        $this->add()->assertCreated();

        $phone = Client::withoutGlobalScopes()->sole()->phones()->sole();

        $this->assertSame('(201) 555-1234', $phone->number, 'Read back as the desk typed it.');
        $this->assertSame('+12015551234', $phone->number_e164);
    }

    public function test_the_chosen_country_decides_what_a_bare_number_means(): void
    {
        $this->add(['mobile' => '020 7946 0100', 'mobile_country' => 'GB'])->assertCreated();

        $this->assertSame('+442079460100', Client::withoutGlobalScopes()->sole()->phones()->sole()->number_e164);
    }

    public function test_a_half_typed_number_cannot_be_saved(): void
    {
        $this->add(['mobile' => '201555'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mobile');

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------ the address

    public function test_an_address_that_is_not_an_address_cannot_be_saved(): void
    {
        $this->add(['email' => 'mia@salon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
    }

    public function test_an_address_is_stored_lowercased_and_trimmed(): void
    {
        $this->add(['email' => '  Mia.Baker@Example.COM  '])->assertCreated();

        $this->assertSame('mia.baker@example.com', Client::withoutGlobalScopes()->sole()->email);
    }

    // ---------------------------------------------------------- duplicates

    /**
     * The same number, written two ways.
     *
     * This is the case the check exists for: matching on the digits as typed
     * used to call these two different people.
     */
    public function test_a_number_already_on_file_is_caught_however_it_was_written(): void
    {
        $existing = $this->existing('Amelia', '+1 201-555-1234');

        $response = $this->add(['mobile' => '(201) 555-1234'])->assertStatus(409);

        $this->assertSame($existing->id, $response->json('duplicates.0.id'));
        $this->assertSame(1, Client::withoutGlobalScopes()->count(), 'Nothing created while the warning stands.');
    }

    public function test_an_address_already_on_file_is_caught(): void
    {
        $existing = $this->existing('Amelia', null, 'mia@example.com');

        $response = $this->add(['mobile' => null, 'email' => 'MIA@example.com'])->assertStatus(409);

        $this->assertSame($existing->id, $response->json('duplicates.0.id'));
    }

    /**
     * A warning, never a block.
     *
     * Two people share a phone, and a receptionist who has read the match and
     * knows this is somebody else must still be able to add them.
     */
    public function test_the_warning_can_be_answered_and_the_client_added(): void
    {
        $this->existing('Amelia', '+1 201-555-1234');

        $this->add(['confirm_duplicate' => true])->assertCreated();

        $this->assertSame(2, Client::withoutGlobalScopes()->count());
    }

    /** The live check reads and never writes. */
    public function test_the_live_check_finds_the_match_without_creating_anything(): void
    {
        $existing = $this->existing('Amelia', '+1 201-555-1234');

        $this->postJson(route('bookings.clients.match'), [
            'mobile' => '(201) 555-1234',
            'country' => 'US',
        ])->assertOk()->assertJsonPath('matches.0.id', $existing->id);

        $this->assertSame(1, Client::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------- loyalty

    public function test_a_new_client_can_be_enrolled_from_the_dialog(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $response = $this->add(['loyalty_enroll' => true])->assertCreated();

        $client = Client::withoutGlobalScopes()->sole();

        $this->assertTrue($response->json('enrolled'));
        $this->assertTrue($client->isEnrolledInLoyalty());
        $this->assertSame('booking', $client->loyalty_enrollment_source);
        $this->assertSame(50, LoyaltyPoints::balanceFor($client));
    }

    public function test_a_client_added_without_the_box_is_not_enrolled(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $this->add()->assertCreated();

        $this->assertFalse(Client::withoutGlobalScopes()->sole()->isEnrolledInLoyalty());
        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    public function test_nobody_is_enrolled_while_the_scheme_is_off(): void
    {
        $this->loyalty(['is_enabled' => false, 'welcome_points' => 50]);

        $this->add(['loyalty_enroll' => true])->assertCreated();

        $this->assertFalse(Client::withoutGlobalScopes()->sole()->isEnrolledInLoyalty());
    }

    /**
     * An existing client is never given a second loyalty account.
     *
     * Choosing "use existing client" attaches them to the booking and writes
     * nothing at all — the dialog's save is never reached.
     */
    public function test_answering_the_warning_never_re_enrols_the_matched_client(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $existing = $this->existing('Amelia', '+1 201-555-1234');

        $this->add(['loyalty_enroll' => true])->assertStatus(409);

        $this->assertFalse($existing->fresh()->isEnrolledInLoyalty());
        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    /** Nothing at all is written while the dialog is merely being filled in. */
    public function test_a_refused_save_leaves_no_client_and_no_points(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $this->add(['mobile' => '201555', 'loyalty_enroll' => true])->assertStatus(422);

        $this->assertSame(0, Client::withoutGlobalScopes()->count());
        $this->assertSame(0, ClientLoyaltyPoint::withoutGlobalScopes()->count());
    }

    // -------------------------------------------------------------- the page

    public function test_the_booking_screen_ships_what_the_dialog_needs(): void
    {
        $this->loyalty(['welcome_points' => 50]);

        $this->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('phoneCountry', false)
            ->assertSee('welcome_points', false)
            ->assertSee('enroll_by_default', false);
    }
}
