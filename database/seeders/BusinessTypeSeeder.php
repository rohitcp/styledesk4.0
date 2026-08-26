<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The starting catalogue from spec section 7.
 *
 * updateOrCreate on slug so re-seeding is safe and never duplicates a type
 * an administrator has since renamed.
 */
class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Hair Salon', 'Barber Shop', 'Nail Salon', 'Spa', 'Massage',
            'Med Spa', 'Esthetics', 'Eyebrows & Lashes', 'Makeup Studio',
            'Tattoo Studio', 'Wellness', 'Fitness', 'Other',
        ];

        foreach ($types as $i => $name) {
            BusinessType::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i, 'is_active' => true]
            );
        }
    }
}
