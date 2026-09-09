<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reference data the onboarding wizard needs in order to render.
        $this->call(BusinessTypeSeeder::class);

        // Somebody who can sign in to the platform console. Prints a one-time
        // password unless the environment sets one; never resets an existing
        // administrator.
        $this->call(BackofficeSuperOwnerSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
