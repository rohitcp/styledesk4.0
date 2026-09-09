<?php

declare(strict_types=1);

use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the default categories to tenants that predate the seeding.
 *
 * Seeding hangs off TenantCreated, which does nothing for a business that
 * already existed when that listener was added — their category dropdown is
 * simply empty, and an empty dropdown makes the "add" action look broken too.
 *
 * Also repairs tenants with no owner_user_id. Without it roleInTenant() returns
 * null, the policy refuses everything, and the owner cannot manage their own
 * categories — locked out of a feature by a column that was added after they
 * signed up.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Tenant::all() as $tenant) {
            if ($tenant->owner_user_id === null) {
                $owner = User::where('tenant_id', $tenant->getTenantKey())->orderBy('id')->first();

                if ($owner !== null) {
                    $tenant->forceFill(['owner_user_id' => $owner->id])->save();
                }
            }

            $has = ServiceCategory::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getTenantKey())
                ->exists();

            if (! $has) {
                ServiceCategory::seedDefaultsFor($tenant);
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: the defaults are indistinguishable from
        // categories a tenant has since edited, so removing them would delete
        // the tenant's own work.
    }
};
