<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
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
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
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
        $type = BusinessType::create(['name' => 'Hair Salon', 'slug' => 'hair-salon']);

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'slug' => 'bella',
                'business_phone' => '555 0100',
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

    public function test_the_business_name_is_capitalised(): void
    {
        $type = BusinessType::create(['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'bella beauty studio',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('onboarding.location'));

        $this->assertSame('Bella Beauty Studio', $user->fresh()->tenant->name);
    }

    public function test_capitalisation_leaves_deliberate_casing_alone(): void
    {
        // Str::title() would turn these into "Bella Beauty" and "Medspa",
        // overriding capitalisation the owner chose.
        $type = BusinessType::create(['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'BELLA MedSpa',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
            ]);

        $this->assertSame('BELLA MedSpa', $user->fresh()->tenant->name);
    }

    public function test_a_logo_can_be_uploaded_before_the_tenant_exists(): void
    {
        Storage::fake('brand');

        $type = BusinessType::create(['name' => 'Spa', 'slug' => 'spa']);
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

    public function test_business_type_is_required(): void
    {
        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'business_phone' => '555 0100',
            ])
            ->assertSessionHasErrors('business_type_ids');

        $this->assertSame(0, Tenant::count());
    }

    public function test_the_slug_is_generated_from_the_business_name(): void
    {
        $type = BusinessType::create(['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->user())
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('onboarding.location'));

        $this->assertSame('bella-beauty-studio', Tenant::first()->slug);
    }

    public function test_a_taken_slug_gets_a_numeric_suffix(): void
    {
        Tenant::create(['name' => 'Bella Beauty Studio', 'slug' => 'bella-beauty-studio']);
        $type = BusinessType::create(['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->user();

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'business_phone' => '555 0100',
                'business_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('onboarding.location'));

        // Found via the user, not Tenant::first(): the primary key is a UUID,
        // so "first" is not creation order and would pick either row.
        $this->assertSame('bella-beauty-studio-2', $user->fresh()->tenant->slug);
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

    public function test_services_are_stored_in_minor_units(): void
    {
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'services']);

        $this->actingAs($user->fresh())
            ->post('http://styledesk.test/onboarding/services', [
                'services' => [
                    ['name' => 'Cut', 'duration_minutes' => 45, 'price' => '38.50'],
                ],
            ])
            ->assertRedirect(route('onboarding.team'));

        $this->assertSame(3850, Service::first()->price_minor);
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
