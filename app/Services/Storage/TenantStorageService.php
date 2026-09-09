<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\TenantStorageContract;
use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one place StyleDesk stores a file.
 *
 * Every module calls this rather than reaching for a disk of its own. That is
 * not tidiness for its own sake — it is what makes four separate things true
 * at once, and true everywhere:
 *
 *  - the same folder structure exists locally and in DigitalOcean, so a path
 *    is not something that changes when the app is deployed;
 *  - switching provider is an environment variable, because nothing above
 *    this class ever names a disk;
 *  - every file has a row saying which business it belongs to, so "may this
 *    person have this file" has an answer rather than a guess;
 *  - a filename cannot be chosen by whoever uploaded it.
 *
 * A module that wrote `Storage::disk('spaces')->put(...)` would have none of
 * those, and would be the reason the next environment behaves differently.
 */
class TenantStorageService implements TenantStorageContract
{
    public function __construct(
        private readonly StoragePathBuilder $paths = new StoragePathBuilder,
        private readonly FileValidator $validator = new FileValidator,
        private readonly FileAccessService $access = new FileAccessService,
    ) {}

    // ------------------------------------------------------------ uploading

    /**
     * Store a file and return the record that stands for it.
     *
     * Everything specific happens here once: the tenant is read from the
     * session rather than passed in — a caller that could name the tenant
     * could name someone else's — the name is replaced with a UUID, and the
     * disk the file actually landed on is written down rather than assumed
     * later from config.
     */
    public function upload(UploadedFile $file, string $category, ?string $entityType = null, int|string|null $entityId = null, string $visibility = 'private'): StoredFile
    {
        $this->validator->validate($file, $category);

        $tenantId = $this->resolveTenant();
        $disk = $this->resolveDisk();

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $this->paths->filename($extension);
        $directory = $this->paths->directory($tenantId, $category, $entityType, $entityId);

        $file->storeAs($directory, $filename, [
            'disk' => $disk,
            'visibility' => $visibility === 'public' ? 'public' : 'private',
        ]);

        return StoredFile::create([
            'tenant_id' => $tenantId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'category' => $category,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $filename,
            'storage_disk' => $disk,
            'storage_path' => $directory.'/'.$filename,
            'mime_type' => $file->getClientMimeType(),
            'extension' => $extension,
            'file_size' => (int) $file->getSize(),
            'visibility' => $visibility,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /** A person's photo. Public, because it is drawn beside their name. */
    public function uploadProfileImage(UploadedFile $file, int|string $userId): StoredFile
    {
        return $this->upload($file, 'profile-image', 'user', $userId, 'public');
    }

    /**
     * A logo, favicon or alternate mark.
     *
     * No entity: branding belongs to the business itself, and the business
     * is already the first thing on every row.
     */
    public function uploadBranding(UploadedFile $file, string $type = 'logo'): StoredFile
    {
        return $this->upload($file, $type, null, null, 'public');
    }

    /** A document on a client's record. Private, always. */
    public function uploadClientFile(UploadedFile $file, int|string $clientId): StoredFile
    {
        return $this->upload($file, 'client-file', 'client', $clientId, 'private');
    }

    /**
     * An image dropped into a note or another editor.
     *
     * Public, because it is rendered inline in a page a colleague is already
     * reading — a private URL would expire mid-note. What protects it is the
     * unguessable name, and the fact that the note itself is permissioned.
     */
    public function uploadEditorAttachment(UploadedFile $file, int|string $clientId): StoredFile
    {
        return $this->upload($file, 'editor-attachment', 'client', $clientId, 'public');
    }

    // ------------------------------------------------------------- reading

    /**
     * One file, if it belongs to the business asking.
     *
     * Null rather than an exception for a file from another tenant, and null
     * for one that does not exist: the two must be indistinguishable, or the
     * difference tells a caller which ids are real.
     */
    public function get(int $fileId): ?StoredFile
    {
        $file = StoredFile::withoutGlobalScopes()->find($fileId);

        if ($file === null || ! $this->access->belongsToTenant($file, $this->resolveTenant())) {
            return null;
        }

        return $file;
    }

    /**
     * A URL for a public file.
     *
     * Built from the disk the file was stored on, not today's default: a
     * business that has since moved to Spaces still has yesterday's files
     * where they were.
     */
    public function url(int|StoredFile $file): ?string
    {
        $file = $this->resolveFile($file);

        if ($file === null) {
            return null;
        }

        /**
         * An object store serves its own files; a local disk does not — the
         * tenants directory is deliberately outside the web root, because a
         * folder holding one business's client documents is not something to
         * leave servable by guessing a path. Locally, every file is reached
         * through the app, which checks who is asking.
         */
        if (config('filesystems.disks.'.$file->storage_disk.'.driver') !== 's3') {
            return route('files.show', $file);
        }

        return Storage::disk($file->storage_disk)->url($file->storage_path);
    }

    /**
     * The file itself, shown rather than downloaded.
     *
     * What an <img> in a note points at. Same checks as a download — the
     * only difference is the disposition, so a picture opens in the page
     * instead of landing in the downloads folder.
     */
    public function show(int|StoredFile $file): StreamedResponse
    {
        $file = $this->resolveFile($file);

        abort_if($file === null, 404);
        abort_unless($this->access->canRead($file, auth()->user()), 403);

        return Storage::disk($file->storage_disk)->response($file->storage_path, $file->original_filename, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
        ]);
    }

    /**
     * A link that stops working.
     *
     * What a private file gets. Local disks cannot sign, so they fall back to
     * the download route — which checks the same permissions on every request
     * rather than trusting a URL.
     */
    public function temporaryUrl(int|StoredFile $file, int $expiresInMinutes = 15): ?string
    {
        $file = $this->resolveFile($file);

        if ($file === null) {
            return null;
        }

        /**
         * Only an object store can hand out a link that expires on its own.
         * A local disk can be asked for one and will answer, but the URL it
         * produces is not served by anything — so local files go through the
         * download route, which checks permissions on every request rather
         * than trusting a URL.
         */
        if (config('filesystems.disks.'.$file->storage_disk.'.driver') !== 's3') {
            return route('files.download', $file);
        }

        try {
            return Storage::disk($file->storage_disk)->temporaryUrl(
                $file->storage_path,
                now()->addMinutes($expiresInMinutes),
            );
        } catch (\RuntimeException) {
            return route('files.download', $file);
        }
    }

    public function download(int|StoredFile $file): StreamedResponse
    {
        $file = $this->resolveFile($file);

        abort_if($file === null, 404);
        abort_unless($this->access->canRead($file, auth()->user()), 403);

        // Downloaded under the name the person gave it, not the UUID.
        return Storage::disk($file->storage_disk)->download($file->storage_path, $file->original_filename);
    }

    // ------------------------------------------------------------ changing

    /**
     * Swap the contents, keep the record.
     *
     * The id is what other rows point at, so replacing a logo must not mean
     * every reference to it needs updating. The old object is removed once
     * the new one is safely stored.
     */
    public function replace(int|StoredFile $file, UploadedFile $newFile): StoredFile
    {
        $file = $this->resolveFile($file);

        abort_if($file === null, 404);
        abort_unless($this->access->canWrite($file, auth()->user()), 403);

        $this->validator->validate($newFile, $file->category);

        $previous = $file->storage_path;
        $extension = strtolower($newFile->getClientOriginalExtension());
        $filename = $this->paths->filename($extension);
        $directory = dirname($file->storage_path);

        $newFile->storeAs($directory, $filename, [
            'disk' => $file->storage_disk,
            'visibility' => $file->isPublic() ? 'public' : 'private',
        ]);

        $file->forceFill([
            'original_filename' => $newFile->getClientOriginalName(),
            'stored_filename' => $filename,
            'storage_path' => $directory.'/'.$filename,
            'mime_type' => $newFile->getClientMimeType(),
            'extension' => $extension,
            'file_size' => (int) $newFile->getSize(),
            'uploaded_by' => auth()->id(),
        ])->save();

        Storage::disk($file->storage_disk)->delete($previous);

        return $file->fresh();
    }

    /**
     * Remove the object, and the row with it.
     *
     * The row is soft-deleted: a file that was on a client's record is part
     * of what happened to that record, and a hard delete leaves nothing to
     * say it ever existed.
     */
    public function delete(int|StoredFile $file): bool
    {
        $file = $this->resolveFile($file);

        if ($file === null) {
            return false;
        }

        abort_unless($this->access->canWrite($file, auth()->user()), 403);

        Storage::disk($file->storage_disk)->delete($file->storage_path);

        return (bool) $file->delete();
    }

    public function exists(int|StoredFile $file): bool
    {
        $file = $this->resolveFile($file);

        return $file !== null && Storage::disk($file->storage_disk)->exists($file->storage_path);
    }

    /**
     * Everything known about a file, without handing over the model.
     *
     * @return array<string, mixed>|null
     */
    public function metadata(int|StoredFile $file): ?array
    {
        $file = $this->resolveFile($file);

        if ($file === null) {
            return null;
        }

        return [
            'id' => $file->id,
            'original_filename' => $file->original_filename,
            'stored_filename' => $file->stored_filename,
            'mime_type' => $file->mime_type,
            'extension' => $file->extension,
            'file_size' => $file->file_size,
            'readable_size' => $file->readableSize(),
            'storage_disk' => $file->storage_disk,
            'storage_path' => $file->storage_path,
            'tenant_id' => $file->tenant_id,
            'entity_type' => $file->entity_type,
            'entity_id' => $file->entity_id,
            'category' => $file->category,
            'visibility' => $file->visibility,
            'uploaded_by' => $file->uploaded_by,
            'created_at' => $file->created_at,
        ];
    }

    // ------------------------------------------------------------ internals

    /**
     * Which disk is in use.
     *
     * Config, never a caller: this is the single decision that makes local
     * and production differ, and a feature that could pass a disk in would be
     * a feature that behaves differently in one of them.
     */
    public function resolveDisk(): string
    {
        return (string) config('filesystems.tenant_disk', 'tenants');
    }

    /**
     * Whose files these are.
     *
     * Read from the session rather than accepted as an argument — a caller
     * that could name the tenant could name somebody else's.
     */
    public function resolveTenant(): string
    {
        $tenantId = auth()->user()?->tenant?->getTenantKey() ?? tenant()?->getTenantKey();

        abort_if($tenantId === null, 403);

        return (string) $tenantId;
    }

    /** An id or a model, always checked against the current business. */
    private function resolveFile(int|StoredFile $file): ?StoredFile
    {
        if ($file instanceof StoredFile) {
            return $this->access->belongsToTenant($file, $this->resolveTenant()) ? $file : null;
        }

        return $this->get($file);
    }
}
