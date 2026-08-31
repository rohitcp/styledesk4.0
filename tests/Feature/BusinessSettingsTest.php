<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Location;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Acceptance criteria from the Business settings spec.
 */
class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio',
            'slug' => 'nadia',
            'business_email' => 'hello@nadia.test',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete',
            'completed_at' => now(),
        ]);
    }

    private function member(string $role): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $role.'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        if ($role === 'owner') {
            $this->tenant->forceFill(['owner_user_id' => $user->id])->save();
        } else {
            Staff::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->getTenantKey(),
                'user_id' => $user->id,
                'first_name' => 'Sam', 'last_name' => 'Person',
                'email' => $role.'@styledesk.test', 'role' => $role,
            ]);
        }

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nadia Hair Studio',
            'status' => 'active',
            'business_email' => 'hello@nadia.test',
        ], $overrides);
    }

    // ------------------------------------------------------- authorisation

    public static function allowedRoles(): array
    {
        return [['owner'], ['administrator']];
    }

    public static function deniedRoles(): array
    {
        return [['manager'], ['front-desk'], ['service-provider']];
    }

    #[DataProvider('allowedRoles')]
    public function test_owners_and_administrators_can_view_and_edit(string $role): void
    {
        $user = $this->member($role);

        $this->actingAs($user)->get('http://styledesk.test/settings/business')->assertOk();
        $this->actingAs($user)->get('http://styledesk.test/settings/business/edit')->assertOk();
    }

    /**
     * The spec requires both the read and the write endpoint to be protected —
     * gating only the page would leave the PATCH open to anyone signed in.
     */
    #[DataProvider('deniedRoles')]
    public function test_other_roles_are_redirected_from_both_reading_and_writing(string $role): void
    {
        $user = $this->member($role);

        $this->actingAs($user)->get('http://styledesk.test/settings/business')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)->get('http://styledesk.test/settings/business/edit')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)->patch('http://styledesk.test/settings/business', $this->validPayload(['name' => 'Hijacked']))
            ->assertRedirect(route('dashboard'));

        $this->assertSame('Nadia Hair Studio', $this->tenant->fresh()->name);
    }

    public function test_a_signed_out_visitor_cannot_reach_business_settings(): void
    {
        $this->get('http://styledesk.test/settings/business')->assertRedirect(route('login'));
        $this->patch('http://styledesk.test/settings/business', $this->validPayload())->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------ view mode

    public function test_the_view_shows_values_as_text_not_as_form_controls(): void
    {
        $this->tenant->forceFill([
            'legal_name' => 'Nadia Hair Studio Ltd',
            'support_email' => 'help@nadia.test',
        ])->save();

        $response = $this->actingAs($this->member('owner'))->get('http://styledesk.test/settings/business');

        $response->assertOk()
            ->assertSee('Nadia Hair Studio Ltd')
            ->assertSee('help@nadia.test')
            ->assertSee('Edit business');

        // Read-only means no inputs at all, not disabled ones.
        $this->assertStringNotContainsString('<input', $this->stripLayout($response->getContent()));
    }

    public function test_the_primary_address_comes_from_the_primary_location(): void
    {
        Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River Street', 'city' => 'Austin',
            'state' => 'Texas', 'postal_code' => '78701', 'country' => 'US',
            'timezone' => 'America/Chicago', 'is_primary' => true,
        ]);

        $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/settings/business')
            ->assertOk()
            ->assertSee('1 River Street')
            ->assertSee('Austin')
            ->assertSee('United States')
            // Addresses belong to Locations, so this page links out rather
            // than offering a second place to edit them.
            ->assertSee('Manage locations');
    }

    public function test_the_business_id_is_shown_but_never_editable(): void
    {
        $owner = $this->member('owner');

        $this->actingAs($owner)
            ->get('http://styledesk.test/settings/business')
            ->assertOk()
            ->assertSee($this->tenant->getTenantKey());

        // Changing it would orphan every row pointing at it, so it is not in
        // the accepted fields at all.
        $this->actingAs($owner)
            ->patch('http://styledesk.test/settings/business', $this->validPayload(['id' => 'hijacked-id']))
            ->assertRedirect(route('settings.business.show'));

        $this->assertNotNull(Tenant::find($this->tenant->getTenantKey()));
    }

    // ------------------------------------------------------------ edit mode

    public function test_saving_updates_the_business_and_returns_to_view_mode(): void
    {
        $this->seed(BusinessTypeSeeder::class);
        $type = BusinessType::where('slug', 'hair-salon')->first();

        $response = $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'legal_name' => 'nadia hair studio ltd',
                'business_category' => 'curly hair specialists',
                'description' => 'a small studio in austin.',
                'business_type_ids' => [$type->id],
                'support_email' => 'help@nadia.test',
                'booking_email' => 'bookings@nadia.test',
                'website' => 'https://nadiahair.test',
                'instagram_url' => 'https://instagram.com/nadiahair',
                'date_format' => 'd/m/Y',
                'time_format' => '24',
                'first_day_of_week' => 1,
                'default_booking_duration' => 45,
                'default_appointment_interval' => 15,
                'default_tax_behavior' => 'inclusive',
                'default_staff_assignment' => 'any',
            ]));

        $response->assertRedirect(route('settings.business.show'));
        $response->assertSessionHas('toast', [
            'type' => 'success',
            'message' => 'Business settings updated successfully.',
        ]);

        $tenant = $this->tenant->fresh();

        // The project capitalisation rule: first character only.
        $this->assertSame('Nadia hair studio ltd', $tenant->legal_name);
        $this->assertSame('Curly hair specialists', $tenant->business_category);
        $this->assertSame('d/m/Y', $tenant->date_format);
        $this->assertSame(45, (int) $tenant->default_booking_duration);
        $this->assertSame('inclusive', $tenant->default_tax_behavior);
        $this->assertSame([$type->id], $tenant->businessTypes->pluck('id')->all());
    }

    /**
     * The tenants table keeps undeclared attributes in a `data` JSON column,
     * so a field missing from Tenant::getCustomColumns() saves silently into
     * JSON and its real column stays null. Nothing about the page looks wrong
     * when that happens — the value round-trips through the model — which is
     * why this is asserted against the raw row.
     */
    public function test_every_field_lands_in_a_real_column_rather_than_the_data_json(): void
    {
        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'legal_name' => 'Nadia Ltd',
                'date_format' => 'Y-m-d',
                'default_tax_behavior' => 'exclusive',
                'tiktok_url' => 'https://tiktok.com/@nadia',
            ]));

        $row = DB::table('tenants')->where('id', $this->tenant->getTenantKey())->first();

        $this->assertSame('Nadia Ltd', $row->legal_name);
        $this->assertSame('Y-m-d', $row->date_format);
        $this->assertSame('exclusive', $row->default_tax_behavior);
        $this->assertSame('https://tiktok.com/@nadia', $row->tiktok_url);

        $data = json_decode((string) $row->data, true) ?: [];

        foreach (['legal_name', 'date_format', 'default_tax_behavior', 'tiktok_url'] as $field) {
            $this->assertArrayNotHasKey($field, $data, "[{$field}] was swept into the data JSON.");
        }
    }

    public function test_the_form_is_prepopulated_with_existing_values(): void
    {
        $this->seed(BusinessTypeSeeder::class);
        $this->tenant->forceFill(['legal_name' => 'Nadia Hair Studio Ltd', 'date_format' => 'd/m/Y'])->save();

        $response = $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/settings/business/edit');

        $response->assertOk()->assertSee('value="Nadia Hair Studio Ltd"', false);

        /**
         * The combos are Vue islands, so the current value arrives as a prop
         * rather than a selected option — and Blade's @json escapes forward
         * slashes, so the stored "d/m/Y" reaches the attribute as "d\/m\/Y".
         */
        $this->assertStringContainsString('d\\/m\\/Y', $response->getContent());
    }

    public function test_required_fields_are_enforced(): void
    {
        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', ['name' => '', 'business_email' => '', 'status' => ''])
            ->assertSessionHasErrors(['name', 'business_email', 'status']);
    }

    public function test_contact_and_presence_values_must_be_well_formed(): void
    {
        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'business_email' => 'not-an-email',
                'support_email' => 'also-not',
                // These become links on the public booking profile, so a value
                // that is not a URL is a broken link on a page clients see.
                'website' => 'nadiahair',
                'instagram_url' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors(['business_email', 'support_email', 'website', 'instagram_url']);
    }

    public function test_a_value_outside_the_offered_options_is_rejected(): void
    {
        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'date_format' => 'D-M-Y-nonsense',
                'default_tax_behavior' => 'free',
                'status' => 'archived',
            ]))
            ->assertSessionHasErrors(['date_format', 'default_tax_behavior', 'status']);
    }

    public function test_both_screens_offer_a_back_link(): void
    {
        $owner = $this->member('owner');

        // The view goes back to the directory it was opened from.
        $this->actingAs($owner)->get('http://styledesk.test/settings/business')
            ->assertOk()
            ->assertSee('Back')
            ->assertSee(route('settings.index'), false);

        // The edit screen goes back to the view, not to the directory.
        $this->actingAs($owner)->get('http://styledesk.test/settings/business/edit')
            ->assertOk()
            ->assertSee('Back')
            ->assertSee(route('settings.business.show'), false);
    }

    /**
     * The form posts over fetch, so a save answers with the address to go to
     * rather than a redirect the request follows itself. Without this the
     * script has no way to know where it landed.
     */
    public function test_an_async_save_answers_with_the_view_address(): void
    {
        $response = $this->actingAs($this->member('owner'))
            ->postJson('http://styledesk.test/settings/business', $this->validPayload([
                '_method' => 'PATCH',
                'legal_name' => 'Nadia Ltd',
            ]));

        $response->assertOk()->assertJsonPath('redirect', route('settings.business.show'));
        $response->assertSessionHas('toast');

        $this->assertSame('Nadia Ltd', $this->tenant->fresh()->legal_name);
    }

    public function test_an_async_save_reports_validation_without_redirecting(): void
    {
        // 422 with the messages, so the page can show them and keep everything
        // that was typed rather than reloading the form empty.
        $this->actingAs($this->member('owner'))
            ->postJson('http://styledesk.test/settings/business', $this->validPayload([
                '_method' => 'PATCH',
                'website' => 'not-a-url',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('website');
    }

    // ----------------------------------------------------- save failures

    /**
     * The confirmation must rest on the database write, not on the request
     * having been accepted.
     */
    public function test_a_database_failure_reports_a_plain_message_and_saves_nothing(): void
    {
        Log::spy();

        // Break the write the way a real outage would, after validation has
        // passed and the controller is committed to saving.
        DB::shouldReceive('transaction')->once()->andThrow(
            new \RuntimeException("SQLSTATE[HY000] [2002] Connection refused for user 'root'@'db-primary'")
        );

        $response = $this->actingAs($this->member('owner'))
            ->postJson('http://styledesk.test/settings/business', $this->validPayload([
                '_method' => 'PATCH',
                'legal_name' => 'Should Not Persist',
            ]));

        $response->assertStatus(500)
            ->assertJsonPath('message', "We couldn't save your changes right now. Please try again.");

        $body = $response->getContent();

        // None of the driver's words reach the browser.
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('Connection refused', $body);
        $this->assertStringNotContainsString('db-primary', $body);
        $this->assertStringNotContainsString('root', $body);

        // No success toast on a failed save.
        $response->assertSessionMissing('toast');

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context) => $message === 'Business settings could not be saved.'
                && str_contains($context['exception'], 'SQLSTATE'))
            ->once();
    }

    public function test_a_failed_save_keeps_the_user_on_the_form_with_their_input(): void
    {
        DB::shouldReceive('transaction')->once()->andThrow(new \RuntimeException('boom'));

        // The non-JavaScript path: back to the form, input intact, error toast.
        $response = $this->actingAs($this->member('owner'))
            ->from('http://styledesk.test/settings/business/edit')
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'legal_name' => 'Typed and worth keeping',
            ]));

        $response->assertRedirect('http://styledesk.test/settings/business/edit');
        $response->assertSessionHasInput('legal_name', 'Typed and worth keeping');
        $response->assertSessionHas('toast', fn ($toast) => $toast['type'] === 'danger');
    }

    public function test_validation_messages_say_what_to_do(): void
    {
        $this->actingAs($this->member('owner'))
            ->postJson('http://styledesk.test/settings/business', [
                '_method' => 'PATCH',
                'name' => '',
                'status' => 'active',
                'business_email' => 'not-an-email',
                'website' => 'nadiahair',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Business name is required.')
            ->assertJsonPath('errors.business_email.0', 'Enter a valid email address.')
            ->assertJsonPath('errors.website.0', 'Enter a valid website URL, including https://');
    }

    /**
     * The tenant row and the business-type pivot are one change to the user.
     * Committing half of it would leave the page showing types that no longer
     * match what was saved, with nothing to say so.
     */
    public function test_the_business_and_its_types_are_saved_together_or_not_at_all(): void
    {
        $this->seed(BusinessTypeSeeder::class);
        $type = BusinessType::where('slug', 'hair-salon')->first();

        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'name' => 'Renamed Studio',
                'business_type_ids' => [$type->id],
            ]))
            ->assertRedirect(route('settings.business.show'));

        $tenant = $this->tenant->fresh();

        $this->assertSame('Renamed Studio', $tenant->name);
        $this->assertSame([$type->id], $tenant->businessTypes->pluck('id')->all());
    }

    public function test_the_settings_directory_links_to_the_module(): void
    {
        $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/settings')
            ->assertOk()
            ->assertSee(route('settings.business.show'), false)
            ->assertSee('Active');
    }

    /**
     * The page's own markup, without the shell around it.
     *
     * Bounded at </main>, not run to the end of the document: the shell now
     * carries a logout form and a session-timeout dialog after the footer, and
     * taking everything from <main> onwards swept their inputs into an
     * assertion about whether this page renders form controls.
     */
    private function stripLayout(string $html): string
    {
        $start = strpos($html, '<main');

        if ($start === false) {
            return $html;
        }

        $end = strpos($html, '</main>', $start);

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    // --------------------------------------------- email, phone and website

    /**
     * The address the browser refuses is the address the server refuses.
     *
     * "nadia@salon" passes Laravel's `email` rule — an address with no dot in
     * the domain is legal on a local network — and has never passed the live
     * check the sign-up form makes. The same address being accepted here and
     * refused there is the bug; App\Support\EmailAddress is the one answer.
     */
    public function test_an_address_with_no_domain_is_refused(): void
    {
        $owner = $this->member('owner');

        foreach (['business_email', 'support_email', 'booking_email'] as $field) {
            $this->actingAs($owner)
                ->from(route('settings.business.edit'))
                ->patch('http://styledesk.test/settings/business', $this->validPayload([$field => 'nadia@salon']))
                ->assertSessionHasErrors($field);
        }
    }

    public function test_a_real_address_is_accepted(): void
    {
        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'business_email' => 'hello@nadia.co.uk',
                'support_email' => 'help@nadia.co.uk',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('hello@nadia.co.uk', $this->tenant->fresh()->business_email);
    }

    public function test_a_business_email_is_still_required(): void
    {
        $this->actingAs($this->member('owner'))
            ->from(route('settings.business.edit'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload(['business_email' => '']))
            ->assertSessionHasErrors('business_email');
    }

    /**
     * The country the number is dialled from is saved with it.
     *
     * The column has always existed; this form never posted it, so every
     * number saved here had no country against it.
     */
    public function test_the_phone_country_is_saved(): void
    {
        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'business_phone' => '20 7946 0958',
                'business_phone_country' => 'GB',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('GB', $this->tenant->fresh()->business_phone_country);
    }

    public function test_the_edit_screen_offers_a_country_dropdown_and_a_scheme_dropdown(): void
    {
        $page = $this->actingAs($this->member('owner'))
            ->get(route('settings.business.edit'))
            ->assertOk();

        $page->assertSee('data-phone-country-value', false);
        $page->assertSee('data-website-scheme', false);
        $page->assertSee('data-validate-form', false);
    }

    /**
     * The scheme is chosen and only the host is typed, so the two are joined
     * on the way in.
     */
    public function test_the_scheme_and_the_host_are_stored_as_one_address(): void
    {
        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'website_scheme' => 'https://www.',
                'website' => 'nadiahair.test',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://www.nadiahair.test', $this->tenant->fresh()->website);
    }

    /**
     * A pasted address is absorbed rather than refused: people paste the whole
     * thing because that is what their browser gave them.
     */
    public function test_a_pasted_full_address_is_absorbed(): void
    {
        $this->actingAs($this->member('owner'))
            ->patch('http://styledesk.test/settings/business', $this->validPayload([
                'website_scheme' => 'https://www.',
                'website' => 'https://www.nadiahair.test',
            ]))
            ->assertSessionHasNoErrors();

        /* And the www. is not doubled. */
        $this->assertSame('https://www.nadiahair.test', $this->tenant->fresh()->website);
    }

    /**
     * "hello world" passes a string rule on its own and would be stored as
     * "https://hello world".
     */
    public function test_a_host_that_is_not_a_host_is_refused(): void
    {
        $owner = $this->member('owner');

        foreach (['hello world', 'nodot', 'https//nadiahair.test'] as $typed) {
            $this->actingAs($owner)
                ->from(route('settings.business.edit'))
                ->patch('http://styledesk.test/settings/business', $this->validPayload([
                    'website_scheme' => 'https://',
                    'website' => $typed,
                ]))
                ->assertSessionHasErrors('website');
        }
    }

    /**
     * The stored address opens in the two controls it is edited in, on the
     * option the form actually offers.
     */
    public function test_a_stored_address_splits_back_into_the_two_controls(): void
    {
        $this->tenant->forceFill(['website' => 'https://www.nadiahair.test'])->save();

        $this->actingAs($this->member('owner'))
            ->get(route('settings.business.edit'))
            ->assertOk()
            ->assertSee('value="nadiahair.test"', false)
            ->assertSee('<option value="https://www." selected>', false);
    }
}
