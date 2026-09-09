<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Icon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;
use Tests\TestCase;

class IconTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_icon_is_inlined_with_the_requested_height(): void
    {
        $svg = Icon::inline('gear', 18)->toHtml();

        $this->assertStringContainsString('<svg ', $svg);
        $this->assertStringContainsString('height="18"', $svg);
        // Inline, inheriting the surrounding colour — this is what lets the
        // existing .sd-navicon hover and is-active states keep working.
        $this->assertStringContainsString('fill="currentColor"', $svg);
    }

    public function test_the_width_follows_the_view_box_so_wide_icons_are_not_squashed(): void
    {
        // Font Awesome draws every icon 512 tall but 448-640 wide. A fixed
        // square box letterboxes the wide ones, leaving them visibly shorter
        // than their neighbours in the same rail.
        $narrow = Icon::inline('user', 18)->toHtml();   // 448 wide
        $wide = Icon::inline('users', 18)->toHtml();    // 640 wide

        $this->assertStringContainsString('width="16"', $narrow);
        $this->assertStringContainsString('width="23"', $wide);

        // The height is what stays constant, which is what keeps them level.
        $this->assertStringContainsString('height="18"', $narrow);
        $this->assertStringContainsString('height="18"', $wide);
    }

    public function test_icons_are_hidden_from_screen_readers_unless_labelled(): void
    {
        // Each one sits inside a link that already carries its own label, so
        // announcing the icon too would read the same thing twice.
        $this->assertStringContainsString('aria-hidden="true"', Icon::inline('gear', 18)->toHtml());

        $labelled = Icon::inline('gear', 18, new ComponentAttributeBag(['aria-label' => 'Settings']))->toHtml();
        $this->assertStringNotContainsString('aria-hidden', $labelled);
    }

    public function test_a_missing_icon_says_how_to_add_it(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not vendored/');

        Icon::inline('definitely-not-an-icon', 18);
    }

    public function test_an_icon_name_cannot_escape_the_icon_directory(): void
    {
        // Nothing passes user input here today, but a name arriving from a
        // column or a query string later must not be a path traversal.
        $this->expectException(InvalidArgumentException::class);

        Icon::inline('../../../../etc/passwd', 18);
    }

    public function test_the_dashboard_top_navigation_renders_the_icons(): void
    {
        $user = $this->onboardedOwner();

        $response = $this->actingAs($user)->get('http://styledesk.test/dashboard');

        $response->assertOk();

        // One <svg> per nav control, inlined rather than a webfont <i>.
        $this->assertGreaterThanOrEqual(15, substr_count($response->getContent(), '<svg '));
        $this->assertStringNotContainsString('fa-light', $response->getContent());
    }

    private function onboardedOwner(): User
    {
        $user = User::create([
            'first_name' => 'Rohit', 'last_name' => 'Philip',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();

        $tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        $tenant->forceFill(['owner_user_id' => $user->id])->save();
        $user->forceFill(['tenant_id' => $tenant->getTenantKey()])->save();

        TenantOnboarding::create([
            'tenant_id' => $tenant->getTenantKey(),
            'current_step' => 'complete',
            'completed_at' => now(),
        ]);

        return $user->fresh();
    }
}
