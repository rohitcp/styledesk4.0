<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Support\Str;

/**
 * Where a file goes.
 *
 * One description of the folder structure, used identically against a local
 * directory and against a DigitalOcean Space. That sameness is the point: a
 * path written on a laptop is the path in production, so a bug that only
 * appears on one of them cannot be a bug about where files are kept.
 *
 *     tenants/{tenant}/clients/{client}/files/{uuid}.pdf
 */
class StoragePathBuilder
{
    /**
     * Which folder each category of file belongs in.
     *
     * A category the app does not know goes under documents/ rather than
     * being refused: a new module should be able to store something before
     * anybody has thought about where it belongs, and a file in the wrong
     * folder is recoverable where a failed upload is not.
     *
     * @var array<string, string>
     */
    private const FOLDERS = [
        'logo' => 'branding',
        'favicon' => 'branding',
        'alternate-logo' => 'branding',
        'branding' => 'branding',
        'profile-image' => 'profiles',
        'client-file' => 'clients',
        'client-photo' => 'clients',
        'editor-attachment' => 'clients',
        'service-image' => 'services',
        'resource-image' => 'resources',
        'booking-attachment' => 'bookings',
        'temp' => 'temp',
    ];

    /**
     * The folder a file of this kind belongs in, without its filename.
     *
     * Entity-scoped categories nest under the record they belong to, so
     * everything about one client is in one place — which is what makes
     * "delete this client's files" a directory rather than a query.
     */
    public function directory(string $tenantId, string $category, ?string $entityType = null, int|string|null $entityId = null): string
    {
        $root = 'tenants/'.$this->segment($tenantId);
        $folder = self::FOLDERS[$category] ?? 'documents';

        return match ($category) {
            'profile-image' => $root.'/profiles/'.$this->segment((string) $entityId),
            /* Treatment photographs sit with the client's documents rather
               than in a folder of their own: "everything about this client"
               is a directory, which is what makes deleting one a directory
               too. */
            'client-file', 'client-photo' => $root.'/clients/'.$this->segment((string) $entityId).'/files',
            'editor-attachment' => $root.'/clients/'.$this->segment((string) $entityId).'/editor-attachments',
            default => $entityType && $entityId !== null
                ? $root.'/'.$folder.'/'.$this->segment((string) $entityId)
                : $root.'/'.$folder,
        };
    }

    public function path(string $tenantId, string $category, string $filename, ?string $entityType = null, int|string|null $entityId = null): string
    {
        return $this->directory($tenantId, $category, $entityType, $entityId).'/'.$filename;
    }

    /**
     * A filename nothing can collide with and nothing can read.
     *
     * A UUID rather than the name the person gave it: two clients uploading
     * "consent-form.pdf" must not overwrite each other, and a stored name
     * taken from user input is a stored name someone can put a path in. The
     * original is kept on the row, which is what a download is named after.
     */
    public function filename(string $extension): string
    {
        $extension = Str::lower(preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '');

        return (string) Str::uuid().($extension === '' ? '' : '.'.$extension);
    }

    /** No path in a path segment: an id is an id, not a route out of the folder. */
    private function segment(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: 'unknown';
    }
}
