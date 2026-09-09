<?php

declare(strict_types=1);

use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The business type catalogue, as part of migrating rather than seeding.
 *
 * These are reference data the application cannot run without: onboarding
 * requires at least one business type, so an environment whose table is empty
 * has an onboarding step nobody can complete — the chips simply do not render
 * and the form refuses to submit.
 *
 * That is what happened in production. A deploy runs `migrate --force`; it
 * does not run `db:seed`, so the table was created and left empty while every
 * local database had been seeded by hand.
 *
 * The seeder is called rather than copied: it is idempotent (updateOrCreate
 * on the slug) and it is the one list. Two copies of the catalogue would
 * eventually disagree about which types exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new BusinessTypeSeeder)->run();
    }

    public function down(): void
    {
        /* Not reversed: by the time this could roll back, businesses have
           chosen these types and the rows are referenced by their records. */
    }
};
