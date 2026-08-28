<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one way StyleDesk stores a file.
 *
 * Stated as a contract so a feature depends on the idea of tenant storage
 * rather than on the class that happens to implement it today — and so a
 * test can substitute one without a filesystem behind it.
 */
interface TenantStorageContract
{
    public function upload(UploadedFile $file, string $category, ?string $entityType = null, int|string|null $entityId = null, string $visibility = 'private'): StoredFile;

    public function get(int $fileId): ?StoredFile;

    public function url(int|StoredFile $file): ?string;

    public function temporaryUrl(int|StoredFile $file, int $expiresInMinutes = 15): ?string;

    public function show(int|StoredFile $file): StreamedResponse;

    public function download(int|StoredFile $file): StreamedResponse;

    public function replace(int|StoredFile $file, UploadedFile $newFile): StoredFile;

    public function delete(int|StoredFile $file): bool;

    public function exists(int|StoredFile $file): bool;

    /** @return array<string, mixed>|null */
    public function metadata(int|StoredFile $file): ?array;
}
