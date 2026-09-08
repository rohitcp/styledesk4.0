<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationClosure;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\BrandPalette;
use App\Support\Locale;
use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Acceptance criteria from the Languages spec.
 */
class LanguageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The lang/ files every offered language is expected to carry.
     *
     * The screens somebody uses on their first day — the Clients, Services,
     * Staff and Bookings modules, their option lists, and the shared words and
     * navigation wrapped around them. Add a module here as it is translated,
     * and the two tests below hold every language to it at once.
     */
    private const TRANSLATED_MODULES = [
        'clients', 'client_options', 'services', 'staff', 'staff_options',
        'bookings', 'leads', 'payments', 'reasons',
        'resources', 'locations', 'sales',
        'common', 'navigation', 'dashboard', 'settings', 'modules',
    ];

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
     * Every registered language can be chosen.
     *
     * `active` used to gate this, so a language without a finished lang/
     * directory never reached the selector. That protected the reader from a
     * half-English app and cost the business the choice: French was offered
     * at sign-up, stored, and then silently ignored, because supports() read
     * the same gated list and had no way to say "yes, but partly".
     */
    public function test_every_registered_language_is_offered(): void
    {
        $available = Locale::available();

        foreach (array_keys(config('languages.supported')) as $code) {
            $this->assertTrue($available->has($code), "Language [{$code}] is registered but not offered.");
            $this->assertTrue(Locale::supports($code), "Language [{$code}] is offered but not usable.");
        }
    }

    /**
     * The flag now describes the translation rather than locking the choice.
     *
     * A language may be picked whether or not it is finished; what must stay
     * true is that the selector can tell the reader which is which.
     */
    public function test_completeness_is_reported_without_gating_the_choice(): void
    {
        // Finished: every key English has.
        $this->assertTrue(Locale::isComplete('en'));
        $this->assertTrue(Locale::isComplete('es'));

        /* Translated in part: Chinese has only the shell, French has no
           lang/ directory at all. */
        $this->assertFalse(Locale::isComplete('zh'));
        $this->assertFalse(Locale::isComplete('fr'));

        // Unfinished never means unusable.
        $this->assertTrue(Locale::supports('fr'));
    }

    /** An unfinished language is labelled as such where it is chosen. */
    public function test_the_picker_marks_an_unfinished_language(): void
    {
        $this->actingAs($this->member('owner'))
            ->get('http://styledesk.test/settings/languages/edit')
            ->assertOk()
            ->assertSee('Français')
            ->assertSee(__('languages.partial'))
            ->assertSee(__('languages.partial_hint'));
    }

    /** A language that claims to be finished must actually have its files. */
    public function test_a_language_marked_complete_has_translations(): void
    {
        foreach (Locale::available()->keys() as $code) {
            if (! Locale::isComplete($code)) {
                continue;
            }

            $this->assertDirectoryExists(base_path('lang/'.$code));

            $missing = array_diff(
                array_map(fn ($f) => basename($f), glob(base_path('lang/en/*.php'))),
                array_map(fn ($f) => basename($f), glob(base_path('lang/'.$code.'/*.php'))),
            );

            $this->assertSame([], array_values($missing), "Language [{$code}] is marked complete but is missing files.");
        }
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

    /**
     * Unregistered, not merely unfinished.
     *
     * This asked for French until French became selectable. The rule being
     * tested is that a code the register has never heard of is refused — an
     * unfinished language is a different thing and is accepted on purpose.
     */
    public function test_an_unsupported_language_is_refused(): void
    {
        $this->assertArrayNotHasKey('ja', config('languages.supported'));

        $this->actingAs($this->owner())
            ->patch(route('settings.languages.update'), ['primary' => 'ja'])
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
     * The frame around every screen, not just the screen.
     *
     * The Add client form was translated and the app around it was not — the
     * menu it was reached through still read "Quick Actions" over "Añadir
     * cliente", and the idle-session dialog was English on every page in the
     * product. Both were the same fault: copy printed straight from a config
     * file or typed into a partial, where nothing resolves a translation.
     */
    public function test_the_navigation_around_a_form_is_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->get(route('clients.create'))
            ->assertOk()
            // The headings inside an open menu.
            ->assertSee('Acciones rápidas')
            ->assertDontSee('Quick Actions')
            // Entries whose own screens are not built yet are still read.
            ->assertSee('Tarjetas regalo')
            ->assertSee('Marketing por SMS')
            ->assertDontSee('Gift Cards')
            ->assertDontSee('SMS Marketing')
            // An accessible name that differs from the label beside it.
            ->assertSee('Servicios y recursos')
            ->assertDontSee('Services and resources');
    }

    /** The idle-session dialogs sit on every signed-in page. */
    public function test_the_session_timeout_dialogs_are_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->get(route('clients.create'))
            ->assertOk()
            ->assertSee('Por tu seguridad, cerraremos tu sesión porque no ha habido actividad.')
            ->assertSee('Seguir con la sesión abierta')
            ->assertSee('Tu sesión terminó porque no hubo actividad. Vuelve a iniciar sesión para continuar.')
            ->assertDontSee('Stay signed in')
            ->assertDontSee('Session expires in');
    }

    /**
     * Every navigation entry a reader can see resolves to a key.
     *
     * Cheaper than a test per label and it catches the next one added: an
     * entry with no key falls back to the English in the config, which is how
     * six of them came to sit untranslated in a Spanish menu.
     */
    public function test_every_visible_navigation_entry_has_a_translation_key(): void
    {
        $missing = [];

        $walk = function (array $items) use (&$walk, &$missing): void {
            foreach ($items as $item) {
                if (isset($item['section'])) {
                    if (! isset($item['key']) || ! trans()->has('navigation.sections.'.$item['key'])) {
                        $missing[] = 'section: '.$item['section'];
                    }

                    continue;
                }

                if (! isset($item['key']) || ! trans()->has('navigation.'.$item['key'])) {
                    $missing[] = 'label: '.($item['label'] ?? '?');
                }

                if (isset($item['aria']) && ! trans()->has('navigation.aria.'.($item['key'] ?? ''))) {
                    $missing[] = 'aria: '.$item['aria'];
                }

                $walk($item['children'] ?? []);
            }
        };

        $walk(array_merge(config('navigation.primary'), config('navigation.utility')));

        $this->assertSame([], $missing);
    }

    /**
     * The add screens read in the reader's language, in every language that
     * claims to cover them.
     *
     * This is what the bug actually was. The Add client form was fully
     * translated in Spanish and fully English in Chinese, because lang/zh had
     * no clients.php at all and every key fell through to the fallback. A
     * language that is offered in the picker has to carry the modules a
     * person uses on their first day, or the picker is offering something the
     * app does not do.
     */
    public function test_the_add_screens_carry_a_translation_in_every_offered_language(): void
    {
        $needed = self::TRANSLATED_MODULES;

        $missing = [];

        foreach (array_keys((array) config('languages.supported')) as $locale) {
            foreach ($needed as $file) {
                if (! file_exists(lang_path($locale.'/'.$file.'.php'))) {
                    $missing[] = $locale.'/'.$file.'.php';
                }
            }
        }

        $this->assertSame([], $missing, 'Every offered language needs these files.');
    }

    /**
     * And every key in them, not just the file.
     *
     * A file present but short of keys is the same screen half in English,
     * one level down — and it is the failure a reader notices rather than the
     * one a directory listing shows.
     */
    public function test_the_add_screen_translations_are_complete(): void
    {
        $needed = self::TRANSLATED_MODULES;

        $flatten = function (array $values, string $prefix = '') use (&$flatten): array {
            $flat = [];

            foreach ($values as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                $flat += is_array($value) ? $flatten($value, $path) : [$path => $value];
            }

            return $flat;
        };

        $gaps = [];

        foreach ($needed as $file) {
            $english = $flatten(require lang_path('en/'.$file.'.php'));

            foreach (array_keys((array) config('languages.supported')) as $locale) {
                if ($locale === 'en' || ! file_exists(lang_path($locale.'/'.$file.'.php'))) {
                    continue;
                }

                $translated = $flatten(require lang_path($locale.'/'.$file.'.php'));
                $absent = array_keys(array_diff_key($english, $translated));

                foreach ($absent as $key) {
                    $gaps[] = $locale.'/'.$file.'.php: '.$key;
                }
            }
        }

        $this->assertSame([], $gaps);
    }

    /**
     * The getting-started checklist, in Chinese.
     *
     * Its nine labels were built as English literals in DashboardController,
     * so the card around them translated and the list inside it did not —
     * copy assembled in PHP is still copy on a screen.
     */
    public function test_the_getting_started_checklist_is_translated_into_chinese(): void
    {
        $owner = $this->owner();
        $owner->tenant->syncLanguages(['en', 'zh']);
        $owner->forceFill(['locale' => 'zh'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('添加你的第一项服务')
            ->assertSee('配置员工排班')
            ->assertSee('添加你的标志和品牌样式')
            /* The owner's dismiss button, which was typed into the view. */
            ->assertSee('不再显示')
            ->assertDontSee('Add your first service')
            ->assertDontSee('>Dismiss<', false);
    }

    /** The App Settings directory, in German. */
    public function test_the_settings_directory_is_translated_into_german(): void
    {
        $owner = $this->owner();
        $owner->tenant->syncLanguages(['en', 'de']);
        $owner->forceFill(['locale' => 'de'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Einstellungen')
            /* A group heading and a module card, which come from different
               files — the page needs both to read. */
            ->assertSee('Betrieb einrichten')
            ->assertSee('Öffnungszeiten')
            ->assertDontSee('Business Setup')
            ->assertDontSee('Business Hours');
    }

    /** The Chinese resources screen, end to end. */
    public function test_the_resources_screen_is_translated_into_chinese(): void
    {
        $owner = $this->owner();
        $owner->tenant->syncLanguages(['en', 'zh']);
        $owner->forceFill(['locale' => 'zh'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('resources.index'))
            ->assertOk()
            ->assertSee('资源')
            ->assertSee('添加资源')
            ->assertDontSee('Add resource')
            ->assertDontSee('All categories');
    }

    /** The Chinese bookings screen, end to end. */
    public function test_the_bookings_screen_is_translated_into_chinese(): void
    {
        $owner = $this->owner();
        $owner->tenant->syncLanguages(['en', 'zh']);
        $owner->forceFill(['locale' => 'zh'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('预约')
            ->assertSee('今天')
            ->assertSee('全部预约')
            ->assertDontSee('All bookings')
            ->assertDontSee('Check-in pending');
    }

    /** The Chinese Add client form, end to end. */
    public function test_the_add_client_form_is_translated_into_chinese(): void
    {
        $owner = $this->owner();
        $owner->tenant->syncLanguages(['en', 'zh']);
        $owner->forceFill(['locale' => 'zh'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('clients.create'))
            ->assertOk()
            ->assertSee('添加客户')
            ->assertSee('名字')
            ->assertSee('电子邮箱')
            ->assertSee('快捷操作')
            ->assertDontSee('First name')
            ->assertDontSee('Quick Actions');
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

    // --------------------------------------------------- the business module

    public function test_the_business_screen_is_translated(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('settings.business.show'))
            ->assertOk()
            ->assertSee('Información del negocio')
            ->assertSee('Nombre del negocio')
            ->assertSee('Datos de contacto')
            ->assertSee('Ajustes regionales')
            ->assertSee('Editar negocio')
            ->assertDontSee('Business information')
            ->assertDontSee('Contact information');
    }

    public function test_the_business_form_is_translated(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        $this->actingAs($owner->fresh())
            ->get(route('settings.business.edit'))
            ->assertOk()
            ->assertSee('Razón social')
            ->assertSee('Primer día de la semana')
            // Dropdown values, not just their labels.
            ->assertSee('Los precios incluyen impuestos')
            ->assertSee('Cualquier miembro del equipo disponible')
            ->assertSee('Domingo')
            // A placeholder.
            ->assertSee('Especialistas en pelo rizado');
    }

    /**
     * A form that validates in English hands its reader the one sentence on
     * the page they most need to understand in the language they did not
     * choose.
     */
    public function test_business_validation_messages_are_translated(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        $response = $this->actingAs($owner->fresh())
            ->patch(route('settings.business.update'), [
                'name' => '',
                'status' => 'active',
                'business_email' => 'not-an-email',
            ]);

        $response->assertSessionHasErrors([
            'name' => 'El nombre del negocio es obligatorio.',
            'business_email' => 'Introduce una dirección de correo válida.',
        ]);
    }

    public function test_the_business_saved_toast_is_translated(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        $this->actingAs($owner->fresh())
            ->patch(route('settings.business.update'), [
                'name' => 'Nadia Hair Studio',
                'status' => 'active',
                'business_email' => 'hello@nadia.test',
            ])
            ->assertSessionHas('toast.message', 'Configuración del negocio actualizada correctamente.');
    }

    /**
     * StyleDesk's own reference list is ours to translate.
     *
     * Unlike a service name or a client note, which is the business's own
     * words and stays exactly as typed.
     */
    public function test_business_types_are_translated_but_business_content_is_not(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        $this->tenant->forceFill(['business_category' => 'Curly hair specialists'])->save();

        $this->seed(BusinessTypeSeeder::class);

        $this->actingAs($owner->fresh())
            ->get(route('settings.business.edit'))
            ->assertOk()
            ->assertSee('Peluquería')
            ->assertDontSee('Hair Salon')
            // What the business typed is untouched.
            ->assertSee('Curly hair specialists');
    }

    // -------------------------------------------------- the locations module

    private function spanishOwner(): User
    {
        $this->enableSpanish();

        $owner = $this->owner();
        $owner->forceFill(['locale' => 'es'])->save();

        return $owner->fresh();
    }

    public function test_the_add_location_form_is_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->get(route('settings.locations.create'))
            ->assertOk()
            ->assertSee('Añadir ubicación')
            ->assertSee('Información de la ubicación')
            ->assertSee('Nombre de la ubicación')
            ->assertSee('Datos de contacto')
            ->assertSee('Responsable de la ubicación')
            ->assertDontSee('Location information')
            ->assertDontSee('Contact details');
    }

    /**
     * Dropdown values, not just their labels.
     *
     * A form whose labels are Spanish and whose options are English is the
     * same half-translated screen, one level in.
     */
    public function test_location_dropdown_values_are_translated(): void
    {
        $content = $this->actingAs($this->spanishOwner())
            ->get(route('settings.locations.create'))
            ->assertOk()
            // Statuses are plain markup, so they are readable as they stand.
            ->assertSee('Activa')
            ->assertSee('Inactiva')
            ->getContent();

        /**
         * The islands' options are decoded rather than matched as text.
         *
         * They reach the page inside a data-props attribute, and json_encode
         * escapes every non-ASCII character — "Peluquería" is literally
         * "Peluquer\u00eda" in the HTML. Asserting on the escape would be
         * asserting on the encoding rather than on the words.
         */
        $props = self::islandProps($content);

        $types = collect($props)->firstWhere('name', 'type');
        $this->assertContains('Peluquería', array_values($types['options']));
        $this->assertContains('Barbería', array_values($types['options']));

        $hours = collect($props)->first(fn (array $p) => isset($p['days']));
        $this->assertContains('Miércoles', $hours['days']);
    }

    /**
     * Every set of island props on a page, decoded.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function islandProps(string $html): array
    {
        preg_match_all("/data-props='([^']*)'/", $html, $matches);

        return collect($matches[1])
            ->map(fn (string $json) => json_decode(html_entity_decode($json, ENT_QUOTES), true))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The island's own controls come from the server.
     *
     * A Vue component carrying its own copy of every string would be a second
     * place to translate and a second place to forget.
     */
    public function test_the_hours_editor_controls_are_translated(): void
    {
        $content = $this->actingAs($this->spanishOwner())
            ->get(route('settings.locations.create'))
            ->assertOk()
            ->getContent();

        $props = collect(self::islandProps($content))->first(fn (array $p) => isset($p['labels']));

        $this->assertSame('Copiar el lunes a mar–vie', $props['labels']['copy_monday']);
        $this->assertSame('Abierto', $props['labels']['open']);
        $this->assertSame('Cerrado todo el día', $props['labels']['closed_all_day']);
        $this->assertSame('a', $props['labels']['to']);
    }

    public function test_the_locations_list_is_translated(): void
    {
        $owner = $this->spanishOwner();

        Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River Street',
            'city' => 'Austin', 'state' => 'Texas', 'postal_code' => '78701',
            'country' => 'US', 'timezone' => 'America/Chicago',
            'phone' => '+1 512 555 0100', 'email' => 'riverside@nadia.test',
            'is_primary' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('settings.locations.index'))
            ->assertOk()
            ->assertSee('Ubicaciones')
            ->assertSee('ubicación activa')
            ->assertSee('Responsable de la ubicación')
            // What the business typed stays as typed.
            ->assertSee('Riverside');
    }

    public function test_location_validation_messages_are_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->post(route('settings.locations.store'), ['status' => 'active'])
            ->assertSessionHasErrors([
                'name' => 'El nombre de la ubicación es obligatorio.',
                'city' => 'La ciudad es obligatoria.',
            ]);
    }

    public function test_the_location_saved_toast_is_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->post(route('settings.locations.store'), [
                'name' => 'Riverside', 'status' => 'active',
                'address_line1' => '1 River Street', 'city' => 'Austin',
                'state' => 'Texas', 'postal_code' => '78701',
                'country' => 'US', 'timezone' => 'America/Chicago',
                'phone' => '+1 512 555 0100', 'email' => 'riverside@nadia.test',
            ])
            ->assertSessionHas('toast.message', 'Ubicación creada correctamente.');
    }

    // ----------------------------------------------- the business hours module

    private function spanishLocation(): Location
    {
        return Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River Street',
            'city' => 'Austin', 'state' => 'Texas', 'postal_code' => '78701',
            'country' => 'US', 'timezone' => 'America/Chicago',
            'phone' => '+1 512 555 0100', 'email' => 'riverside@nadia.test',
            'is_primary' => true,
        ]);
    }

    public function test_the_business_hours_overview_is_translated(): void
    {
        $owner = $this->spanishOwner();
        $location = $this->spanishLocation();

        LocationClosure::create([
            'location_id' => $location->id, 'type' => 'maintenance', 'name' => 'Reforma',
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('settings.hours.index'))
            ->assertOk()
            ->assertSee('Horario comercial')
            ->assertSee('Próximamente')
            ->assertSee('En curso')
            ->assertSee('Cierre por mantenimiento')
            ->assertSee('Miércoles')
            ->assertDontSee('Coming up')
            ->assertDontSee('In progress');
    }

    public function test_the_business_hours_editor_is_translated(): void
    {
        $owner = $this->spanishOwner();
        $location = $this->spanishLocation();

        $this->actingAs($owner)
            ->get(route('settings.hours.edit', $location))
            ->assertOk()
            ->assertSee('Cuándo empieza este horario')
            ->assertSee('Festivos, cierres y horarios especiales')
            ->assertSee('Añadir una fecha')
            // Exception types, in the dropdown itself.
            ->assertSee('Cierre por mantenimiento')
            ->assertSee('Horario especial')
            ->assertDontSee('When these hours start')
            ->assertDontSee('Public holiday');
    }

    public function test_business_hours_messages_are_translated(): void
    {
        $owner = $this->spanishOwner();
        $location = $this->spanishLocation();

        $this->actingAs($owner)
            ->patch(route('settings.hours.update', $location), [
                'hours' => [1 => ['is_open' => '1', ['opens_at' => '09:00', 'closes_at' => '17:00']]],
            ])
            ->assertSessionHas('toast.message', 'Horario comercial guardado correctamente.');

        $this->actingAs($owner)
            ->post(route('settings.hours.closures.store', $location), [
                'type' => 'public_holiday', 'starts_on' => '2026-12-25', 'is_closed_all_day' => '1',
            ])
            ->assertSessionHasErrors([
                'name' => 'Ponle un nombre, para que el equipo sepa de qué se trata.',
            ]);
    }

    /**
     * The "also applied" sentence is two whole sentences joined, not one
     * assembled from parts, so each language keeps its own word order.
     */
    public function test_the_applied_to_others_message_is_translated(): void
    {
        $owner = $this->spanishOwner();
        $location = $this->spanishLocation();

        $other = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Eastside', 'address_line1' => '2 East Street',
            'city' => 'Austin', 'state' => 'Texas', 'postal_code' => '78702',
            'country' => 'US', 'timezone' => 'America/Chicago',
            'phone' => '+1 512 555 0177', 'email' => 'east@nadia.test',
        ]);

        $this->actingAs($owner)
            ->patch(route('settings.hours.update', $location), [
                'hours' => [1 => ['is_open' => '1', ['opens_at' => '09:00', 'closes_at' => '17:00']]],
                'apply_to' => [$other->id],
            ])
            ->assertSessionHas(
                'toast.message',
                'Horario comercial guardado correctamente. También aplicado a 1 ubicación más.'
            );
    }

    // --------------------------------------------------- the branding module

    public function test_the_branding_screen_is_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->get(route('settings.branding.show'))
            ->assertOk()
            ->assertSee('Identidad de marca')
            ->assertSee('Logotipo del negocio')
            ->assertSee('Colores de marca')
            ->assertSee('Favicon / icono de la app')
            ->assertDontSee('Business logo')
            ->assertDontSee('Brand colours');
    }

    /**
     * The previews are representations of what branding produces, so a reader
     * looking at what their colours will do should be able to read them.
     */
    public function test_the_branding_previews_are_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->get(route('settings.branding.show'))
            ->assertOk()
            ->assertSee('Vista previa')
            ->assertSee('Tu página de reservas')
            ->assertSee('Tu cita está confirmada')
            ->assertSee('Recibos y facturas')
            ->assertSee('Dónde se usa')
            ->assertDontSee('Your appointment is confirmed')
            ->assertDontSee('Where this is used');
    }

    public function test_branding_messages_are_translated(): void
    {
        $owner = $this->spanishOwner();

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), [
                'brand_primary' => '#0f766e',
                'brand_secondary' => '#0d9488',
                'brand_accent' => '#b45309',
            ])
            ->assertSessionHas('toast.message', 'Identidad de marca actualizada correctamente.');

        $this->actingAs($owner)
            ->patch(route('settings.branding.update'), [
                'brand_primary' => '',
                'brand_secondary' => '#0d9488',
                'brand_accent' => '#b45309',
            ])
            ->assertSessionHasErrors(['brand_primary' => 'Elige un color principal.']);
    }

    /**
     * The contrast grade is a key, not a phrase.
     *
     * BrandPalette decides which of the four grades a colour earns; the
     * screen decides how to say it. "AA" stays as it is — that is the name of
     * a conformance level, not an English word.
     */
    public function test_the_contrast_grade_is_translated(): void
    {
        $this->assertSame('fails', BrandPalette::grade('#f5e663', '#ffffff')['key']);

        $this->assertSame('No cumple', __('branding.grades.fails', [], 'es'));
        $this->assertSame('Fails', __('branding.grades.fails', [], 'en'));
        // Unchanged in both: a conformance level, not a word.
        $this->assertSame('AA', __('branding.grades.aa', [], 'es'));
    }

    // ------------------------------------------------------ the staff module

    public function test_the_staff_directory_is_translated(): void
    {
        $owner = $this->spanishOwner();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Amara', 'last_name' => 'Osei',
            'email' => 'amara@nadia.test', 'role' => 'manager',
            'job_title' => 'Salon Manager',
        ]);

        /* The page's own chrome — heading, CTA and the column titles the
           grid is configured with. The rows themselves come from the grid's
           endpoint, so they are asked separately. */
        $this->actingAs($owner)
            ->get(route('settings.staff.index'))
            ->assertOk()
            ->assertSee('Miembros del personal')
            ->assertSee('Añadir miembro del personal')
            /* A column title from the grid's own config. ASCII on purpose:
               the config travels as JSON, where json_encode writes an accent
               as \u00f3 and asserting on the accented form would be asserting
               on the encoder. */
            ->assertSee('Estado', false)
            ->assertDontSee('Staff members')
            ->assertDontSee('Status', false);

        $row = $this->actingAs($owner)
            ->get(route('settings.staff.data'))
            ->assertOk()
            ->json('data.0');

        /* The status is worded by the server, in the reader's language. */
        $this->assertSame('Activo', $row['status']);
        // What the business typed is untouched.
        $this->assertSame('Salon Manager', $row['role']);
    }

    public function test_the_add_staff_form_is_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->get(route('settings.staff.create'))
            ->assertOk()
            ->assertSee('Añadir miembro del personal')
            ->assertSee('Información básica')
            ->assertSee('Rol y contratación')
            ->assertSee('Estado de la cuenta')
            // Specialities are rendered as checkboxes, so they are plain text.
            ->assertSee('Colorista')
            ->assertSee('Maquillador')
            ->assertDontSee('Basic information')
            ->assertDontSee('Account status');
    }

    /**
     * Dropdown values, not just their labels.
     */
    public function test_staff_dropdown_values_are_translated(): void
    {
        $content = $this->actingAs($this->spanishOwner())
            ->get(route('settings.staff.create'))
            ->assertOk()
            ->getContent();

        $props = collect(self::islandProps($content));

        $employment = $props->firstWhere('name', 'employment_type');
        $this->assertContains('Empleado a tiempo completo', array_values($employment['options']));

        $provider = $props->firstWhere('name', 'provider_type');
        $this->assertContains('Recepción', array_values($provider['options']));
    }

    /**
     * System roles are StyleDesk's own vocabulary, so they translate.
     *
     * A role a business created and named itself keeps its own words, exactly
     * as a service name or a client note does.
     */
    public function test_system_roles_translate_but_custom_roles_keep_their_name(): void
    {
        $owner = $this->spanishOwner();

        $custom = Role::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'front-of-house',
            'name' => 'Front of House',
            'description' => 'A role this business invented.',
        ]);

        $this->actingAs($owner)
            ->get(route('settings.roles.index'))
            ->assertOk()
            ->assertSee('Recepción')
            ->assertSee('Prestador de servicios')
            // Named by the business, so left exactly as written.
            ->assertSee('Front of House');

        $this->assertSame('Front of House', $custom->label());
    }

    public function test_staff_messages_are_translated(): void
    {
        $owner = $this->spanishOwner();

        $this->actingAs($owner)
            ->post(route('settings.staff.store'), ['account_status' => 'active'])
            ->assertSessionHasErrors([
                'first_name' => 'El nombre es obligatorio.',
                'email' => 'El correo principal es obligatorio.',
            ]);
    }

    // ----------------------------------------------------- the clients module

    public function test_the_client_settings_screen_is_translated(): void
    {
        $this->actingAs($this->spanishOwner())
            ->get(route('settings.clients.show'))
            ->assertOk()
            ->assertSee('Fichas de cliente')
            ->assertSee('Campos de la ficha')
            ->assertSee('Preferencias y etiquetas')
            ->assertSee('Privacidad y consentimiento')
            ->assertDontSee('Client records')
            ->assertDontSee('Profile fields');
    }

    /**
     * The catalogue values, not just the labels around them.
     *
     * Field names, statuses, panels and duplicate rules all live in
     * config/clients.php, so a page whose headings translated and whose
     * fifteen field names did not would be half in each language.
     */
    public function test_client_option_values_are_translated(): void
    {
        $content = $this->actingAs($this->spanishOwner())
            ->get(route('settings.clients.show'))
            ->assertOk()
            // Field names, rendered as plain text in the field table.
            ->assertSee('Número de móvil')
            ->assertSee('Fecha de nacimiento')
            ->assertSee('Foto de perfil')
            // Checkbox sets.
            ->assertSee('Próximas citas')
            ->assertSee('El mismo número de móvil')
            ->assertSee('Punto de venta')
            ->assertDontSee('Mobile number')
            ->assertDontSee('Date of birth')
            ->getContent();

        // Dropdown options reach the page inside a data-props attribute,
        // where json_encode escapes every non-ASCII character, so they are
        // decoded rather than matched as text.
        $props = collect(self::islandProps($content));

        $marketing = $props->firstWhere('name', 'default_marketing');
        $this->assertContains('Preguntar al cliente', array_values($marketing['options']));

        $format = $props->firstWhere('name', 'name_format');
        $this->assertContains('Nombre e inicial del apellido', array_values($format['options']));
    }

    public function test_client_settings_messages_are_translated(): void
    {
        $owner = $this->spanishOwner();

        $this->actingAs($owner)
            ->patch(route('settings.clients.update'), [
                'default_status' => 'active',
                'default_communication' => 'email',
                'default_marketing' => 'ask',
                'name_format' => 'first_last',
            ])
            ->assertSessionHas('toast.message', 'Configuración de clientes actualizada correctamente.');

        $this->actingAs($owner)
            ->post(route('settings.clients.preferences.store'), ['label' => 'Cuero cabelludo sensible'])
            ->assertSessionHas('toast.message', 'Preferencia añadida.');
    }

    // ---------------------------------------------------------- the module

    public function test_the_app_settings_card_links_to_the_module(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.languages.show'), false);
    }

    /**
     * The primary language is shown in the additional list as included.
     *
     * It is an enabled language by definition — App\Support\Locale always
     * counts it — so an unticked row said the opposite: that the business's
     * own main language was one it had not switched on. Locked, because
     * unticking it would be asking to disable the language everything else
     * falls back to.
     */
    public function test_the_primary_language_is_ticked_and_locked_in_the_additional_list(): void
    {
        $this->enableSpanish();

        $html = $this->actingAs($this->owner())
            ->get(route('settings.languages.edit'))
            ->assertOk()
            ->getContent();

        preg_match('/<input type="checkbox" name="secondary\[\]" value="en"[^>]*>/', $html, $english);

        $this->assertNotEmpty($english, 'The primary language has no row in the additional list.');
        $this->assertStringContainsString('checked', $english[0]);
        $this->assertStringContainsString('disabled', $english[0]);
    }

    /**
     * A disabled box posts nothing, so the primary is never written into the
     * pivot as well — it lives in tenants.default_language and one copy of a
     * fact is enough.
     */
    public function test_the_primary_is_not_stored_as_an_additional_language(): void
    {
        $this->enableSpanish();

        $owner = $this->owner();

        $this->actingAs($owner)->patch(route('settings.languages.update'), [
            'primary' => 'en',
            'secondary' => ['en', 'es'],
        ])->assertSessionHasNoErrors();

        $codes = $owner->tenant->fresh()->languages()->pluck('language_code');

        $this->assertFalse($codes->contains('en'));
        $this->assertTrue($codes->contains('es'));
    }
}
