<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Acceptance criteria from the Languages spec.
 */
class LanguageTest extends TestCase
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

    private function enableSpanish(): void
    {
        $this->tenant->languages()->create(['language_code' => 'es', 'position' => 1]);
    }

    // ------------------------------------------------------------ registry

    /**
     * A language planned but not translated must not reach a selector.
     *
     * A business switching to French and finding half its app in English
     * reads that as a fault, not as a work in progress.
     */
    public function test_only_active_languages_are_offered(): void
    {
        $available = Locale::available();

        $this->assertTrue($available->has('en'));
        $this->assertTrue($available->has('es'));
        $this->assertFalse($available->has('fr'));
        $this->assertFalse($available->has('de'));
        $this->assertFalse($available->has('zh'));
    }

    /** A selector offering "Spanish" asks a Spanish speaker to read English. */
    public function test_a_language_is_named_in_its_own_words(): void
    {
        $this->assertSame('Español', Locale::nativeName('es'));
        $this->assertSame('Spanish', Locale::name('es'));
    }

    // ------------------------------------------------------- authorisation

    public function test_owner_and_admin_can_open_the_module(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.languages.show'))
            ->assertOk()
            ->assertSee('Languages');

        $this->actingAs($this->member('administrator'))
            ->get(route('settings.languages.show'))
            ->assertOk();
    }

    public function test_other_roles_cannot_change_the_business_configuration(): void
    {
        $this->actingAs($this->member('front-desk'))
            ->get(route('settings.languages.show'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->member('service-provider'))
            ->patch(route('settings.languages.update'), ['primary' => 'es'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('en', $this->tenant->fresh()->default_language);
    }

    /**
     * Choosing your own language is not an administrative act.
     *
     * Gating it behind App Settings would put the feature out of reach of
     * everyone it exists for.
     */
    public function test_any_signed_in_user_can_choose_their_own_language(): void
    {
        $this->enableSpanish();

        $member = $this->member('front-desk');

        $this->actingAs($member)
            ->patch(route('language.preference'), ['locale' => 'es'])
            ->assertSessionHasNoErrors();

        $this->assertSame('es', $member->fresh()->locale);
    }

    // ------------------------------------------------ business configuration

    public function test_the_primary_and_secondary_languages_are_saved(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.languages.update'), ['primary' => 'en', 'secondary' => ['es']])
            ->assertRedirect(route('settings.languages.show'))
            ->assertSessionHas('toast.message', __('languages.saved'));

        $tenant = $this->tenant->fresh();

        $this->assertSame('en', $tenant->default_language);
        $this->assertSame(['es'], $tenant->languages()->pluck('language_code')->all());
        $this->assertSame(['en', 'es'], Locale::enabledFor($tenant)->all());
    }

    /**
     * Ticking your own primary as a secondary is a harmless mistake.
     *
     * The language is enabled either way, so it is dropped rather than
     * refused — an error would stop a save that already said what was wanted.
     */
    public function test_the_primary_is_dropped_from_the_secondaries(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.languages.update'), ['primary' => 'es', 'secondary' => ['es', 'en']])
            ->assertSessionHasNoErrors();

        $tenant = $this->tenant->fresh();

        $this->assertSame('es', $tenant->default_language);
        $this->assertSame(['en'], $tenant->languages()->pluck('language_code')->all());
    }

    public function test_an_unsupported_language_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.languages.update'), ['primary' => 'fr'])
            ->assertSessionHasErrors('primary');

        $this->assertSame('en', $this->tenant->fresh()->default_language);
    }

    /**
     * The primary is enabled by being the primary.
     *
     * A list that omitted it would leave the person who chose it unable to
     * select it in the header.
     */
    public function test_the_primary_is_always_among_the_enabled_languages(): void
    {
        $this->tenant->forceFill(['default_language' => 'es'])->save();

        $this->assertContains('es', Locale::enabledFor($this->tenant->fresh())->all());
    }

    // ---------------------------------------------------- resolution order

    public function test_a_user_without_a_choice_gets_the_business_default(): void
    {
        $this->tenant->forceFill(['default_language' => 'es'])->save();
        $this->enableSpanish();

        $this->assertSame('es', Locale::forUser($this->owner()));
    }

    public function test_a_personal_choice_beats_the_business_default(): void
    {
        $this->enableSpanish();

        $user = $this->owner();
        $user->forceFill(['locale' => 'es'])->save();

        $this->assertSame('es', Locale::forUser($user->fresh()));
    }

    /**
     * A personal choice only counts while the business still offers it.
     *
     * Honouring a disabled language would make the business setting a
     * suggestion rather than a setting.
     */
    public function test_a_choice_the_business_has_switched_off_falls_back(): void
    {
        $this->enableSpanish();

        $user = $this->owner();
        $user->forceFill(['locale' => 'es'])->save();

        $this->tenant->languages()->delete();

        $this->assertSame('en', Locale::forUser($user->fresh()));

        // The preference is kept, not cleared, so switching Spanish back on
        // restores what the person had chosen.
        $this->assertSame('es', $user->fresh()->locale);
    }

    /**
     * Two people in one salon, two languages.
     */
    public function test_one_users_language_does_not_change_anothers(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $colleague = $this->member('manager');

        $this->actingAs($owner)->patch(route('language.preference'), ['locale' => 'es']);

        $this->assertSame('es', $owner->fresh()->locale);
        $this->assertNull($colleague->fresh()->locale);
        $this->assertSame('en', Locale::forUser($colleague->fresh()));
    }

    public function test_a_language_the_business_has_not_enabled_is_refused(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->patch(route('language.preference'), ['locale' => 'es'])
            ->assertSessionHasErrors('locale');

        $this->assertNull($owner->fresh()->locale);
    }

    // ------------------------------------------------------- the interface

    public function test_the_interface_is_rendered_in_the_chosen_language(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Configuración')
            ->assertSee('lang="es"', false);
    }

    public function test_the_interface_stays_english_by_default(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('App settings')
            ->assertSee('lang="en"', false);
    }

    /**
     * The selector is furniture when there is only one language.
     */
    public function test_the_header_selector_appears_only_with_more_than_one_language(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('data-language-menu', false);

        $this->enableSpanish();

        $this->actingAs($owner->fresh())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('data-language-menu', false);
    }

    // ------------------------------------------------------------ fallback

    /**
     * A gap shows English, never the key.
     *
     * Laravel's __() returns the key when nothing matches, which is how
     * "staff.status.active" ends up printed in front of a client.
     */
    public function test_a_missing_translation_falls_back_to_english_and_is_logged(): void
    {
        Log::spy();

        app()->setLocale('es');

        // A key that exists in English only. `common.save` is translated, so
        // one that is deliberately absent from lang/es is what proves this.
        $this->assertSame('Dashboard', Locale::get('navigation.dashboard', [], 'en'));

        $value = Locale::get('settings.no_such_key_for_this_test');

        $this->assertStringNotContainsString('no_such_key_for_this_test', $value);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_translated_key_is_returned_in_the_chosen_language(): void
    {
        $this->assertSame('Guardar', Locale::get('common.save', [], 'es'));
        $this->assertSame('Save', Locale::get('common.save', [], 'en'));
    }

    // ------------------------------------------------- the settings cards

    /**
     * Every card, not just the page around it.
     *
     * The module names and descriptions lived in config/app_settings.php as
     * English literals, so the heading translated and the thirty-six cards
     * beneath it did not — a page half in each language, which reads as a
     * fault rather than as partial coverage.
     */
    public function test_the_settings_cards_are_translated(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('settings.index'))
            ->assertOk()
            // Card names.
            ->assertSee('Ubicaciones')
            ->assertSee('Identidad de marca')
            ->assertSee('Roles y permisos')
            // A description.
            ->assertSee('Sucursales, direcciones, responsables')
            // A group heading and its description.
            ->assertSee('Reservas y operaciones')
            ->assertSee('Lo que tus clientes ven, rellenan y compran.')
            // A status badge.
            ->assertSee('Próximamente')
            ->assertDontSee('Coming soon');
    }

    public function test_the_settings_cards_stay_english_by_default(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Locations')
            ->assertSee('Branding')
            ->assertSee('Business Setup')
            ->assertSee('Coming soon')
            ->assertDontSee('Ubicaciones');
    }

    /**
     * Counts are pluralised by the language, not by Str::plural().
     *
     * That helper only knows English and would have produced "2 ubicación
     * activas" — an English rule applied to a language it was never written
     * for. The figure itself is not in the phrase: the card prints it in bold
     * beside the words, and including it rendered "1 1 active location".
     */
    public function test_a_card_count_is_pluralised_in_the_chosen_language(): void
    {
        $this->assertSame('ubicación activa', trans_choice('modules.counts.active_locations', 1, [], 'es'));
        $this->assertSame('ubicaciones activas', trans_choice('modules.counts.active_locations', 2, [], 'es'));
        $this->assertSame('active location', trans_choice('modules.counts.active_locations', 1, [], 'en'));
        $this->assertSame('active locations', trans_choice('modules.counts.active_locations', 2, [], 'en'));
    }

    /**
     * A module without a translation still shows its own name.
     *
     * The config keeps its English literals as the last-resort fallback, so
     * adding a module and forgetting its translation is an untranslated card
     * rather than "modules.modules.foo.name" printed on the page.
     */
    public function test_an_untranslated_module_falls_back_to_its_config_name(): void
    {
        config()->set('app_settings.groups', [[
            'name' => 'Nowhere', 'description' => 'A group with no translation.',
            'modules' => [[
                'key' => 'no-translation-for-this',
                'name' => 'Untranslated Module',
                'description' => 'It has no key.',
                'icon' => 'gear',
            ]],
        ]]);

        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Untranslated Module')
            ->assertSee('Nowhere')
            ->assertDontSee('modules.modules', false);
    }

    // ---------------------------------------------------------- the module

    public function test_the_app_settings_card_links_to_the_module(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.languages.show'), false);
    }
}
