<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * What may be stored, and how big.
 *
 * Per category rather than one rule for everything: a logo and a signed
 * consent form are different risks. An allowlist of extensions *and* MIME
 * types, because either alone is bypassable — a .php named .jpg passes an
 * extension check, and a browser will happily report whatever MIME type the
 * uploader claims.
 */
class FileValidator
{
    /**
     * @var array<string, array{extensions: list<string>, mimes: list<string>, max: int}>
     */
    private const RULES = [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'max' => 5120,
        ],
        'document' => [
            'extensions' => ['pdf', 'doc', 'docx', 'txt', 'csv', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'],
            'mimes' => [
                'application/pdf', 'application/msword', 'text/plain', 'text/csv',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg', 'image/png', 'image/webp',
            ],
            'max' => 20480,
        ],
    ];

    /** Which rule set a category is held to. */
    private const CATEGORIES = [
        'logo' => 'image',
        'favicon' => 'image',
        'alternate-logo' => 'image',
        'branding' => 'image',
        'profile-image' => 'image',
        'editor-attachment' => 'image',
        'service-image' => 'image',
        'resource-image' => 'image',
    ];

    /**
     * @throws ValidationException when the file is not one this category takes
     */
    public function validate(UploadedFile $file, string $category): void
    {
        $rules = self::RULES[self::CATEGORIES[$category] ?? 'document'];

        if (! $file->isValid()) {
            $this->fail(__('storage.upload_failed'));
        }

        $extension = Str::lower($file->getClientOriginalExtension());

        if (! in_array($extension, $rules['extensions'], true)) {
            $this->fail(__('storage.extension_not_allowed', ['list' => implode(', ', $rules['extensions'])]));
        }

        /* The real type, read from the file's own bytes rather than from
           what the browser said it was sending. */
        if (! in_array((string) $file->getMimeType(), $rules['mimes'], true)) {
            $this->fail(__('storage.type_not_allowed'));
        }

        if ($file->getSize() > $rules['max'] * 1024) {
            $this->fail(__('storage.too_large', ['size' => round($rules['max'] / 1024, 1).' MB']));
        }
    }

    /** The largest a file of this kind may be, in kilobytes. */
    public function maxKilobytes(string $category): int
    {
        return self::RULES[self::CATEGORIES[$category] ?? 'document']['max'];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
