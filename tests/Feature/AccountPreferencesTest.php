<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\AccountPreferences;
use App\Support\TimeFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * My Preferences: one person differing from the business, and following it again.
 *
 * The distinction the tests are built around is null versus a value. Null is
 * "whatever the business says" and has to keep meaning that after the
 * business changes its mind — which is why a reset writes nulls rather than
 * copying today's business setting into the row.
 */
class AccountPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $tenantAttributes = []): User
    {
        $tenant = Tenant::create([
            'name' => 'Acme', 'slug' => 'acme-prefs',
            'default_language' => 'en', 'time_format' => '12',
            'date_format' => 'm/d/Y', 'timezone' => 'America/New_York',
            'first_day_of_week' => 0,
        ] + $tenantAttributes);

        $user = User::factory()->create(['tenant_id' => $tenant->getTenantKey()]);
        $tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    public function test_the_screen_opens(): void
    {
        $this->actingAs($this->member())
            ->get(route('account.preferences'))
            ->assertOk();
    }

    /**
     * Three cards, each shipping open with a summary to close onto.
     *
     * The summaries read the same values the controls are built from, so the
     * assertion below is on the shipping state rather than on any one label:
     * what has to hold is that a reader with no JavaScript still gets the
     * fields, and that the script has both halves to swap between.
     */
    public function test_every_card_ships_open_with_a_summary_to_close_onto(): void
    {
        $response = $this->actingAs($this->member())
            ->get(route('account.preferences'))
            ->assertOk();

        $body = $response->getContent();

        $this->assertSame(3, substr_count($body, 'data-editable-card data-editing='));
        $this->assertSame(3, substr_count($body, 'data-editable-view hidden'));
        $this->assertSame(3, substr_count($body, 'data-editable-edit hidden'));
        $this->assertStringNotContainsString('data-editing="true"', $body);

        /* Without the partial the markup is a form that never closes, which
           is exactly how this shipped once. */
        $this->assertStringContainsString("querySelectorAll('[data-editable-card]')", $body);
    }

    /** The summary says what the control says, blank included. */
    public function test_the_summary_names_the_value_the_control_holds(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch(route('account.preferences.update'), [
            'time_format' => '24',
            'show_weekends' => '1',
            'show_cancelled' => '0',
        ]);

        $formats = (array) config('business_profile.time_formats');

        $this->actingAs($user->fresh())
            ->get(route('account.preferences'))
            ->assertOk()
            ->assertSee($formats['24'])
            ->assertSee(__('account.preferences.use_business'))
            ->assertSee(__('common.on'))
            ->assertSee(__('common.off'));
    }

    public function test_preferences_are_saved(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->patch(route('account.preferences.update'), [
                'date_format' => 'Y-m-d',
                'time_format' => '24',
                'timezone' => 'Europe/Madrid',
                'first_day_of_week' => 1,
                'calendar_view' => 'day',
                'show_weekends' => '0',
                'show_cancelled' => '1',
                'show_resource_color' => '1',
                'show_staff_color' => '0',
            ])
            ->assertRedirect(route('account.preferences'))
            ->assertSessionHas('toast');

        $user->refresh();

        $this->assertSame('Y-m-d', AccountPreferences::dateFormat($user));
        $this->assertSame('24', AccountPreferences::timeFormat($user));
        $this->assertSame('Europe/Madrid', AccountPreferences::timezone($user));
        $this->assertSame(1, AccountPreferences::firstDayOfWeek($user));
        $this->assertSame('day', AccountPreferences::calendarView($user));
        $this->assertFalse(AccountPreferences::calendarToggle($user, 'show_weekends'));
        $this->assertTrue(AccountPreferences::calendarToggle($user, 'show_cancelled'));
    }

    /**
     * Nothing chosen means the business's answer, not the product's.
     */
    public function test_an_unset_preference_follows_the_business(): void
    {
        $user = $this->member();

        $this->assertSame('m/d/Y', AccountPreferences::dateFormat($user));
        $this->assertSame('12', AccountPreferences::timeFormat($user));
        $this->assertSame('America/New_York', AccountPreferences::timezone($user));
    }

    public function test_a_personal_clock_wins_over_the_business_clock_everywhere(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch(route('account.preferences.update'), ['time_format' => '24']);

        /* TimeFormat is what every screen in the app formats a time through,
           so this is the assertion that the preference actually reaches the
           product rather than only the row. */
        $this->actingAs($user->fresh());
        $this->assertFalse(TimeFormat::use12Hours());
        $this->assertSame('14:30', TimeFormat::time('14:30'));
    }

    public function test_the_language_is_stored_on_the_user_and_changes_the_interface(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme', 'slug' => 'acme-lang', 'default_language' => 'en',
        ]);
        $tenant->languages()->create(['language_code' => 'es', 'position' => 1]);

        $user = User::factory()->create(['tenant_id' => $tenant->getTenantKey()]);

        $this->actingAs($user)->patch(route('account.preferences.update'), ['locale' => 'es']);

        $this->assertSame('es', $user->fresh()->locale);

        $this->actingAs($user->fresh())
            ->get(route('account.preferences'))
            ->assertSee('Mis preferencias');
    }

    /**
     * A language the business has switched off is not on offer.
     */
    public function test_a_language_the_business_has_not_enabled_is_refused(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->from(route('account.preferences'))
            ->patch(route('account.preferences.update'), ['locale' => 'es'])
            ->assertSessionHasErrors('locale');

        $this->assertNull($user->fresh()->locale);
    }

    public function test_a_format_that_is_not_on_the_list_is_refused(): void
    {
        $this->actingAs($this->member())
            ->from(route('account.preferences'))
            ->patch(route('account.preferences.update'), ['date_format' => 'the-day-before-yesterday'])
            ->assertSessionHasErrors('date_format');
    }

    /**
     * Reset clears the choices rather than freezing today's business setting.
     */
    public function test_reset_puts_the_person_back_on_the_business_defaults(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch(route('account.preferences.update'), [
            'time_format' => '24',
            'date_format' => 'Y-m-d',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->actingAs($user->fresh())
            ->post(route('account.preferences.reset'))
            ->assertRedirect(route('account.preferences'));

        $user->refresh();

        $this->assertNull($user->preferences->time_format);
        $this->assertSame('12', AccountPreferences::timeFormat($user));

        /* The proof that it is a clearing and not a copy: the business moves,
           and the person who reset moves with it. */
        $user->tenant->forceFill(['time_format' => '24'])->save();
        $this->assertSame('24', AccountPreferences::timeFormat($user->fresh()));
    }

    public function test_preferences_belong_to_the_person_who_saved_them(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-two', 'time_format' => '12']);
        $mine = User::factory()->create(['tenant_id' => $tenant->getTenantKey()]);
        $theirs = User::factory()->create(['tenant_id' => $tenant->getTenantKey()]);

        $this->actingAs($mine)->patch(route('account.preferences.update'), ['time_format' => '24']);

        $this->assertSame('24', AccountPreferences::timeFormat($mine->fresh()));
        $this->assertSame('12', AccountPreferences::timeFormat($theirs->fresh()));
    }

    /**
     * A timestamp follows the reader's clock, not a hard-coded one.
     *
     * Twenty screens each formatting "j M Y · H:i" is how a business that
     * chose a 12-hour clock still read "last updated 2 Sep 2026 · 14:09".
     */
    public function test_a_timestamp_is_twelve_hour_by_default_and_follows_the_setting(): void
    {
        $member = $this->member();
        $moment = Carbon::parse('2026-09-02 14:09');

        $this->actingAs($member);

        $this->assertSame('2 Sep 2026 · 2:09 PM', TimeFormat::dateTime($moment));

        $member->preferences()->updateOrCreate([], ['time_format' => '24']);
        $member->unsetRelation('preferences');

        $this->assertSame('2 Sep 2026 · 14:09', TimeFormat::dateTime($moment));
    }

    /** Null in, null out: a moment that never happened is not a formatting error. */
    public function test_a_missing_timestamp_formats_to_nothing(): void
    {
        $this->assertNull(TimeFormat::dateTime(null));
    }
}
