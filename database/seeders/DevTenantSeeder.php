<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A working business to develop against.
 *
 * Written after a `migrate:fresh` wiped the development database and there was
 * no way to rebuild it except by clicking through onboarding again. One
 * command is cheaper than that, and a seeder is also a statement of what a
 * complete tenant looks like.
 *
 *   php artisan db:seed --class=DevTenantSeeder
 *
 * Password for every account: Str0ng!Pass
 */
class DevTenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BusinessTypeSeeder::class);

        $owner = User::updateOrCreate(
            ['email' => 'owner@styledesk.test'],
            [
                'first_name' => 'Nadia',
                'last_name' => 'Khan',
                'password' => Hash::make('Str0ng!Pass'),
                'terms_accepted_at' => now(),
            ]
        );
        $owner->markEmailAsVerified();

        // TenantCreated provisions the system roles and default categories.
        $tenant = Tenant::firstWhere('slug', 'nadia') ?? Tenant::create([
            'name' => 'Nadia Hair Studio',
            'slug' => 'nadia',
            'business_email' => 'hello@nadia.test',
            'country_code' => 'US',
            'currency_code' => 'USD',
            'default_language' => 'en',
            'timezone' => 'America/Chicago',
            'owner_user_id' => $owner->id,
        ]);

        $tenant->forceFill(['owner_user_id' => $owner->id])->save();
        $owner->forceFill(['tenant_id' => $tenant->getTenantKey()])->save();

        TenantOnboarding::updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            ['current_step' => 'complete', 'completed_at' => now()]
        );

        $location = Location::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey(), 'name' => 'Riverside'],
            [
                'address_line1' => '1 River Street', 'city' => 'Austin', 'state' => 'Texas',
                'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
                'is_primary' => true,
            ]
        );

        $services = collect([
            ["Women's Cut & Finish", 60],
            ['Full Head Colour', 120],
            ['Balayage', 150],
        ])->map(fn (array $s) => Service::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey(), 'name' => $s[0]],
            ['duration_minutes' => $s[1]]
        ));

        // The owner as staff, plus one of each remaining role so the directory
        // has something to filter.
        $team = [
            ['Nadia', 'Khan', 'owner', 'Owner', $owner->id, 'manager-provider', 'full-time'],
            ['Amara', 'Osei', 'manager', 'Salon Manager', null, 'manager-provider', 'full-time'],
            ['Priya', 'Nair', 'service-provider', 'Senior Colourist', null, 'service-provider', 'commission'],
            ['Sam', 'Reid', 'front-desk', 'Front Desk', null, 'front-desk', 'part-time'],
        ];

        foreach ($team as [$first, $last, $role, $title, $userId, $providerType, $employment]) {
            $staff = Staff::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'email' => mb_strtolower($first).'@nadia.test'],
                [
                    'user_id' => $userId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'role' => $role,
                    'job_title' => $title,
                    'location_id' => $location->id,
                    'provider_type' => $providerType,
                    'employment_type' => $employment,
                    'provides_services' => $providerType !== 'front-desk',
                    'is_active' => true,
                ]
            );

            if ($providerType !== 'front-desk') {
                $staff->services()->sync($services->pluck('id')->all());
            }
        }

        $this->command?->info('Seeded Nadia Hair Studio — owner@styledesk.test / Str0ng!Pass');
    }
}
