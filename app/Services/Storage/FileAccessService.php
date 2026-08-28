<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\StoredFile;
use App\Models\User;

/**
 * Whether this request may have this file.
 *
 * Its own class because the question is asked from several places — reading,
 * downloading, replacing, deleting — and an answer that lived in only one of
 * them would be a rule the others could forget.
 *
 * The first rule is the only one that matters most of the time: a file
 * belongs to exactly one business, and nobody outside that business may have
 * it whatever else is true about them.
 */
class FileAccessService
{
    public function belongsToTenant(StoredFile $file, ?string $tenantId): bool
    {
        return $tenantId !== null && (string) $file->tenant_id === (string) $tenantId;
    }

    /**
     * Whether this person may read the file.
     *
     * Tenant first, then the permission the file's own module uses — a
     * client's document is client data, so `clients.view` is what governs it
     * rather than a permission invented for storage.
     */
    public function canRead(StoredFile $file, ?User $user): bool
    {
        if ($user === null || ! $this->belongsToTenant($file, $user->tenant?->getTenantKey())) {
            return false;
        }

        return match ($file->entity_type) {
            'client' => $user->hasPermission('clients.view', 'own'),
            'staff' => $user->hasPermission('staff.view', 'own'),
            default => true,
        };
    }

    /**
     * Whether this person may replace or remove it.
     *
     * Deliberately stricter than reading: seeing a client's consent form and
     * being able to delete it are different things, and the module's own edit
     * permission is what separates them.
     */
    public function canWrite(StoredFile $file, ?User $user): bool
    {
        if ($user === null || ! $this->belongsToTenant($file, $user->tenant?->getTenantKey())) {
            return false;
        }

        return match ($file->entity_type) {
            'client' => $user->hasPermission('clients.edit', 'own'),
            'staff' => $user->hasPermission('staff.edit', 'own'),
            default => $user->canManageSettings(),
        };
    }
}
