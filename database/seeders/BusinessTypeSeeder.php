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
        // Name => icon key, matching the set drawn in the types-icon partial.
        $types = [
            'Hair Salon' => 'scissors',
            'Barber Shop' => 'comb',
            'Nail Salon' => 'polish',
            'Spa' => 'leaf',
            'Massage' => 'hand',
            'Med Spa' => 'medical',
            'Esthetics' => 'sparkle',
            'Eyebrows & Lashes' => 'eye',
            'Makeup Studio' => 'lipstick',
            'Tattoo Studio' => 'pen',
            'Wellness' => 'heart',
            'Fitness' => 'dumbbell',
            'Other' => 'dots',
        ];

        $i = 0;

        foreach ($types as $name => $icon) {
            BusinessType::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'sort_order' => $i++, 'is_active' => true]
            );
        }
    }
}
