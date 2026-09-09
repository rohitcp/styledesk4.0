<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BackofficeAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The administrator a fresh install can sign in as.
 *
 * The password is never written in source. It is read from the environment
 * where one is set, and otherwise generated here and printed once — so the
 * only copy that has ever existed outside the hash is the one on the console
 * of whoever ran the seeder.
 *
 * Idempotent, and it will not reset a password that already exists: running
 * the seeders again on a live install must not hand the account back to
 * whoever runs them.
 */
class BackofficeSuperOwnerSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower((string) config('backoffice.super_owner.email'));

        if (BackofficeAdmin::query()->where('email', $email)->exists()) {
            $this->command?->info('Backoffice Super Owner already exists; leaving it alone.');

            return;
        }

        $fromEnv = (string) env('BACKOFFICE_SUPER_OWNER_PASSWORD', '');
        $password = $fromEnv !== '' ? $fromEnv : Str::password(20);

        BackofficeAdmin::query()->create([
            'name' => (string) config('backoffice.super_owner.name'),
            'email' => $email,
            'password' => $password,
            'role' => 'super-owner',
            'status' => BackofficeAdmin::STATUS_ACTIVE,
        ]);

        if ($fromEnv !== '') {
            $this->command?->info('Backoffice Super Owner created for '.$email.' with the password from the environment.');

            return;
        }

        /* Once, here, and never again. Nothing stores it and nothing can
           print it a second time. */
        $this->command?->warn('Backoffice Super Owner created for '.$email);
        $this->command?->warn('One-time password: '.$password);
        $this->command?->warn('Sign in and change it. It is not recorded anywhere else.');
    }
}
