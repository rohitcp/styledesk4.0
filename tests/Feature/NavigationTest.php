<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);

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

    public function test_the_app_bar_offers_a_menu_button_and_a_drawer(): void
    {
        $response = $this->actingAs($this->member('owner'))->get('http://styledesk.test/dashboard');

        $response->assertOk()
            ->assertSee('data-drawer-toggle', false)
            ->assertSee('id="sd-drawer"', false)
            // The button is what a screen reader and a test both identify it
            // by; without it this is an unlabelled icon.
            ->assertSee('aria-label="Main menu"', false)
            ->assertSee('aria-controls="sd-drawer"', false);
    }

    /**
     * Below lg the icon rail is hidden, so anything reachable only from the
     * rail is unreachable on a tablet or phone unless the drawer repeats it.
     */
    public function test_the_drawer_offers_every_primary_destination(): void
    {
        $content = $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->getContent();

        $drawer = $this->drawerMarkup($content);

        foreach (config('navigation.primary') as $item) {
            $this->assertStringContainsString(
                e($item['label'], false),
                $drawer,
                "[{$item['label']}] is missing from the drawer."
            );

            foreach ($item['children'] ?? [] as $child) {
                if (! empty($child['separator'])) {
                    continue;
                }

                /* A section heading names the group below it and has no page
                   of its own — the drawer still has to show it, or six links
                   arrive as one undifferentiated list. */
                $label = $child['label'] ?? $child['section'];

                $this->assertStringContainsString(
                    e($label, false),
                    $drawer,
                    "[{$label}] is missing from the drawer."
                );
            }
        }

        foreach (config('navigation.utility') as $item) {
            $this->assertStringContainsString(e($item['label'], false), $drawer);
        }
    }

    /**
     * Shrinking the window must not become a way around a permission check.
     */
    public function test_app_settings_is_in_the_drawer_only_for_roles_that_may_open_it(): void
    {
        $owner = $this->actingAs($this->member('owner'))->get('http://styledesk.test/dashboard');
        $this->assertStringContainsString('App settings', $this->drawerMarkup($owner->getContent()));

        $manager = $this->actingAs($this->member('manager'))->get('http://styledesk.test/dashboard');
        $this->assertStringNotContainsString('App settings', $this->drawerMarkup($manager->getContent()));
    }

    public function test_the_current_page_is_marked_in_both_the_rail_and_the_drawer(): void
    {
        $content = $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/dashboard')
            ->getContent();

        // Once in the rail and once in the drawer: the two render from the
        // same config, so both should agree on where the user is.
        $this->assertSame(2, substr_count($content, 'aria-current="page"'));
    }

    /**
     * The footer must not float mid-page when the content is short.
     *
     * Asserted on the mechanism rather than on rendered geometry, which a
     * server-side test cannot see: the shell class is what turns the body into
     * a full-height column, and losing it during a class tidy-up is exactly
     * the change that would go unnoticed until someone opened an empty list.
     */
    public function test_the_application_shell_holds_the_footer_to_the_bottom(): void
    {
        $content = $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('styledesk_shell', $content);

        // The footer is the last block in the shell, so "push it down" has
        // somewhere to push it to.
        $footerAt = strrpos($content, '<footer');
        $mainAt = strrpos($content, '<main');

        $this->assertNotFalse($footerAt);
        $this->assertNotFalse($mainAt);
        $this->assertGreaterThan($mainAt, $footerAt, 'The footer must come after the content it sits below.');
    }

    /** Everything between the drawer's opening tag and its closing nav. */
    private function drawerMarkup(string $html): string
    {
        $start = strpos($html, 'id="sd-drawer"');

        $this->assertNotFalse($start, 'The drawer is not on the page at all.');

        $end = strpos($html, '</nav>', $start);

        return substr($html, $start, $end - $start);
    }

    /**
     * A screen that does not exist yet must not look like one you can open.
     *
     * Shifts, Calendar and Resource Availability rendered as ordinary links
     * to "#": clicking one did nothing, which reads as a broken product
     * rather than as work still to come.
     */
    public function test_an_unbuilt_screen_is_marked_unavailable_rather_than_linked(): void
    {
        $content = $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->getContent();

        /* Shifts is in the Staff menu and has no route. It is drawn dimmed,
           marked unavailable to a screen reader, and says why. */
        $this->assertMatchesRegularExpression(
            '~sd-menu__item--soon.*?aria-disabled="true".*?Shifts~s',
            $content,
        );

        /* And nothing anywhere is a link to nowhere. */
        $this->assertStringNotContainsString('href="#" data-pending-route', $content);
    }

    public function test_a_built_screen_is_still_an_ordinary_link(): void
    {
        $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('staff.index').'"', false)
            ->assertSee('href="'.route('staff.create').'"', false);
    }
}
