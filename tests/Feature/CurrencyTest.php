<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Currencies;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Acceptance criteria from the Currency spec.
 */
class CurrencyTest extends TestCase
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

    // -------------------------------------------------------- the formatter

    /**
     * The reason the component exists.
     *
     * Any screen writing its own number_format($amount, 2) would be right for
     * the dollar and wrong for four of these — and nobody would notice until a
     * business in Mumbai or Munich read a receipt.
     */
    public static function formats(): array
    {
        return [
            'dollar' => ['USD', 1234.5, '$1,234.50'],
            // Symbol after, dot thousands, comma decimal.
            'euro' => ['EUR', 1234.5, "1.234,50\u{00A0}€"],
            // Grouped in twos after the first three digits.
            'rupee' => ['INR', 123456.78, '₹1,23,456.78'],
            // No minor unit at all.
            'yen' => ['JPY', 1234.5, '¥1,235'],
            // Apostrophe grouping, and a word-like symbol takes a space.
            'franc' => ['CHF', 1234.5, "CHF\u{00A0}1'234.50"],
            'krona' => ['SEK', 1234.5, "1 234,50\u{00A0}kr"],
            // Disambiguated from the US dollar.
            'canadian' => ['CAD', 1234.5, 'C$1,234.50'],
        ];
    }

    #[DataProvider('formats')]
    public function test_each_currency_is_written_the_way_it_is_written(string $code, float $amount, string $expected): void
    {
        $this->assertSame($expected, Money::format($amount, $code));
    }

    /**
     * "$-75.00" is what you get from formatting a negative and then adding a
     * symbol. "-$75.00" is what a refund looks like.
     */
    public function test_the_minus_sign_goes_outside_the_symbol(): void
    {
        $this->assertSame('-$75.00', Money::format(-75, 'USD'));
        $this->assertSame("-1.234,50\u{00A0}€", Money::format(-1234.5, 'EUR'));
    }

    public function test_zero_is_formatted_rather_than_blank(): void
    {
        $this->assertSame('$0.00', Money::zero('USD'));
        $this->assertSame('¥0', Money::zero('JPY'));
    }

    /**
     * §Currency Display: the code identifies the money, not the symbol.
     */
    public function test_an_amount_can_be_stated_unambiguously(): void
    {
        $this->assertSame('$100.00 USD', Money::formatWithCode(100, 'USD'));
        $this->assertSame('C$100.00 CAD', Money::formatWithCode(100, 'CAD'));
    }

    /**
     * A price shown as "1.234,56 €" has to survive a round trip.
     *
     * Reading it with (float) gives 1.234 — the separators mean the opposite
     * of what PHP assumes.
     */
    public function test_a_formatted_price_parses_back_to_its_number(): void
    {
        $this->assertSame(1234.56, Money::parse('1.234,56 €', 'EUR'));
        $this->assertSame(1234.56, Money::parse('$1,234.56', 'USD'));
        $this->assertSame(1234.56, Money::parse('1234.56', 'USD'));
        $this->assertNull(Money::parse('', 'USD'));
        $this->assertNull(Money::parse('not a price', 'USD'));
    }

    public function test_an_unknown_currency_falls_back_rather_than_failing(): void
    {
        // Never a crash and never a half-formatted number: Money reads seven
        // keys off the definition and a missing separator would render a
        // price with nothing between the digits.
        $this->assertSame('$1,234.50', Money::format(1234.5, 'XYZ'));
    }

    // --------------------------------------------------------- the registry

    public function test_only_active_currencies_are_offered(): void
    {
        $this->assertTrue(Currencies::supports('USD'));
        $this->assertTrue(Currencies::supports('INR'));
        $this->assertFalse(Currencies::supports('XYZ'));
    }

    public function test_a_currency_is_labelled_code_first(): void
    {
        $this->assertSame('USD — US Dollar ($)', Currencies::label('USD'));
        $this->assertSame('EUR — Euro (€)', Currencies::label('EUR'));
    }

    /**
     * The primary is enabled by being the primary.
     *
     * A list that omitted it would leave a pricing field unable to offer the
     * one currency the business definitely uses.
     */
    public function test_the_primary_is_always_enabled(): void
    {
        $this->assertSame(['USD'], Currencies::enabledFor($this->tenant)->all());
        $this->assertFalse(Currencies::hasMultiple($this->tenant));
    }

    // -------------------------------------------------------- authorisation

    public function test_owner_and_admin_can_open_the_module(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.currency.show'))
            ->assertOk()
            ->assertSee('USD — US Dollar ($)');

        $this->actingAs($this->member('administrator'))
            ->get(route('settings.currency.show'))
            ->assertOk();
    }

    public function test_other_roles_cannot_reach_or_change_it(): void
    {
        $this->actingAs($this->member('front-desk'))
            ->get(route('settings.currency.show'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->member('service-provider'))
            ->patch(route('settings.currency.update'), ['primary' => 'EUR'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('USD', $this->tenant->fresh()->currency_code);
    }

    // --------------------------------------------------------------- saving

    public function test_the_primary_and_additional_currencies_are_saved(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.currency.update'), ['primary' => 'GBP', 'secondary' => ['EUR', 'USD']])
            ->assertRedirect(route('settings.currency.show'))
            ->assertSessionHas('toast.message', __('currency.saved'));

        $tenant = $this->tenant->fresh();

        $this->assertSame('GBP', $tenant->currency_code);
        $this->assertSame(['GBP', 'EUR', 'USD'], Currencies::enabledFor($tenant)->all());
        $this->assertTrue(Currencies::hasMultiple($tenant));
    }

    /**
     * Ticking your own primary is a harmless mistake, so it is dropped rather
     * than refused: the currency is enabled either way.
     */
    public function test_the_primary_is_dropped_from_the_additional_list(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.currency.update'), ['primary' => 'EUR', 'secondary' => ['EUR', 'GBP']])
            ->assertSessionHasNoErrors();

        $tenant = $this->tenant->fresh();

        $this->assertSame(['GBP'], $tenant->currencies()->pluck('currency_code')->all());
        $this->assertSame(['EUR', 'GBP'], Currencies::enabledFor($tenant)->all());
    }

    public function test_an_unsupported_currency_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->patch(route('settings.currency.update'), ['primary' => 'XYZ'])
            ->assertSessionHasErrors('primary');

        $this->assertSame('USD', $this->tenant->fresh()->currency_code);
    }

    // ------------------------------------------------------ pricing fields

    /**
     * §Pricing Behavior: one input, or a row per currency.
     */
    public function test_the_price_field_asks_once_when_there_is_one_currency(): void
    {
        $html = $this->renderPriceField($this->owner());

        $this->assertSame(1, substr_count($html, 'name="price['));
        $this->assertStringContainsString('name="price[USD]"', $html);
    }

    public function test_the_price_field_asks_per_currency_when_there_are_several(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->patch(route('settings.currency.update'), [
            'primary' => 'USD', 'secondary' => ['EUR', 'INR'],
        ]);

        $html = $this->renderPriceField($owner->fresh());

        $this->assertSame(3, substr_count($html, 'name="price['));
        $this->assertStringContainsString('name="price[EUR]"', $html);
        $this->assertStringContainsString('name="price[INR]"', $html);

        // Said on the field itself, not only in settings: someone typing three
        // numbers is exactly who might assume the others fill themselves in.
        $this->assertStringContainsString('not converted', $html);
    }

    /**
     * The component as a screen would use it.
     *
     * Rendered from a string rather than through a fixture view, so nothing
     * exists in resources/views that only the tests need. The error bag is
     * shared by hand because the web middleware normally does it, and the
     * component's own @error directives read it.
     */
    private function renderPriceField(User $user): string
    {
        $this->actingAs($user);

        view()->share('errors', new ViewErrorBag);

        return Blade::render('<x-price-input name="price" label="Price" />');
    }
}
