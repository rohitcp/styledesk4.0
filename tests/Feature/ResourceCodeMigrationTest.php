<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration that added the unique code index, run against data that
 * predates it.
 *
 * Written because the first version of it failed on live data and left the
 * database half-changed: the tenant columns were added, the index was refused
 * because two chairs already shared a code, and — the migration never having
 * finished — nothing was recorded, so the next attempt failed again on the
 * columns it had just added.
 *
 * Codes were free text until that release, so duplicates are ordinary history
 * rather than corruption, and the migration has to make room for the
 * constraint without throwing anybody's labels away.
 */
class ResourceCodeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_08_31_144631_add_resource_code_format_to_tenants.php';

    private function migration(): object
    {
        return require base_path(self::MIGRATION);
    }

    /** Back to the state a business was in before this release. */
    private function undoMigration(): void
    {
        Schema::table('resources', function ($table) {
            $table->dropUnique('resources_tenant_code_unique');
        });
    }

    private function chair(Tenant $tenant, string $name, ?string $code, int $daysAgo = 0): int
    {
        return DB::table('resources')->insertGetId([
            'tenant_id' => $tenant->getTenantKey(),
            'name' => $name,
            'capacity' => 1,
            'code' => $code,
            'created_at' => now()->subDays($daysAgo),
            'updated_at' => now(),
        ]);
    }

    public function test_duplicate_codes_are_moved_aside_and_the_oldest_keeps_its_own(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-migrate']);

        $this->undoMigration();

        $first = $this->chair($tenant, 'Chair one', 'RES-0001', daysAgo: 3);
        $second = $this->chair($tenant, 'Chair two', 'RES-0001', daysAgo: 2);
        $third = $this->chair($tenant, 'Chair three', 'RES-0001', daysAgo: 1);

        $this->migration()->up();

        /* The oldest keeps what it has: its label has been read the longest. */
        $this->assertSame('RES-0001', DB::table('resources')->where('id', $first)->value('code'));

        /* The others are suffixed rather than blanked, so whoever tidies up
           can still see what the code was and which rows were involved. */
        $this->assertSame('RES-0001-2', DB::table('resources')->where('id', $second)->value('code'));
        $this->assertSame('RES-0001-3', DB::table('resources')->where('id', $third)->value('code'));

        $this->assertTrue(Schema::hasIndex('resources', 'resources_tenant_code_unique'));
    }

    /**
     * A suffix that is itself somebody's label is stepped over.
     */
    public function test_an_existing_suffix_is_not_overwritten(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-suffix']);

        $this->undoMigration();

        $this->chair($tenant, 'Chair one', 'RES-0001', daysAgo: 2);
        $second = $this->chair($tenant, 'Chair two', 'RES-0001', daysAgo: 1);
        $this->chair($tenant, 'Already taken', 'RES-0001-2');

        $this->migration()->up();

        $this->assertSame('RES-0001-3', DB::table('resources')->where('id', $second)->value('code'));
    }

    /**
     * An empty code is not a code — and left as '' it would break the index
     * on its own, because MySQL allows many NULLs and exactly one ''.
     */
    public function test_empty_codes_become_null(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-blank']);

        $this->undoMigration();

        $blank = $this->chair($tenant, 'Unlabelled', '');
        $alsoBlank = $this->chair($tenant, 'Also unlabelled', '');

        $this->migration()->up();

        $this->assertNull(DB::table('resources')->where('id', $blank)->value('code'));
        $this->assertNull(DB::table('resources')->where('id', $alsoBlank)->value('code'));
    }

    /**
     * Two businesses using the same code are not duplicates, and neither is
     * touched.
     */
    public function test_the_same_code_in_two_businesses_is_left_alone(): void
    {
        $one = Tenant::create(['name' => 'One', 'slug' => 'one-migrate']);
        $two = Tenant::create(['name' => 'Two', 'slug' => 'two-migrate']);

        $this->undoMigration();

        $a = $this->chair($one, 'Chair', 'RES-0001');
        $b = $this->chair($two, 'Chair', 'RES-0001');

        $this->migration()->up();

        $this->assertSame('RES-0001', DB::table('resources')->where('id', $a)->value('code'));
        $this->assertSame('RES-0001', DB::table('resources')->where('id', $b)->value('code'));
    }

    /**
     * Safe to run again after a failure part-way through.
     *
     * This is the property production actually needed: the columns were
     * already added when the index was refused, so the retry has to add
     * neither and finish the half that is missing.
     */
    public function test_running_it_twice_changes_nothing_and_does_not_fail(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme-twice']);

        $this->undoMigration();

        $chair = $this->chair($tenant, 'Chair', 'RES-0001');

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('RES-0001', DB::table('resources')->where('id', $chair)->value('code'));
        $this->assertTrue(Schema::hasIndex('resources', 'resources_tenant_code_unique'));
        $this->assertTrue(Schema::hasColumn('tenants', 'resource_code_prefix'));
    }
}
