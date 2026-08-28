<?php

declare(strict_types=1);

use App\Models\ResourceCategory;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Bring existing businesses onto the keyed catalogue.
 *
 * The categories seeded before this release were matched by name and carry no
 * key, no group and no system flag. Adopting them by name first is what stops
 * the seeding below adding a second "Styling chair" beside the one already
 * there — and what keeps a business's own edits, since the row is updated
 * rather than replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* The names the defaults were seeded under, mapped back to the key
           they should have had. Read from config so this cannot drift from
           the catalogue it is adopting. */
        $byName = collect(config('resources.seed_categories'))
            ->mapWithKeys(fn (array $category) => [
                __('resources.categories.'.$category['key']) => $category,
            ]);

        ResourceCategory::withoutGlobalScopes()
            ->whereNull('key')
            ->get()
            ->each(function (ResourceCategory $category) use ($byName) {
                $default = $byName->get($category->name);

                if ($default === null) {
                    return;
                }

                $category->forceFill([
                    'key' => $default['key'],
                    'group' => $default['group'],
                    'is_system' => true,
                ])->save();
            });

        /* Then the ones that did not exist before: the catalogue went from
           eleven entries to thirty. */
        Tenant::query()->each(fn (Tenant $tenant) => ResourceCategory::seedDefaultsFor($tenant));
    }

    public function down(): void
    {
        // Adoption is not a change worth undoing: reversing it would strip
        // keys from rows that are now correct.
    }
};
