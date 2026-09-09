<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\InputCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The project-wide capitalisation rule: uppercase the first character of the
 * value and change nothing else.
 */
class InputCapitalizationTest extends TestCase
{
    use RefreshDatabase;

    public static function caseProvider(): array
    {
        return [
            'lowercase' => ['main location', 'Main location'],
            'already capitalised' => ['Main Location', 'Main Location'],
            'lowercase name' => ['john smith', 'John smith'],
            'mixed already' => ['John smith', 'John smith'],
            'full uppercase name' => ['JOHN SMITH', 'JOHN SMITH'],
            'full uppercase phrase' => ['MAIN LOCATION', 'MAIN LOCATION'],
            'inner capital preserved' => ['styleDesk NYC', 'StyleDesk NYC'],
            'multibyte' => ['ñoño', 'Ñoño'],
            'leading whitespace' => ['  spaced out', 'Spaced out'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('caseProvider')]
    public function test_the_rule_matches_the_specification(string $input, string $expected): void
    {
        $this->assertSame($expected, InputCase::sentence($input));
    }

    public function test_the_rule_never_lowercases_anything(): void
    {
        // The guarantee that separates this from Str::title() and ucwords(),
        // both of which would rewrite JOHN SMITH and StyleDesk.
        foreach (['JOHN SMITH', 'StyleDesk NYC', 'MacDonald', 'iSalon'] as $value) {
            $result = InputCase::sentence($value);

            $this->assertSame(mb_substr($value, 1), mb_substr($result, 1), "Characters after the first changed for {$value}.");
        }
    }

    public function test_registration_capitalises_names_but_not_the_email(): void
    {
        $this->post('http://styledesk.test/signup', [
            'first_name' => 'john',
            'last_name' => 'SMITH',
            'email' => 'John.Smith@StyleDesk.test',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
            'terms' => '1',
        ]);

        $user = User::first();

        $this->assertSame('John', $user->first_name);
        $this->assertSame('SMITH', $user->last_name, 'Full uppercase must survive.');

        // Case in an address is not the user's to choose; lower-casing it is
        // what keeps the same inbox from registering twice.
        $this->assertSame('john.smith@styledesk.test', $user->email);
    }

    public function test_the_business_name_uses_the_project_rule(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->verifiedUser();

        $this->actingAs($user)->post('http://styledesk.test/onboarding/business', [
            'name' => 'bella beauty studio',
            'business_phone' => '555 0100',
            'country_codes' => ['US'],
            'currency_code' => 'USD',
            'default_language' => 'en',
            'business_type_ids' => [$type->id],
        ]);

        // First character only. An earlier implementation capitalised every
        // word, which this supersedes.
        $this->assertSame('Bella beauty studio', $user->fresh()->tenant->name);
    }

    public function test_the_business_slug_is_untouched_by_capitalisation(): void
    {
        $type = BusinessType::firstOrCreate(['slug' => 'spa'], ['name' => 'Spa', 'slug' => 'spa']);
        $user = $this->verifiedUser();

        $this->actingAs($user)->post('http://styledesk.test/onboarding/business', [
            'name' => 'bella beauty studio',
            'business_phone' => '555 0100',
            'country_codes' => ['US'],
            'currency_code' => 'USD',
            'default_language' => 'en',
            'business_type_ids' => [$type->id],
        ]);

        // A subdomain must stay lowercase whatever the display name does —
        // and carries no spaces, so the three words run together.
        $this->assertSame('bellabeautystudio', $user->fresh()->tenant->slug);
    }

    public function test_location_and_service_names_are_capitalised(): void
    {
        $user = $this->verifiedUser();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->tenant_id = $tenant->getTenantKey();
        $user->save();
        TenantOnboarding::create(['tenant_id' => $tenant->getTenantKey(), 'current_step' => 'location']);

        $this->actingAs($user->fresh())->post('http://styledesk.test/onboarding/location', [
            'name' => 'main location',
            'address_line1' => '128 grand street',
            'city' => 'brooklyn',
            'postal_code' => '11211',
            'timezone' => 'America/New_York',
        ]);

        $location = $tenant->locations()->first();

        $this->assertSame('Main location', $location->name);
        $this->assertSame('Brooklyn', $location->city);

        $this->actingAs($user->fresh())->post('http://styledesk.test/onboarding/services', [
            'services' => [
                ['name' => "women's cut", 'duration_minutes' => 45, 'price' => '38.50'],
            ],
        ]);

        $service = Service::first();

        $this->assertSame("Women's cut", $service->name);
    }

    private function verifiedUser(): User
    {
        $user = User::create([
            'first_name' => 'Rohit',
            'last_name' => 'Philip',
            'email' => 'r@styledesk.test',
            'password' => 'Str0ng!Pass',
        ]);

        $user->markEmailAsVerified();

        return $user->fresh();
    }
}
