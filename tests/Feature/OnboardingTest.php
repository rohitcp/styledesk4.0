<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureBusinessIsActive;
use App\Models\BusinessType;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\LocationOptions;
use App\Support\MarketLanguages;
use App\Support\Subdomain;
use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Acceptance criteria from docs/decisions/0002-onboarding-spec.md.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Business types are reference data the business step renders from. Without
     * them the chip loop has nothing to iterate and a broken chip template
     * would sail past every render assertion.
     */
    private function seedBusinessTypes(): void
    {
        $this->seed(BusinessTypeSeeder::class);
    }

    private function user(): User
    {
        $user = User::create([
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'r@styledesk.test',
            'password' => 'Str0ng!Pass',
        ]);

        // Set outside create(): email_verified_at is deliberately not
        // mass-assignable, so passing it to create() is silently dropped.
        // Verification is part of the minimum setup, so onboarding is
        // unreachable without it.
        $user->markEmailAsVerified();

        return $user->fresh();
    }

    private function onboardedUser(): User
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();

        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'complete',
            'completed_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * Every step must actually render.
     *
     * The POST tests below exercise the controller but never compile the
     * views, so a Blade syntax error sails past them and only shows up in a
     * browser as a 500. These GETs are what catch that.
     */
    public function test_every_step_renders(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'business']);

        $this->seedBusinessTypes();

        $expected = [
            'business' => 'Tell us about your business',
            'location' => 'Where do you operate?',
            'services' => 'What do you offer?',
            'team' => 'Who works with you?',
            'booking' => 'How should clients book?',
        ];

        foreach ($expected as $step => $heading) {
            $this->actingAs($user->fresh())
                ->get('http://styledesk.test/onboarding/'.$step)
                ->assertOk()
                ->assertSee($heading);
        }

        // The chips must actually render, not silently skip on empty data.
        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/onboarding/business')
            ->assertSee('Hair Salon')
            ->assertSee('Eyebrows &amp; Lashes', false);

    }

    public function test_steps_after_the_first_offer_a_back_link(): void
    {
        $this->seedBusinessTypes();

        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'business']);

        // The first step has nowhere to go back to.
        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/onboarding/business')
            ->assertOk()
            ->assertDontSee(route('onboarding.location'));

        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/onboarding/location')
            ->assertOk()
            ->assertSee(route('onboarding.business'));
    }

    public function test_going_back_does_not_rewind_progress(): void
    {
        // Back is a link, not a submission: re-reading an earlier step must
        // not move current_step or unset a completed flag.
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        $onboarding = TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'location',
            'business_completed' => true,
        ]);

        $this->seedBusinessTypes();

        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/onboarding/business')
            ->assertOk();

        $onboarding->refresh();

        $this->assertSame('location', $onboarding->current_step);
        $this->assertTrue($onboarding->business_completed);
    }

    public function test_every_inferred_timezone_can_actually_be_selected(): void
    {
        // Inference sets the timezone select to a value. If that value is not
        // one of the offered options the field silently reads as blank, and
        // the only symptom is a form that will not submit.
        $offered = array_keys(config('locations.timezones'));

        $referenced = array_values(config('locations.country_timezones'));

        foreach (config('locations.region_timezones') as $map) {
            $referenced = array_merge($referenced, array_values($map));
        }

        $this->assertSame([], array_values(array_diff(array_unique($referenced), $offered)));
    }

    public function test_every_country_offered_has_a_default_timezone(): void
    {
        $missing = array_diff(
            array_keys(config('locations.countries')),
            array_keys(config('locations.country_timezones'))
        );

        $this->assertSame([], array_values($missing));
    }

    public function test_regions_are_only_listed_for_countries_we_offer(): void
    {
        $unknown = array_diff(
            array_keys(config('locations.regions')),
            array_keys(config('locations.countries'))
        );

        $this->assertSame([], array_values($unknown));
    }

    public function test_a_user_without_a_business_is_sent_to_step_one(): void
    {
        $this->actingAs($this->user())
            ->get('http://styledesk.test/dashboard')
            ->assertRedirect(route('onboarding.business'));
    }

    public function test_step_one_creates_the_tenant_and_links_the_user(): void
    {
        $user = $this->user();
        $type = BusinessType::firstOrCreate(['slug' => 'hair-salon'], ['name' => 'Hair Salon', 'slug' => 'hair-salon']);

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'slug' => 'bella',
                'business_phone' => '555 0100',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'business_email' => 'hello@bella.test',
                'business_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('onboarding.location'));

        $tenant = Tenant::where('slug', 'bella')->first();

        $this->assertNotNull($tenant);
        $this->assertSame('Bella Beauty Studio', $tenant->name);
        $this->assertSame($tenant->getTenantKey(), $user->fresh()->tenant_id);
        $this->assertTrue($tenant->onboarding->business_completed);
        $this->assertSame('location', $tenant->onboarding->current_step);

        // The booking subdomain must exist, or the public site is unreachable.
        $this->assertSame('bella', $tenant->domains()->first()->domain);

        // Sign-up never asks for payment, so the workspace opens on a trial.
        $this->assertSame('trial', $tenant->status);
        $this->assertSame('trialing', $tenant->subscription_status);
        $this->assertSame(14, $tenant->trialDaysRemaining());
        $this->assertSame($user->id, $tenant->owner_user_id);
        $this->assertSame(['Hair Salon'], $tenant->businessTypes->pluck('name')->all());
    }

    /**
     * The regression whose absence let the trial status ship.
     *
     * The Business step is the first moment a tenant exists, so the redirect
     * it returns is the first request EnsureBusinessIsActive has a tenant to
     * judge. While trial counted as inactive that request logged the owner
     * straight back out, and no signup could reach step two.
     */
    public function test_the_owner_stays_signed_in_after_the_business_step(): void
    {
        $user = $this->user();
        $type = BusinessType::firstOrCreate(['slug' => 'hair-salon'], ['name' => 'Hair Salon', 'slug' => 'hair-salon']);

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'slug' => 'bella',
                'business_phone' => '555 0100',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'business_email' => 'hello@bella.test',
                'business_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('onboarding.location'));

        /* Re-resolved from the database, or the middleware judges the user
           this test already holds — whose tenant relation is still the null
           it was before the controller created one. A real second request
           loads both afresh. */
        $owner = $user->fresh();

        $this->actingAs($owner)
            ->get('http://styledesk.test/onboarding/location')
            ->assertOk()
            ->assertSessionMissing(EnsureBusinessIsActive::FLAG);

        $this->assertAuthenticatedAs($owner);
    }

    public function test_the_business_name_is_capitalised(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'bella beauty studio',
                'business_phone' => '555 0100',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'business_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('onboarding.location'));

        // First character only, per the project-wide rule. An earlier version
        // capitalised every word; InputCapitalizationTest owns that rule now.
        $this->assertSame('Bella beauty studio', $user->fresh()->tenant->name);
    }

    public function test_capitalisation_leaves_deliberate_casing_alone(): void
    {
        // Str::title() would turn these into "Bella Beauty" and "Medspa",
        // overriding capitalisation the owner chose.
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'BELLA MedSpa',
                'business_phone' => '555 0100',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'business_type_ids' => [$type->id],
            ]);

        $this->assertSame('BELLA MedSpa', $user->fresh()->tenant->name);
    }

    public function test_a_logo_can_be_uploaded_before_the_tenant_exists(): void
    {
        Storage::fake('brand');

        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        // Step 1 runs before any tenant exists, so the upload endpoint parks
        // the path in the session for the business save to pick up.
        $response = $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/logo', [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertOk();

        $path = $response->json('path');
        Storage::disk('brand')->assertExists($path);

        $this->post('http://styledesk.test/onboarding/business', [
            'name' => 'Bella Beauty Studio',
            'business_phone' => '555 0100',
            'country_codes' => ['US'],
            'currency_code' => 'USD',
            'default_language' => 'en',
            'business_type_ids' => [$type->id],
        ])->assertRedirect(route('onboarding.location'));

        $this->assertSame($path, $user->fresh()->tenant->logo_path);
    }

    public function test_the_logo_is_stored_outside_tenant_suffixed_storage(): void
    {
        // Regression: the 'public' disk is tenant-suffixed, so once tenancy is
        // initialized an upload lands in storage/tenant<id>/... while
        // Storage::url() still points at the central /storage symlink. The
        // file uploads fine and then 404s, which no assertion on the response
        // alone would catch.
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'business']);

        $path = $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/logo', [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertOk()
            ->json('path');

        $root = config('filesystems.disks.brand.root');

        $this->assertStringNotContainsString('tenant', $root, 'Brand assets must not live under a tenant-suffixed root.');
        $this->assertFileExists($root.'/'.$path);
    }

    public function test_an_oversized_logo_is_rejected(): void
    {
        Storage::fake('brand');

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/logo', [
                'logo' => UploadedFile::fake()->create('huge.png', 3000, 'image/png'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_country_currency_and_language_are_stored_against_the_tenant(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Mumbai Salon',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => ['IN'],
                'currency_code' => 'INR',
                'default_language' => 'hi',
            ])
            ->assertRedirect(route('onboarding.location'));

        $tenant = $user->fresh()->tenant;

        $this->assertSame('IN', $tenant->country_code);
        $this->assertSame('INR', $tenant->currency_code);
        $this->assertSame('hi', $tenant->default_language);
    }

    public function test_multiple_countries_and_currencies_are_stored_with_a_primary(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Cross Border Salon',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => ['IN', 'AU', 'CA'],
                'currency_code' => 'INR',
                'secondary_currency_codes' => ['AUD'],
                'default_language' => 'hi',
            ])
            ->assertRedirect(route('onboarding.location'));

        $tenant = $user->fresh()->tenant;

        $this->assertSame(['IN', 'AU', 'CA'], $tenant->countries->pluck('country_code')->all());
        $this->assertSame(['INR', 'AUD'], $tenant->currencies->pluck('currency_code')->all());

        // The first of each is mirrored onto the tenant, and that mirror is
        // what every later screen filters and prices on.
        $this->assertSame('IN', $tenant->country_code);
        $this->assertSame('INR', $tenant->currency_code);
    }

    public function test_secondary_currencies_and_languages_are_stored_after_the_primary(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Cross Border Salon',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => ['US', 'CA'],
                'currency_code' => 'USD',
                'secondary_currency_codes' => ['CAD', 'EUR'],
                'default_language' => 'en',
                'secondary_language_codes' => ['es', 'fr'],
            ])
            ->assertRedirect(route('onboarding.location'));

        $tenant = $user->fresh()->tenant;

        $this->assertSame(['USD', 'CAD', 'EUR'], $tenant->currencies->pluck('currency_code')->all());
        $this->assertSame(['en', 'es', 'fr'], $tenant->languages->pluck('language_code')->all());

        // The mirrored columns are the primary, and they are what the rest of
        // the app reads.
        $this->assertSame('USD', $tenant->currency_code);
        $this->assertSame('en', $tenant->default_language);
    }

    public function test_a_secondary_may_not_repeat_the_primary(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Acme',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'secondary_currency_codes' => ['USD'],
                'default_language' => 'en',
                'secondary_language_codes' => ['en'],
            ])
            ->assertSessionHasErrors(['secondary_currency_codes.0', 'secondary_language_codes.0']);
    }

    public function test_a_repeated_secondary_is_stored_once(): void
    {
        // Belt and braces behind the validation: even if a stale form posts a
        // duplicate, the stored list must not contain it twice.
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $tenant->syncCurrencies(['USD', 'CAD', 'CAD', 'USD']);

        $this->assertSame(['USD', 'CAD'], $tenant->fresh()->currencies->pluck('currency_code')->all());
    }

    public function test_reordering_changes_which_country_is_primary(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $tenant->syncCountries(['US', 'CA']);
        $this->assertSame('US', $tenant->fresh()->country_code);

        // Promoting Canada is simply putting it first — there is no separate
        // flag that could disagree with the ordering.
        $tenant->syncCountries(['CA', 'US']);
        $this->assertSame('CA', $tenant->fresh()->country_code);
        $this->assertSame(['CA', 'US'], $tenant->fresh()->countries->pluck('country_code')->all());
    }

    public function test_removing_a_country_drops_it_from_the_list(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $tenant->syncCountries(['US', 'CA', 'GB']);
        $tenant->syncCountries(['CA']);

        $this->assertSame(['CA'], $tenant->fresh()->countries->pluck('country_code')->all());
        $this->assertSame('CA', $tenant->fresh()->country_code);
    }

    public function test_an_empty_country_or_currency_list_is_refused(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Acme',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => [],
                'currency_code' => '',
                'default_language' => 'en',
            ])
            ->assertSessionHasErrors(['country_codes', 'currency_code']);
    }

    public function test_country_currency_and_language_are_required(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Acme',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
            ])
            ->assertSessionHasErrors(['country_codes', 'currency_code', 'default_language']);
    }

    public function test_only_supported_languages_are_accepted(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Acme',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'zz',
            ])
            ->assertSessionHasErrors('default_language');
    }

    /**
     * The onboarding hours contract, pinned because the editor is shared.
     *
     * Location settings mounts the same BusinessHours island in split-period
     * mode, where a row posts hours[day][index][field]. Onboarding posts the
     * flat hours[day][field] its controller reads, and nothing else asserted
     * that — so a change made for one screen could have silently stopped the
     * other from storing a single opening time.
     */
    public function test_the_location_step_stores_flat_hours(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Nadia Hair Studio', 'slug' => 'nadia', 'country_code' => 'US']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'location']);

        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/location', [
                'name' => 'Main Location',
                'address_line1' => '1 River Street',
                'city' => 'Austin',
                'postal_code' => '78701',
                'timezone' => 'America/Chicago',
                'hours' => [
                    0 => ['opens_at' => '09:00', 'closes_at' => '17:00'],
                    1 => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ],
            ])->assertRedirect(route('onboarding.services'));

        $hours = $tenant->locations()->first()->hours;

        $monday = $hours->firstWhere('day_of_week', 1);
        $this->assertTrue($monday->is_open);
        $this->assertSame('09:00', $monday->timeValue('opens_at'));
        $this->assertSame('18:00', $monday->timeValue('closes_at'));

        // Sunday was submitted with times but no toggle, so it is closed.
        $this->assertFalse($hours->firstWhere('day_of_week', 0)->is_open);
    }

    /**
     * Onboarding asks the simplest version of the question.
     *
     * Split periods belong to Location settings; turning them on here would
     * put "Add another period" in front of someone who has not yet finished
     * telling us where their business is.
     */
    public function test_the_onboarding_hours_editor_is_not_in_split_mode(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Nadia Hair Studio', 'slug' => 'nadia', 'country_code' => 'US']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'location']);

        $content = $this->actingAs($user->fresh())
            ->get('http://styledesk.test/onboarding/location')
            ->assertOk()
            ->assertSee('data-vue-component="BusinessHours"', false)
            ->getContent();

        $this->assertStringNotContainsString('splitPeriods', $content);
    }

    /**
     * The location step's fields answer nothing on the reader's behalf.
     *
     * The name arrived as "Main Location" and the timezone as New York —
     * both plausible enough to be accepted without being read, and every
     * booking and reminder is scheduled against the second one.
     */
    public function test_the_location_step_prefills_nothing_and_guards_its_continue(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Nadia Hair Studio', 'slug' => 'nadia', 'country_code' => 'US']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'location']);

        $content = $this->actingAs($user->fresh())
            ->get('http://styledesk.test/onboarding/location')
            ->assertOk()
            ->getContent();

        /* The name is a placeholder, not a value. */
        $this->assertStringContainsString('placeholder="Main Location"', $content);
        $this->assertStringNotContainsString('value="Main Location"', $content);

        /* No timezone chosen, and the empty option is the selected one. */
        $this->assertStringNotContainsString('value="America/New_York" selected', $content);
        $this->assertStringContainsString('Search or select a timezone', $content);

        /* Continue fires once, and the phone refuses letters as they land. */
        $this->assertStringContainsString('data-submit-once', $content);
        $this->assertStringContainsString('data-digits-only', $content);

        /* The read-only country sits centred in its field rather than
           against the top of it — the flex utility loses to prototype.css. */
        $this->assertStringContainsString('styledesk_readonlyfield', $content);
    }

    public function test_the_location_step_uses_the_country_from_the_business_step(): void
    {
        // The country must not be re-asked or accepted from the request: the
        // two screens would otherwise be able to disagree.
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Mumbai Salon', 'slug' => 'mumbai', 'country_code' => 'IN']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'location']);

        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/onboarding/location')
            ->assertOk()
            ->assertSee('India')
            ->assertSee('Karnataka')          // an Indian state is offered
            ->assertDontSee('Alberta');       // a Canadian province is not

        // A crafted country in the request must be ignored.
        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/location', [
                'name' => 'Main Location',
                'address_line1' => '1 MG Road',
                'city' => 'Bengaluru',
                'postal_code' => '560001',
                'country' => 'CA',
                'timezone' => 'Asia/Kolkata',
            ])->assertRedirect(route('onboarding.services'));

        $this->assertSame('IN', $tenant->locations()->first()->country);
    }

    public function test_business_type_is_required(): void
    {
        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'business_phone' => '555 0100',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
            ])
            ->assertSessionHasErrors('business_type_ids');

        $this->assertSame(0, Tenant::count());
    }

    public function test_the_slug_is_generated_from_the_business_name(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'business_phone' => '555 0100',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'business_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('onboarding.location'));

        /* Spaces are removed rather than hyphenated: "Bella Beauty Studio"
           becomes "bellabeautystudio", which is what a business reads out
           over the phone. */
        $this->assertSame('bellabeautystudio', Tenant::first()->slug);
    }

    public function test_a_taken_slug_gets_a_numeric_suffix(): void
    {
        Tenant::create(['name' => 'Bella Beauty Studio', 'slug' => 'bellabeautystudio']);
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'business_phone' => '555 0100',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'country_codes' => ['US'],
                'currency_code' => 'USD',
                'default_language' => 'en',
                'business_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('onboarding.location'));

        // Found via the user, not Tenant::first(): the primary key is a UUID,
        // so "first" is not creation order and would pick either row.
        $this->assertSame('bellabeautystudio-2', $user->fresh()->tenant->slug);
    }

    /**
     * The address rules, in one place.
     *
     * Three things have to agree about what a subdomain may contain — the
     * suggestion the browser makes, the value the server stores and the
     * availability check between them — so they all read the same class.
     */
    public function test_a_business_name_becomes_an_address_without_spaces(): void
    {
        $this->assertSame('bellbody', Subdomain::fromName('Bell Body'));
        $this->assertSame('bellabeautyspa', Subdomain::fromName('Bella Beauty Spa'));
        $this->assertSame('johnshairbeauty', Subdomain::fromName("John's Hair & Beauty"));

        /* A hyphen the user typed between words is theirs to keep; one at
           either end is not, because a subdomain may not begin or end with
           it. A space is removed rather than becoming a hyphen. */
        $this->assertSame('my-salon', Subdomain::normalise('  -My-Salon-  '));
        $this->assertSame('mysalon', Subdomain::normalise('My Salon'));

        $this->assertTrue(Subdomain::isValid('bellbody'));
        $this->assertFalse(Subdomain::isValid('-bad'));
        $this->assertFalse(Subdomain::isValid('Bell Body'));
    }

    /** Free, and said so while the user is still typing. */
    public function test_an_unused_address_reports_available(): void
    {
        $this->actingAs($this->user())
            ->getJson('http://styledesk.test/onboarding/business/slug-availability?slug=bellbody')
            ->assertOk()
            ->assertJson(['status' => 'available', 'slug' => 'bellbody']);
    }

    public function test_an_address_another_business_holds_reports_taken(): void
    {
        Tenant::create(['name' => 'Bell Body', 'slug' => 'bellbody']);

        $this->actingAs($this->user())
            ->getJson('http://styledesk.test/onboarding/business/slug-availability?slug=bellbody')
            ->assertOk()
            ->assertJson(['status' => 'taken']);
    }

    /**
     * The three answers the check can give, in the order the validator asks
     * them: is it a legal shape, is it ours to give, is it taken.
     */
    public function test_the_check_reports_invalid_and_reserved_addresses(): void
    {
        $user = $this->user();

        /* Cleaned before it is judged, exactly as the field cleans it — so
           "Bell Body" arrives as "bellbody" and is available rather than
           being called invalid. */
        $this->actingAs($user)
            ->getJson('http://styledesk.test/onboarding/business/slug-availability?slug=Bell%20Body')
            ->assertOk()
            ->assertJson(['status' => 'available', 'slug' => 'bellbody']);

        $this->actingAs($user)
            ->getJson('http://styledesk.test/onboarding/business/slug-availability?slug=www')
            ->assertOk()
            ->assertJson(['status' => 'reserved']);

        $this->actingAs($user)
            ->getJson('http://styledesk.test/onboarding/business/slug-availability?slug=')
            ->assertOk()
            ->assertJson(['status' => 'empty']);
    }

    /** A business editing its own address is not competing with itself. */
    public function test_a_business_keeps_its_own_address(): void
    {
        $tenant = Tenant::create(['name' => 'Bell Body', 'slug' => 'bellbody']);

        $user = $this->user();
        $user->forceFill(['tenant_id' => $tenant->getTenantKey()])->save();
        $user = $user->fresh();

        $this->actingAs($user)
            ->getJson('http://styledesk.test/onboarding/business/slug-availability?slug=bellbody')
            ->assertOk()
            ->assertJson(['status' => 'available']);
    }

    /** The address field is the shared component, not a page-local script. */
    public function test_the_business_step_mounts_the_shared_address_field(): void
    {
        $this->actingAs($this->user())
            ->get('http://styledesk.test/onboarding/business')
            ->assertOk()
            ->assertSee('data-subdomain', false)
            ->assertSee('data-subdomain-regenerate', false)
            ->assertSee(route('onboarding.business.slug'), false)
            ->assertSee(config('tenancy.tenant_domain_suffix'), false);
    }

    /**
     * Business types come from the database, active ones only, in the order
     * the table sets — so adding or retiring one never touches a template.
     */
    public function test_the_business_step_lists_active_types_in_order(): void
    {
        BusinessType::query()->delete();

        $second = BusinessType::create(['name' => 'Barber Shop', 'slug' => 'barber-shop', 'sort_order' => 1]);
        $first = BusinessType::create(['name' => 'Hair Salon', 'slug' => 'hair-salon', 'sort_order' => 0]);
        $hidden = BusinessType::create(['name' => 'Retired Type', 'slug' => 'retired', 'sort_order' => 2, 'is_active' => false]);

        $content = $this->actingAs($this->user())
            ->get('http://styledesk.test/onboarding/business')
            ->assertOk()
            ->assertDontSee('Retired Type')
            ->getContent();

        $this->assertLessThan(
            mb_strpos($content, 'Barber Shop'),
            mb_strpos($content, 'Hair Salon'),
            'Types are listed in their configured order.',
        );
    }

    /**
     * The catalogue arrives with the migrations, not only with the seeder.
     *
     * A deploy runs `migrate --force`; it does not run `db:seed`. Production
     * therefore had the table and none of the rows, which turned a required
     * field into one with no options — an onboarding step nobody could
     * finish. The migration seeds it, so any environment that migrates has a
     * catalogue.
     */
    public function test_migrating_provides_the_business_type_catalogue(): void
    {
        $types = BusinessType::query()->orderBy('sort_order')->get();

        $this->assertGreaterThanOrEqual(13, $types->count());
        $this->assertNotNull($types->firstWhere('slug', 'hair-salon'));

        /* Other stays last, whatever is added before it. */
        $this->assertSame('Other', $types->last()->name);
    }

    /**
     * An empty catalogue says so.
     *
     * The step is a required field: with no options it is unanswerable, and
     * silence made that take far longer to diagnose than it should have.
     */
    public function test_an_empty_catalogue_is_reported_rather_than_left_blank(): void
    {
        BusinessType::query()->delete();

        $this->actingAs($this->user())
            ->get('http://styledesk.test/onboarding/business')
            ->assertOk()
            ->assertSee(__('onboarding.business.types.none'));
    }

    /**
     * Country and currency start empty.
     *
     * They used to arrive as the United States and the US dollar — a guess
     * wearing the clothes of an answer, which a salon in Leeds could sign up
     * without ever noticing. Both are required, so the step cannot be passed
     * without a deliberate choice.
     */
    public function test_the_step_offers_no_country_or_currency_by_default(): void
    {
        $content = $this->actingAs($this->user())
            ->get('http://styledesk.test/onboarding/business')
            ->assertOk()
            ->getContent();

        /* The island is handed its selection as props; nothing preselected
           means both lists reach it empty. */
        $this->assertStringContainsString('"selectedCountries":[]', $content);
        $this->assertStringContainsString('"selectedCurrencies":[]', $content);
    }

    /** And the server refuses a submission that leaves them unanswered. */
    public function test_the_step_cannot_be_passed_without_a_country_and_currency(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty',
                'business_phone' => '555 0100',
                'default_language' => 'en',
                'business_type_ids' => [$type->id],
            ])
            ->assertSessionHasErrors(['country_codes', 'currency_code']);
    }

    /**
     * The countries most businesses pick, offered first.
     *
     * Alphabetically the list runs Argentina to United States, which puts the
     * likeliest answers at the bottom of thirty-three. Presentation only —
     * validation still reads the config, so promoting a country cannot
     * narrow what is accepted.
     */
    public function test_the_likeliest_countries_are_offered_first(): void
    {
        $offered = array_keys(LocationOptions::countries());

        $this->assertSame(
            ['US', 'GB', 'CA', 'AU', 'ES', 'MX'],
            array_slice($offered, 0, 6),
        );

        /* The rest keep their alphabetical order, and nothing is lost or
           repeated on the way. */
        $this->assertSame('AR', $offered[6]);
        $this->assertSame(count(config('locations.countries')), count($offered));
        $this->assertSame($offered, array_unique($offered));
    }

    /** A country promoted in config but absent from the list is ignored. */
    public function test_a_promotion_cannot_invent_a_country(): void
    {
        config()->set('locations.countries_first', ['US', 'ZZ']);

        $offered = array_keys(LocationOptions::countries());

        $this->assertSame('US', $offered[0]);
        $this->assertNotContains('ZZ', $offered);
        $this->assertSame(count(config('locations.countries')), count($offered));
    }

    /**
     * Country of operation offers the markets we sell into, not every country.
     *
     * A narrower question than "what address can we record", which is what
     * countries() answers and what the client and location forms ask.
     */
    public function test_only_operating_countries_are_offered_as_countries_of_operation(): void
    {
        $offered = array_keys(LocationOptions::operatingCountries());

        sort($offered);

        $this->assertSame(['AU', 'CA', 'CN', 'DE', 'FR', 'IN', 'MX', 'US'], $offered);

        /* The full list is untouched: a business in the United States may
           still have a client who lives in Japan. */
        $this->assertArrayHasKey('JP', LocationOptions::countries());
    }

    /** The likeliest markets stay at the top, as on the full list. */
    public function test_operating_countries_keep_the_promotion_order(): void
    {
        $offered = array_keys(LocationOptions::operatingCountries());

        $this->assertSame(['US', 'CA', 'AU', 'MX'], array_slice($offered, 0, 4));

        // The rest alphabetically by name: China, France, Germany, India.
        $this->assertSame(['CN', 'FR', 'DE', 'IN'], array_slice($offered, 4));
        $this->assertSame($offered, array_unique($offered));
    }

    /** Every market has a name, a currency, a timezone and a language. */
    public function test_every_operating_country_is_completely_described(): void
    {
        foreach (config('locations.operating_countries') as $code) {
            $this->assertArrayHasKey($code, config('locations.countries'), $code.' has no name');
            $this->assertArrayHasKey($code, config('currencies.country_currencies'), $code.' has no currency');
            $this->assertArrayHasKey($code, config('locations.country_timezones'), $code.' has no timezone');

            $languages = config('currencies.country_languages.'.$code);

            $this->assertNotEmpty($languages, $code.' has no languages');
            $this->assertSame([], array_diff($languages, array_keys(config('currencies.languages'))));
        }
    }

    /** A country we do not sell into is refused, however it is posted. */
    public function test_a_country_we_do_not_operate_in_is_refused(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Acme', 'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                // Still a real country, and still offered on the client form.
                'country_codes' => ['US', 'JP'],
                'currency_code' => 'USD',
                'default_language' => 'en',
            ])
            ->assertSessionHasErrors('country_codes.1');
    }

    /**
     * The language list follows the countries, and so does the rule.
     *
     * The browser filters the dropdown as countries are ticked. A rule that
     * did not narrow with it would accept, on a hand-rolled post, exactly the
     * option the form refused to show.
     */
    public function test_a_language_no_chosen_country_offers_is_refused(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Acme', 'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => ['DE'],
                'currency_code' => 'EUR',
                // A language StyleDesk has, that Germany is not served in.
                'default_language' => 'hi',
            ])
            ->assertSessionHasErrors('default_language');
    }

    /** The same narrowing applies to the secondary languages. */
    public function test_a_secondary_language_no_chosen_country_offers_is_refused(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Acme', 'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => ['MX'],
                'currency_code' => 'MXN',
                'default_language' => 'es',
                'secondary_language_codes' => ['zh'],
            ])
            ->assertSessionHasErrors('secondary_language_codes.0');
    }

    /**
     * Several countries offer the union of their languages, not the
     * intersection — which for Canada and Mexico would be empty.
     */
    public function test_languages_from_every_chosen_country_are_accepted(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Frontera', 'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
                'country_codes' => ['CA', 'MX'],
                'currency_code' => 'CAD',
                'default_language' => 'fr',
                'secondary_language_codes' => ['en', 'es'],
            ])
            ->assertRedirect(route('onboarding.location'));

        $this->assertSame(
            ['fr', 'en', 'es'],
            $user->fresh()->tenant->languages->pluck('language_code')->all()
        );
    }

    /** The map the form filters by, and the map the rules read, are one map. */
    public function test_the_offered_languages_are_the_union_of_the_chosen_countries(): void
    {
        $this->assertSame(['en', 'es', 'fr'], array_keys(MarketLanguages::forCountries(['US', 'CA'])));
        $this->assertSame(['zh'], array_keys(MarketLanguages::forCountries(['CN'])));

        /* Nothing chosen yet: the country field is required and is the error
           worth showing, so the language rule stays wide rather than adding a
           second complaint that follows from the first. */
        $this->assertSame(array_keys(config('currencies.languages')), MarketLanguages::codesFor([]));
    }

    /**
     * The website is checked as the whole address.
     *
     * The scheme lives in a dropdown beside the field, so a plain string rule
     * on what was typed accepted "hello world" and stored it as
     * "https://hello world".
     */
    public function test_a_website_that_is_not_an_address_is_refused(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $user = $this->user();

        foreach (['hello world', 'bellabeauty', 'https://'] as $bad) {
            $this->actingAs($user)
                ->post('http://styledesk.test/onboarding/business', [
                    'name' => 'Bella Beauty', 'business_phone' => '555 0100',
                    'country_codes' => ['US'], 'currency_code' => 'USD', 'default_language' => 'en',
                    'business_type_ids' => [$type->id],
                    'website_scheme' => 'https://www.', 'website' => $bad,
                ])
                ->assertSessionHasErrors('website');
        }
    }

    /** A real address is kept, joined to the scheme beside it. */
    public function test_a_valid_website_is_stored_with_its_scheme(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty', 'business_phone' => '555 0100',
                'country_codes' => ['US'], 'currency_code' => 'USD', 'default_language' => 'en',
                'business_type_ids' => [$type->id],
                'website_scheme' => 'https://www.', 'website' => 'bellabeauty.com',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://www.bellabeauty.com', $user->fresh()->tenant->website);
    }

    /** A scheme that is not one of the offered four is refused. */
    public function test_an_invented_scheme_is_refused(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty', 'business_phone' => '555 0100',
                'country_codes' => ['US'], 'currency_code' => 'USD', 'default_language' => 'en',
                'business_type_ids' => [$type->id],
                'website_scheme' => 'javascript:', 'website' => 'bellabeauty.com',
            ])
            ->assertSessionHasErrors('website_scheme');
    }

    /** The field is checked as it is typed, not only on submit. */
    public function test_the_website_field_is_wired_for_live_checking(): void
    {
        $this->actingAs($this->user())
            ->get('http://styledesk.test/onboarding/business')
            ->assertOk()
            ->assertSee('data-website-field', false)
            ->assertSee('data-website-scheme', false)
            ->assertSee(__('business.validation.url_invalid'), false);
    }

    /**
     * Continue fires once.
     *
     * A slow POST gives the reader nothing to look at, so they click again —
     * and a second submit on this step is a second tenant. Bound to the
     * form's submit rather than the button's click, so a form the browser
     * refuses never disables the button the reader still needs.
     */
    public function test_the_continue_button_cannot_be_submitted_twice(): void
    {
        $this->actingAs($this->user())
            ->get('http://styledesk.test/onboarding/business')
            ->assertOk()
            ->assertSee('data-submit-once', false)
            ->assertSee('data-busy-label="'.e(__('common.saving')).'"', false);
    }

    public function test_reserved_slugs_are_rejected(): void
    {
        // These become subdomains, so they must not collide with infrastructure.
        $user = $this->user();

        foreach (['www', 'admin', 'api', 'mail'] as $reserved) {
            $this->actingAs($user)
                ->post('http://styledesk.test/onboarding/business', [
                    'name' => 'Test',
                    'slug' => $reserved,
                ])
                ->assertSessionHasErrors('slug');
        }

        $this->assertSame(0, Tenant::count());
    }

    public function test_slug_must_be_url_safe(): void
    {
        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Test',
                'slug' => 'Not A Slug!',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_skipping_advances_without_marking_the_step_done(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'services']);

        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/skip/services')
            ->assertRedirect(route('onboarding.team'));

        $onboarding = $tenant->onboarding()->first();

        $this->assertSame('team', $onboarding->current_step);
        $this->assertFalse($onboarding->services_completed, 'Skipping must stay distinguishable from completing.');
    }

    public function test_a_price_is_stored_per_currency_in_minor_units(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        $tenant->syncCurrencies(['USD', 'CAD']);
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'services']);

        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/services', [
                'services' => [
                    ['name' => 'Cut', 'duration_minutes' => 45, 'prices' => ['USD' => '38.50', 'CAD' => '52.00']],
                ],
            ])
            ->assertRedirect(route('onboarding.team'));

        $service = Service::first();

        $this->assertSame(3850, $service->prices->firstWhere('currency_code', 'USD')->price_minor);
        $this->assertSame(5200, $service->prices->firstWhere('currency_code', 'CAD')->price_minor);
    }

    public function test_a_price_in_a_currency_the_tenant_has_not_enabled_is_ignored(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        $tenant->syncCurrencies(['USD']);
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'services']);

        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/services', [
                'services' => [
                    ['name' => 'Cut', 'duration_minutes' => 45, 'prices' => ['USD' => '38.50', 'JPY' => '9999']],
                ],
            ])
            ->assertRedirect(route('onboarding.team'));

        $codes = Service::first()->prices->pluck('currency_code')->all();

        $this->assertSame(['USD'], $codes, 'Only currencies the tenant enabled may be priced.');
    }

    public function test_the_owner_is_seeded_as_staff(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'team']);

        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/team', ['provides_services' => '1'])
            ->assertRedirect(route('onboarding.booking'));

        $owner = Staff::where('user_id', $user->id)->first();

        $this->assertNotNull($owner, 'The team step pre-populates the owner, so they must exist as staff.');
        $this->assertSame('owner', $owner->role);
        $this->assertTrue($owner->provides_services);
    }

    public function test_an_owner_who_does_not_provide_services_gets_none_assigned(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'team']);

        tenancy()->initialize($tenant);
        $service = Service::create(['name' => 'Cut', 'duration_minutes' => 30]);
        tenancy()->end();

        // Answering "no" must clear any selection rather than leaving orphans.
        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/team', [
                'provides_services' => '0',
                'owner_services' => [$service->id],
            ])
            ->assertRedirect(route('onboarding.booking'));

        $owner = Staff::where('user_id', $user->id)->first();

        $this->assertFalse($owner->provides_services);
        $this->assertSame(0, $owner->services()->count());
    }

    public function test_a_finished_account_cannot_re_enter_the_wizard(): void
    {
        $this->actingAs($this->onboardedUser())
            ->get('http://styledesk.test/onboarding/business')
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_finished_account_reaches_the_dashboard(): void
    {
        $this->actingAs($this->onboardedUser())
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('Acme Salon');
    }

    public function test_the_dashboard_offers_a_getting_started_checklist(): void
    {
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            // Not 'Getting started': the announcement banner already carries
            // "Getting started with StyleDesk", so that would pass either way.
            ->assertSee('Add your first service')
            ->assertSee('Configure appointment reminders');
    }

    public function test_the_owner_can_dismiss_the_checklist(): void
    {
        $user = $this->onboardedUser();
        $user->tenant->update(['owner_user_id' => $user->id]);

        $this->actingAs($user)
            ->delete('http://styledesk.test/getting-started')
            ->assertRedirect();

        $this->assertNotNull($user->tenant->onboarding->fresh()->getting_started_dismissed_at);

        $this->actingAs($user)
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertDontSee('Add your first service');
    }

    public function test_a_non_owner_cannot_dismiss_the_checklist(): void
    {
        // It is the owner's setup list; one staff member hiding it for the
        // whole business would be surprising.
        $owner = $this->onboardedUser();

        $staff = User::create([
            'first_name' => 'Sam',
            'last_name' => 'Doe',
            'email' => 'sam@styledesk.test',
            'password' => 'Str0ng!Pass',
        ]);
        $staff->markEmailAsVerified();
        $staff->tenant_id = $owner->tenant_id;
        $staff->save();

        $owner->tenant->update(['owner_user_id' => $owner->id]);

        $this->actingAs($staff->fresh())
            ->delete('http://styledesk.test/getting-started')
            ->assertForbidden();
    }

    public function test_the_app_opens_once_the_minimum_setup_is_met(): void
    {
        // Section 23: business, location and hours are the minimum. Services,
        // team and booking are skippable and must not hold anyone out.
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();

        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'services',
            'business_completed' => true,
            'location_completed' => true,
            'hours_completed' => true,
        ]);

        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/dashboard')
            ->assertOk();
    }

    public function test_the_app_stays_closed_until_location_and_hours_exist(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();

        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'location',
            'business_completed' => true,
        ]);

        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/dashboard')
            ->assertRedirect(route('onboarding.location'));
    }

    public function test_progress_survives_a_cleared_browser(): void
    {
        // The whole point of holding progress server-side: there is no request
        // input or client state involved in deciding where the user resumes.
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        // Deliberately below the minimum, so the redirect is about resuming
        // rather than about the minimum-setup allowance.
        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'booking',
            'business_completed' => true,
        ]);

        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/dashboard')
            ->assertRedirect(route('onboarding.booking'));
    }
}
