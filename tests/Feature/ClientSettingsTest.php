<?php

namespace Tests\Feature;

use App\Models\ClientSettings;
use App\Models\ClientTag;
use App\Models\Location;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance criteria from the App Settings → Clients spec.
 */
class ClientSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio', 'slug' => 'nadia', 'business_email' => 'hello@nadia.test',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
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
            'tenant_id' => $this->tenant->getTenantKey(), 'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => $role,
        ]);

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'default_status' => 'active',
            'default_communication' => 'email',
            'default_marketing' => 'ask',
            'name_format' => 'first_last',
        ], $overrides);
    }

    // -------------------------------------------------------- §18 access

    public function test_owner_and_admin_can_configure_clients(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.clients.show'))
            ->assertOk()
            ->assertSee('Client records');

        $this->actingAs($this->member('administrator'))
            ->get(route('settings.clients.show'))
            ->assertOk();
    }

    public function test_other_roles_cannot_reach_or_change_it(): void
    {
        $this->actingAs($this->member('front-desk'))
            ->get(route('settings.clients.show'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->member('service-provider'))
            ->patch(route('settings.clients.update'), $this->payload(['name_format' => 'last_first']))
            ->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------ the settings row

    /**
     * A business that never opens this screen still needs an answer to every
     * question on it, and the answers are the column defaults.
     */
    public function test_settings_are_created_on_first_read_with_usable_defaults(): void
    {
        $settings = ClientSettings::forTenant($this->tenant);

        $this->assertSame('active', $settings->default_status);
        $this->assertSame('ask', $settings->default_marketing);
        $this->assertSame('first_last', $settings->name_format);

        // Empty sets would mean "search by nothing" and a booking screen that
        // shows the client's name and no more.
        $this->assertNotEmpty($settings->search_fields);
        $this->assertNotEmpty($settings->booking_panels);
        $this->assertNotEmpty($settings->history_panels);
        $this->assertContains('email', $settings->duplicate_rules);
    }

    public function test_reading_settings_twice_does_not_create_two_rows(): void
    {
        ClientSettings::forTenant($this->tenant);
        ClientSettings::forTenant($this->tenant->fresh());

        $this->assertDatabaseCount('client_settings', 1);
    }

    public function test_the_settings_are_saved(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), $this->payload([
                'name_format' => 'first_initial',
                'default_marketing' => 'out',
                'duplicate_rules' => ['mobile', 'name_mobile'],
                'search_fields' => ['first_name', 'mobile'],
                'notes_enabled' => '1',
            ]))
            ->assertRedirect(route('settings.clients.show'))
            ->assertSessionHas('toast.message', __('clients.saved'));

        $settings = ClientSettings::forTenant($this->tenant->fresh());

        $this->assertSame('first_initial', $settings->name_format);
        $this->assertSame('out', $settings->default_marketing);
        $this->assertSame(['mobile', 'name_mobile'], $settings->duplicate_rules);
        $this->assertSame(['first_name', 'mobile'], $settings->search_fields);
        $this->assertTrue($settings->notes_enabled);
        // An unchecked box posts nothing, so a switch left out is off.
        $this->assertFalse($settings->notes_multiple);
    }

    // -------------------------------------------------------- §2 the fields

    /**
     * §2: First Name always stays enabled and required.
     *
     * The screen does not offer the toggle, and this is what makes it true of
     * a form posted by hand as well.
     */
    public function test_first_name_cannot_be_switched_off_or_made_optional(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), $this->payload([
                'fields' => ['first_name' => ['enabled' => '0', 'required' => '0', 'order' => '3']],
            ]));

        $field = ClientSettings::forTenant($this->tenant->fresh())->field('first_name');

        $this->assertTrue($field['enabled']);
        $this->assertTrue($field['required']);
        $this->assertTrue($field['locked']);
    }

    /**
     * A client the business is never asked for cannot be missing.
     */
    public function test_a_disabled_field_cannot_be_required(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), $this->payload([
                'fields' => ['gender' => ['enabled' => '0', 'required' => '1', 'order' => '5']],
            ]));

        $field = ClientSettings::forTenant($this->tenant->fresh())->field('gender');

        $this->assertFalse($field['enabled']);
        $this->assertFalse($field['required']);
    }

    public function test_field_order_is_kept(): void
    {
        // The screen posts every field, so the test does too — a partial post
        // is covered separately below.
        $order = collect(array_keys(config('clients.fields')))
            ->sortBy(fn (string $key) => $key === 'notes' ? -1 : 0)
            ->values()
            ->mapWithKeys(fn (string $key, int $index) => [$key => ['enabled' => '1', 'order' => (string) $index]])
            ->all();

        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), $this->payload(['fields' => $order]));

        $ordered = ClientSettings::forTenant($this->tenant->fresh())->orderedFields();

        $this->assertSame('notes', $ordered[0]['key']);
    }

    /**
     * A field the form did not mention keeps the position it had.
     *
     * Defaulting to zero collapsed the whole order the moment anything posted
     * a partial set — which is what a field added to the catalogue after a
     * business last saved would do.
     */
    public function test_a_field_left_out_of_a_save_keeps_its_position(): void
    {
        $owner = $this->owner();

        $all = collect(array_keys(config('clients.fields')))
            ->sortBy(fn (string $key) => $key === 'notes' ? -1 : 0)
            ->values()
            ->mapWithKeys(fn (string $key, int $index) => [$key => ['enabled' => '1', 'order' => (string) $index]])
            ->all();

        $this->actingAs($owner)->patch(route('settings.clients.update'), $this->payload(['fields' => $all]));

        // A second save that mentions one field only.
        $this->actingAs($owner)->patch(route('settings.clients.update'), $this->payload([
            'fields' => ['gender' => ['enabled' => '1', 'order' => '99']],
        ]));

        $settings = ClientSettings::forTenant($this->tenant->fresh());

        $this->assertSame(0, $settings->field('notes')['order']);
        $this->assertSame(99, $settings->field('gender')['order']);
    }

    // ------------------------------------------------------- §1 the defaults

    /**
     * A bare exists rule would accept another salon's id and quietly file
     * every new client against a branch this business has never heard of.
     */
    public function test_a_default_from_another_business_is_refused(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'business_email' => 'hi@other.test']);

        $theirs = Location::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(), 'name' => 'Their Branch',
            'address_line1' => '1 Street', 'city' => 'Austin', 'state' => 'Texas',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
        ]);

        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), $this->payload(['default_location_id' => $theirs->id]))
            ->assertSessionHasErrors('default_location_id');
    }

    public function test_an_unsupported_value_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), $this->payload(['name_format' => 'nope']))
            ->assertSessionHasErrors('name_format');
    }

    /**
     * Archived is not a status a client can be created as.
     */
    public function test_a_new_client_cannot_default_to_archived(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), $this->payload(['default_status' => 'archived']))
            ->assertSessionHasErrors('default_status');
    }

    // ---------------------------------------------------- §4, §5 the lists

    public function test_a_preference_can_be_added(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.clients.preferences.store'), ['label' => 'sensitive scalp'])
            ->assertSessionHasNoErrors();

        // The project capitalisation rule applies to what a business types.
        $this->assertSame('Sensitive scalp', $this->tenant->clientPreferences()->first()->label);
    }

    public function test_a_preference_cannot_be_added_twice(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('settings.clients.preferences.store'), ['label' => 'Sensitive scalp']);

        $this->actingAs($owner)
            ->post(route('settings.clients.preferences.store'), ['label' => 'Sensitive scalp'])
            ->assertSessionHasErrors('label');

        $this->assertSame(1, $this->tenant->clientPreferences()->count());
    }

    /**
     * Deactivating keeps it on the clients who already have it.
     *
     * The record is never deleted from this screen, so a preference assigned
     * to two hundred people stops being offered without erasing what those
     * records say about them.
     */
    public function test_a_preference_is_deactivated_rather_than_deleted(): void
    {
        $preference = $this->tenant->clientPreferences()->create(['label' => 'Quiet appointment']);

        $this->actingAs($this->owner())
            ->patch(route('settings.clients.preferences.toggle', $preference))
            ->assertSessionHas('toast.type', 'success');

        $this->assertModelExists($preference);
        $this->assertFalse($preference->fresh()->is_active);
    }

    /**
     * A card saves its own fields and leaves the rest alone.
     *
     * The page is a set of independent sections now, and an unchecked box
     * looks exactly like an absent one in a request — so a save that wrote
     * every column would switch off everything the card never showed.
     */
    public function test_a_section_save_touches_only_its_own_settings(): void
    {
        $settings = ClientSettings::forTenant($this->tenant);
        $settings->forceFill([
            'notes_multiple' => true,
            'comm_sms' => true,
            'name_format' => 'last_first',
        ])->save();

        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), [
                'section' => 'notes',
                'notes_enabled' => 1,
                // notes_multiple deliberately absent: unticked.
            ])
            ->assertRedirect(route('settings.clients.show'));

        $settings = $settings->fresh();

        $this->assertFalse($settings->notes_multiple);
        // Neither of these was on the card, so neither moved.
        $this->assertTrue($settings->comm_sms);
        $this->assertSame('last_first', $settings->name_format);
    }

    /** A section nothing on the page posts is refused rather than guessed at. */
    public function test_an_unknown_section_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.clients.update'), ['section' => 'nonsense'])
            ->assertStatus(422);
    }

    public function test_preferences_can_be_reordered(): void
    {
        $first = $this->tenant->clientPreferences()->create(['label' => 'Morning', 'position' => 0]);
        $second = $this->tenant->clientPreferences()->create(['label' => 'Evening', 'position' => 1]);

        $this->actingAs($this->owner())
            ->patch(route('settings.clients.preferences.reorder'), ['order' => [$second->id, $first->id]]);

        $this->assertSame(
            ['Evening', 'Morning'],
            $this->tenant->clientPreferences()->inOrder()->pluck('label')->all()
        );
    }

    public function test_a_tag_carries_a_colour_from_the_palette(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.clients.tags.store'), ['label' => 'Photoshoot', 'color' => 'violet'])
            ->assertSessionHasNoErrors();

        $tag = $this->tenant->clientTags()->where('label', 'Photoshoot')->firstOrFail();

        $this->assertSame('violet', $tag->color);
        // The palette entry, not a stored hex, so a shade can be corrected
        // once rather than in every row that chose it.
        $this->assertSame(config('clients.tag_colors.violet'), $tag->hex());
    }

    public function test_a_tag_colour_outside_the_palette_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->post(route('settings.clients.tags.store'), ['label' => 'Photoshoot', 'color' => '#ff0000'])
            ->assertSessionHasErrors('color');
    }

    /**
     * These are lists a salon curates about its own clients.
     */
    public function test_another_businesss_list_is_not_reachable(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'business_email' => 'hi@other.test']);

        $theirs = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(), 'label' => 'Theirs',
        ]);

        $this->actingAs($this->owner())
            ->patch(route('settings.clients.tags.toggle', $theirs))
            ->assertNotFound();

        $this->assertTrue($theirs->fresh()->is_active);
    }

    public function test_the_app_settings_card_links_to_the_module(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.clients.show'), false);
    }
}
