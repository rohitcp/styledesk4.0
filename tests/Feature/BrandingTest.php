<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Branding;
use App\Support\BrandPalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Acceptance criteria from the Branding spec.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Branding::DISK);

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

    private function owner(): User
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

    private function member(string $role): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $role.'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => $role,
        ]);

        return $user->fresh();
    }

    /** @return array<string, string> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'brand_primary' => '#0f766e',
            'brand_secondary' => '#0d9488',
            'brand_accent' => '#b45309',
        ], $overrides);
    }

    // ------------------------------------------------------- authorisation

    public static function allowedRoles(): array
    {
        return ['owner' => ['owner'], 'administrator' => ['administrator']];
    }

    #[DataProvider('allowedRoles')]
    public function test_owner_and_admin_can_open_branding(string $role): void
    {
        $user = $role === 'owner' ? $this->owner() : $this->member($role);

        $this->actingAs($user)
            ->get(route('settings.branding.show'))
            ->assertOk()
            ->assertSee('Branding');
    }

    public function test_other_roles_are_turned_away(): void
    {
        $this->actingAs($this->member('front-desk'))
            ->get(route('settings.branding.show'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_other_roles_cannot_save_branding(): void
    {
        $this->actingAs($this->member('service-provider'))
            ->patch(route('settings.branding.update'), $this->validPayload())
            ->assertRedirect(route('dashboard'));

        $this->assertNull($this->tenant->fresh()->brand_primary);
    }

    // -------------------------------------------------------------- saving

    public function test_colours_are_saved_and_confirmed(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.branding.update'), $this->validPayload())
            ->assertRedirect(route('settings.branding.show'))
            ->assertSessionHas('toast.message', 'Branding settings updated successfully.');

        $tenant = $this->tenant->fresh();

        $this->assertSame('#0f766e', $tenant->brand_primary);
        $this->assertSame('#0d9488', $tenant->brand_secondary);
        $this->assertSame('#b45309', $tenant->brand_accent);
    }

    /**
     * The columns must be real columns.
     *
     * Tenant sweeps any attribute not named in getCustomColumns() into a
     * `data` JSON blob and leaves the real column null — silently, and only
     * discoverable by querying the column directly.
     */
    public function test_the_palette_lands_in_real_columns(): void
    {
        $this->actingAs($this->owner())->patch(route('settings.branding.update'), $this->validPayload());

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->getTenantKey(),
            'brand_primary' => '#0f766e',
        ]);
    }

    public static function acceptedHexForms(): array
    {
        return [
            'with hash' => ['#1d4ed8', '#1d4ed8'],
            'without hash' => ['1d4ed8', '#1d4ed8'],
            'shorthand' => ['#abc', '#aabbcc'],
            'upper case' => ['#1D4ED8', '#1d4ed8'],
        ];
    }

    /**
     * Hex is normalised on the way in.
     *
     * "3D348B" and "#3d348b" are the same colour, and a brand guideline will
     * be written in whichever the designer preferred.
     */
    #[DataProvider('acceptedHexForms')]
    public function test_hex_values_are_normalised(string $input, string $stored): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.branding.update'), $this->validPayload(['brand_primary' => $input]))
            ->assertSessionHasNoErrors();

        $this->assertSame($stored, $this->tenant->fresh()->brand_primary);
    }

    public static function rejectedHexValues(): array
    {
        return [
            'not a colour' => ['teal'],
            'too short' => ['#12'],
            'too long' => ['#1234567'],
            'not hex digits' => ['#gggggg'],
            'a css function' => ['rgb(0,0,0)'],
        ];
    }

    #[DataProvider('rejectedHexValues')]
    public function test_invalid_hex_values_are_refused(string $input): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.branding.update'), $this->validPayload(['brand_primary' => $input]))
            ->assertSessionHasErrors('brand_primary');

        $this->assertNull($this->tenant->fresh()->brand_primary);
    }

    // -------------------------------------------------------------- assets

    public function test_a_logo_can_be_uploaded_and_saved(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)
            ->post(route('settings.branding.logo.upload'), [
                'logo' => UploadedFile::fake()->image('logo.png', 400, 120),
            ])
            ->assertOk();

        $path = $response->json('path');

        Storage::disk(Branding::DISK)->assertExists($path);

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), $this->validPayload(['logo_path' => $path]))
            ->assertSessionHasNoErrors();

        $this->assertSame($path, $this->tenant->fresh()->logo_path);
    }

    /**
     * SVG is accepted, which the `image` rule would have refused.
     */
    public function test_an_svg_logo_is_accepted(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.branding.logo.upload'), [
                'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            ])
            ->assertOk();
    }

    public function test_an_ico_favicon_is_accepted(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.branding.favicon.upload'), [
                'favicon' => UploadedFile::fake()->create('icon.ico', 4, 'image/vnd.microsoft.icon'),
            ])
            ->assertOk();
    }

    public function test_an_unsupported_file_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.branding.logo.upload'), [
                'logo' => UploadedFile::fake()->create('brand.pdf', 4, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_an_oversized_file_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.branding.logo.upload'), [
                'logo' => UploadedFile::fake()->create('logo.png', Branding::MAX_KB + 1, 'image/png'),
            ])
            ->assertSessionHasErrors('logo');
    }

    /**
     * A path that is not one of ours never reaches a src attribute.
     *
     * This value is rendered into every page the business serves, so a
     * crafted path would point the whole app's logo wherever the attacker
     * liked.
     */
    public function test_a_forged_asset_path_is_ignored(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.branding.update'), $this->validPayload([
                'logo_path' => '../../../etc/passwd',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->tenant->fresh()->logo_path);
    }

    /**
     * Replacing a logo does not leave the old one on disk.
     *
     * Otherwise every change leaves a file that is invisible in the UI, still
     * publicly reachable by anyone who noted the URL, and never cleaned up.
     */
    public function test_replacing_a_logo_deletes_the_previous_file(): void
    {
        $owner = $this->owner();

        $first = $this->actingAs($owner)->post(route('settings.branding.logo.upload'), [
            'logo' => UploadedFile::fake()->image('one.png'),
        ])->json('path');

        $this->actingAs($owner)->patch(route('settings.branding.update'), $this->validPayload(['logo_path' => $first]));

        $second = $this->actingAs($owner)->post(route('settings.branding.logo.upload'), [
            'logo' => UploadedFile::fake()->image('two.png'),
        ])->json('path');

        $this->actingAs($owner)->patch(route('settings.branding.update'), $this->validPayload(['logo_path' => $second]));

        Storage::disk(Branding::DISK)->assertMissing($first);
        Storage::disk(Branding::DISK)->assertExists($second);
    }

    // --------------------------------------------------------------- reset

    public function test_reset_restores_the_styledesk_default(): void
    {
        $owner = $this->owner();

        $path = $this->actingAs($owner)->post(route('settings.branding.logo.upload'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->json('path');

        $this->actingAs($owner)->patch(route('settings.branding.update'), $this->validPayload(['logo_path' => $path]));

        $this->actingAs($owner)
            ->delete(route('settings.branding.reset'))
            ->assertSessionHas('toast.type', 'success');

        $tenant = $this->tenant->fresh();

        /**
         * Nulled, not set to a copy of the defaults. A stored default is
         * indistinguishable from a deliberate choice, and the next release
         * that changes the house palette would strand every reset business
         * on the old one.
         */
        $this->assertNull($tenant->brand_primary);
        $this->assertNull($tenant->logo_path);
        $this->assertTrue(BrandPalette::forTenant($tenant)->isDefault());

        Storage::disk(Branding::DISK)->assertMissing($path);
    }

    // ------------------------------------------------------ where it lands

    /**
     * The palette is rendered by the server, not left to the browser.
     *
     * It used to come from localStorage, which made the brand a property of
     * one browser: a colleague saw the house purple, and an email or booking
     * page could never be branded at all.
     */
    public function test_the_palette_is_rendered_into_the_page(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->patch(route('settings.branding.update'), $this->validPayload());

        $this->actingAs($owner->fresh())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('--sd-brand:#0f766e', false);
    }

    public function test_a_business_without_branding_gets_the_house_palette(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('--sd-brand:'.BrandPalette::DEFAULTS['primary'], false);
    }

    public function test_the_favicon_is_linked_once_uploaded(): void
    {
        $owner = $this->owner();

        $path = $this->actingAs($owner)->post(route('settings.branding.favicon.upload'), [
            'favicon' => UploadedFile::fake()->image('icon.png', 512, 512),
        ])->json('path');

        $this->actingAs($owner)->patch(route('settings.branding.update'), $this->validPayload(['favicon_path' => $path]));

        $this->actingAs($owner->fresh())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('rel="icon"', false);
    }

    public function test_the_app_settings_card_links_to_the_module(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.branding.show'), false);
    }

    // -------------------------------------------------------- colour maths

    /**
     * Text on a button is computed, never chosen.
     *
     * This is the calculation that stops someone shipping white-on-yellow to
     * every client they have.
     */
    public function test_button_text_is_black_on_a_pale_fill_and_white_on_a_dark_one(): void
    {
        $this->assertSame('#0f0f10', BrandPalette::inkFor('#f5e663'));
        $this->assertSame('#ffffff', BrandPalette::inkFor('#3d348b'));
    }

    /**
     * The hover shade darkens, except on a brand already near-black.
     */
    public function test_the_hover_shade_lightens_only_for_a_near_black_brand(): void
    {
        $this->assertTrue(BrandPalette::luminance(BrandPalette::hoverFor('#3d348b')) < BrandPalette::luminance('#3d348b'));
        $this->assertTrue(BrandPalette::luminance(BrandPalette::hoverFor('#050505')) > BrandPalette::luminance('#050505'));
    }

    /**
     * The reading is taken against white, not against the computed ink.
     *
     * inkFor() returns whichever of black and white scores better, so grading
     * against it always passes and the warning could never fire. White is
     * what the app bar, booking header and email header actually print.
     */
    public function test_a_pale_primary_is_reported_as_failing_against_white(): void
    {
        $this->assertFalse(BrandPalette::grade('#f5e663', '#ffffff')['ok']);
        $this->assertTrue(BrandPalette::grade('#3d348b', '#ffffff')['ok']);
    }
}
