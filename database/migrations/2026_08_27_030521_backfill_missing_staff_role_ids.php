<?php

declare(strict_types=1);

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair staff rows carrying a role name but no role_id.
 *
 * They were created between roles becoming data and the saving hook learning
 * to resolve the tenant itself — which affected exactly the rows created
 * without an explicit tenant_id, and onboarding seeds the business owner that
 * way. The visible symptom was an owner whose role read as a dash; the real
 * one was an owner resolving to no permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('staff')
            ->whereNull('role_id')
            ->whereNotNull('role')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $roleId = Role::withoutGlobalScopes()
                        ->where('tenant_id', $row->tenant_id)
                        ->where('key', $row->role)
                        ->value('id');

                    if ($roleId !== null) {
                        DB::table('staff')->where('id', $row->id)->update(['role_id' => $roleId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Nothing to undo: this only fills in what should have been set.
    }
};
