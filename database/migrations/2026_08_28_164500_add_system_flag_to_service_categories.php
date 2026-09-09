<?php

declare(strict_types=1);

use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Which service categories StyleDesk supplied, and which a business invented.
 *
 * The distinction decides one thing: a default may be switched off and
 * reordered but never deleted, because there is no way to get it back. The
 * same rule resource categories already carry.
 *
 * `key` is what makes seeding idempotent after a rename. Matching on the name
 * meant a business that renamed "Hair" to "Hair services" got a second Hair
 * the next time defaults were seeded — the comment on seedDefaultsFor claimed
 * otherwise, and was wrong about exactly that case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('name');
            $table->string('key', 60)->nullable()->after('is_system');
            $table->unique(['tenant_id', 'key']);
        });

        /* Adopt the rows already seeded from the default list, so the seeding
           below adds nothing and nobody's edits are touched. */
        $defaults = collect(config('service_categories'))
            ->mapWithKeys(fn (string $name) => [$name => Str::slug($name)]);

        ServiceCategory::withoutGlobalScopes()
            ->whereNull('key')
            ->get()
            ->each(function (ServiceCategory $category) use ($defaults) {
                $key = $defaults->get($category->name);

                if ($key === null) {
                    return;
                }

                $category->forceFill(['key' => $key, 'is_system' => true])->save();
            });

        Tenant::query()->each(fn (Tenant $tenant) => ServiceCategory::seedDefaultsFor($tenant));
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
            $table->dropColumn(['is_system', 'key']);
        });
    }
};
