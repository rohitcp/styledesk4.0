<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance criteria from docs/decisions/0002-onboarding-spec.md.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

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

        $this->actingAs($user)
            ->post('http://styledesk.test/onboarding/business', [
                'name' => 'Bella Beauty Studio',
                'slug' => 'bella',
                'business_email' => 'hello@bella.test',
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
            ->post('http://styledesk.test/onboarding/team', [])
            ->assertRedirect(route('onboarding.booking'));

        $owner = Staff::where('user_id', $user->id)->first();

        $this->assertNotNull($owner, 'The team step pre-populates the owner, so they must exist as staff.');
        $this->assertSame('owner', $owner->role);
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

    public function test_progress_survives_a_cleared_browser(): void
    {
        // The whole point of holding progress server-side: there is no request
        // input or client state involved in deciding where the user resumes.
        $user = $this->user();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'booking']);

        $this->actingAs($user->fresh())
            ->get('http://styledesk.test/dashboard')
            ->assertRedirect(route('onboarding.booking'));
    }
}
