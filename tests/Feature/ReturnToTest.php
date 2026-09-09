<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\ReturnTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Leaving an edit screen returns to the page that opened it.
 *
 * Languages, Currency, Branding and Locations are each reachable from their
 * own module page and from the Business summary. Back, Cancel and the redirect
 * after a save all follow `?return=`, so somebody who arrived from Business
 * does not land on the Languages page they never asked for.
 */
class ReturnToTest extends TestCase
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
            'currency_code' => 'USD',
            'default_language' => 'en',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete',
            'completed_at' => now(),
        ]);
    }

    private function owner(): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'owner@styledesk.test', 'role' => 'owner',
        ]);

        return $user->fresh();
    }

    // ------------------------------------------------------------ the rule

    /** @return array<string, array{0: string, 1: string}> */
    public static function editScreens(): array
    {
        return [
            'languages' => ['settings/languages/edit', 'settings/languages'],
            'currency' => ['settings/currency/edit', 'settings/currency'],
            'branding' => ['settings/branding', 'settings'],
        ];
    }

    #[DataProvider('editScreens')]
    public function test_back_returns_to_the_page_that_opened_the_form(string $path, string $ownPage): void
    {
        $body = $this->actingAs($this->owner())
            ->get('http://styledesk.test/'.$path.'?return=settings/business')
            ->assertOk()
            ->getContent();

        // Both ways out of the form, and only those two: the breadcrumb still
        // names the module, which is the trail rather than an exit.
        $this->assertSame(
            2,
            substr_count($body, 'href="http://styledesk.test/settings/business"'),
            "Back and Cancel on /{$path} do not both return to the page that opened it."
        );
    }

    #[DataProvider('editScreens')]
    public function test_back_falls_back_to_the_module_when_nothing_opened_it(string $path, string $ownPage): void
    {
        $this->actingAs($this->owner())
            ->get('http://styledesk.test/'.$path)
            ->assertOk()
            ->assertSee('href="http://styledesk.test/'.$ownPage.'"', false);
    }

    public function test_a_save_lands_where_back_would_have(): void
    {
        $this->actingAs($this->owner())
            ->patch('http://styledesk.test/settings/languages', [
                'primary' => 'en',
                'return' => 'settings/business',
            ])
            ->assertRedirect('http://styledesk.test/settings/business');
    }

    public function test_a_save_with_nothing_to_return_to_stays_in_its_own_module(): void
    {
        $this->actingAs($this->owner())
            ->patch('http://styledesk.test/settings/languages', ['primary' => 'en'])
            ->assertRedirect(route('settings.languages.show'));
    }

    public function test_a_branch_form_returns_to_the_page_that_opened_it(): void
    {
        $location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'code' => 'RIV', 'status' => 'active',
            'address_line1' => '1 River Street', 'city' => 'Austin',
            'state' => 'Texas', 'postal_code' => '78701', 'country' => 'US',
            'timezone' => 'America/Chicago', 'is_primary' => true,
            'email' => 'riverside@nadia.test', 'phone' => '5125550100',
        ]);

        $body = $this->actingAs($this->owner())
            ->get('http://styledesk.test/settings/locations/'.$location->id.'/edit?return=settings/business')
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($body, 'href="http://styledesk.test/settings/business"'));
    }

    // ------------------------------------------------------------ the guard

    /**
     * An edit link must not become somewhere to send a signed-in user.
     *
     * Every one of these is refused and the module's own page is used, so a
     * crafted `return` cannot bounce anybody off this application.
     */
    public function test_a_return_pointing_off_this_application_is_refused(): void
    {
        $fallback = 'https://styledesk.test/settings/languages';

        foreach ([
            'https://evil.test/phish',
            '//evil.test/phish',
            'http://evil.test',
            'javascript:alert(1)',
            'settings/business?x=\\',
            '',
            'https://styledesk.test.evil.test/',
        ] as $candidate) {
            $request = Request::create('/settings/languages/edit', 'GET', ['return' => $candidate]);

            $this->assertSame(
                $fallback,
                ReturnTo::resolve($request, $fallback),
                "A return of [{$candidate}] was not refused."
            );
        }
    }

    public function test_a_path_within_this_application_is_accepted(): void
    {
        $request = Request::create('/settings/languages/edit', 'GET', ['return' => 'settings/business']);

        $this->assertSame(
            $request->getSchemeAndHttpHost().'/settings/business',
            ReturnTo::resolve($request, 'unused')
        );
        $this->assertSame('settings/business', ReturnTo::path($request));
    }
}
