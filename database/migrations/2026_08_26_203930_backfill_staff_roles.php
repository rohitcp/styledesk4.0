<?php

declare(strict_types=1);

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give existing tenants their roles, and point existing staff at them.
 *
 * Businesses created before roles became data have staff carrying a role
 * string and no role_id. Without this they would resolve to no role at all,
 * which reads as "no permissions" — the owner of a working salon locked out of
 * their own product by a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $provisioner = app(ProvisionSystemRoles::class);

        Tenant::withoutGlobalScopes()->cursor()->each(function (Tenant $tenant) use ($provisioner) {
            $provisioner->forTenant($tenant);

            $roles = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getTenantKey())
                ->pluck('id', 'key');

            foreach ($roles as $key => $roleId) {
                DB::table('staff')
                    ->where('tenant_id', $tenant->getTenantKey())
                    ->where('role', $key)
                    ->whereNull('role_id')
                    ->update(['role_id' => $roleId]);
            }

            /**
             * Anything left over carried a role string the catalogue does not
             * recognise. Rather than leave it null — which reads as no access
             * — it becomes the tenant's default role, which is the narrowest
             * one on offer.
             */
            $default = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getTenantKey())
                ->where('is_default', true)
                ->value('id');

            if ($default !== null) {
                DB::table('staff')
                    ->where('tenant_id', $tenant->getTenantKey())
                    ->whereNull('role_id')
                    ->update(['role_id' => $default]);
            }
        });
    }

    public function down(): void
    {
        DB::table('staff')->update(['role_id' => null]);
    }
};
